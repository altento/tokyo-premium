<?php
declare(strict_types=1);

/** Focused protected-admin regression for custom set format management. */
$root=dirname(__DIR__);
if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"SKIP: pdo_sqlite extension is required.\n");exit(77);}
$dbPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-set-variant-admin-'.bin2hex(random_bytes(6)).'.sqlite';
$pdo=null;$sessionIds=[];$assertions=0;
$assert=static function(bool $condition,string $message)use(&$assertions):void{if(!$condition)throw new RuntimeException('FAIL: '.$message);$assertions++;};

try{
    putenv('RESTAURANT_DB='.$dbPath);
    require $root.'/bootstrap.php';
    $pdo=db();
    $route=var_export($root.'/admin-marketing.php',true);
    $builderRoute=var_export($root.'/set-builder.php',true);
    $sessionPath=var_export(sys_get_temp_dir(),true);
    $runPhp=static function(string $code)use($root):string{
        $command=[PHP_BINARY];$ini=php_ini_loaded_file();if($ini)$command=array_merge($command,['-c',$ini]);$command=array_merge($command,['-r',$code]);
        $pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        if(!is_resource($process))throw new RuntimeException('Could not start isolated set-builder admin check.');
        fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
        if($status!==0)throw new RuntimeException('Route check failed: '.$stderr.' '.$stdout);
        return(string)$stdout;
    };
    $postAsAdmin=static function(array $post,bool $validCsrf=true)use($runPhp,$route,$sessionPath,&$sessionIds):string{
        $sessionId='tp'.bin2hex(random_bytes(12));$sessionIds[]=$sessionId;$token='csrf'.bin2hex(random_bytes(12));
        $seed='session_name("tokyo_session");session_save_path('.$sessionPath.');session_id('.var_export($sessionId,true).');session_start();$_SESSION["admin_id"]=1;$_SESSION["csrf"]='.var_export($token,true).';session_write_close();';
        $runPhp($seed);$post['csrf']=$validCsrf?$token:'invalid-token';
        $request='session_name("tokyo_session");session_save_path('.$sessionPath.');session_id('.var_export($sessionId,true).');$_SERVER["REQUEST_METHOD"]="POST";$_POST='.var_export($post,true).';require '.$route.';';
        return$runPhp($request);
    };
    $renderBuilder=static function(string $slug)use($runPhp,$builderRoute,$sessionPath):string{
        $request='session_name("tokyo_session");session_save_path('.$sessionPath.');$_SERVER["REQUEST_METHOD"]="GET";$_GET=["variant"=>'.var_export($slug,true).'];require '.$builderRoute.';';
        return$runPhp($request);
    };

    $anonymousSlug='qa-denied-'.bin2hex(random_bytes(3));
    $anonymousSession='tp'.bin2hex(random_bytes(12));$sessionIds[]=$anonymousSession;
    $anonymous=$runPhp('session_name("tokyo_session");session_save_path('.$sessionPath.');session_id('.var_export($anonymousSession,true).');$_SERVER["REQUEST_METHOD"]="POST";$_POST=["action"=>"set-variant-save","slug"=>'.var_export($anonymousSlug,true).',"name"=>"Unauthorized","minimum_distinct_items"=>"3","maximum_distinct_items"=>"5","maximum_total_quantity"=>"8"];require '.$route.';');
    $assert(str_contains($anonymous,'ДОСТУП ОГРАНИЧЕН'),'anonymous request receives the protected admin response');
    $checkDenied=$pdo->prepare('SELECT 1 FROM custom_set_variants WHERE slug=?');$checkDenied->execute([$anonymousSlug]);
    $assert(!$checkDenied->fetchColumn(),'anonymous request cannot create a set format');

    $slug='qa-set-'.bin2hex(random_bytes(4));
    $created=$postAsAdmin(['action'=>'set-variant-save','slug'=>$slug,'name'=>'QA Seasonal Set','minimum_distinct_items'=>'3','maximum_distinct_items'=>'5','maximum_total_quantity'=>'8','sort_order'=>'70','active'=>'1']);
    $q=$pdo->prepare('SELECT * FROM custom_set_variants WHERE slug=?');$q->execute([$slug]);$row=$q->fetch(PDO::FETCH_ASSOC);
    $assert(str_contains($created,'href="#set-formats"')&&str_contains($created,'Формат сета создан')&&$row&&$row['name']==='QA Seasonal Set'&&(int)$row['minimum_distinct_items']===3&&(int)$row['maximum_distinct_items']===5&&(int)$row['maximum_total_quantity']===8&&(int)$row['active']===1&&(int)$row['demo_only']===1,'authorized admin creates a demo format with bounded composition rules');
    $assert(str_contains($created,'href="#set-formats"')&&str_contains($created,'id="set-formats"')&&str_contains($created,'data-set-variant="'.$slug.'"'),'admin renders the saved format in its management list');

    $builder=$renderBuilder($slug);
    $assert(str_contains($builder,'data-set-type="'.$slug.'"')&&str_contains($builder,'data-set-name="QA Seasonal Set"'),'PHP set builder uses the saved variant and its display name');
    $edit=['action'=>'set-variant-save','id'=>$slug,'slug'=>$slug,'name'=>'QA Seasonal Atelier','minimum_distinct_items'=>'4','maximum_distinct_items'=>'6','maximum_total_quantity'=>'9','sort_order'=>'25'];
    $updated=$postAsAdmin($edit);
    $q->execute([$slug]);$row=$q->fetch(PDO::FETCH_ASSOC);
    $assert(str_contains($updated,'Формат сета обновлён')&&$row&&$row['name']==='QA Seasonal Atelier'&&(int)$row['minimum_distinct_items']===4&&(int)$row['maximum_distinct_items']===6&&(int)$row['maximum_total_quantity']===9&&(int)$row['active']===0&&(int)$row['sort_order']===25,'edit updates bounded rules, preserves slug, and handles a cleared checkbox');

    $invalid=$edit;$invalid['minimum_distinct_items']='6';$invalid['maximum_distinct_items']='5';$invalid['maximum_total_quantity']='5';
    $invalidResponse=$postAsAdmin($invalid);$q->execute([$slug]);$row=$q->fetch(PDO::FETCH_ASSOC);
    $assert(str_contains($invalidResponse,'Проверьте название и формат ограничений')&&(int)$row['minimum_distinct_items']===4&&(int)$row['maximum_distinct_items']===6,'inconsistent item bounds are rejected without writes');
    $invalid=$edit;$invalid['maximum_total_quantity']='13';$invalidResponse=$postAsAdmin($invalid);$q->execute([$slug]);$row=$q->fetch(PDO::FETCH_ASSOC);
    $assert(str_contains($invalidResponse,'Проверьте название и формат ограничений')&&(int)$row['maximum_total_quantity']===9,'portions above the existing server cap are rejected');

    $duplicate=$postAsAdmin(['action'=>'set-variant-save','slug'=>$slug,'name'=>'Duplicate QA','minimum_distinct_items'=>'3','maximum_distinct_items'=>'5','maximum_total_quantity'=>'8','active'=>'1']);
    $assert(str_contains($duplicate,'Такой формат уже существует')&&(int)$pdo->query('SELECT COUNT(*) FROM custom_set_variants WHERE slug='.$pdo->quote($slug))->fetchColumn()===1,'create does not overwrite an existing format');
    $csrf=$postAsAdmin($edit,false);$q->execute([$slug]);$row=$q->fetch(PDO::FETCH_ASSOC);
    $assert(str_contains($csrf,'Сессия формы истекла')&&$row['name']==='QA Seasonal Atelier','invalid CSRF cannot edit a format');

    $pdo->exec('CREATE TRIGGER set_variant_admin_abort BEFORE UPDATE ON custom_set_variants WHEN OLD.slug='.$pdo->quote($slug)." BEGIN SELECT RAISE(ABORT,'set variant rollback probe'); END");
    $rollback=$postAsAdmin($edit);$pdo->exec('DROP TRIGGER set_variant_admin_abort');$q->execute([$slug]);$row=$q->fetch(PDO::FETCH_ASSOC);
    $assert(str_contains($rollback,'Изменения не сохранены')&&$row['name']==='QA Seasonal Atelier'&&(int)$row['sort_order']===25,'database failure rolls back the attempted edit');

    $postAsAdmin(['action'=>'set-variant-toggle','slug'=>$slug]);$q->execute([$slug]);$row=$q->fetch(PDO::FETCH_ASSOC);
    $assert((int)$row['active']===1&&str_contains($renderBuilder($slug),'data-set-type="'.$slug.'"'),'activation makes the format selectable in the PHP builder');
    $postAsAdmin(['action'=>'set-variant-toggle','slug'=>$slug]);$q->execute([$slug]);$row=$q->fetch(PDO::FETCH_ASSOC);$builderAfterDisable=$renderBuilder($slug);
    $assert((int)$row['active']===0&&!str_contains($builderAfterDisable,'data-set-type="'.$slug.'"'),'deactivation hides the format from the PHP builder');

    $pdo->exec('UPDATE custom_set_variants SET active=0');
    $pdo->prepare('UPDATE custom_set_variants SET active=1 WHERE slug=?')->execute(['signature']);
    $lastDisable=$postAsAdmin(['action'=>'set-variant-toggle','slug'=>'signature']);
    $signatureActive=(int)$pdo->query("SELECT active FROM custom_set_variants WHERE slug='signature'")->fetchColumn();
    $assert(str_contains($lastDisable,'Оставьте активным хотя бы один формат сета.')&&$signatureActive===1,'toggle refuses to deactivate the final active format');
    $lastEdit=$postAsAdmin(['action'=>'set-variant-save','id'=>'signature','slug'=>'signature','name'=>'Tokyo Signature','minimum_distinct_items'=>'3','maximum_distinct_items'=>'5','maximum_total_quantity'=>'8','sort_order'=>'10']);
    $signatureActive=(int)$pdo->query("SELECT active FROM custom_set_variants WHERE slug='signature'")->fetchColumn();
    $assert(str_contains($lastEdit,'Оставьте активным хотя бы один формат сета.')&&$signatureActive===1,'edit form cannot deactivate the final active format either');
    echo "PASS: {$assertions} focused set-format admin assertions.\n";
}finally{
    $pdo=null;
    foreach($sessionIds as $sessionId){$sessionFile=sys_get_temp_dir().DIRECTORY_SEPARATOR.'sess_'.$sessionId;if(is_file($sessionFile))@unlink($sessionFile);}
    foreach([$dbPath,$dbPath.'-wal',$dbPath.'-shm']as$file)if(is_file($file))@unlink($file);
}