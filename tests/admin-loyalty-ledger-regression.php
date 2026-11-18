<?php
declare(strict_types=1);

/** Focused protected-page regression for the Tokyo Privilege operations ledger. */
$root=dirname(__DIR__);
if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"SKIP: pdo_sqlite extension is required.\n");exit(77);}
$dbPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-admin-ledger-'.bin2hex(random_bytes(6)).'.sqlite';
$sessionIds=[];$outputFiles=[];$pdo=null;$assertions=0;
$assert=static function(bool $condition,string $message)use(&$assertions):void{if(!$condition)throw new RuntimeException('FAIL: '.$message);$assertions++;};

try {
    putenv('RESTAURANT_DB='.$dbPath);
    require $root.'/bootstrap.php';
    $pdo=db();
    $insertUser=$pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)');
    $insertUser->execute(['ledger-alpha+'.bin2hex(random_bytes(4)).'@example.test','Ledger Alpha',password_hash('test-only',PASSWORD_DEFAULT)]);
    $alphaId=(int)$pdo->lastInsertId();$alphaEmail=(string)$pdo->query('SELECT email FROM users WHERE id='.$alphaId)->fetchColumn();
    $insertUser->execute(['ledger-beta+'.bin2hex(random_bytes(4)).'@example.test','Ledger Beta',password_hash('test-only',PASSWORD_DEFAULT)]);
    $betaId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)')->execute(['ledger-admin+'.bin2hex(random_bytes(4)).'@example.test',password_hash('test-only',PASSWORD_DEFAULT)]);

    $order=$pdo->prepare("INSERT INTO orders(user_id,customer_name,phone,address,fulfillment,comment,total,status,created_at) VALUES(?,?,?,'','pickup','',1200,'completed','2026-11-17 15:00:00')");
    $order->execute([$alphaId,'Ledger Alpha','+77010000000']);$orderId=(int)$pdo->lastInsertId();
    $performanceSlug='qa-admin-performance-'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,reward_type,active,demo_only) VALUES(?,'exclusive','QA campaign performance','none',1,1)")->execute([$performanceSlug]);
    $performanceCampaignId=(int)$pdo->lastInsertId();
    $performanceCode='PERF'.$performanceCampaignId;
    $pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active) VALUES(?,?,1)')->execute([$performanceCode,$performanceCampaignId]);
    $performanceRedemption=$pdo->prepare('INSERT INTO promo_redemptions(promo_code,campaign_id,user_id,actor_key,order_id,discount_amount,created_at) VALUES(?,?,?,?,?,?,?)');
    $performanceRedemption->execute([$performanceCode,$performanceCampaignId,$alphaId,'qa:performance:completed',$orderId,250,'2026-11-17 15:05:00']);
    $cancelledOrder=$pdo->prepare("INSERT INTO orders(user_id,customer_name,phone,address,fulfillment,comment,total,status,created_at) VALUES(?,?,?,'','pickup','',1200,'cancelled','2026-11-17 15:10:00')");
    $cancelledOrder->execute([$alphaId,'Ledger Alpha','+77010000000']);$cancelledOrderId=(int)$pdo->lastInsertId();
    $performanceRedemption->execute([$performanceCode,$performanceCampaignId,$alphaId,'qa:performance:cancelled',$cancelledOrderId,800,'2026-11-17 15:15:00']);
    $referral=$pdo->prepare("INSERT INTO referrals(code,inviter_user_id,referred_user_id,status,created_at) VALUES(?,?,?,'rewarded','2026-11-17 14:00:00')");
    $referral->execute(['TP'.strtoupper(bin2hex(random_bytes(5))),$alphaId,$betaId]);$referralId=(int)$pdo->lastInsertId();

    $insertLedger=$pdo->prepare('INSERT INTO loyalty_ledger(user_id,order_id,referral_id,event_key,transaction_type,points,note,created_at) VALUES(?,?,?,?,?,?,?,?)');
    $alphaEvents=27;
    for($index=1;$index<=$alphaEvents;$index++){
        $orderLink=$index===2?$orderId:null;$referralLink=$index===3?$referralId:null;
        $type=$index===3?'referral':'earn';$note=$index===5?'<script>ledger-xss-probe</script>':'Операция участника Alpha '.$index;
        $createdAt=match($index){
            1=>'2026-11-15 18:59:59',
            2=>'2026-11-15 19:00:00',
            3=>'2026-11-16 18:59:59',
            4=>'2026-11-16 19:00:00',
            default=>'2026-11-10 12:00:00',
        };
        $insertLedger->execute([$alphaId,$orderLink,$referralLink,'qa:ledger-alpha:'.$index,$type,10,$note,$createdAt]);
    }
    $insertLedger->execute([$betaId,null,null,'qa:ledger-beta:earn','earn',40,'Стартовые баллы Beta','2026-11-09 12:00:00']);
    $insertLedger->execute([$betaId,null,null,'qa:ledger-beta:adjust','admin_adjustment',-5,'Ручная корректировка Beta','2026-11-09 12:01:00']);

    $sessionPath=sys_get_temp_dir();$route=var_export($root.'/admin-marketing.php',true);
    $runPhp=static function(string $code)use($root,&$outputFiles):string{
        $command=[PHP_BINARY];$ini=php_ini_loaded_file();if($ini)$command=array_merge($command,['-c',$ini]);$command=array_merge($command,['-r',$code]);
        $outputFile=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-admin-ledger-render-'.bin2hex(random_bytes(6)).'.html';$outputFiles[]=$outputFile;
        $pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['file',$outputFile,'w'],2=>['pipe','w']],$pipes,$root);
        if(!is_resource($process))throw new RuntimeException('Could not start isolated admin route check.');
        fclose($pipes[0]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);$status=proc_close($process);
        $stdout=is_file($outputFile)?(string)file_get_contents($outputFile):'';
        if($status!==0)throw new RuntimeException('Admin route check failed: '.$stderr.' '.$stdout);
        return $stdout;
    };
    $render=static function(array $query=[])use($runPhp,$route,$sessionPath,&$sessionIds):string{
        $sessionId='tp'.bin2hex(random_bytes(12));$sessionIds[]=$sessionId;
        $sessionSeed='session_name("tokyo_session"); session_save_path('.var_export($sessionPath,true).'); session_id('.var_export($sessionId,true).'); session_start(); $_SESSION["admin_id"]=1; session_write_close();';
        $runPhp($sessionSeed);
        $encoded=base64_encode(json_encode($query,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $request='session_save_path('.var_export($sessionPath,true).'); session_id('.var_export($sessionId,true).'); $_SERVER["REQUEST_METHOD"]="GET"; $_GET=json_decode(base64_decode('.var_export($encoded,true).'),true,512,JSON_THROW_ON_ERROR); require '.$route.';';
        return $runPhp($request);
    };
    $rowCount=static fn(string $html):int=>substr_count($html,'class="tp20-admin-ledger-row"');

    $default=$render();
    $performanceStart=strpos($default,'data-campaign-performance="'.$performanceCampaignId.'"');
    $performanceEnd=$performanceStart===false?false:strpos($default,'</tr>',$performanceStart);
    $performanceRow=$performanceStart!==false&&$performanceEnd!==false?substr($default,$performanceStart,$performanceEnd-$performanceStart):'';
    $assert(str_contains($default,'id="performance"')&&str_contains($performanceRow,'data-performance-redemptions="2" data-performance-orders="1" data-performance-discount="250"'),'campaign analytics count saved uses, exclude cancelled orders, and total only their discounts');
    $assert(str_contains($default,'Журнал бонусных операций')&&str_contains($default,'name="ledger_search"'),'admin dashboard renders the protected search ledger');
    $assert(str_contains($default,'На телефоне проведите таблицу по горизонтали')&&str_contains($default,'aria-describedby="loyalty-ledger-scroll-hint"'),'mobile table scrolling has an explicit visible and accessible hint');
    $assert($rowCount($default)===25&&str_contains($default,'Найдено операций: <strong>29</strong>')&&str_contains($default,'Страница 1 из 2'),'default view paginates the actual 29 ledger records at 25 rows');
    $assert(str_contains($default,'Заказ #'.$orderId)&&str_contains($default,'Приглашение #'.$referralId),'order and referral transactions retain their linked event references');
    $assert(str_contains($default,'270')&&str_contains($default,'+10'),'rows show balances computed from the complete ledger and signed positive points');

    $alpha=$render(['ledger_search'=>$alphaEmail]);
    $assert($rowCount($alpha)===25&&str_contains($alpha,'Найдено операций: <strong>27</strong>')&&str_contains($alpha,$alphaEmail),'participant email search is scoped and paginated (rows='.$rowCount($alpha).', summary='.(preg_match('/Найдено операций: <strong>(.*?)<\\/strong>/u',$alpha,$summary)?$summary[1]:'missing').', email='.(str_contains($alpha,$alphaEmail)?'yes':'no').')');
    $eventId=(int)$pdo->query("SELECT id FROM loyalty_ledger WHERE event_key='qa:ledger-alpha:4'")->fetchColumn();
    $eventById=$render(['ledger_search'=>'#'.$eventId]);
    $assert($rowCount($eventById)===1&&str_contains($eventById,'qa:ledger-alpha:4'),'search accepts the visible hash-prefixed ledger operation ID');
    $alphaSecond=$render(['ledger_search'=>$alphaEmail,'ledger_page'=>'2']);
    $tableStart=strpos($alphaSecond,'<table class="tp20-admin-ledger-table">');$tableEnd=$tableStart===false?false:strpos($alphaSecond,'</table>',$tableStart);
    $alphaSecondTable=$tableStart!==false&&$tableEnd!==false?substr($alphaSecond,$tableStart,$tableEnd-$tableStart):'';
    $assert($rowCount($alphaSecond)===2&&str_contains($alphaSecond,'Страница 2 из 2')&&!str_contains($alphaSecondTable,'ledger-beta'),'second page preserves the search and excludes other members');

    $adjustments=$render(['ledger_type'=>'admin_adjustment']);
    $assert($rowCount($adjustments)===1&&str_contains($adjustments,'-5')&&str_contains($adjustments,'35'),'event type filter displays the signed adjustment and current member balance');
    $day=$render(['ledger_from'=>'2026-11-16','ledger_to'=>'2026-11-16']);
    $assert($rowCount($day)===2&&str_contains($day,'Заказ #'.$orderId)&&str_contains($day,'Приглашение #'.$referralId),'local-date filters include both Asia/Qyzylorda day boundaries and exclude the next day');
    $literalWildcard=$render(['ledger_search'=>'%']);
    $assert($rowCount($literalWildcard)===0&&str_contains($literalWildcard,'соответствующих фильтрам'),'search treats SQL wildcard characters as literal text');
    $invalidDate=$render(['ledger_from'=>'2026-02-30']);
    $assert(str_contains($invalidDate,'корректный календарный диапазон дат')&&$rowCount($invalidDate)===25,'invalid calendar dates fail safely and do not alter ledger records');
    $xss=$render(['ledger_search'=>'ledger-xss-probe']);
    $assert(!str_contains($xss,'<script>ledger-xss-probe</script>')&&str_contains($xss,'&lt;script&gt;ledger-xss-probe&lt;/script&gt;'),'operator-entered ledger notes are escaped when rendered');

    if(getenv('TOKYO_SAVE_ADMIN_LEDGER_PREVIEW')==='1')file_put_contents($root.'/__admin-ledger-preview.html',$default);
    echo "PASS: {$assertions} focused admin loyalty ledger assertions.\n";
} finally {
    $pdo=null;
    foreach($sessionIds as $sessionId){$sessionFile=sys_get_temp_dir().DIRECTORY_SEPARATOR.'sess_'.$sessionId;if(is_file($sessionFile))@unlink($sessionFile);}
    foreach($outputFiles as $outputFile)if(is_file($outputFile))@unlink($outputFile);
    foreach([$dbPath,$dbPath.'-wal',$dbPath.'-shm']as$file)if(is_file($file))@unlink($file);
    $initLock=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-db-init-'.hash('sha256',realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.basename($dbPath)).'.lock';
    if(is_file($initLock))@unlink($initLock);
}
