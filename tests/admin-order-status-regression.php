<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$dbPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-admin-status-'.bin2hex(random_bytes(6)).'.sqlite';
$sessionId='tp'.bin2hex(random_bytes(12));
$sessionPath=sys_get_temp_dir();
$originalDb=getenv('RESTAURANT_DB');
$assertions=0;
$assert=static function(bool $condition,string $message) use (&$assertions):void {
    if(!$condition)throw new RuntimeException('FAIL: '.$message);
    $assertions++;
};

try {
    putenv('RESTAURANT_DB='.$dbPath);
    require $root.'/bootstrap.php';
    $pdo=db();
    $pdo->exec('UPDATE loyalty_settings SET enabled=1,points_per_100=5,referral_inviter_points=20,referral_friend_points=10,referral_minimum_order=1 WHERE id=1');
    $userInsert=$pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)');
    $userInsert->execute(['status-inviter+'.bin2hex(random_bytes(4)).'@example.test','Status Inviter',password_hash('test-only',PASSWORD_DEFAULT)]);
    $inviterId=(int)$pdo->lastInsertId();
    $userInsert->execute(['status-friend+'.bin2hex(random_bytes(4)).'@example.test','Status Friend',password_hash('test-only',PASSWORD_DEFAULT)]);
    $friendId=(int)$pdo->lastInsertId();
    $profiles=$pdo->prepare('INSERT INTO user_profiles(user_id) VALUES(?)');
    $profiles->execute([$inviterId]);
    $profiles->execute([$friendId]);
    $referralCode=commerce_referral_code_for_user($pdo,$inviterId);
    commerce_attach_referral($pdo,$friendId,$referralCode);

    $pdo->beginTransaction();
    try {
        $order=commerce_persist_order($pdo,[
            'name'=>'Status Friend','phone'=>'+77010000000','fulfillment'=>'pickup',
            'items'=>[['id'=>1,'qty'=>1]],
        ],$friendId,commerce_actor_key($friendId,'admin-status-regression'));
        $pdo->commit();
    } catch(Throwable $error) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
    $orderId=(int)$order['id'];
    $pdo=null;

    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    session_name('tokyo_session');
    session_save_path($sessionPath);
    session_id($sessionId);
    session_start();
    $csrf=bin2hex(random_bytes(32));
    $_SESSION=['admin_id'=>1,'csrf'=>$csrf];
    session_write_close();

    $postStatus=static function(string $status) use ($root,$sessionPath,$sessionId,$csrf,$orderId): string {
        $fields=['csrf'=>$csrf,'action'=>'status','table'=>'orders','id'=>(string)$orderId,'status'=>$status];
        $phpCode='session_save_path('.var_export($sessionPath,true).'); session_id('.var_export($sessionId,true).'); $_SERVER["REQUEST_METHOD"]="POST"; $_POST='.var_export($fields,true).'; require '.var_export($root.'/admin.php',true).';';
        $command=[PHP_BINARY];
        if(PHP_OS_FAMILY==='Windows'){
            array_push($command,'-d','extension_dir='.ini_get('extension_dir'),'-d','extension=php_sqlite3.dll','-d','extension=php_pdo_sqlite.dll');
        }
        array_push($command,'-r',$phpCode);
        $pipes=[];
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,null);
        if(!is_resource($process))throw new RuntimeException('Could not start isolated admin status request.');
        fclose($pipes[0]);
        $stdout=(string)stream_get_contents($pipes[1]);
        $stderr=(string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        $exit=proc_close($process);
        if($exit!==0)throw new RuntimeException('Admin status request failed: '.$stderr.' '.$stdout);
        return $stdout.$stderr;
    };

    $postStatus('completed');
    $dbCheck=new PDO('sqlite:'.$dbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $status=(string)$dbCheck->query('SELECT status FROM orders WHERE id='.$orderId)->fetchColumn();
    $eventCount=(int)$dbCheck->query('SELECT COUNT(*) FROM order_status_events WHERE order_id='.$orderId)->fetchColumn();
    $earned=(int)$dbCheck->query("SELECT COALESCE(SUM(points),0) FROM loyalty_ledger WHERE order_id=$orderId AND transaction_type='earn'")->fetchColumn();
    $rewards=(int)$dbCheck->query('SELECT COUNT(*) FROM referral_rewards WHERE qualifying_order_id='.$orderId)->fetchColumn();
    $referralStatus=(string)$dbCheck->query('SELECT status FROM referrals WHERE referred_user_id='.$friendId)->fetchColumn();
    $assert($status==='completed'&&$eventCount===1,'admin status route completes the order and records one status event');
    $assert($earned>0&&$rewards===2&&$referralStatus==='rewarded','admin completion triggers loyalty earning and both referral rewards');

    $postStatus('completed');
    $assert((int)$dbCheck->query('SELECT COUNT(*) FROM order_status_events WHERE order_id='.$orderId)->fetchColumn()===1
        &&(int)$dbCheck->query('SELECT COUNT(*) FROM referral_rewards WHERE qualifying_order_id='.$orderId)->fetchColumn()===2,
        'repeating the same admin status does not duplicate loyalty or referral events');

    $postStatus('cancelled');
    $balances=$dbCheck->prepare('SELECT COALESCE(SUM(points),0) FROM loyalty_ledger WHERE user_id=?');
    $balances->execute([$inviterId]);$inviterBalance=(int)$balances->fetchColumn();
    $balances->execute([$friendId]);$friendBalance=(int)$balances->fetchColumn();
    $assert((string)$dbCheck->query('SELECT status FROM orders WHERE id='.$orderId)->fetchColumn()==='cancelled'
        &&(string)$dbCheck->query('SELECT status FROM referrals WHERE referred_user_id='.$friendId)->fetchColumn()==='cancelled'
        &&$inviterBalance===0&&$friendBalance===0,
        'admin cancellation reverses earned and referral points transactionally');
    $postStatus('cancelled');
    $assert((int)$dbCheck->query('SELECT COUNT(*) FROM order_status_events WHERE order_id='.$orderId)->fetchColumn()===2,
        'repeating cancellation does not create a duplicate status event');

    $invalidOutput=$postStatus('pending');
    $assert(str_contains($invalidOutput,'Недопустимый статус.')
        &&(string)$dbCheck->query('SELECT status FROM orders WHERE id='.$orderId)->fetchColumn()==='cancelled',
        'admin route rejects an invalid order status without mutation');

    echo "PASS: {$assertions} focused admin order-status assertions.\n";
} finally {
    if(isset($dbCheck)&&$dbCheck instanceof PDO)$dbCheck=null;
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    $sessionFile=$sessionPath.DIRECTORY_SEPARATOR.'sess_'.$sessionId;
    if(is_file($sessionFile))@unlink($sessionFile);
    if($originalDb===false)putenv('RESTAURANT_DB');else putenv('RESTAURANT_DB='.$originalDb);
    foreach([$dbPath,$dbPath.'-wal',$dbPath.'-shm'] as $file)if(is_file($file))@unlink($file);
}
