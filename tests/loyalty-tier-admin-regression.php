<?php
declare(strict_types=1);

/** Focused endpoint regression for editing Tokyo Privilege tiers. */
$root=dirname(__DIR__);
if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"SKIP: pdo_sqlite extension is required.\n");exit(77);}
$dbPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-loyalty-tier-'.bin2hex(random_bytes(6)).'.sqlite';
$pdo=null;$assertions=0;$sessionIds=[];
$assert=static function(bool $condition,string $message)use(&$assertions):void{if(!$condition)throw new RuntimeException('FAIL: '.$message);$assertions++;};

try{
    putenv('RESTAURANT_DB='.$dbPath);
    require $root.'/bootstrap.php';
    $pdo=db();
    $pdo->prepare('INSERT INTO loyalty_tiers(slug,name,minimum_completed_spend,earn_multiplier_basis_points,benefits_json,active,sort_order,demo_only) VALUES(?,?,?,?,?,?,?,1)')->execute([
        'qa-tier-'.bin2hex(random_bytes(3)),'QA Silver',1000,10000,json_encode(['Tea'],JSON_UNESCAPED_UNICODE),1,20
    ]);
    $tierId=(int)$pdo->lastInsertId();
    $slug=(string)$pdo->query('SELECT slug FROM loyalty_tiers WHERE id='.$tierId)->fetchColumn();
    $route=var_export($root.'/admin-marketing.php',true);
    $sessionPath=var_export(sys_get_temp_dir(),true);
    $runPhp=static function(string $code)use($root):string{
        $command=[PHP_BINARY];$ini=php_ini_loaded_file();if($ini)$command=array_merge($command,['-c',$ini]);$command=array_merge($command,['-r',$code]);
        $pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        if(!is_resource($process))throw new RuntimeException('Could not start isolated admin route check.');
        fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
        if($status!==0)throw new RuntimeException('Admin route check failed: '.$stderr.' '.$stdout);
        return(string)$stdout;
    };
    $postAsAdmin=static function(array $post,bool $validCsrf=true)use($runPhp,$route,$sessionPath,&$sessionIds):string{
        $sessionId='tp'.bin2hex(random_bytes(12));$sessionIds[]=$sessionId;$token='csrf'.bin2hex(random_bytes(12));
        $seed='session_name("tokyo_session"); session_save_path('.$sessionPath.'); session_id('.var_export($sessionId,true).'); session_start(); $_SESSION["admin_id"]=1; $_SESSION["csrf"]='.var_export($token,true).'; session_write_close();';
        $runPhp($seed);
        $post['csrf']=$validCsrf?$token:'invalid-token';
        $request='session_save_path('.$sessionPath.'); session_id('.var_export($sessionId,true).'); $_SERVER["REQUEST_METHOD"]="POST"; $_POST='.var_export($post,true).'; require '.$route.';';
        $response=$runPhp($request);
        if($response!=='')return$response;
        // Successful admin POSTs use PRG; render the next GET with the same session.
        return$runPhp('session_save_path('.$sessionPath.'); session_id('.var_export($sessionId,true).'); $_SERVER["REQUEST_METHOD"]="GET"; $_GET=[]; require '.$route.';');
    };

    $anonymousSession='tp'.bin2hex(random_bytes(12));$sessionIds[]=$anonymousSession;
    $anonymous=$runPhp('session_save_path('.$sessionPath.'); session_id('.var_export($anonymousSession,true).'); $_SERVER["REQUEST_METHOD"]="POST"; $_POST=["action"=>"tier-save","id"=>'.var_export((string)$tierId,true).',"name"=>"Intruder","minimum_completed_spend"=>"0","earn_multiplier_basis_points"=>"10000"]; require '.$route.';');
    $assert(str_contains($anonymous,'ДОСТУП ОГРАНИЧЕН')&&(string)$pdo->query('SELECT name FROM loyalty_tiers WHERE id='.$tierId)->fetchColumn()==='QA Silver','unauthenticated admin POST is denied before it can edit a tier');

    $changes=[
        'action'=>'tier-save','id'=>(string)$tierId,'name'=>'Silver Atelier',
        'minimum_completed_spend'=>'245000','earn_multiplier_basis_points'=>'12500',
        'benefits'=>"Chef's seasonal pairing\nPriority reservation",'sort_order'=>'35'
    ];
    $response=$postAsAdmin($changes);
    $row=$pdo->prepare('SELECT * FROM loyalty_tiers WHERE id=?');$row->execute([$tierId]);$tier=$row->fetch();
    $benefits=json_decode((string)$tier['benefits_json'],true);
    $assert(str_contains($response,'Уровень клуба обновлён.')&&str_contains($response,'Редактировать уровень')&&str_contains($response,'name="id" value="'.$tierId.'"'),'authorized CSRF-protected request reports success and the admin renders an ID-bound edit form');
    $assert($tier&&$tier['slug']===$slug&&$tier['name']==='Silver Atelier'&&(int)$tier['minimum_completed_spend']===245000&&(int)$tier['earn_multiplier_basis_points']===12500&&$benefits===["Chef's seasonal pairing",'Priority reservation']&&(int)$tier['active']===0&&(int)$tier['sort_order']===35&&(int)$tier['demo_only']===1,'edit updates name, threshold, multiplier, benefits, active status and display order while preserving the referenced slug');

    $countBefore=(int)$pdo->query('SELECT COUNT(*) FROM loyalty_tiers')->fetchColumn();
    $duplicate=$postAsAdmin(['action'=>'tier-save','slug'=>$slug,'name'=>'Duplicate','minimum_completed_spend'=>'0','earn_multiplier_basis_points'=>'10000','benefits'=>'','sort_order'=>'1','active'=>'1']);
    $assert(str_contains($duplicate,'Такой slug уровня уже существует')&&(int)$pdo->query('SELECT COUNT(*) FROM loyalty_tiers')->fetchColumn()===$countBefore,'create flow refuses a duplicate slug instead of silently changing an existing tier');

    $invalid=$changes;$invalid['earn_multiplier_basis_points']='100001';$invalidResponse=$postAsAdmin($invalid);
    $fresh=$pdo->prepare('SELECT name,earn_multiplier_basis_points FROM loyalty_tiers WHERE id=?');$fresh->execute([$tierId]);$afterInvalid=$fresh->fetch();
    $assert(str_contains($invalidResponse,'Проверьте уровень')&&$afterInvalid['name']==='Silver Atelier'&&(int)$afterInvalid['earn_multiplier_basis_points']===12500,'out-of-range multiplier is rejected without changing saved values');

    $longBenefit=$changes;$longBenefit['benefits']=str_repeat('x',121);$longResponse=$postAsAdmin($longBenefit);
    $assert(str_contains($longResponse,'не более 12 привилегий'),'benefit line length is bounded by server validation');

    $csrfResponse=$postAsAdmin($changes,false);
    $assert(str_contains($csrfResponse,'Сессия формы истекла')&&(string)$pdo->query('SELECT name FROM loyalty_tiers WHERE id='.$tierId)->fetchColumn()==='Silver Atelier','invalid CSRF token cannot update a loyalty tier');

    $pdo->exec('CREATE TRIGGER loyalty_tier_regression_abort BEFORE UPDATE ON loyalty_tiers WHEN OLD.id='.$tierId." BEGIN SELECT RAISE(ABORT,'loyalty tier rollback probe'); END");
    $rollbackResponse=$postAsAdmin($changes);
    $pdo->exec('DROP TRIGGER loyalty_tier_regression_abort');
    $afterRollback=$pdo->prepare('SELECT name,sort_order FROM loyalty_tiers WHERE id=?');$afterRollback->execute([$tierId]);$rollbackRow=$afterRollback->fetch();
    $assert(str_contains($rollbackResponse,'Изменения не сохранены')&&$rollbackRow['name']==='Silver Atelier'&&(int)$rollbackRow['sort_order']===35,'database update error rolls back without partial tier changes');

    echo "PASS: {$assertions} focused loyalty-tier admin assertions.\n";
}finally{
    $pdo=null;
    foreach($sessionIds as $sessionId){$sessionFile=sys_get_temp_dir().DIRECTORY_SEPARATOR.'sess_'.$sessionId;if(is_file($sessionFile))@unlink($sessionFile);}
    foreach([$dbPath,$dbPath.'-wal',$dbPath.'-shm']as$file)if(is_file($file))@unlink($file);
}
