<?php
declare(strict_types=1);

/** Optional FreedomPay hosted checkout. Card data stays on FreedomPay's page. */
function freedompay_env(string $key, string $fallback = ''): string
{
    $value = getenv($key);
    return $value === false ? $fallback : trim((string)$value);
}

function freedompay_config(): ?array
{
    $merchantId = freedompay_env('FREEDOMPAY_MERCHANT_ID');
    $secret = freedompay_env('FREEDOMPAY_SECRET_KEY');
    $publicBase = rtrim(freedompay_env('TOKYO_PUBLIC_BASE_URL'), '/');
    if ($merchantId === '' && $secret === '' && $publicBase === '') return null;
    if (!preg_match('/^[0-9]{1,20}$/', $merchantId) || strlen($secret) < 16 || strlen($secret) > 500) {
        throw new RuntimeException('FreedomPay merchant configuration is incomplete.');
    }
    $url = parse_url($publicBase);
    if (!$url || strtolower((string)($url['scheme'] ?? '')) !== 'https'
        || empty($url['host']) || !str_contains((string)$url['host'], '.')
        || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
        || (isset($url['port']) && (int)$url['port'] !== 443)) {
        throw new RuntimeException('TOKYO_PUBLIC_BASE_URL must be a public HTTPS site URL.');
    }
    $testRaw = freedompay_env('FREEDOMPAY_TEST_MODE', '1');
    if (!in_array($testRaw, ['0', '1'], true)) throw new RuntimeException('FREEDOMPAY_TEST_MODE must be 0 or 1.');
    $testMode = $testRaw === '1';
    if (!$testMode && freedompay_env('TOKYO_ALLOW_LIVE_PAYMENTS') !== '1') {
        throw new RuntimeException('Live payments require TOKYO_ALLOW_LIVE_PAYMENTS=1 after real merchant and restaurant details are configured.');
    }
    return ['merchant_id'=>$merchantId,'secret'=>$secret,'public_base'=>$publicBase,'test_mode'=>$testMode];
}

function freedompay_is_enabled(): bool
{
    try { return freedompay_config() !== null; }
    catch (Throwable) { return false; }
}

/** Signature recipe from FreedomPay Merchant API: script; sorted field values; secret. */
function freedompay_signature(string $script, array $fields, string $secret): string
{
    unset($fields['pg_sig']);
    foreach ($fields as $key => $value) {
        if (!is_string($key) || !is_scalar($value)) throw new InvalidArgumentException('FreedomPay signatures support flat scalar fields only.');
        $fields[$key] = (string)$value;
    }
    ksort($fields, SORT_STRING);
    return md5($script . ';' . implode(';', array_values($fields)) . ';' . $secret);
}

function freedompay_signature_is_valid(string $script, array $fields, string $secret): bool
{
    $signature = strtolower(trim((string)($fields['pg_sig'] ?? '')));
    return preg_match('/^[a-f0-9]{32}$/', $signature) === 1
        && hash_equals(freedompay_signature($script, $fields, $secret), $signature);
}

function freedompay_amount_to_minor_units(string $amount): ?int
{
    if (!preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?$/', trim($amount), $match)) return null;
    $fraction = str_pad($match[2] ?? '', 2, '0');
    return ((int)$match[1] * 100) + (int)$fraction;
}

function freedompay_return_signature(int $orderId, string $secret): string
{
    return hash_hmac('sha256', 'tokyo-payment-return|' . $orderId, $secret);
}

function freedompay_post_form(string $url, array $fields): string
{
    $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>20,
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/xml'],
        ]);
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($response) || $status !== 200) throw new RuntimeException('FreedomPay request failed' . ($error !== '' ? ' (' . $error . ')' : '') . '.');
        return $response;
    }
    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) throw new RuntimeException('PHP needs cURL or allow_url_fopen enabled for FreedomPay.');
    $context = stream_context_create(['http'=>[
        'method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\nAccept: application/xml\r\n",
        'content'=>$body,'timeout'=>20,'ignore_errors'=>true,'follow_location'=>0,
    ],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $response = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $statusLine = (string)($headers[0] ?? '');
    if (!is_string($response) || !preg_match('/\s200\s/', $statusLine)) throw new RuntimeException('FreedomPay request failed.');
    return $response;
}

function freedompay_parse_xml_fields(string $body): array
{
    if (!function_exists('simplexml_load_string')) throw new RuntimeException('PHP SimpleXML is required for FreedomPay.');
    if (strlen($body) > 65536) throw new RuntimeException('FreedomPay response is too large.');
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        if (!$xml || $xml->getName() !== 'response') throw new RuntimeException('FreedomPay returned an unreadable response.');
        $fields = [];
        foreach ($xml->children() as $key => $value) {
            $key = (string)$key;
            if (array_key_exists($key, $fields)) throw new RuntimeException('FreedomPay returned a duplicate response field.');
            $fields[$key] = (string)$value;
        }
        return $fields;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function freedompay_parse_signed_xml(string $body, string $script, string $secret): array
{
    $fields = freedompay_parse_xml_fields($body);
    if (!freedompay_signature_is_valid($script, $fields, $secret)) throw new RuntimeException('FreedomPay response signature is invalid.');
    return $fields;
}

/** The payment-page API signs successful responses; documented error responses are unsigned. */
function freedompay_parse_payment_page_response(string $body, string $secret): array
{
    $fields = freedompay_parse_xml_fields($body);
    $status = (string)($fields['pg_status'] ?? '');
    if ($status === 'error') {
        $errorCode = (string)($fields['pg_error_code'] ?? '');
        $description = (string)($fields['pg_error_description'] ?? '');
        if (!preg_match('/^[0-9]{1,12}$/', $errorCode) || strlen($description) > 2048) {
            throw new RuntimeException('FreedomPay returned a malformed error response.');
        }
        if (array_key_exists('pg_sig', $fields) && !freedompay_signature_is_valid('payment_page', $fields, $secret)) {
            throw new RuntimeException('FreedomPay error response contains an invalid signature.');
        }
        return $fields;
    }
    if ($status !== 'ok' || !freedompay_signature_is_valid('payment_page', $fields, $secret)) {
        throw new RuntimeException('FreedomPay success response signature is invalid.');
    }
    return $fields;
}

function freedompay_callback_fields(): array
{
    $fields = $_POST;
    if (!$fields) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode(is_string($raw) ? $raw : '', true);
        if (is_array($decoded)) $fields = $decoded;
    }
    foreach ($fields as $key => $value) if (!is_string($key) || !is_scalar($value)) unset($fields[$key]);
    return $fields;
}

function freedompay_callback_response(string $status, string $description, string $secret): never
{
    $fields = ['pg_status'=>$status,'pg_description'=>$description,'pg_salt'=>bin2hex(random_bytes(8))];
    $fields['pg_sig'] = freedompay_signature('payment.php', $fields, $secret);
    header('Content-Type: application/xml; charset=utf-8');
    http_response_code(200);
    echo '<?xml version="1.0" encoding="utf-8"?><response>';
    foreach ($fields as $key => $value) echo '<' . $key . '>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $key . '>';
    echo '</response>';
    exit;
}
