<?php
declare(strict_types=1);
require dirname(__DIR__).'/lib/commerce.php';
require dirname(__DIR__).'/lib/booking_mail.php';
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR,"PDO SQLite required\n"); exit(1); }
$checks=0;
$check=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException($message);$checks++;};
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');
$schema=(string)file_get_contents(dirname(__DIR__).'/schema.sql');
$pdo->exec(str_replace("'pending','sending','sent','failed','cancelled'","'pending','sending','sent','failed'",$schema));
for($version=1;$version<=16;$version++)$pdo->exec('INSERT INTO app_migrations(version) VALUES('.$version.')');
$add=$pdo->prepare("INSERT INTO bookings(guest_name,guest_email,phone,guest_count,booking_date,booking_time,status) VALUES('QA Guest','qa@example.test','+77010000000',2,'2030-01-10','19:00',?)");
$add->execute(['confirmed']);$confirmed=(int)$pdo->lastInsertId();
$add->execute(['cancelled']);$cancelled=(int)$pdo->lastInsertId();
$cancelMail=booking_mail_queue($pdo,$cancelled,"booking:$cancelled:confirmed:guest",'qa@example.test','Old confirmation','Preserved body');
$validMail=booking_mail_queue($pdo,$confirmed,"booking:$confirmed:confirmed:guest",'qa@example.test','Valid confirmation','Valid body');
$requestMail=booking_mail_queue($pdo,$cancelled,"booking:$cancelled:request:guest",'qa@example.test','Receipt','Receipt body');
$sentMail=booking_mail_queue($pdo,$cancelled,'qa:already-sent','qa@example.test','Sent','Sent body');
$pdo->exec("UPDATE email_outbox SET status='sent',attempts=1,sent_at=CURRENT_TIMESTAMP WHERE id=$sentMail");
$pdo->exec("UPDATE sqlite_sequence SET seq=100 WHERE name='email_outbox'");
commerce_migrate($pdo);
$check((int)$pdo->query('SELECT MAX(version) FROM app_migrations')->fetchColumn()===17,'migration v17 is applied');
$check((int)$pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn()===4,'migration preserves every outbox row');
$check($pdo->query("SELECT body FROM email_outbox WHERE id=$cancelMail")->fetchColumn()==='Preserved body','migration preserves body and ID');
$check($pdo->query("SELECT status FROM email_outbox WHERE id=$cancelMail")->fetchColumn()==='cancelled','migration suppresses obsolete pending confirmations');
$check($pdo->query("SELECT status FROM email_outbox WHERE id=$validMail")->fetchColumn()==='pending','migration keeps eligible confirmations');
$check($pdo->query("SELECT status FROM email_outbox WHERE id=$sentMail")->fetchColumn()==='sent','migration preserves sent history');
$check(count($pdo->query('PRAGMA foreign_key_check')->fetchAll())===0,'foreign keys remain valid');
$check(count($pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND name IN('idx_email_outbox_booking_id','idx_email_outbox_status_id')")->fetchAll())===2,'outbox indexes survive');
$newId=booking_mail_queue($pdo,$confirmed,'qa:sequence','qa@example.test','Sequence','Body');
$check($newId===101,'migration preserves the autoincrement high-water mark');
commerce_migrate($pdo);
$check((int)$pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn()===5,'migration is repeatable without duplicates');
$envKeys=['TOKYO_SMTP_HOST','TOKYO_SMTP_PORT','TOKYO_SMTP_USER','TOKYO_SMTP_PASSWORD','TOKYO_SMTP_ENCRYPTION','TOKYO_SMTP_SECURE','TOKYO_MAIL_FROM'];
$original=[];foreach($envKeys as $key){$original[$key]=getenv($key);putenv($key);}
try{
    $check(booking_mail_retry($pdo,$cancelMail)==='cancelled','manual retry cannot revive cancelled mail');
    $check(booking_mail_deliver($pdo,$requestMail)==='pending','historical request receipt is unaffected');
    $pdo->exec("UPDATE bookings SET status='cancelled' WHERE id=$confirmed");
    // Invalid config is deliberate: suppression must happen before any SMTP work.
    putenv('TOKYO_SMTP_HOST=invalid host');
    $check(booking_mail_deliver($pdo,$validMail)==='cancelled','delivery rechecks current booking status before SMTP configuration');
    $check((int)$pdo->query("SELECT attempts FROM email_outbox WHERE id=$validMail")->fetchColumn()===0,'suppression consumes no SMTP attempt');
    $check(booking_mail_queue_confirmation($pdo,$confirmed)===null,'unconfirmed bookings cannot enqueue confirmations');
    $pdo->exec("UPDATE bookings SET status='confirmed' WHERE id=$confirmed");
    $check(booking_mail_queue_confirmation($pdo,$confirmed)===$validMail,'reconfirmation reuses the same unique event');
    $check($pdo->query("SELECT status FROM email_outbox WHERE id=$validMail")->fetchColumn()==='pending','explicit reconfirmation reactivates an unsent cancelled message');
    $pdo->exec("UPDATE email_outbox SET status='sending',locked_at=datetime('now','-11 minutes') WHERE id=$validMail");
    $pdo->exec("UPDATE bookings SET status='cancelled' WHERE id=$confirmed");
    $check(booking_mail_deliver($pdo,$validMail)==='cancelled','obsolete expired send leases are cancelled');
    $pdo->exec("UPDATE email_outbox SET status='sending',locked_at=CURRENT_TIMESTAMP WHERE id=$validMail");
    booking_mail_cancel_obsolete_confirmation($pdo,$confirmed);
    $check($pdo->query("SELECT status FROM email_outbox WHERE id=$validMail")->fetchColumn()==='sending','in-flight messages are not falsely claimed recalled');
    $check(booking_mail_deliver($pdo,$validMail)==='sending','fresh leases survive even a changed invalid SMTP configuration');
    $pdo->exec("UPDATE email_outbox SET status='pending',locked_at=NULL WHERE id=$validMail");
    $pdo->exec("DELETE FROM bookings WHERE id=$confirmed");
    $check(booking_mail_deliver($pdo,$validMail)==='cancelled','deleted bookings cannot leave sendable orphan confirmations');
}finally{foreach($original as $key=>$value)putenv($value===false?$key:$key.'='.$value);}
echo "PASS: $checks booking-mail migration/cancellation assertions; no SMTP connection made.\n";
