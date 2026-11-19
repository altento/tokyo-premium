<?php
declare(strict_types=1);

/** Focused HTTP checks; all records, sessions and server files are temporary. */
$root = dirname(__DIR__);
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n");
    exit(77);
}
$dbPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-admin-settings-'.bin2hex(random_bytes(6)).'.sqlite';
$sessionPath = $dbPath.'.sessions';
$serverLog = $dbPath.'.server.log';
$server = null;
$pdo = null;
$cookie = '';
$checks = [];
$exitCode = 0;
$evidencePath = null;
foreach ($argv as $argument) if (str_starts_with($argument, '--evidence=')) $evidencePath = substr($argument, 11);
$evidence = ['suite'=>'admin-settings-integrity', 'transport'=>'HTTP /admin.php', 'database'=>'isolated temporary SQLite', 'checks'=>[]];
$oldRestaurantDb = getenv('RESTAURANT_DB');
$oldWorkers = getenv('PHP_CLI_SERVER_WORKERS');
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks[] = ['check'=>$message, 'passed'=>$condition];
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
};
$request = static function (array $fields = []) use (&$base, &$cookie): array {
    $headers = ['Accept: text/html'];
    if ($cookie !== '') $headers[] = 'Cookie: '.$cookie;
    if ($fields) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $options = ['http'=>['method'=>$fields ? 'POST' : 'GET', 'ignore_errors'=>true,
        'follow_location'=>0, 'max_redirects'=>0, 'timeout'=>10,
        'header'=>implode("\r\n", $headers), 'content'=>$fields ? http_build_query($fields) : '']];
    $body = @file_get_contents($base.'/admin.php', false, stream_context_create($options));
    $responseHeaders = $http_response_header ?? [];
    foreach ($responseHeaders as $line) {
        if (stripos($line, 'Set-Cookie: tokyo_session=') === 0) $cookie = explode(';', substr($line, 12))[0];
    }
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
    return [(int)($match[1] ?? 0), (string)$body];
};
$csrfFrom = static function (string $html): string {
    if (!preg_match('/name="csrf" value="([^"]+)"/', $html, $match)) throw new RuntimeException('CSRF token missing.');
    return $match[1];
};

try {
    mkdir($sessionPath);
    putenv('RESTAURANT_DB='.$dbPath);
    putenv('PHP_CLI_SERVER_WORKERS');
    require $root.'/bootstrap.php';
    $pdo = db();
    $snapshot = static fn(): array => $pdo->query('SELECT key,value FROM settings ORDER BY key')->fetchAll(PDO::FETCH_KEY_PAIR);
    $original = $snapshot();
    $adminEmail = 'settings-'.bin2hex(random_bytes(4)).'@example.test';
    $adminPassword = 'Settings-'.bin2hex(random_bytes(12));
    $pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)')->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    if (!$socket) throw new RuntimeException('Could not select a local port: '.$errorMessage);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://'.$address;
    $command = [PHP_BINARY];
    if ($ini = php_ini_loaded_file()) array_push($command, '-c', $ini);
    // CLI -d flags are not inherited by child PHP processes.
    if (!$ini) {
        array_push($command, '-d', 'extension_dir='.ini_get('extension_dir'));
        array_push($command, '-d', 'extension='.(PHP_OS_FAMILY === 'Windows' ? 'php_pdo_sqlite.dll' : 'pdo_sqlite'));
    }
    array_push($command, '-d', 'session.save_path="'.$sessionPath.'"', '-S', $address, '-t', $root);
    $pipes = [];
    $server = proc_open($command, [0=>['pipe','r'],1=>['file',$serverLog,'a'],2=>['file',$serverLog,'a']], $pipes, $root);
    if (!is_resource($server)) throw new RuntimeException('Could not start the isolated HTTP server.');
    fclose($pipes[0]);
    $ready = false;
    for ($attempt=0; $attempt<40; $attempt++) {
        [$status, $html] = $request();
        if ($status === 200) { $ready = true; break; }
        usleep(100000);
    }
    $assert($ready, 'isolated admin HTTP route is available');
    $csrf = $csrfFrom($html);
    [$status] = $request(['action'=>'settings','csrf'=>$csrf,'site_name'=>'Unauthorized write']);
    $assert($status === 200 && $snapshot() === $original, 'anonymous settings POST cannot mutate data');
    [$status, $loginResult] = $request(['action'=>'login','csrf'=>$csrf,'email'=>$adminEmail,'password'=>$adminPassword]);
    $assert($status === 302, 'real admin login succeeds');
    [$status, $html] = $request();
    $csrf = $csrfFrom($html);
    [$status] = $request(['action'=>'settings','csrf'=>'invalid','site_name'=>'CSRF write']);
    $assert($status === 419 && $snapshot() === $original, 'invalid CSRF prevents settings writes');

    [$status] = $request(['action'=>'settings','csrf'=>$csrf,'delivery_fee'=>'1450']);
    $expected = $original;
    $expected['delivery_fee'] = '1450';
    ksort($expected);
    $actual = $snapshot();
    $changedKeys = [];
    foreach ($actual as $key=>$value) if (($original[$key] ?? null) !== $value) $changedKeys[] = $key;
    $evidence['partial_submission'] = ['submitted_keys'=>['delivery_fee'], 'changed_keys'=>$changedKeys, 'status'=>$status];
    $assert($status === 302 && $actual === $expected, 'delivery-only submission preserves every omitted setting');

    $invalidPayloads = [
        'text array'=>['site_name'=>'Must not save','phone'=>['nested'=>'invalid']],
        'fee array'=>['site_name'=>'Must not save','delivery_fee'=>['invalid']],
        'negative fee'=>['site_name'=>'Must not save','delivery_fee'=>'-1'],
        'fee above maximum'=>['site_name'=>'Must not save','delivery_fee'=>'100001'],
        'fractional fee'=>['site_name'=>'Must not save','delivery_fee'=>'1.5'],
        'text above 500 characters'=>['site_name'=>'Must not save','hours'=>str_repeat('界', 501)],
        'invalid UTF-8'=>['site_name'=>'Must not save','hours'=>"invalid\xFF"],
    ];
    foreach ($invalidPayloads as $label=>$payload) {
        [$status, $invalidHtml] = $request(['action'=>'settings','csrf'=>$csrf] + $payload);
        $assert($status === 200 && $snapshot() === $expected && str_contains($invalidHtml, 'class="form-status error'), $label.' rejects the entire combined update');
    }

    $full = [];
    foreach (['site_name','meta_title','meta_description','hero_eyebrow','hero_title','hero_text','address','phone','hours','instagram'] as $key) {
        $assert(str_contains($html, 'name="'.$key.'"'), 'browser settings form exposes '.$key);
        $full[$key] = 'Updated '.$key;
    }
    $full['hero_text'] = str_repeat('界', 500);
    $full['instagram'] = ''; // Explicit clearing remains supported.
    $full['delivery_fee'] = '100000';
    [$status] = $request(['action'=>'settings','csrf'=>$csrf] + $full);
    $expected = array_replace($expected, $full);
    ksort($expected);
    $assert($status === 302 && $snapshot() === $expected, 'complete browser submission accepts 500 Unicode characters, explicit clearing and fee maximum');
    [$status, $savedHtml] = $request();
    $assert($status === 200 && str_contains($savedHtml, 'Настройки сайта сохранены.'), 'successful browser update exposes its redirect confirmation');
    [$status] = $request(['action'=>'settings','csrf'=>$csrf,'delivery_fee'=>'0']);
    $expected['delivery_fee'] = '0';
    $assert($status === 302 && $snapshot() === $expected, 'zero delivery fee is valid and preserves text settings');

    $pdo->exec("CREATE TRIGGER qa_settings_failure BEFORE UPDATE ON settings WHEN NEW.key='phone' BEGIN SELECT RAISE(ABORT,'QA settings failure'); END");
    [$status] = $request(['action'=>'settings','csrf'=>$csrf,'site_name'=>'Must roll back','phone'=>'Trigger failure']);
    $assert($status === 200 && $snapshot() === $expected, 'database failure rolls back earlier settings in the same update');
    echo 'PASS: '.count($checks)." focused admin settings HTTP assertions.\n";
} catch (Throwable $error) {
    $exitCode = 1;
    $evidence['failure'] = $error->getMessage();
    fwrite(STDERR, $error->getMessage()."\n");
} finally {
    $evidence['passed'] = $exitCode === 0;
    $evidence['checks'] = $checks;
    if ($evidencePath !== null) file_put_contents($evidencePath, json_encode($evidence, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo = null;
    if (is_dir($sessionPath)) {
        foreach (glob($sessionPath.DIRECTORY_SEPARATOR.'sess_*') ?: [] as $file) @unlink($file);
        @rmdir($sessionPath);
    }
    $initLock = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-db-init-'.hash('sha256', realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.basename($dbPath)).'.lock';
    $rateKey = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-admin-rate-'.hash('sha256', $dbPath).'.key';
    foreach ([$dbPath,$dbPath.'-wal',$dbPath.'-shm',$serverLog,$initLock,$rateKey] as $file) if (is_file($file)) @unlink($file);
    if ($oldRestaurantDb === false) putenv('RESTAURANT_DB'); else putenv('RESTAURANT_DB='.$oldRestaurantDb);
    if ($oldWorkers === false) putenv('PHP_CLI_SERVER_WORKERS'); else putenv('PHP_CLI_SERVER_WORKERS='.$oldWorkers);
}
exit($exitCode);
