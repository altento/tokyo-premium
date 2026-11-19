<?php
declare(strict_types=1);

// Focused real-HTTP admin checks against a disposable SQLite database.
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "SKIP: pdo_sqlite required.\n"); exit(77); }
$root = dirname(__DIR__);
$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tokyo-marketing-integrity-' . bin2hex(random_bytes(6)) . '.sqlite';
$previousDb = getenv('RESTAURANT_DB');
putenv('RESTAURANT_DB=' . $dbPath);
require $root . '/bootstrap.php';
$pdo = db();
$checks = []; $cookie = ''; $server = null;
$assert = static function (bool $ok, string $name, array $observed = []) use (&$checks): void {
    $checks[] = ['name' => $name, 'passed' => $ok, 'observed' => $observed];
};
$read = static function (string $sql, array $parameters = []) use ($dbPath): array {
    $connection = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $query = $connection->prepare($sql); $query->execute($parameters);
    return $query->fetchAll();
};
$log = $dbPath . '.server.log';
try {
    $password = 'Local-test-' . bin2hex(random_bytes(8));
    $pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)')->execute(['marketing-integrity@example.test', password_hash($password, PASSWORD_DEFAULT)]);
    $port = random_int(21000, 45000); $base = 'http://127.0.0.1:' . $port;
    $command = [PHP_BINARY];
    if ($ini = php_ini_loaded_file()) array_push($command, '-c', $ini);
    array_push($command, '-S', '127.0.0.1:' . $port, '-t', $root);
    $pipes = [];
    $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root);
    if (!is_resource($server)) throw new RuntimeException('Cannot start HTTP server.');
    fclose($pipes[0]);
    $request = static function (string $path, ?array $fields = null) use ($base, &$cookie): array {
        $headers = ['Accept: text/html'];
        if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
        if ($fields !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $context = stream_context_create(['http' => ['method' => $fields === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers), 'content' => $fields === null ? '' : http_build_query($fields), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15]]);
        $body = @file_get_contents($base . $path, false, $context);
        $responseHeaders = $http_response_header ?? [];
        foreach ($responseHeaders as $header) if (stripos($header, 'Set-Cookie: tokyo_session=') === 0) $cookie = explode(';', substr($header, 12))[0];
        preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
        return [(int)($match[1] ?? 0), (string)$body];
    };
    for ($attempt = 0; $attempt < 100; $attempt++) { [$status, $html] = $request('/admin.php'); if ($status > 0) break; usleep(20000); }
    $token = static function (string $html): string {
        if (!preg_match('/name="csrf" value="([^"]+)"/', $html, $match)) throw new RuntimeException('CSRF token missing.');
        return html_entity_decode($match[1], ENT_QUOTES);
    };
    [$anonymousStatus] = $request('/admin-marketing.php', ['action' => 'promo-save', 'code' => 'FORGED']);
    $assert($anonymousStatus === 403, 'unauthenticated marketing mutation denied', ['status' => $anonymousStatus]);
    [$status, $html] = $request('/admin.php');
    [$loginStatus] = $request('/admin.php', ['action' => 'login', 'csrf' => $token($html), 'email' => 'marketing-integrity@example.test', 'password' => $password]);
    $assert(in_array($loginStatus, [302, 303], true), 'real HTTP admin login succeeds', ['status' => $loginStatus]);
    [$status, $html] = $request('/admin-marketing.php'); $csrf = $token($html);
    $post = static function (array $fields) use ($request, $csrf): array {
        [$status, $html] = $request('/admin-marketing.php', ['csrf' => $csrf] + $fields);
        if ($status === 303) return $request('/admin-marketing.php');
        return [$status, $html];
    };
    $dishes = array_map('intval', array_column($read('SELECT id FROM dishes WHERE active=1 ORDER BY id LIMIT 2'), 'id'));
    [$dishId, $secondDishId] = $dishes;
    $campaign = ['action' => 'campaign-save', 'slug' => 'qa-marketing-gift', 'campaign_type' => 'gift_threshold', 'title' => 'QA gift', 'description' => '', 'image_url' => 'assets/generated/room-main-hall.webp', 'reward_type' => 'gift_dish', 'reward_value' => '0', 'gift_dish_id' => (string)$dishId, 'gift_quantity' => '3', 'gift_max_per_order' => '2', 'minimum_subtotal' => '1', 'maximum_discount' => '0', 'active' => 'on', 'stackable' => 'on'];
    [$status] = $post($campaign);
    $campaignRow = $read('SELECT * FROM promotion_campaigns WHERE slug=?', [$campaign['slug']])[0] ?? [];
    $campaignId = (int)($campaignRow['id'] ?? 0);
    $stock = $read('SELECT * FROM campaign_gift_options WHERE campaign_id=? AND dish_id=?', [$campaignId, $dishId])[0] ?? [];
    $assert($status === 200 && $campaignId > 0 && (int)($stock['remaining_quantity'] ?? -1) === 3 && (int)($stock['max_per_order'] ?? -1) === 2, 'campaign creation sets initial gift stock');
    $campaign['id'] = (string)$campaignId;

    // Explicitly disable the gift, then save unrelated campaign copy.
    $stockFields = ['action' => 'gift-stock-save', 'campaign_id' => (string)$campaignId, 'dish_id' => (string)$dishId, 'remaining_quantity' => '3', 'max_per_order' => '2'];
    $post($stockFields); $campaign['title'] = 'Changed campaign title'; $post($campaign);
    $stock = $read('SELECT active,remaining_quantity,max_per_order FROM campaign_gift_options WHERE campaign_id=? AND dish_id=?', [$campaignId, $dishId])[0];
    $assert((int)$stock['active'] === 0, 'metadata save preserves explicitly disabled gift', $stock);

    // The editor still carries 3, while another transaction consumed 2.
    $pdo->prepare('UPDATE campaign_gift_options SET active=1,remaining_quantity=1 WHERE campaign_id=? AND dish_id=?')->execute([$campaignId, $dishId]);
    $post($campaign);
    $stock = $read('SELECT active,remaining_quantity,max_per_order FROM campaign_gift_options WHERE campaign_id=? AND dish_id=?', [$campaignId, $dishId])[0];
    $assert((int)$stock['remaining_quantity'] === 1 && (int)$stock['max_per_order'] === 2, 'metadata save cannot replenish stock consumed since editor opened', $stock);
    $campaign['reward_type'] = 'none'; $post($campaign);
    $quote = commerce_quote($pdo, [['id' => $secondDishId, 'qty' => 1]], [], '', 'pickup');
    $assert($quote['ok'] && $quote['gift_items'] === [] && $quote['gift_options'] === [], 'switching reward to none stops threshold gifts while retaining stored inventory', ['gift_count' => count($quote['gift_items'] ?? []), 'option_count' => count($quote['gift_options'] ?? [])]);
    $campaign['reward_type'] = 'gift_dish'; $campaign['campaign_type'] = 'exclusive'; $post($campaign);
    $promoStockFields = $stockFields + ['active' => 'on']; $promoStockFields['remaining_quantity'] = '2';
    $post($promoStockFields);
    $stock = $read('SELECT remaining_quantity,active FROM campaign_gift_options WHERE campaign_id=? AND dish_id=?', [$campaignId, $dishId])[0];
    $assert((int)$stock['remaining_quantity'] === 2 && (int)$stock['active'] === 1, 'explicit stock management also supports promo gift campaigns', $stock);

    $promo = ['action' => 'promo-save', 'code' => 'QAADMIN', 'campaign_id' => (string)$campaignId, 'maximum_uses' => '10', 'maximum_uses_per_user' => '2', 'active' => 'on'];
    $post($promo); $pdo->prepare('UPDATE promo_codes SET uses_count=2 WHERE code=?')->execute([$promo['code']]);
    $original = $read('SELECT * FROM promo_codes WHERE code=?', [$promo['code']])[0];
    $duplicate = $promo; $duplicate['maximum_uses'] = '999'; $post($duplicate);
    $after = $read('SELECT * FROM promo_codes WHERE code=?', [$promo['code']])[0];
    $assert($after === $original, 'new promo form cannot overwrite existing code', ['maximum_uses' => $after['maximum_uses']]);
    $edit = $promo + ['original_code' => 'QAADMIN']; $edit['maximum_uses'] = '20';
    [$status, $html] = $post($edit);
    $after = $read('SELECT * FROM promo_codes WHERE code=?', [$promo['code']])[0];
    $assert($status === 200 && (int)$after['maximum_uses'] === 20 && (int)$after['uses_count'] === 2 && $after['created_at'] === $original['created_at'] && str_contains($html, 'name="original_code" value="QAADMIN"'), 'explicit promo edit preserves identity and use history');
    $missing = $edit; $missing['code'] = 'QAMISSING'; $missing['original_code'] = 'QAMISSING'; $post($missing);
    $assert($read('SELECT code FROM promo_codes WHERE code=?', ['QAMISSING']) === [], 'stale promo editor cannot create a replacement code');
    $renamed = $edit; $renamed['code'] = 'QARENAMED'; $post($renamed);
    $assert($read('SELECT code FROM promo_codes WHERE code=?', ['QARENAMED']) === [], 'promo edit rejects changed primary key');
    $beforeCsrf = $read('SELECT * FROM promo_codes WHERE code=?', ['QAADMIN']);
    [$csrfStatus] = $request('/admin-marketing.php', ['csrf' => 'invalid'] + $edit);
    $assert($csrfStatus === 419 && $beforeCsrf === $read('SELECT * FROM promo_codes WHERE code=?', ['QAADMIN']), 'invalid CSRF cannot mutate a promo');

    $collection = ['action' => 'collection-save', 'slug' => 'qa-marketing-collection', 'name' => 'QA collection', 'description' => 'Initial story', 'image_url' => 'assets/generated/room-main-hall.webp', 'theme_key' => 'autumn', 'dish_ids' => implode(',', $dishes), 'active' => 'on'];
    $post($collection); $collectionId = (int)($read('SELECT id FROM seasonal_collections WHERE slug=?', [$collection['slug']])[0]['id'] ?? 0);
    $collection['id'] = (string)$collectionId; $collection['name'] = 'Edited collection'; $post($collection);
    $members = array_map('intval', array_column($read('SELECT dish_id FROM seasonal_collection_items WHERE collection_id=? ORDER BY sort_order', [$collectionId]), 'dish_id'));
    $assert($collectionId > 0 && $members === $dishes && ($read('SELECT name FROM seasonal_collections WHERE id=?', [$collectionId])[0]['name'] ?? '') === 'Edited collection', 'collection create and edit retain item membership');
    $collection['dish_ids'] = '1,invalid'; $collection['name'] = 'Must not save'; $post($collection);
    $assert(($read('SELECT name FROM seasonal_collections WHERE id=?', [$collectionId])[0]['name'] ?? '') === 'Edited collection', 'invalid collection edit leaves existing record intact');
    $savedCollection = $read('SELECT * FROM seasonal_collections WHERE id=?', [$collectionId]);
    $savedItems = $read('SELECT * FROM seasonal_collection_items WHERE collection_id=? ORDER BY sort_order', [$collectionId]);
    foreach (['0', '1,0', '999999999999999999999999999999999999999999'] as $invalidIds) {
        $collection['dish_ids'] = $invalidIds; $post($collection);
        $assert($savedCollection === $read('SELECT * FROM seasonal_collections WHERE id=?', [$collectionId]) && $savedItems === $read('SELECT * FROM seasonal_collection_items WHERE collection_id=? ORDER BY sort_order', [$collectionId]), 'zero or overflowing collection IDs preserve metadata and links: ' . $invalidIds);
    }
    $collection['dish_ids'] = ''; $collection['name'] = 'Explicitly cleared collection'; $post($collection);
    $assert($read('SELECT dish_id FROM seasonal_collection_items WHERE collection_id=?', [$collectionId]) === [] && ($read('SELECT name FROM seasonal_collections WHERE id=?', [$collectionId])[0]['name'] ?? '') === 'Explicitly cleared collection', 'blank collection membership remains an explicit clear operation');

    $failed = count(array_filter($checks, static fn(array $check): bool => !$check['passed']));
    $report = ['date' => gmdate(DATE_ATOM), 'scope' => 'isolated HTTP and SQLite marketing integrity', 'checks' => count($checks), 'failed' => $failed, 'results' => $checks];
    if (($argv[1] ?? '') === '--evidence' && isset($argv[2])) file_put_contents($argv[2], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo = null;
    foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm', $log] as $file) if (is_file($file)) @unlink($file);
    if ($previousDb === false) putenv('RESTAURANT_DB'); else putenv('RESTAURANT_DB=' . $previousDb);
}
exit(($failed ?? 1) > 0 ? 1 : 0);
