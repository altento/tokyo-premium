<?php
declare(strict_types=1);

/** Focused SQLite regression for capped custom-set prices and promo accounting. */
$root = dirname(__DIR__);
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n");
    exit(77);
}

$dbPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-commerce-pricing-'.bin2hex(random_bytes(6)).'.sqlite';
$previousDb = getenv('RESTAURANT_DB');
putenv('RESTAURANT_DB='.$dbPath);
$pdo = null;
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    $checks++;
};

try {
    require $root.'/bootstrap.php';
    $pdo = db();

    $setDishes = $pdo->query('SELECT id,price FROM dishes WHERE active=1 ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
    if (count($setDishes) !== 3) throw new RuntimeException('The pricing fixture requires three active dishes.');
    $setItems = [];
    $setSubtotal = 0;
    foreach ($setDishes as $dish) {
        $setItems[] = ['id'=>(int)$dish['id'],'qty'=>1];
        $setSubtotal += (int)$dish['price'];
    }
    $customSet = ['variant'=>'signature','items'=>$setItems];

    $setSlug = 'qa-set-price-'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,reward_value,maximum_discount,active,stackable,demo_only) VALUES(?,'exclusive','QA set price','set_price',0,1000,1,1,1)")->execute([$setSlug]);
    $setCampaignId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active) VALUES(?,?,1)')->execute(['SETCAP'.$setCampaignId,$setCampaignId]);
    $setCode = 'SETCAP'.$setCampaignId;

    $setQuote = commerce_quote($pdo,[],[$customSet,$customSet],$setCode,'pickup',null,'pricing-set-quote');
    $check($setQuote['ok'] && $setQuote['discount_total'] === 1000 && array_sum(array_column($setQuote['custom_sets'],'discount')) === 1000, 'aggregate set-price cap is allocated across both sets');
    $check(array_sum(array_column($setQuote['custom_sets'],'discount')) <= 1000 && $setQuote['total'] === $setQuote['subtotal'] - 1000, 'quoted set lines and order total use the same capped discount');

    $setOrderInput = ['name'=>'Pricing QA','phone'=>'+77010000000','address'=>'','fulfillment'=>'pickup','items'=>[],'custom_sets'=>[$customSet,$customSet],'promo_code'=>$setCode];
    $pdo->beginTransaction();
    $setOrder = commerce_persist_order($pdo,$setOrderInput,null,'pricing-set-order');
    $pdo->commit();
    $setLines = $pdo->prepare('SELECT price,quantity FROM order_items WHERE order_id=? AND dish_id IS NULL ORDER BY id');
    $setLines->execute([$setOrder['id']]);
    $savedSetLines = $setLines->fetchAll(PDO::FETCH_ASSOC);
    $savedSetMerchandise = array_sum(array_map(static fn(array $line): int => (int)$line['price'] * (int)$line['quantity'],$savedSetLines));
    $check(count($savedSetLines) === 2 && $savedSetMerchandise === $setOrder['total'] && $savedSetMerchandise === $setQuote['subtotal'] - $setQuote['discount_total'], 'persisted custom-set line prices sum to the authoritative quoted merchandise total');
    $setRedemption = $pdo->prepare('SELECT discount_amount FROM promo_redemptions WHERE order_id=?');
    $setRedemption->execute([$setOrder['id']]);
    $check((int)$setRedemption->fetchColumn() === 1000, 'set-price redemption records only the capped promotion discount');

    $email = 'pricing+'.bin2hex(random_bytes(5)).'@example.test';
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute([$email,'Pricing QA',password_hash('test-only',PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO loyalty_ledger(user_id,event_key,transaction_type,points,note) VALUES(?,'qa:pricing:points','earn',1000,'Pricing regression balance')")->execute([$userId]);

    $promoSlug = 'qa-stack-promo-'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,reward_value,active,stackable,demo_only) VALUES(?,'exclusive','QA stackable promo','percent',10,1,1,1)")->execute([$promoSlug]);
    $promoCampaignId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active) VALUES(?,?,1)')->execute(['POINTS'.$promoCampaignId,$promoCampaignId]);
    $promoCode = 'POINTS'.$promoCampaignId;
    $pointsToRedeem = 100;
    $pointItems = [['id'=>(int)$setDishes[0]['id'],'qty'=>2]];
    $expectedPromoDiscount = (int)floor((int)$setDishes[0]['price'] * 2 * 10 / 100);
    $quote = commerce_quote($pdo,$pointItems,[],$promoCode,'pickup',$userId,'pricing-points-quote',null,$pointsToRedeem);
    $check($quote['ok'] && $quote['promotion_discount'] === $expectedPromoDiscount && $quote['discount_total'] === $expectedPromoDiscount + $pointsToRedeem, 'quote separates promo discount from loyalty redemption');

    $pointOrderInput = ['name'=>'Pricing QA','phone'=>'+77010000000','address'=>'','fulfillment'=>'pickup','items'=>$pointItems,'custom_sets'=>[],'promo_code'=>$promoCode,'points_to_redeem'=>$pointsToRedeem];
    $pdo->beginTransaction();
    $pointOrder = commerce_persist_order($pdo,$pointOrderInput,$userId,'pricing-points-order');
    $pdo->commit();
    $redemption = $pdo->prepare('SELECT discount_amount FROM promo_redemptions WHERE order_id=?');
    $redemption->execute([$pointOrder['id']]);
    $storedPromoDiscount = (int)$redemption->fetchColumn();
    $storedOrderDiscount = (int)$pdo->query('SELECT discount_total FROM orders WHERE id='.(int)$pointOrder['id'])->fetchColumn();
    $check($storedPromoDiscount === $expectedPromoDiscount && $storedOrderDiscount === $expectedPromoDiscount + $pointsToRedeem, 'promo redemption stores only the promo discount while order totals include points');

    $certificateAmount = commerce_certificate_denominations($pdo)[0] ?? 0;
    $certificate = commerce_create_certificate_preview($pdo,$userId,'qa-certificate-privacy-'.bin2hex(random_bytes(4)),'QA Recipient','recipient@example.test',$certificateAmount,'sakura','Privacy regression preview');
    $certificateEmail = $pdo->prepare('SELECT recipient_email FROM gift_certificate_previews WHERE id=?');
    $certificateEmail->execute([$certificate['id']]);
    $check((string)$certificateEmail->fetchColumn() === '', 'gift certificate preview does not persist a recipient email, even if a caller supplies one');
    $check(!array_key_exists('recipient_email',$certificate), 'gift certificate preview response omits recipient contact data');

    fwrite(STDOUT,"PASS: {$checks} commerce pricing regression checks\n");
} finally {
    $pdo = null;
    foreach ([$dbPath,$dbPath.'-wal',$dbPath.'-shm'] as $file) if (is_file($file)) @unlink($file);
    if ($previousDb === false) putenv('RESTAURANT_DB');
    else putenv('RESTAURANT_DB='.$previousDb);
}
