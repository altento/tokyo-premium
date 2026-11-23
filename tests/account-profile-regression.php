<?php
declare(strict_types=1);

/** Focused PHP/SQLite regression for authenticated profile details. */
$root = dirname(__DIR__);
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n");
    exit(77);
}

$dbPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-account-profile-'.bin2hex(random_bytes(6)).'.sqlite';
$sessionId = 'tp'.bin2hex(random_bytes(12));
$csrfToken = bin2hex(random_bytes(32));
$originalRestaurantDb = getenv('RESTAURANT_DB');
$pdo = null;
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    $assertions++;
};

try {
    putenv('RESTAURANT_DB='.$dbPath);
    require $root.'/bootstrap.php';
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $pdo = db();
    $password = 'Profile-qa-current-password';
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute([
        'profile-owner+'.bin2hex(random_bytes(4)).'@example.test', 'Исходное имя', password_hash($password, PASSWORD_DEFAULT),
    ]);
    $ownerId = (int)$pdo->lastInsertId();
    $owner = $pdo->query('SELECT email,password_hash FROM users WHERE id='.$ownerId)->fetch();
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute([
        'other-owner+'.bin2hex(random_bytes(4)).'@example.test', 'Другой аккаунт', password_hash('Other-profile-password', PASSWORD_DEFAULT),
    ]);
    $otherId = (int)$pdo->lastInsertId();
    $otherEmail = (string)$pdo->query('SELECT email FROM users WHERE id='.$otherId)->fetchColumn();
    $route = var_export($root.'/account.php', true);
    $runPost = static function (array $post, bool $includeCsrf = true) use ($root, $route, $ownerId, $sessionId, $csrfToken): string {
        static $childHasSqlite = null;
        if ($includeCsrf && !array_key_exists('csrf', $post)) $post['csrf'] = $csrfToken;
        $code = 'session_name("tokyo_session");'
            .'session_save_path('.var_export(sys_get_temp_dir(), true).');'
            .'session_id('.var_export($sessionId, true).');'
            .'session_start();'
            .'$_SESSION["user_id"]='.(int)$ownerId.';'
            .'$_SESSION["csrf"]='.var_export($csrfToken, true).';'
            .'session_write_close();'
            .'$_SERVER["REQUEST_METHOD"]="POST";'
            .'$_POST='.var_export($post, true).';'
            .'require '.$route.';';
        $pipes = [];
        $command = [PHP_BINARY];
        $iniFile = php_ini_loaded_file();
        if ($iniFile) $command = array_merge($command, ['-c', $iniFile]);
        if ($childHasSqlite === null) {
            $probeCommand = array_merge($command, ['-r', 'echo in_array("sqlite", PDO::getAvailableDrivers(), true) ? "1" : "0";']);
            $probePipes = [];
            $probe = proc_open($probeCommand, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $probePipes, $root);
            if (!is_resource($probe)) throw new RuntimeException('Could not inspect child PHP SQLite configuration.');
            fclose($probePipes[0]);
            $probeOutput = stream_get_contents($probePipes[1]);
            $probeError = stream_get_contents($probePipes[2]);
            fclose($probePipes[1]); fclose($probePipes[2]);
            $probeStatus = proc_close($probe);
            if ($probeStatus !== 0) throw new RuntimeException('Could not inspect child PHP SQLite configuration: '.$probeError);
            $childHasSqlite = trim((string)$probeOutput) === '1';
        }
        if (!$childHasSqlite) {
            $extensionDir = ini_get('extension_dir');
            if ($extensionDir !== '') $command = array_merge($command, ['-d', 'extension_dir='.$extensionDir]);
            $extensions = PHP_OS_FAMILY === 'Windows' ? ['php_sqlite3.dll','php_pdo_sqlite.dll'] : ['sqlite3','pdo_sqlite'];
            foreach ($extensions as $extension) $command = array_merge($command, ['-d', 'extension='.$extension]);
        }
        $command = array_merge($command, ['-r', $code]);
        $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
        if (!is_resource($process)) throw new RuntimeException('Could not start isolated profile route request.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) throw new RuntimeException('Profile route request failed: '.$stderr.' '.$stdout);
        return (string)$stdout;
    };
    $details = static fn(string $name, string $email, string $currentPassword = ''): array => [
        'action'=>'account-details-save',
        'full_name'=>$name,
        'email'=>$email,
        'current_password'=>$currentPassword,
    ];

    $nameOnlyHtml = $runPost($details('Ая Такада', (string)$owner['email']));
    $updated = $pdo->query('SELECT full_name,email,password_hash FROM users WHERE id='.$ownerId)->fetch();
    $assert(str_contains($nameOnlyHtml, 'Имя и контактный email сохранены.') && $updated['full_name']==='Ая Такада' && $updated['email']===$owner['email'], 'authenticated member can change name without entering a password when email stays the same');
    foreach (['account-loyalty','account-personal-offers','account-ledger'] as $targetId) {
        $assert(str_contains($nameOnlyHtml, 'href="#'.$targetId.'"') && str_contains($nameOnlyHtml, 'id="'.$targetId.'"'), 'account navigation target resolves: '.$targetId);
    }

    $missingPasswordHtml = $runPost($details('Ая Такада', 'new-profile+'.bin2hex(random_bytes(4)).'@example.test'));
    $unchanged = $pdo->query('SELECT full_name,email FROM users WHERE id='.$ownerId)->fetch();
    $assert(str_contains($missingPasswordHtml, 'Введите текущий пароль') && $unchanged['email']===$owner['email'], 'an actual email change is rejected without the current password');

    $wrongPasswordHtml = $runPost($details('Ая Такада', 'new-profile+'.bin2hex(random_bytes(4)).'@example.test', 'incorrect-password'));
    $assert(str_contains($wrongPasswordHtml, 'Введите текущий пароль') && $pdo->query('SELECT email FROM users WHERE id='.$ownerId)->fetchColumn()===$owner['email'], 'an incorrect current password cannot change the login email');

    $duplicateHtml = $runPost($details('Ая Такада', strtoupper($otherEmail), $password));
    $assert(str_contains($duplicateHtml, 'Этот email уже используется другим аккаунтом.') && $pdo->query('SELECT email FROM users WHERE id='.$ownerId)->fetchColumn()===$owner['email'], 'email uniqueness is enforced without regard to case');

    $newEmail = 'profile-updated+'.bin2hex(random_bytes(4)).'@example.test';
    $emailChangeHtml = $runPost($details('Ая Премиум', $newEmail, $password));
    $updated = $pdo->query('SELECT full_name,email,password_hash FROM users WHERE id='.$ownerId)->fetch();
    $assert(str_contains($emailChangeHtml, 'Имя и контактный email сохранены.') && $updated['full_name']==='Ая Премиум' && $updated['email']===$newEmail, 'a valid current password authorizes a prepared profile and email update');
    $assert(password_verify($password, $updated['password_hash']), 'profile update preserves the account password hash');

    $caseOnlyEmail = strtoupper($newEmail);
    $caseOnlyHtml = $runPost($details('Ая Премиум', $caseOnlyEmail));
    $assert(str_contains($caseOnlyHtml, 'Имя и контактный email сохранены.') && $pdo->query('SELECT email FROM users WHERE id='.$ownerId)->fetchColumn()===$caseOnlyEmail, 'email casing alone is not treated as an email change requiring reauthentication');

    $invalidNameHtml = $runPost($details('A', $caseOnlyEmail));
    $assert(str_contains($invalidNameHtml, 'Введите имя длиной') && $pdo->query('SELECT full_name FROM users WHERE id='.$ownerId)->fetchColumn()==='Ая Премиум', 'invalid short names are rejected without changing stored details');
    $invalidEmailHtml = $runPost($details('Ая Премиум', 'not-an-email'));
    $assert(str_contains($invalidEmailHtml, 'Введите корректный email') && $pdo->query('SELECT email FROM users WHERE id='.$ownerId)->fetchColumn()===$caseOnlyEmail, 'malformed email addresses are rejected without a write');
    $nonScalarHtml = $runPost(['action'=>'account-details-save','full_name'=>['Подмена'],'email'=>['attacker@example.test']]);
    $assert(str_contains($nonScalarHtml, 'Введите имя длиной') && $pdo->query('SELECT email FROM users WHERE id='.$ownerId)->fetchColumn()===$caseOnlyEmail, 'array-shaped form values are rejected without warnings or a write');

    $csrfHtml = $runPost($details('Подмена', 'attacker@example.test', $password), false);
    $assert(str_contains($csrfHtml, 'Сессия формы истекла') && $pdo->query('SELECT full_name FROM users WHERE id='.$ownerId)->fetchColumn()==='Ая Премиум', 'the profile editor remains protected by the existing CSRF check');

    echo "PASS: {$assertions} focused account profile assertions.\n";
} finally {
    $pdo = null;
    if ($originalRestaurantDb === false) putenv('RESTAURANT_DB'); else putenv('RESTAURANT_DB='.$originalRestaurantDb);
    foreach ([$dbPath,$dbPath.'-wal',$dbPath.'-shm',sys_get_temp_dir().DIRECTORY_SEPARATOR.'sess_'.$sessionId] as $file) if (is_file($file)) @unlink($file);
}
