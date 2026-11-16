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
$createScheduledCampaign = static function (string $suffix, array $schedule) use ($pdo, $campaignSlug, $giftDish): int {
    $insert = $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,minimum_subtotal,active,stackable,demo_only,sort_order,starts_at,ends_at,weekday_mask,starts_time,ends_time,timezone)
        VALUES(?,'gift_threshold',?,'gift_dish',1,1,0,1,-100,?,?,?,?,?,'Asia/Qyzylorda')");
    $insert->execute([
        $campaignSlug . '-' . $suffix,
        'QA scheduled gift ' . $suffix,
        $schedule['starts_at'] ?? null,
        $schedule['ends_at'] ?? null,
        $schedule['weekday_mask'] ?? 127,
        $schedule['starts_time'] ?? null,
        $schedule['ends_time'] ?? null,
    ]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO campaign_gift_options(campaign_id,dish_id,remaining_quantity,max_per_order,active) VALUES(?,?,3,2,1)')->execute([$id, $giftDish]);
    return $id;
};
$nowInRestaurantZone = new DateTimeImmutable('now', new DateTimeZone('Asia/Qyzylorda'));
$scheduledCandidates = [
    'future' => $createScheduledCampaign('future', ['starts_at'=>'2099-01-01 00:00:00']),
    'expired' => $createScheduledCampaign('expired', ['starts_at'=>'2000-01-01 00:00:00','ends_at'=>'2000-01-02 00:00:00']),
    'weekday' => $createScheduledCampaign('weekday', ['weekday_mask'=>0]),
    'time' => $createScheduledCampaign('time', [
        'starts_time'=>$nowInRestaurantZone->modify('+2 hours')->format('H:i'),
        'ends_time'=>$nowInRestaurantZone->modify('+3 hours')->format('H:i'),
    ]),
];
$cart = [['id'=>$eligibleDish,'qty'=>1]];
$actor = 'gift-quantity-regression';

$scheduledCampaignActive = $pdo->prepare('UPDATE promotion_campaigns SET active=? WHERE id=?');
foreach ($scheduledCandidates as $scheduleName => $scheduledCampaignId) {
    $pdo->exec("UPDATE promotion_campaigns SET active=0 WHERE campaign_type='gift_threshold' AND id<>" . $campaignId);
    $scheduledCampaignActive->execute([1, $scheduledCampaignId]);
    $scheduledQuote = commerce_quote($pdo,$cart,[],'','pickup',null,$actor,$giftDish,0,'',false,2);
    $selectedCampaignId = (int)($scheduledQuote['gift_items'][0]['campaign_id'] ?? 0);
    $check($scheduledQuote['ok'] && $selectedCampaignId === $campaignId, "{$scheduleName} threshold-gift campaign is skipped in favor of the active campaign");
}
$pdo->exec("UPDATE promotion_campaigns SET active=0 WHERE campaign_type='gift_threshold' AND id<>" . $campaignId);

$pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute(['tier-low+'.bin2hex(random_bytes(4)).'@example.test','Below required tier',password_hash('test-only',PASSWORD_DEFAULT)]);
$belowTierUserId = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute(['tier-gold+'.bin2hex(random_bytes(4)).'@example.test','Required tier',password_hash('test-only',PASSWORD_DEFAULT)]);
$requiredTierUserId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO orders(user_id,customer_name,phone,fulfillment,total,subtotal,discount_total,delivery_fee,status) VALUES(?,?,?,'pickup',150000,150000,0,0,'completed')")
    ->execute([$requiredTierUserId,'Tier QA','+77010000000']);
$pdo->prepare('UPDATE promotion_campaigns SET required_tier=? WHERE id=?')->execute(['gold',$campaignId]);
$belowTierGift = commerce_quote($pdo,$cart,[],'','pickup',$belowTierUserId,'gift-tier-low',$giftDish,0,'',false,1);
$check(!$belowTierGift['ok'], 'threshold gift requiring Gold is denied to a below-tier account');
$qualifiedTierGift = commerce_quote($pdo,$cart,[],'','pickup',$requiredTierUserId,'gift-tier-gold',$giftDish,0,'',false,1);
$check($qualifiedTierGift['ok'] && (int)($qualifiedTierGift['gift_items'][0]['campaign_id'] ?? 0) === $campaignId, 'threshold gift requiring Gold is available to a qualifying account');
$pdo->prepare('UPDATE promotion_campaigns SET required_tier=NULL WHERE id=?')->execute([$campaignId]);

$tierPromoSlug = 'qa-tier-promo-' . bin2hex(random_bytes(4));
$tierPromo = $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,reward_value,active,stackable,demo_only,required_tier) VALUES(?,'exclusive','QA tier offer','percent',10,1,1,1,'gold')");
$tierPromo->execute([$tierPromoSlug]);
$tierPromoCampaignId = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active) VALUES(?,?,1)')->execute(['QATIER'.$tierPromoCampaignId,$tierPromoCampaignId]);
$tierPromoCode = 'QATIER'.$tierPromoCampaignId;
$belowTierPromo = commerce_quote($pdo,$cart,[],$tierPromoCode,'pickup',$belowTierUserId,'promo-tier-low');
$check(!$belowTierPromo['ok'] && str_contains($belowTierPromo['message'],'требуемого уровня'), 'tier-restricted promo quote rejects an account below Gold');
$qualifiedTierPromo = commerce_quote($pdo,$cart,[],$tierPromoCode,'pickup',$requiredTierUserId,'promo-tier-gold');
$check($qualifiedTierPromo['ok'] && $qualifiedTierPromo['discount_total'] > 0, 'tier-restricted promo quote is server-priced for a qualifying Gold account');
$visibleToBelowTier = array_map(static fn(array $row): int => (int)$row['id'],commerce_public_offers($pdo,$belowTierUserId));
$visibleToRequiredTier = array_map(static fn(array $row): int => (int)$row['id'],commerce_public_offers($pdo,$requiredTierUserId));
$check(!in_array($tierPromoCampaignId,$visibleToBelowTier,true) && in_array($tierPromoCampaignId,$visibleToRequiredTier,true), 'tier-restricted offer cards are hidden from below-tier accounts');
$orderInput = ['name'=>'Гость QA','phone'=>'+77010000000','address'=>'','fulfillment'=>'pickup','items'=>$cart,'custom_sets'=>[],'promo_code'=>$tierPromoCode];
$pdo->beginTransaction();
$orderTierRejected = false;
try { commerce_persist_order($pdo,$orderInput,$belowTierUserId,'promo-tier-low-order'); }
catch (DomainException $error) { $orderTierRejected = str_contains($error->getMessage(),'требуемого уровня'); }
finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
$check($orderTierRejected, 'order persistence rechecks a tier-restricted promo for a below-tier account');

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
