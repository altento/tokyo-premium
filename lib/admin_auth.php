<?php
declare(strict_types=1);

/** Return a stable, private HMAC key without storing it in the web project or database. */
function admin_auth_hmac_key(): string
{
    static $cached = null;
    if (is_string($cached)) return $cached;

    $configured = getenv('TOKYO_ADMIN_RATE_LIMIT_SECRET');
    if (is_string($configured) && strlen($configured) >= 32) {
        return $cached = $configured;
    }

    $database = getenv('RESTAURANT_DB') ?: rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tokyo-restaurant.sqlite';
    $databaseIdentity = realpath($database) ?: $database;
    $keyPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'tokyo-admin-rate-' . hash('sha256', $databaseIdentity) . '.key';
    $handle = @fopen($keyPath, 'c+b');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Could not initialize the administrative rate-limit key.');
    }

    try {
        rewind($handle);
        $key = trim((string)stream_get_contents($handle));
        if (!preg_match('/\A[a-f0-9]{64}\z/i', $key)) {
            $key = bin2hex(random_bytes(32));
            if (!ftruncate($handle, 0) || !rewind($handle) || fwrite($handle, $key) !== strlen($key) || !fflush($handle)) {
                throw new RuntimeException('Could not persist the administrative rate-limit key.');
            }
            @chmod($keyPath, 0600);
        }
        return $cached = $key;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Verify an administrator login with atomic SQLite cooldown buckets.
 * The IP bucket allows five failures per 15 minutes; an account bucket also
 * slows distributed guessing. Raw addresses and email addresses are not stored.
 */
function admin_authenticate(PDO $pdo, string $emailInput, string $password, string $remoteAddress, ?int $now = null): ?int
{
    $now ??= time();
    $windowSeconds = 900;
    $emailCandidate = substr(trim($emailInput), 0, 254);
    $email = filter_var($emailCandidate, FILTER_VALIDATE_EMAIL);
    $normalizedEmail = $email ? strtolower((string)$email) : '';
    $ip = filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : 'unknown';
    $secret = admin_auth_hmac_key();
    $buckets = [
        [hash_hmac('sha256', 'admin-ip:' . $ip, $secret), 5],
    ];
    if ($normalizedEmail !== '') {
        $buckets[] = [hash_hmac('sha256', 'admin-account:' . $normalizedEmail, $secret), 20];
    }
    $bucketHashes = array_column($buckets, 0);
    $login = $pdo->prepare('SELECT id,password_hash FROM admins WHERE email=? LIMIT 1');
    $login->execute([$normalizedEmail]);
    $admin = $login->fetch(PDO::FETCH_ASSOC);

    $passwordForVerify = strlen($password) <= 4096 ? $password : substr($password, 0, 4096);
    // Equalize unknown accounts and oversized submissions with a dummy bcrypt check.
    $hash = $admin === false
        ? '$2y$12$kUVSVechHpw3fCuY6ZfSAOv.ErXq.BDg71pL/fNeYQcUL6zQiTCLi'
        : (string)$admin['password_hash'];
    $passwordMatches = password_verify($passwordForVerify, $hash);
    $verified = $admin !== false && strlen($password) <= 4096 && $passwordMatches;

    sqlite_begin_immediate($pdo);
    try {
        $pdo->exec('DELETE FROM admin_login_attempts WHERE updated_at < ' . max(0, $now - 86400));
        $read = $pdo->prepare('SELECT bucket_hash,window_started_at,attempts,locked_until FROM admin_login_attempts WHERE bucket_hash IN ('
            . implode(',', array_fill(0, count($bucketHashes), '?')) . ')');
        $read->execute($bucketHashes);
        $rows = [];
        foreach ($read->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(string)$row['bucket_hash']] = $row;

        $locked = false;
        foreach ($bucketHashes as $hash) {
            if (isset($rows[$hash]) && (int)$rows[$hash]['locked_until'] > $now) $locked = true;
        }
        if ($locked) {
            sqlite_commit($pdo);
            return null;
        }

        if ($verified) {
            $clear = $pdo->prepare('DELETE FROM admin_login_attempts WHERE bucket_hash IN ('
                . implode(',', array_fill(0, count($bucketHashes), '?')) . ')');
            $clear->execute($bucketHashes);
            sqlite_commit($pdo);
            return (int)$admin['id'];
        }

        $save = $pdo->prepare('INSERT INTO admin_login_attempts(bucket_hash,window_started_at,attempts,locked_until,updated_at)
            VALUES(?,?,?,?,?)
            ON CONFLICT(bucket_hash) DO UPDATE SET window_started_at=excluded.window_started_at,
                attempts=excluded.attempts,locked_until=excluded.locked_until,updated_at=excluded.updated_at');
        foreach ($buckets as [$hash, $limit]) {
            $row = $rows[$hash] ?? null;
            $expired = !$row || $now < (int)$row['window_started_at']
                || $now - (int)$row['window_started_at'] >= $windowSeconds
                || (int)$row['attempts'] >= $limit;
            $started = $expired ? $now : (int)$row['window_started_at'];
            $attempts = $expired ? 1 : (int)$row['attempts'] + 1;
            $lockedUntil = $attempts >= $limit ? $now + $windowSeconds : 0;
            $save->execute([$hash, $started, $attempts, $lockedUntil, $now]);
        }
        sqlite_commit($pdo);
        return null;
    } catch (Throwable $error) {
        sqlite_rollback($pdo);
        throw $error;
    }
}
