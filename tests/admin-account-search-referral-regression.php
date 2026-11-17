<?php
declare(strict_types=1);

/** Focused HTTP regression for Cyrillic admin account search and invite login. */
$root = dirname(__DIR__);
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n");
    exit(77);
}

$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tokyo-account-search-referral-' . bin2hex(random_bytes(6)) . '.sqlite';
$serverLog = $dbPath . '.server.log';
$server = null;
$serverPipes = [];
$pdo = null;
$checks = 0;
$exitCode = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};

function flowRequest(string $path, array $fields = []): array
{
    global $base, $cookie;
    $headers = ['Accept: text/html'];
    if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
    $options = ['http'=>[
        'method'=>$fields ? 'POST' : 'GET',
        'ignore_errors'=>true,
        'follow_location'=>0,
        'max_redirects'=>0,
        'header'=>implode("\r\n", $headers),
        'content'=>$fields ? http_build_query($fields) : '',
        'timeout'=>10,
    ]];
    if ($fields) $options['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
    $body = @file_get_contents($base . $path, false, stream_context_create($options));
    $responseHeaders = $http_response_header ?? [];
    foreach ($responseHeaders as $line) {
        if (stripos($line, 'Set-Cookie: tokyo_session=') === 0) $cookie = explode(';', substr($line, 12))[0];
    }
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
    return [(int)($match[1] ?? 0), (string)$body, $responseHeaders];
}

function flowCsrf(string $html): string
{
    if (!preg_match('/name="csrf" value="([^"]+)"/', $html, $match)) throw new RuntimeException('CSRF token was not rendered.');
    return $match[1];
}

$oldRestaurantDb = getenv('RESTAURANT_DB');
putenv('RESTAURANT_DB=' . $dbPath);
try {
    require $root . '/bootstrap.php';
    $pdo = db();
    $adminEmail = 'marketing-' . bin2hex(random_bytes(4)) . '@example.test';
    $adminPassword = 'Focused-Admin-' . bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)')->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);

    $inviterEmail = 'inviter-' . bin2hex(random_bytes(4)) . '@example.test';
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute([$inviterEmail, 'Пригласивший администратор теста', password_hash('test-only', PASSWORD_DEFAULT)]);
    $inviterId = (int)$pdo->lastInsertId();
    $referralCode = commerce_referral_code_for_user($pdo, $inviterId);

    $memberEmail = 'member-' . bin2hex(random_bytes(4)) . '@example.test';
    $memberPassword = 'Focused-Member-' . bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute([$memberEmail, 'Илья Қайрат', password_hash($memberPassword, PASSWORD_DEFAULT)]);
    $memberId = (int)$pdo->lastInsertId();

    $port = random_int(23000, 45000);
    $base = 'http://127.0.0.1:' . $port;
    $cookie = '';
    $serverCommand = [PHP_BINARY];
    $iniFile = php_ini_loaded_file();
    if ($iniFile) array_push($serverCommand, '-c', $iniFile);
    $probeCommand = array_merge($serverCommand, ['-r', 'echo in_array("sqlite", PDO::getAvailableDrivers(), true) ? "1" : "0";']);
    $probePipes = [];
    $probe = proc_open($probeCommand, [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $probePipes, $root, null);
    if (!is_resource($probe)) throw new RuntimeException('Could not inspect PHP server SQLite configuration.');
    fclose($probePipes[0]);
    $probeOutput = trim((string)stream_get_contents($probePipes[1]));
    $probeError = trim((string)stream_get_contents($probePipes[2]));
    fclose($probePipes[1]);
    fclose($probePipes[2]);
    $probeStatus = proc_close($probe);
    if ($probeStatus !== 0) throw new RuntimeException('Could not inspect PHP server SQLite configuration: ' . $probeError);
    if ($probeOutput !== '1') {
        if (ini_get('extension_dir') !== '') array_push($serverCommand, '-d', 'extension_dir=' . ini_get('extension_dir'));
        $sqliteExtensions = PHP_OS_FAMILY === 'Windows' ? ['php_sqlite3.dll','php_pdo_sqlite.dll'] : ['sqlite3','pdo_sqlite'];
        foreach ($sqliteExtensions as $extension) array_push($serverCommand, '-d', 'extension=' . $extension);
    }
    array_push($serverCommand, '-S', '127.0.0.1:' . $port, '-t', $root);
    $server = proc_open($serverCommand, [0=>['pipe','r'], 1=>['file',$serverLog,'a'], 2=>['file',$serverLog,'a']], $serverPipes, $root, null);
    if (!is_resource($server)) throw new RuntimeException('Could not start the focused PHP server.');
    fclose($serverPipes[0]);

    $ready = false;
    for ($attempt=0; $attempt<40; $attempt++) {
        [$status] = flowRequest('/account.php');
        if ($status === 200) { $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('Focused PHP server did not become ready.');

    [$status, $adminLogin] = flowRequest('/admin.php');
    $assert($status === 200 && str_contains($adminLogin, 'Вход в панель'), 'admin login page opens for the focused search test');
    [$status] = flowRequest('/admin.php', [
        'csrf'=>flowCsrf($adminLogin), 'action'=>'login', 'email'=>$adminEmail, 'password'=>$adminPassword,
    ]);
    $assert($status === 302, 'admin login succeeds');
    [$status, $marketingHtml] = flowRequest('/admin-marketing.php?user_search=' . rawurlencode('илья қайрат'));
    $assert($status === 200 && str_contains($marketingHtml, 'Найденные аккаунты') && str_contains($marketingHtml, 'Илья Қайрат'), 'marketing admin finds a mixed-case Cyrillic name');

    $cookie = '';
    [$status, $inviteLanding] = flowRequest('/account.php?ref=' . rawurlencode($referralCode));
    $assert($status === 200 && str_contains($inviteLanding, 'name="action" value="login"'), 'existing member can open an invite link while logged out');
    $assert(str_contains($inviteLanding, 'name="referral_code" value="' . $referralCode . '"'), 'invite code is carried in the login form');
    [$status, , $loginHeaders] = flowRequest('/account.php?ref=' . rawurlencode($referralCode), [
        'csrf'=>flowCsrf($inviteLanding), 'action'=>'login', 'email'=>$memberEmail, 'password'=>$memberPassword, 'referral_code'=>$referralCode,
    ]);
    $location = '';
    foreach ($loginHeaders as $line) if (stripos($line, 'Location:') === 0) $location = trim(substr($line, 9));
    $assert($status === 302 && str_contains($location, 'account.php?ref=' . $referralCode . '#account-referrals'), 'successful existing-member login preserves the invite code and opens referrals');

    [$status, $accountHtml] = flowRequest('/account.php?ref=' . rawurlencode($referralCode));
    $assert($status === 200 && str_contains($accountHtml, 'name="referral_code" value="' . $referralCode . '"'), 'signed-in account keeps the invite code prefilled for explicit attachment');
    [$status, $attachedHtml] = flowRequest('/account.php?ref=' . rawurlencode($referralCode), [
        'csrf'=>flowCsrf($accountHtml), 'action'=>'referral-attach', 'referral_code'=>$referralCode,
    ]);
    $referralQuery = $pdo->prepare('SELECT COUNT(*) FROM referrals WHERE inviter_user_id=? AND referred_user_id=?');
    $referralQuery->execute([$inviterId, $memberId]);
    $assert($status === 200 && str_contains($attachedHtml, 'Код приглашения сохранён') && (int)$referralQuery->fetchColumn() === 1, 'member can attach the preserved invite code after login');

    echo 'PASS: admin Cyrillic account search and existing-member referral login (' . $checks . " assertions).\n";
} catch (Throwable $error) {
    $log = is_file($serverLog) ? file_get_contents($serverLog) : '';
    fwrite(STDERR, $error->getMessage() . ($log !== '' ? "\nPHP server log:\n" . $log : '') . "\n");
    $exitCode = 1;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        foreach ($serverPipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($server);
    }
    $pdo = null;
    foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm', $serverLog] as $file) if (is_file($file)) @unlink($file);
    if ($oldRestaurantDb === false) putenv('RESTAURANT_DB'); else putenv('RESTAURANT_DB=' . $oldRestaurantDb);
}
if ($exitCode !== 0) exit($exitCode);
