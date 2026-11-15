<?php
declare(strict_types=1);

// Fast isolated SQLite regression for configurable threshold-gift quantities.
$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tokyo-gift-quantity-' . bin2hex(random_bytes(6)) . '.sqlite';
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n"); exit(77); }
putenv('RESTAURANT_DB=' . $dbPath);
require dirname(__DIR__) . '/bootstrap.php';
$pdo = db();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    $checks++;
};

$pdo->exec("UPDATE promotion_campaigns SET active=0 WHERE campaign_type='gift_threshold'");
$eligibleDish = (int)$pdo->query('SELECT id FROM dishes WHERE active=1 AND id<>2 ORDER BY id LIMIT 1')->fetchColumn();
$giftDish = 2;
$campaignSlug = 'qa-gift-quantity-' . bin2hex(random_bytes(4));
$campaign = $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,description,reward_type,minimum_subtotal,active,stackable,demo_only) VALUES(?,'gift_threshold','QA quantity gift','Fast integration fixture','gift_dish',1,1,0,1)");
$campaign->execute([$campaignSlug]);
$campaignId = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO campaign_gift_options(campaign_id,dish_id,remaining_quantity,max_per_order,active) VALUES(?,?,3,2,1)')->execute([$campaignId, $giftDish]);
$cart = [['id'=>$eligibleDish,'qty'=>1]];
$actor = 'gift-quantity-regression';

$quote = commerce_quote($pdo,$cart,[],'','pickup',null,$actor,$giftDish,0,'',false,2);
$gift = $quote['gift_items'][0] ?? null;
$option = $quote['gift_options'][0] ?? [];
$check($quote['ok'] && $gift && $gift['qty'] === 2, 'quote accepts two gifts within the configured limit');
$check((int)($option['max_per_order'] ?? 0) === 2 && (int)($option['remaining_quantity'] ?? 0) === 3, 'quote exposes server-owned quantity and stock limits');
$overLimit = commerce_quote($pdo,$cart,[],'','pickup',null,$actor,$giftDish,0,'',false,3);
$check(!$overLimit['ok'], 'quote rejects quantity above max_per_order');
$zero = commerce_quote($pdo,$cart,[],'','pickup',null,$actor,$giftDish,0,'',false,0);
$check(!$zero['ok'], 'quote rejects zero quantity');
$pdo->prepare('UPDATE campaign_gift_options SET remaining_quantity=1 WHERE campaign_id=? AND dish_id=?')->execute([$campaignId,$giftDish]);
$overStock = commerce_quote($pdo,$cart,[],'','pickup',null,$actor,$giftDish,0,'',false,2);
$check(!$overStock['ok'], 'quote rejects quantity above remaining stock');
$pdo->prepare('UPDATE campaign_gift_options SET remaining_quantity=3 WHERE campaign_id=? AND dish_id=?')->execute([$campaignId,$giftDish]);

$orderInput = ['name'=>'Гость QA','phone'=>'+77010000000','address'=>'','fulfillment'=>'pickup','items'=>$cart,'custom_sets'=>[],'gift_dish_id'=>$giftDish,'gift_quantity'=>2];
$pdo->beginTransaction();
$order = commerce_persist_order($pdo,$orderInput,null,$actor);
$pdo->commit();
$giftLine = $pdo->prepare("SELECT quantity FROM order_items WHERE order_id=? AND item_name LIKE 'Подарок · %'");
$giftLine->execute([$order['id']]);
$check((int)$giftLine->fetchColumn() === 2, 'order stores the selected gift quantity');
$stock = $pdo->prepare('SELECT remaining_quantity FROM campaign_gift_options WHERE campaign_id=? AND dish_id=?');
$stock->execute([$campaignId,$giftDish]);
$check((int)$stock->fetchColumn() === 1, 'order atomically decrements stock by the gift quantity');
$legacyInput = $orderInput;
unset($legacyInput['gift_quantity']);
$pdo->beginTransaction();
$legacyOrder = commerce_persist_order($pdo,$legacyInput,null,$actor);
$pdo->commit();
$legacyLine = $pdo->prepare("SELECT quantity FROM order_items WHERE order_id=? AND item_name LIKE 'Подарок · %'");
$legacyLine->execute([$legacyOrder['id']]);
$check((int)$legacyLine->fetchColumn() === 1, 'omitted quantity preserves the one-gift default for older callers');
$stock->execute([$campaignId,$giftDish]);
$check((int)$stock->fetchColumn() === 0, 'default gift order decrements one item');

unset($pdo);
foreach ([$dbPath,$dbPath.'-wal',$dbPath.'-shm'] as $file) if (is_file($file)) @unlink($file);
fwrite(STDOUT, "PASS: {$checks} gift quantity regression checks\n");
