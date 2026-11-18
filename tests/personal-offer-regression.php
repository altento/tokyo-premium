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
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute(['other+'.bin2hex(random_bytes(5)).'@example.test','Other QA',password_hash('test-only',PASSWORD_DEFAULT)]);
    $otherUserId=(int)$pdo->lastInsertId();
    $testPasswordHash=password_hash('test-only',PASSWORD_DEFAULT);
    for($index=0;$index<205;$index++)$pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute(['newer-'.$index.'+'.bin2hex(random_bytes(3)).'@example.test','Newer account '.$index,$testPasswordHash]);
    $recentIds=array_map('intval',$pdo->query('SELECT id FROM users ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_COLUMN));
    $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$userEmail).'%';
    $search=$pdo->prepare("SELECT id,email,full_name FROM users WHERE full_name COLLATE NOCASE LIKE ? ESCAPE '!' OR email COLLATE NOCASE LIKE ? ESCAPE '!' OR CAST(id AS TEXT)=? ORDER BY id DESC LIMIT 200");
    $search->execute([$pattern,$pattern,$userEmail]);$found=$search->fetch();
    $assert(!in_array($userId,$recentIds,true)&&$found&&(int)$found['id']===$userId,'account search can find a registered user older than the recent-200 list by email');
    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,description,reward_type,reward_value,active,stackable,demo_only) VALUES(?,'personal','Private invitation','Account-scoped integration offer','percent',10,1,1,1)")->execute(['personal-'.bin2hex(random_bytes(5))]);
    $campaignId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active,maximum_uses) VALUES(?,?,1,1)')->execute(['PERSONALQA',$campaignId]);
    $expires=(new DateTimeImmutable('+2 hours',new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    // Exercise the route gates in separate PHP processes so admin exit paths
    // cannot terminate this isolated regression runner.
    $runPhp = static function (string $code) use ($root): string {
        static $childHasSqlite=null;
        $pipes=[];
        $command=[PHP_BINARY];
        $iniFile=php_ini_loaded_file();
        if($iniFile)$command=array_merge($command,['-c',$iniFile]);
        // CLI-only extension flags used by the parent test process are not
        // inherited by child PHP processes. Re-enable SQLite only when the
        // loaded php.ini does not provide its PDO driver.
        if($childHasSqlite===null) {
            $probeCommand=$command;
            $probeCommand=array_merge($probeCommand,['-r','echo in_array("sqlite", PDO::getAvailableDrivers(), true) ? "1" : "0";']);
            $probePipes=[];
            $probe=proc_open($probeCommand,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$probePipes,$root);
            if(!is_resource($probe))throw new RuntimeException('Could not inspect child PHP SQLite configuration.');
            fclose($probePipes[0]);$probeOutput=stream_get_contents($probePipes[1]);$probeError=stream_get_contents($probePipes[2]);fclose($probePipes[1]);fclose($probePipes[2]);$probeStatus=proc_close($probe);
            if($probeStatus!==0)throw new RuntimeException('Could not inspect child PHP SQLite configuration: '.$probeError);
            $childHasSqlite=trim((string)$probeOutput)==='1';
        }
        if(!$childHasSqlite) {
            $extensionDir=ini_get('extension_dir');
            if($extensionDir!=='')$command=array_merge($command,['-d','extension_dir='.$extensionDir]);
            $sqliteExtensions=PHP_OS_FAMILY==='Windows'
                ? ['php_sqlite3.dll','php_pdo_sqlite.dll']
                : ['sqlite3','pdo_sqlite'];
            foreach($sqliteExtensions as $extension)$command=array_merge($command,['-d','extension='.$extension]);
        }
        $command=array_merge($command,['-r',$code]);
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        if(!is_resource($process))throw new RuntimeException('Could not start isolated PHP route check.');
        fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
        if($status!==0)throw new RuntimeException('Isolated PHP route check failed: '.$stderr.' '.$stdout);
        return (string)$stdout;
    };
    $route=var_export($root.'/admin-marketing.php',true);
    $anonymousOutput=$runPhp('$_SERVER["REQUEST_METHOD"]="GET"; require '.$route.';');
    $assert(str_contains($anonymousOutput,'ДОСТУП ОГРАНИЧЕН'),'marketing route denies an unauthenticated session before rendering controls');
    $csrfSessionId='tp'.bin2hex(random_bytes(12));
    try {
        $csrfOutput=$runPhp('session_name("tokyo_session"); session_save_path('.var_export(sys_get_temp_dir(),true).'); session_id('.var_export($csrfSessionId,true).'); session_start(); $_SESSION["admin_id"]=1; session_write_close(); $_SERVER["REQUEST_METHOD"]="POST"; $_POST=["action"=>"personal-offer-save","user_id"=>'.var_export((string)$userId,true).',"campaign_id"=>'.var_export((string)$campaignId,true).']; require '.$route.';');
        $assert(str_contains($csrfOutput,'Сессия формы истекла'),'marketing route rejects a privileged POST without a valid CSRF token');
    } finally {
        $sessionFile=sys_get_temp_dir().DIRECTORY_SEPARATOR.'sess_'.$csrfSessionId;
        if(is_file($sessionFile))@unlink($sessionFile);
    }
    $assert((int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE campaign_id='.$campaignId)->fetchColumn()===0,'authorization and CSRF failures do not create assignments');

    $unassigned=commerce_quote($pdo,[['id'=>1,'qty'=>1]],[],'PERSONALQA','pickup',$userId,'personal-qa-actor');
    $assert(!$unassigned['ok']&&str_contains($unassigned['message'],'вам недоступно'),'a valid personal promo is denied before assignment');

    $offerId=commerce_assign_personal_offer($pdo,$userId,$campaignId,$expires);
    $assigned=commerce_quote($pdo,[['id'=>1,'qty'=>1]],[],'PERSONALQA','pickup',$userId,'personal-qa-actor');
    $assert($assigned['ok']&&$assigned['discount_total']===450,'an assigned eligible offer is accepted and server-priced');
    $assert((int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE user_id='.$userId.' AND campaign_id='.$campaignId)->fetchColumn()===1,'assignment is stored once');
    $visibleCampaignIds=static fn(): array=>array_map(static fn(array $row):int=>(int)$row['id'],commerce_public_offers($pdo,$userId));
    $assert(in_array($campaignId,$visibleCampaignIds(),true),'assigned offer with an active, in-window, unused code is listed');
    $pdo->prepare('UPDATE promo_codes SET active=0 WHERE code=?')->execute(['PERSONALQA']);
    $assert(!in_array($campaignId,$visibleCampaignIds(),true),'offer card disappears when its only promo code is disabled');
    $pdo->prepare('UPDATE promo_codes SET active=1,uses_count=1 WHERE code=?')->execute(['PERSONALQA']);
    $assert(!in_array($campaignId,$visibleCampaignIds(),true),'offer card disappears when its only promo code reaches its usage limit');
    $pdo->prepare("UPDATE promo_codes SET uses_count=0,ends_at='2000-01-01 00:00:00' WHERE code=?")->execute(['PERSONALQA']);
    $assert(!in_array($campaignId,$visibleCampaignIds(),true),'offer card disappears when its only promo code has expired');
    $pdo->prepare("UPDATE promo_codes SET ends_at=NULL,starts_at='2099-01-01 00:00:00' WHERE code=?")->execute(['PERSONALQA']);
    $assert(!in_array($campaignId,$visibleCampaignIds(),true),'offer card stays hidden before its only promo code starts');
    $pdo->prepare('UPDATE promo_codes SET starts_at=NULL WHERE code=?')->execute(['PERSONALQA']);
    $otherAccountQuote=commerce_quote($pdo,[['id'=>1,'qty'=>1]],[],'PERSONALQA','pickup',$otherUserId,'other-personal-qa-actor');
    $assert(!$otherAccountQuote['ok']&&str_contains($otherAccountQuote['message'],'вам недоступно'),'an assigned offer is not visible to another account');

    $missingUserId=(int)$pdo->query('SELECT MAX(id)+1000 FROM users')->fetchColumn();
    $missingUserRejected=false;
    try { commerce_assign_personal_offer($pdo,$missingUserId,$campaignId,null); } catch (DomainException $error) { $missingUserRejected=str_contains($error->getMessage(),'Аккаунт не найден'); }
    $assert($missingUserRejected&&!$pdo->inTransaction()&&(int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE user_id='.$missingUserId)->fetchColumn()===0,'assignment validates the selected owner and leaves no open transaction for a missing account');

    // Force an insert failure after BEGIN IMMEDIATE; the function must roll
    // back and leave both the connection and assignment table clean.
    $pdo->exec('CREATE TRIGGER personal_offer_regression_abort BEFORE INSERT ON personalized_offers WHEN NEW.user_id='.$otherUserId.' AND NEW.campaign_id='.$campaignId." BEGIN SELECT RAISE(ABORT,'personal offer rollback probe'); END");
    $insertRolledBack=false;
    try { commerce_assign_personal_offer($pdo,$otherUserId,$campaignId,null); } catch (PDOException $error) { $insertRolledBack=str_contains($error->getMessage(),'personal offer rollback probe'); }
    $pdo->exec('DROP TRIGGER personal_offer_regression_abort');
    $assert($insertRolledBack&&!$pdo->inTransaction()&&(int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE user_id='.$otherUserId.' AND campaign_id='.$campaignId)->fetchColumn()===0,'failed assignment insertion rolls back atomically and releases its transaction');

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
    try { commerce_assign_personal_offer($pdo,$userId,$wrongTypeId,null); } catch (DomainException $error) { $wrongTypeRejected=str_contains($error->getMessage(),'персональную или birthday-кампанию'); }
    $assert($wrongTypeRejected,'exclusive campaigns cannot be assigned through the personal-offer path');

    $noCodeSlug='personal-no-code-'.bin2hex(random_bytes(5));
    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,active) VALUES(?,'personal','No code','percent',1)")->execute([$noCodeSlug]);
    $noCodeCampaignId=(int)$pdo->lastInsertId();
    $noCodeRejected=false;
    try { commerce_assign_personal_offer($pdo,$userId,$noCodeCampaignId,null); } catch (DomainException $error) { $noCodeRejected=str_contains($error->getMessage(),'действующий промокод'); }
    $assert($noCodeRejected,'campaigns without an active redeemable code cannot be assigned as personal invitations');

    $pdo->exec('UPDATE loyalty_settings SET birthday_window_days=0 WHERE id=1');
    $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Qyzylorda'));
    $birthDate=$today->format('Y-m-d');
    $pdo->prepare('INSERT INTO user_profiles(user_id,birth_date,birthday_offer_consent,birthday_consent_at) VALUES(?,?,1,CURRENT_TIMESTAMP)')->execute([$otherUserId,$birthDate]);
    $pdo->prepare('INSERT INTO user_profiles(user_id,birth_date,birthday_offer_consent,birthday_consent_at) VALUES(?,?,0,NULL)')->execute([$userId,$birthDate]);
    $birthdaySlug='birthday-qa-'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,reward_value,active,stackable,demo_only) VALUES(?,'birthday','Birthday QA campaign','percent',10,1,1,1)")->execute([$birthdaySlug]);
    $birthdayCampaignId=(int)$pdo->lastInsertId();
    $birthdayCode='BDAYQA'.$birthdayCampaignId;
    $pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active,maximum_uses) VALUES(?,?,1,10)')->execute([$birthdayCode,$birthdayCampaignId]);

    $noConsentRejected=false;
    try { commerce_assign_personal_offer($pdo,$userId,$birthdayCampaignId,null); }
    catch (DomainException $error) { $noConsentRejected=str_contains($error->getMessage(),'отдельное согласие'); }
    $assert($noConsentRejected && (int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE user_id='.$userId.' AND campaign_id='.$birthdayCampaignId)->fetchColumn()===0, 'birthday assignment rejects an account without separate birthday-offer consent');

    $tomorrow=$today->modify('+1 day');
    $birthYear=checkdate((int)$tomorrow->format('n'),(int)$tomorrow->format('j'),1990)?1990:1992;
    $pdo->prepare('UPDATE user_profiles SET birth_date=?,birthday_offer_consent=1,birthday_consent_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([$birthYear.'-'.$tomorrow->format('m-d'),$userId]);
    $outsideWindowRejected=false;
    try { commerce_assign_personal_offer($pdo,$userId,$birthdayCampaignId,null); }
    catch (DomainException $error) { $outsideWindowRejected=str_contains($error->getMessage(),'актуальное окно'); }
    $assert($outsideWindowRejected && (int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE user_id='.$userId.' AND campaign_id='.$birthdayCampaignId)->fetchColumn()===0, 'birthday assignment rejects an opted-in account outside the configured birthday window');

    $birthdayOfferId=commerce_assign_personal_offer($pdo,$otherUserId,$birthdayCampaignId,null);
    $birthdayQuote=commerce_quote($pdo,[['id'=>1,'qty'=>1]],[],$birthdayCode,'pickup',$otherUserId,'birthday-qa-actor');
    $birthdayOfferIds=array_map(static fn(array $row):int=>(int)$row['id'],commerce_public_offers($pdo,$otherUserId));
    $assert($birthdayOfferId>0 && $birthdayQuote['ok'] && in_array($birthdayCampaignId,$birthdayOfferIds,true), 'eligible, consented account can receive, see, and redeem an assigned birthday campaign');

    $adminRoute=var_export($root.'/admin-marketing.php',true);
    $adminGetSessionId='tp'.bin2hex(random_bytes(12));
    $adminCsrf=bin2hex(random_bytes(16));
    try {
        $birthdayAdminPage=$runPhp('session_name("tokyo_session"); session_save_path('.var_export(sys_get_temp_dir(),true).'); session_id('.var_export($adminGetSessionId,true).'); session_start(); $_SESSION["admin_id"]=1; $_SESSION["csrf"]='.var_export($adminCsrf,true).'; session_write_close(); $_SERVER["REQUEST_METHOD"]="GET"; require '.$adminRoute.';');
        preg_match('/name="action" value="personal-offer-save".*?name="campaign_id".*?<\/select>/s',$birthdayAdminPage,$assignmentForm);
        $assert(isset($assignmentForm[0]) && str_contains($assignmentForm[0],'Birthday QA campaign') && str_contains($assignmentForm[0],'BIRTHDAY'), 'admin assignment UI exposes active birthday campaigns');
        $runPhp('session_name("tokyo_session"); session_save_path('.var_export(sys_get_temp_dir(),true).'); session_id('.var_export($adminGetSessionId,true).'); session_start(); $_SESSION["admin_id"]=1; session_write_close(); $_SERVER["REQUEST_METHOD"]="POST"; $_POST=["csrf"=>'.var_export($adminCsrf,true).',"action"=>"personal-offer-save","user_id"=>'.var_export((string)$otherUserId,true).',"campaign_id"=>'.var_export((string)$birthdayCampaignId,true).']; require '.$adminRoute.';');
        $birthdayAdminResult=$runPhp('session_name("tokyo_session"); session_save_path('.var_export(sys_get_temp_dir(),true).'); session_id('.var_export($adminGetSessionId,true).'); $_SERVER["REQUEST_METHOD"]="GET"; require '.$adminRoute.';');
        $assert(str_contains($birthdayAdminResult,'Персональное предложение назначено') && (int)$pdo->query('SELECT COUNT(*) FROM personalized_offers WHERE user_id='.$otherUserId.' AND campaign_id='.$birthdayCampaignId)->fetchColumn()===1, 'admin route assigns a birthday campaign and exposes the post-redirect confirmation');
    } finally {
        $adminSessionFile=sys_get_temp_dir().DIRECTORY_SEPARATOR.'sess_'.$adminGetSessionId;
        if(is_file($adminSessionFile))@unlink($adminSessionFile);
    }

    echo "PASS: {$assertions} focused personal-offer assertions.\n";
} finally {
    $pdo=null;
    foreach([$dbPath,$dbPath.'-wal',$dbPath.'-shm'] as $file) if(is_file($file)) @unlink($file);
}
