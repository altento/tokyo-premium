<?php
declare(strict_types=1);

/** Deterministic restaurant-clock boundaries plus isolated HTTP persistence checks. */
require dirname(__DIR__).'/lib/booking_time.php';
require dirname(__DIR__).'/lib/booking_tables.php';
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: PDO SQLite is required.\n");
    exit(77);
}
$root = dirname(__DIR__);
$checks = [];
$skipped = [];
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    $checks[] = $label;
};
$clock = new DateTimeImmutable('2026-11-19 12:00:00', new DateTimeZone('Asia/Qyzylorda'));
$assert(!booking_visit_time_is_valid('2026-11-19', '12:00', $clock), 'equal visit time is rejected');
$assert(!booking_visit_time_is_valid('2026-11-19', '12:00', $clock->modify('+1 second')), 'past visit time is rejected');
$assert(booking_visit_time_is_valid('2026-11-19', '12:00', $clock->modify('-1 second')), 'future visit one second away is accepted without a lead-time policy');
$assert(booking_visit_time_is_valid('2026-11-19', '13:00', $clock), 'later same-day slot is accepted');
$assert(booking_visit_time_is_valid('2026-11-20', '12:00', $clock->setTime(23, 59, 59)), 'next-day slot survives restaurant midnight boundary');
$assert(!booking_visit_time_is_valid('2026-11-19', '21:00', $clock->setTime(23, 59, 59)), 'last same-day slot is rejected after closing');
$utcClock = new DateTimeImmutable('2026-11-18 23:30:00', new DateTimeZone('UTC'));
$assert(booking_visit_time_is_valid('2026-11-19', '12:00', $utcClock), 'external clock is converted to restaurant date');
$assert(!booking_visit_time_is_valid('2026-11-18', '21:00', $utcClock), 'UTC date cannot admit previous restaurant day');
$assert(!booking_visit_time_is_valid('2026-11-19', '12:00', new DateTimeImmutable('2026-11-19 07:00:00 UTC')), 'equal UTC instant is rejected in restaurant zone');
$assert(booking_visit_time_is_valid('2027-11-19', '21:00', $clock), '365th day remains accepted');
$assert(!booking_visit_time_is_valid('2027-11-20', '12:00', $clock), '366th day remains rejected');
$assert(booking_visit_time_is_valid('2028-02-29', '12:00', new DateTimeImmutable('2027-03-01 12:00:00 UTC')), 'valid leap day is accepted in range');
foreach ([['2026-02-29', '12:00'], ['2026-11-31', '12:00'], ['2026-11-19', '15:00'], ['2026-11-19', '12:01'], ['2026-11-19', "12:00\n"], ['2026-11-19 ', '12:00'], ["2026-11-19\0", '12:00']] as [$date, $time]) {
    $assert(!booking_visit_time_is_valid($date, $time, $clock), 'invalid date/time is rejected: '.json_encode([$date, $time]));
}
$defaultZone = date_default_timezone_get();
date_default_timezone_set('Pacific/Honolulu');
$assert(!booking_visit_time_is_valid('2026-11-19', '12:00', $clock), 'restaurant clock is independent of process default timezone');
date_default_timezone_set($defaultZone);
$tables = booking_tables();
$assert(count($tables) === 42 && array_keys($tables) === range(1, 42), 'trusted SVG exposes every table from 1 through 42');
$assert(array_count_values(array_column($tables, 'zone')) === ['Основной зал'=>20, 'Суши-бар'=>8, 'Приватная комната'=>4, 'Летняя терраса'=>10], 'trusted SVG provides current four-zone distribution');
foreach ([1=>'Основной зал', 20=>'Основной зал', 21=>'Суши-бар', 28=>'Суши-бар', 29=>'Приватная комната', 32=>'Приватная комната', 33=>'Летняя терраса', 42=>'Летняя терраса'] as $id => $zone) {
    $assert(booking_table_preferences($zone, (string)$id) === ['zone'=>$zone, 'table'=>(string)$id], 'table '.$id.' accepts its actual SVG zone');
}
$assert(booking_table_preferences('', '42') === ['zone'=>'Летняя терраса', 'table'=>'42'], 'blank zone is inferred from selected table');
$assert(booking_table_preferences('Суши-стойка', '21') === ['zone'=>'Суши-бар', 'table'=>'21'], 'legacy zone alias is normalized before table consistency validation');
$assert(booking_table_preferences('', '') === ['zone'=>'', 'table'=>''], 'table and zone preferences remain optional');
$assert(booking_table_preferences('Приватная комната', '') === ['zone'=>'Приватная комната', 'table'=>''], 'zone-only preference remains valid');
foreach ([['Основной зал', '42'], ['', '43'], ['', '0'], ['', '042'], ['', '1e1'], ['', "42\0"], ['Unknown', '']] as [$zone, $table]) {
    $rejected = false;
    try { booking_table_preferences($zone, $table); } catch (DomainException) { $rejected = true; }
    $assert($rejected, 'invalid table preference is rejected: '.json_encode([$zone, $table]));
}

$temp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-booking-time-'.bin2hex(random_bytes(8));
if (!mkdir($temp, 0700)) throw new RuntimeException('Could not create isolated test directory.');
$process = null;
$pdo = null;
$report = [];
try {
    $command = [PHP_BINARY];
    if ($ini = php_ini_loaded_file()) $command = array_merge($command, ['-c', $ini]);
    // Parent CLI extension flags are not inherited by the HTTP server.
    $pipes = [];
    $probe = proc_open(array_merge($command, ['-r', 'echo json_encode([extension_loaded("pdo_sqlite"), extension_loaded("mbstring")]);']), [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, $root);
    if (!is_resource($probe)) throw new RuntimeException('Could not inspect HTTP runtime.');
    fclose($pipes[0]);
    $extensions = json_decode((string)stream_get_contents($pipes[1]), true);
    $probeError = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($probe) !== 0 || !is_array($extensions)) throw new RuntimeException('HTTP runtime probe failed: '.$probeError);
    $extensionDir = (string)ini_get('extension_dir');
    $command = array_merge($command, ['-d', 'extension_dir="'.str_replace('\\', '/', $extensionDir).'"']);
    foreach (['pdo_sqlite', 'mbstring'] as $index => $extension) {
        if (!$extensions[$index]) $command = array_merge($command, ['-d', 'extension='.(PHP_OS_FAMILY === 'Windows' ? 'php_'.$extension.'.dll' : $extension)]);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Could not reserve HTTP test port: '.$error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $command = array_merge($command, ['-d', 'session.save_path="'.str_replace('\\', '/', $temp).'"', '-S', $address, '-t', $root]);
    $environment = getenv();
    $environment['RESTAURANT_DB'] = $temp.DIRECTORY_SEPARATOR.'booking.sqlite';
    unset($environment['TOKYO_HTTPS_ONLY']);
    foreach (array_keys($environment) as $key) {
        if (str_starts_with($key, 'TOKYO_SMTP_') || str_starts_with($key, 'TOKYO_MAIL_') || $key === 'TOKYO_BOOKING_NOTIFY_EMAIL') unset($environment[$key]);
    }
    $log = $temp.DIRECTORY_SEPARATOR.'server.log';
    $process = proc_open($command, [0=>['pipe','r'], 1=>['file',$log,'a'], 2=>['file',$log,'a']], $pipes, $root, $environment);
    if (!is_resource($process)) throw new RuntimeException('Could not start HTTP test server.');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $socket = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
        if ($socket) { fclose($socket); $ready = true; break; }
        usleep(50000);
    }
    if (!$ready) throw new RuntimeException('HTTP server did not start: '.file_get_contents($log));
    $cookie = '';
    $request = static function (string $route, ?array $data = null, ?string $key = null) use ($address, &$cookie): array {
        $headers = ['Connection: close'];
        if ($cookie !== '') $headers[] = 'Cookie: '.$cookie;
        if ($key !== null) $headers[] = 'Idempotency-Key: '.$key;
        if ($data !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $context = stream_context_create(['http'=>['method'=>$data === null ? 'GET' : 'POST', 'header'=>implode("\r\n", $headers), 'content'=>$data === null ? '' : http_build_query($data), 'ignore_errors'=>true, 'timeout'=>15]]);
        $body = file_get_contents('http://'.$address.'/'.$route, false, $context);
        if ($body === false) throw new RuntimeException('HTTP request failed.');
        $responseHeaders = $http_response_header ?? [];
        preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $status);
        foreach ($responseHeaders as $header) if (preg_match('/^Set-Cookie:\s*(tokyo_session=[^;]+)/i', $header, $match)) $cookie = $match[1];
        return ['status'=>(int)($status[1] ?? 0), 'body'=>$body, 'json'=>json_decode($body, true)];
    };
    $page = $request('pages.php?page=booking');
    if (!preg_match('/name="csrf" value="([^"]+)"/', $page['body'], $match)) throw new RuntimeException('Booking CSRF not found: '.$page['body']);
    $assert($page['status'] === 200 && $cookie !== '', 'isolated booking page creates CSRF-protected session');
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Qyzylorda'));
    $today = $now->format('Y-m-d');
    $tomorrow = $now->modify('+1 day')->format('Y-m-d');
    $base = ['type'=>'booking', 'csrf'=>$match[1], 'name'=>'Time QA', 'email'=>'booking-time@example.test', 'phone'=>'+77000000000', 'guests'=>'2', 'date'=>$tomorrow, 'time'=>'12:00'];
    $pdo = new PDO('sqlite:'.$environment['RESTAURANT_DB'], null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $counts = static function () use ($pdo): array {
        $result = [];
        foreach (['bookings', 'email_outbox', 'booking_email_rate_limits', 'api_booking_requests'] as $table) $result[$table] = (int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        return $result;
    };
    $before = $counts();
    $invalid = [
        ['date'=>$now->modify('-1 day')->format('Y-m-d')], ['date'=>'2026-02-30'],
        ['date'=>$now->modify('+366 days')->format('Y-m-d')], ['time'=>'15:00'], ['guests'=>'0'], ['guests'=>'13'],
        ['table'=>'42', 'zone'=>'Основной зал'], ['table'=>'43', 'zone'=>'Летняя терраса'], ['table'=>'042', 'zone'=>'Летняя терраса'],
    ];
    $pastTime = null;
    foreach (['21:00', '20:00', '19:00', '18:00', '17:00', '14:00', '13:00', '12:00'] as $slot) {
        if (new DateTimeImmutable($today.' '.$slot, $now->getTimezone()) <= $now) { $pastTime = $slot; break; }
    }
    if ($pastTime !== null) {
        array_unshift($invalid, ['date'=>$today, 'time'=>'12:00']);
        if ($pastTime !== '12:00') $invalid[] = ['date'=>$today, 'time'=>$pastTime];
    }
    else $skipped[] = 'same-day elapsed HTTP slot unavailable before opening; deterministic past/equal clock cases cover boundary';
    foreach ($invalid as $index => $fields) {
        $response = $request('api.php', array_replace($base, $fields), 'booking-time-invalid-'.$index);
        $assert($response['status'] === 422 && ($response['json']['ok'] ?? null) === false, 'HTTP rejects invalid booking '.json_encode($fields));
        $report[] = ['request'=>$fields, 'status'=>$response['status'], 'response'=>$response['json']];
    }
    $assert($counts() === $before, 'invalid visits create no booking, email, rate-limit entry or idempotency reservation');
    $noCsrf = $request('api.php', array_replace($base, ['csrf'=>'']));
    $assert($noCsrf['status'] === 419, 'booking endpoint retains CSRF protection');
    $created = $request('api.php', $base, 'booking-time-valid-nextday');
    $assert($created['status'] === 201 && ($created['json']['ok'] ?? null) === true, 'HTTP accepts next-day future visit');
    $replayed = $request('api.php', $base, 'booking-time-valid-nextday');
    $assert($replayed['status'] === 201 && $replayed['json'] === $created['json'], 'successful booking retry preserves original response');
    $assert((int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn() === $before['bookings'] + 1, 'idempotent retry stores one booking');
    $maximum = $request('api.php', array_replace($base, ['date'=>$now->modify('+365 days')->format('Y-m-d'), 'time'=>'21:00']), 'booking-time-valid-maxday');
    $assert($maximum['status'] === 201, 'HTTP accepts final allowed booking day');
    $corrected = $request('api.php', $base, 'booking-time-invalid-0');
    $assert($corrected['status'] === 201, 'rejected request key remains usable after correcting visit');
    $report[] = ['request'=>['date'=>$tomorrow, 'time'=>'12:00'], 'status'=>$created['status'], 'response'=>$created['json'], 'replay_status'=>$replayed['status']];
    $report[] = ['request'=>['date'=>$now->modify('+365 days')->format('Y-m-d'), 'time'=>'21:00'], 'status'=>$maximum['status'], 'response'=>$maximum['json']];
    foreach ([['table'=>'42', 'zone'=>'Летняя терраса', 'guests'=>'2'], ['table'=>'21', 'zone'=>'', 'guests'=>'12']] as $index => $fields) {
        $response = $request('api.php', array_replace($base, $fields, ['email'=>'booking-table@example.test']), 'booking-table-valid-'.$index);
        $assert($response['status'] === 201 && ($response['json']['ok'] ?? null) === true, 'HTTP accepts table preference '.json_encode($fields));
        $saved = $pdo->query('SELECT preferred_table,zone,guest_count FROM bookings WHERE id='.(int)$response['json']['id'])->fetch(PDO::FETCH_ASSOC);
        $expectedZone = $fields['zone'] !== '' ? $fields['zone'] : $tables[(int)$fields['table']]['zone'];
        $assert($saved && $saved['preferred_table'] === $fields['table'] && $saved['zone'] === $expectedZone && (int)$saved['guest_count'] === (int)$fields['guests'], 'HTTP stores canonical table zone without enforcing drawn capacity');
        $report[] = ['request'=>$fields, 'status'=>$response['status'], 'response'=>$response['json'], 'saved'=>$saved];
    }
    echo json_encode(['result'=>'PASS', 'assertions'=>count($checks), 'restaurant_clock'=>$now->format(DateTimeInterface::ATOM), 'isolated_database'=>true, 'isolated_session'=>true, 'smtp_disabled'=>true, 'checks'=>$checks, 'skipped'=>$skipped, 'http'=>$report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
} finally {
    $counts = null;
    $pdo = null;
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    foreach (scandir($temp) ?: [] as $file) if ($file !== '.' && $file !== '..' && is_file($temp.DIRECTORY_SEPARATOR.$file)) unlink($temp.DIRECTORY_SEPARATOR.$file);
    rmdir($temp);
}
