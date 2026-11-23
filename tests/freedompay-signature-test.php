<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/freedompay.php';

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$secret = 'test_secret_key';
$fields = [
    'pg_merchant_id'=>'12345','pg_order_id'=>'order_1001','pg_amount'=>'100',
    'pg_currency'=>'KZT','pg_description'=>'Order #1001','pg_salt'=>'a1b2c3d4',
];
$check(freedompay_signature('init_payment.php',$fields,$secret)==='b9ad75871668ca040f80f5f6b530e6ed','matches FreedomPay published signature vector');
$fields['pg_sig'] = freedompay_signature('init_payment.php',$fields,$secret);
$check(freedompay_signature_is_valid('init_payment.php',$fields,$secret),'accepts a valid callback signature');
$fields['pg_amount'] = '101';
$check(!freedompay_signature_is_valid('init_payment.php',$fields,$secret),'rejects a modified signed amount');
$check(freedompay_amount_to_minor_units('125.5')===12550&&freedompay_amount_to_minor_units('125.55')===12555,'converts KZT decimal values without float rounding');
$check(freedompay_amount_to_minor_units('-1')===null&&freedompay_amount_to_minor_units('1.001')===null,'rejects malformed amounts');
$unsignedError = '<?xml version="1.0"?><response><pg_status>error</pg_status><pg_error_code>1100</pg_error_code><pg_error_description>Signature rejected</pg_error_description></response>';
$parsedError = freedompay_parse_payment_page_response($unsignedError, $secret);
$check(($parsedError['pg_status'] ?? '')==='error'&&($parsedError['pg_error_code'] ?? '')==='1100','accepts the provider-documented unsigned payment rejection');
$rejects = static function (callable $operation, string $label) use ($check): void {
    try { $operation(); } catch (Throwable) { $check(true, $label); return; }
    $check(false, $label);
};
$rejects(static fn()=>freedompay_parse_payment_page_response('<response><pg_status>error</pg_status></response>', $secret),'rejects unsigned error XML without a provider error code');
$rejects(static fn()=>freedompay_parse_payment_page_response('<response><pg_status>ok</pg_status><pg_payment_id>123</pg_payment_id></response>', $secret),'rejects unsigned success responses');
$badSignedError = '<response><pg_status>error</pg_status><pg_error_code>1100</pg_error_code><pg_sig>bad</pg_sig></response>';
$rejects(static fn()=>freedompay_parse_payment_page_response($badSignedError, $secret),'rejects an invalid signature when an error response includes one');
$successFields = [
    'pg_status'=>'ok','pg_payment_id'=>'123456','pg_redirect_url'=>'https://customer.freedompay.kz/pay.html?customer=fixture',
    'pg_redirect_url_type'=>'need data','pg_salt'=>'a1b2c3d4',
];
$successFields['pg_sig']=freedompay_signature('payment_page',$successFields,$secret);
$successXml='<response>'.implode('',array_map(static fn($key,$value)=>'<'.$key.'>'.htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8').'</'.$key.'>',array_keys($successFields),array_values($successFields))).'</response>';
$parsedSuccess=freedompay_parse_payment_page_response($successXml,$secret);
$check(($parsedSuccess['pg_status']??'')==='ok'&&($parsedSuccess['pg_payment_id']??'')==='123456','continues to verify a signed payment-page success response');
$rejects(static fn()=>freedompay_parse_signed_xml($unsignedError,'payment_page',$secret),'generic signed-response parser still rejects unsigned XML');
fwrite(STDOUT, 'FreedomPay signature and response checks passed: ' . $checks . PHP_EOL);
