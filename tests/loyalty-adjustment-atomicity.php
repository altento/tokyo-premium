<?php
declare(strict_types=1);

/** Focused SQLite race regression for administrative loyalty debits. */
$root = dirname(__DIR__);
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n");
    exit(77);
}

if (($argv[1] ?? '') === 'worker') {
    [, , $dbPath, $userId, $adminId, $eventKey, $readyFile, $goFile] = $argv;
    putenv('RESTAURANT_DB='.$dbPath);
    require $root.'/bootstrap.php';
    $pdo = db();
    file_put_contents($readyFile, 'ready');
    $deadline = microtime(true) + 10;
    while (!is_file($goFile) && microtime(true) < $deadline) usleep(10000);
    if (!is_file($goFile)) exit(70);
    try {
        commerce_adjust_loyalty($pdo, (int)$userId, -80, $eventKey, 'Atomicity test debit', (int)$adminId);
        echo "adjusted\n";
        exit(0);
    } catch (DomainException $error) {
        if ($error->getMessage() !== 'Нельзя списать больше доступного баланса.') throw $error;
        echo "rejected\n";
        exit(0);
    }
}

$dbPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-loyalty-race-'.bin2hex(random_bytes(6)).'.sqlite';
$goFile = $dbPath.'.go';
$workers = [];
$pdo = null;
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    $assertions++;
};

try {
    putenv('RESTAURANT_DB='.$dbPath);
    require $root.'/bootstrap.php';
    $pdo = db();
    $email = 'loyalty-race+'.bin2hex(random_bytes(5)).'@example.test';
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute([$email, 'Race Test', password_hash('test-only', PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)')->execute(['admin+'.bin2hex(random_bytes(5)).'@example.test', password_hash('test-only', PASSWORD_DEFAULT)]);
    $adminId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO loyalty_ledger(user_id,event_key,transaction_type,points,note) VALUES(?,'test:opening-balance','earn',100,'Race test opening balance')")->execute([$userId]);

    // Release two debitters together while a parent writer lock keeps their
    // arrival at the transaction boundary deterministic. Without BEGIN
    // IMMEDIATE they both read 100 points before either insert can commit.
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    for ($index = 1; $index <= 2; $index++) {
        $readyFile = $dbPath.'.ready.'.$index;
        $eventKey = 'test:parallel-debit-'.$index;
        $command = [PHP_BINARY];
        if (PHP_OS_FAMILY === 'Windows' && (php_ini_loaded_file() === false || !extension_loaded('pdo_sqlite'))) {
            $command[] = '-d';
            $command[] = 'extension_dir='.ini_get('extension_dir');
            if (php_ini_loaded_file() === false || !extension_loaded('sqlite3')) { $command[] = '-d'; $command[] = 'extension=php_sqlite3.dll'; }
            if (php_ini_loaded_file() === false || !extension_loaded('pdo_sqlite')) { $command[] = '-d'; $command[] = 'extension=php_pdo_sqlite.dll'; }
        }
        array_push($command, __FILE__, 'worker', $dbPath, (string)$userId, (string)$adminId, $eventKey, $readyFile, $goFile);
        $pipes = [];
        $process = proc_open($command, [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, $root);
        if (!is_resource($process)) throw new RuntimeException('Could not start loyalty debit worker.');
        fclose($pipes[0]);
        $workers[] = [$process, $pipes, $readyFile, $index];
    }
    $deadline = microtime(true) + 10;
    while (microtime(true) < $deadline && (!is_file($workers[0][2]) || !is_file($workers[1][2]))) usleep(10000);
    if (!is_file($workers[0][2]) || !is_file($workers[1][2])) {
        $diagnostics = [];
        foreach ($workers as [$process, $pipes, , $index]) {
            foreach ([$pipes[1], $pipes[2]] as $pipe) stream_set_blocking($pipe, false);
            $state = proc_get_status($process);
            $stdout = trim((string)stream_get_contents($pipes[1]));
            $stderr = trim((string)stream_get_contents($pipes[2]));
            $diagnostics[] = 'worker '.$index.' running='.(!empty($state['running'])?'yes':'no').' stdout='.$stdout.' stderr='.$stderr;
        }
        throw new RuntimeException('Loyalty debit workers did not reach the start gate: '.implode(' | ', $diagnostics));
    }
    file_put_contents($goFile, 'go');
    usleep(250000);
    $pdo->exec('COMMIT');

    $results = [];
    foreach ($workers as [$process, $pipes, , $index]) {
        $stdout = trim((string)stream_get_contents($pipes[1]));
        $stderr = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) throw new RuntimeException('Loyalty worker '.$index.' failed: '.$stdout.' '.$stderr);
        $results[] = $stdout;
    }
    $workers = [];
    sort($results);
    $assert($results === ['adjusted', 'rejected'], 'parallel 80-point debits serialize and only one succeeds');
    $assert(commerce_get_loyalty_balance($pdo, $userId) === 20, 'parallel debits cannot produce a negative balance');
    $assert((int)$pdo->query("SELECT COUNT(*) FROM loyalty_ledger WHERE user_id={$userId} AND transaction_type='admin_adjustment'")->fetchColumn() === 1, 'only the accepted debit is written to the ledger');

    try {
        commerce_adjust_loyalty($pdo, $userId, -21, 'test:overdraft-debit', 'Overdraft test', $adminId);
        throw new RuntimeException('Overdraft debit unexpectedly succeeded.');
    } catch (DomainException $error) {
        $assert($error->getMessage() === 'Нельзя списать больше доступного баланса.', 'overdraft is rejected');
    }
    $assert(commerce_get_loyalty_balance($pdo, $userId) === 20, 'rejected overdraft leaves the balance unchanged');

    commerce_adjust_loyalty($pdo, $userId, 10, 'test:independent-credit', 'Independent credit', $adminId);
    $assert(commerce_get_loyalty_balance($pdo, $userId) === 30, 'a separate valid adjustment still succeeds');
    echo "PASS: {$assertions} focused loyalty atomicity assertions.\n";
} finally {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    foreach ($workers as [$process, $pipes]) {
        proc_terminate($process);
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
    $pdo = null;
    foreach ([$dbPath, $dbPath.'-wal', $dbPath.'-shm', $goFile, $dbPath.'.ready.1', $dbPath.'.ready.2'] as $file) {
        if (is_file($file)) @unlink($file);
    }
}
