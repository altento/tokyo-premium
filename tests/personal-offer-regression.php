<?php
declare(strict_types=1);

/** Focused server regression for admin-assigned personal offers. */
$root = dirname(__DIR__);
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n");
    exit(77);
}
$dbPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-personal-offer-'.bin2hex(random_bytes(6)).'.sqlite';
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
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute(['personal+'.bin2hex(random_bytes(5)).'@example.test','Personal QA',password_hash('test-only',PASSWORD_DEFAULT)]);
    $userId=(int)$pdo->lastInsertId();
    $userEmail=(string)$pdo->query('SELECT email FROM users WHERE id='.$userId)->fetchColumn();
    $testPasswordHash=password_hash('test-only',PASSWORD_DEFAULT);
    for($index=0;$index<205;$index++)$pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute(['newer-'.$index.'+'.bin2hex(random_bytes(3)).'@example.test','Newer account '.$index,$testPasswordHash]);
    $recentIds=array_map('intval',$pdo->query('SELECT id FROM users ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_COLUMN));
    $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$userEmail).'%';
    $search=$pdo->prepare("SELECT id,email,full_name FROM users WHERE full_name COLLATE NOCASE LIKE ? ESCAPE '!' OR email COLLATE NOCASE LIKE ? ESCAPE '!' OR CAST(id AS TEXT)=? ORDER BY id DESC LIMIT 200");
    $search->execute([$pattern,$pattern,$userEmail]);$found=$search->fetch();
    $assert(!in_array($userId,$recentIds,true)&&$found&&(int)$found['id']===$userId,'account search can find a registered user older than the recent-200 list by email');
    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,description,reward_type,reward_value,active,stackable,demo_only) VALUES(?,'personal','Private invitation','Account-scoped integration offer','percent',10,1,1,1)")->execute(['personal-'.bin2hex(random_bytes(5))]);
    $campaignId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active) VALUES(?,?,1)')->execute(['PERSONALQA',$campaignId]);
    $expires=(new DateTimeImmutable('+2 hours',new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    $unassigned=commerce_quote($pdo,[['id'=>1,'qty'=>1]],[],'PERSONALQA','pickup',$userId,'personal-qa-actor');
    $assert(!$unassigned['ok']&&str_contains($unassigned['message'],'вам недоступно'),'a valid personal promo is denied before assignment');

    $offerId=commerce_assign_personal_offer($pdo,$userId,$campaignId,$expires);
    $assigned=commerce_quote($pdo,[['id'=>1,'qty'=>1]],[],'PERSONALQA','pickup',$userId,'personal-qa-actor');
    $assert($assigned['ok']&&$assigned['discount_total']===450,'an assigned eligible offer is accepted and server-priced');
    $assert((int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE user_id='.$userId.' AND campaign_id='.$campaignId)->fetchColumn()===1,'assignment is stored once');

    $revisedExpiry=(new DateTimeImmutable('+4 hours',new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $sameOfferId=commerce_assign_personal_offer($pdo,$userId,$campaignId,$revisedExpiry);
    $assert($sameOfferId===$offerId&&(int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE user_id='.$userId.' AND campaign_id='.$campaignId)->fetchColumn()===1&&(string)$pdo->query('SELECT expires_at FROM personalized_offers WHERE id='.$offerId)->fetchColumn()===$revisedExpiry,'reassigning an unused offer updates its expiry without duplicating it');

    $invalidExpiryRejected=false;
    try { commerce_assign_personal_offer($pdo,$userId,$campaignId,'2020-02-30 12:00:00'); } catch (DomainException $error) { $invalidExpiryRejected=str_contains($error->getMessage(),'корректной будущей датой'); }
    $assert($invalidExpiryRejected,'invalid and past expiry values are rejected');
    $pdo->prepare("UPDATE personalized_offers SET expires_at='2000-01-01 00:00:00' WHERE id=?")->execute([$offerId]);
    $expired=commerce_quote($pdo,[['id'=>1,'qty'=>1]],[],'PERSONALQA','pickup',$userId,'personal-qa-actor');
    $assert(!$expired['ok']&&str_contains($expired['message'],'вам недоступно'),'expired assignment no longer authorizes the personal promo');

    commerce_revoke_personal_offer($pdo,$offerId);
    $assert((int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE id='.$offerId)->fetchColumn()===0,'admin revocation removes an unused assignment');

    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,active) VALUES(?,'exclusive','Not personal','none',1)")->execute(['not-personal-'.bin2hex(random_bytes(5))]);
    $wrongTypeId=(int)$pdo->lastInsertId();
    $wrongTypeRejected=false;
    try { commerce_assign_personal_offer($pdo,$userId,$wrongTypeId,null); } catch (DomainException $error) { $wrongTypeRejected=str_contains($error->getMessage(),'активную персональную кампанию'); }
    $assert($wrongTypeRejected,'exclusive campaigns cannot be assigned through the personal-offer path');

    $noCodeSlug='personal-no-code-'.bin2hex(random_bytes(5));
    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,active) VALUES(?,'personal','No code','percent',1)")->execute([$noCodeSlug]);
    $noCodeCampaignId=(int)$pdo->lastInsertId();
    $noCodeRejected=false;
    try { commerce_assign_personal_offer($pdo,$userId,$noCodeCampaignId,null); } catch (DomainException $error) { $noCodeRejected=str_contains($error->getMessage(),'действующий промокод'); }
    $assert($noCodeRejected,'campaigns without an active redeemable code cannot be assigned as personal invitations');

    echo "PASS: {$assertions} focused personal-offer assertions.\n";
} finally {
    $pdo=null;
    foreach([$dbPath,$dbPath.'-wal',$dbPath.'-shm'] as $file) if(is_file($file)) @unlink($file);
}
