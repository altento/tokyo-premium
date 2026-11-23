<?php
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/lib/booking_time.php';
require_once __DIR__.'/lib/booking_tables.php';
header('Content-Type: application/json; charset=utf-8');
function answer(int $status,array $body): never {$json=json_encode($body,JSON_UNESCAPED_UNICODE);if($status===201&&!empty($GLOBALS['api_idempotency_token'])&&!in_array(($GLOBALS['api_idempotency_type']??''),['order','booking'],true)){try{$q=db()->prepare('INSERT OR IGNORE INTO api_idempotency(token,response_json,response_status) VALUES(?,?,?)');$q->execute([$GLOBALS['api_idempotency_token'],$json,$status]);}catch(Throwable $ignored){error_log('Idempotency cache write failed: '.$ignored->getMessage());}}http_response_code($status);echo $json;exit;}
function api_canonicalize_idempotency_payload(mixed $value): mixed
{
 if(!is_array($value))return $value;
 if(array_is_list($value))return array_map('api_canonicalize_idempotency_payload',$value);
 $normalized=[];foreach($value as $key=>$item)$normalized[(string)$key]=api_canonicalize_idempotency_payload($item);ksort($normalized,SORT_STRING);return $normalized;
}
function api_request_fingerprint(string $type): string
{
 $payload=$_POST;unset($payload['csrf']);
 $canonical=api_canonicalize_idempotency_payload(['type'=>$type,'user_id'=>$_SESSION['user_id']??null,'admin_id'=>$_SESSION['admin_id']??null,'payload'=>$payload]);
 $encoded=json_encode($canonical,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 if(!is_string($encoded)||session_id()==='')throw new RuntimeException('Cannot fingerprint the idempotent request.');
 return hash_hmac('sha256',$encoded,session_id());
}
function api_replay_idempotent_response(array $cached): never
{
 $expected=(string)($GLOBALS['api_request_fingerprint']??'');$saved=$cached['request_hash']??null;
 if($expected===''||!is_string($saved)||$saved===''||!hash_equals($saved,$expected))answer(409,['ok'=>false,'message'=>'Этот ключ повтора уже связан с другой или более ранней заявкой. Проверьте историю перед новой отправкой.']);
 http_response_code((int)$cached['response_status']);echo (string)$cached['response_json'];exit;
}
if($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['type']??'')==='offers'){
 try{
  $pdo=db();$userId=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;
  $offers=array_map(static function(array $row):array{return ['id'=>(int)$row['id'],'slug'=>$row['slug'],'type'=>$row['campaign_type'],'title'=>$row['title'],'description'=>$row['description'],'image_url'=>$row['image_url'],'reward_type'=>$row['reward_type'],'reward_value'=>(int)$row['reward_value'],'minimum_subtotal'=>(int)$row['minimum_subtotal'],'maximum_discount'=>(int)$row['maximum_discount'],'starts_at'=>$row['starts_at'],'ends_at'=>$row['ends_at'],'starts_time'=>$row['starts_time'],'ends_time'=>$row['ends_time'],'timezone'=>$row['timezone'],'demo_only'=>(bool)$row['demo_only']];},commerce_public_offers($pdo,$userId));
  $collections=commerce_public_collections($pdo);
  foreach($collections as &$collection){$q=$pdo->prepare('SELECT d.id,d.name,d.description,d.price,d.image_url,d.category FROM seasonal_collection_items ci JOIN dishes d ON d.id=ci.dish_id AND d.active=1 WHERE ci.collection_id=? ORDER BY ci.sort_order,d.id');$q->execute([$collection['id']]);$collection['items']=commerce_filter_secret_menu_dishes($pdo,$q->fetchAll(),$userId,(string)($_SESSION['secret_menu_code']??''),!empty($_SESSION['admin_id']));}
  unset($collection);
  $variants=$pdo->query('SELECT slug,name,minimum_distinct_items,maximum_distinct_items,maximum_total_quantity,demo_only FROM custom_set_variants WHERE active=1 ORDER BY sort_order,slug')->fetchAll();
  answer(200,['ok'=>true,'demo_only'=>true,'offers'=>$offers,'collections'=>$collections,'set_variants'=>$variants]);
 }catch(Throwable $error){error_log($error->getMessage());answer(500,['ok'=>false,'message'=>'Не удалось загрузить предложения.']);}
}
if($_SERVER['REQUEST_METHOD']!=='POST') answer(405,['ok'=>false,'message'=>'Метод не поддерживается.']);
check_csrf();
$type=(string)($_POST['type']??'');
$GLOBALS['api_idempotency_type']=$type;
$paymentMethod='upon_receipt';
$paymentConfig=null;
if($type==='order'){
 $paymentMethod=(string)($_POST['payment_method']??'upon_receipt');
 if(!in_array($paymentMethod,['upon_receipt','freedompay'],true))answer(422,['ok'=>false,'message'=>'Выберите доступный способ оплаты.']);
 if($paymentMethod==='freedompay'){
  try{$paymentConfig=freedompay_config();}catch(Throwable $configError){error_log('FreedomPay configuration is invalid.');answer(503,['ok'=>false,'message'=>'Онлайн-оплата временно недоступна. Выберите оплату при получении.']);}
  if($paymentConfig===null)answer(503,['ok'=>false,'message'=>'Онлайн-оплата ещё не подключена. Выберите оплату при получении.']);
 }
}
if($type==='secret_unlock'){
 $code=trim((string)($_POST['code']??''));if($code===''||strlen($code)>80)answer(422,['ok'=>false,'message'=>'Код имеет неверный формат.']);
 $userId=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;$adminId=isset($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null;
 $items=commerce_secret_menu_dishes(db(),$userId,$code,$adminId>0);
 if(!$items)answer(403,['ok'=>false,'message'=>'Секретное меню недоступно для этого кода или аккаунта.']);
 // Keep this capability server-side so the unlocked catalog survives navigation.
 // Every page and order request re-checks the code against currently active rules.
 $_SESSION['secret_menu_code']=$code;
 session_write_close();
 answer(200,['ok'=>true,'demo_only'=>true,'items'=>$items]);
}
// Callers may provide a stable key to safely retry a request after a network timeout.
$requestKey=(string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??'');
if($requestKey!==''){
 if(!preg_match('/^[A-Za-z0-9._:-]{16,128}$/',$requestKey))answer(422,['ok'=>false,'message'=>'Ключ повтора имеет неверный формат.']);
 $GLOBALS['api_idempotency_token']=hash('sha256',session_id().'|'.$type.'|'.$requestKey);
 if(in_array($type,['order','booking'],true))$GLOBALS['api_request_fingerprint']=api_request_fingerprint($type);
  if(in_array($type,['order','booking'],true)){
   // Older releases cached these successful responses in the generic table
   // without a request fingerprint. Fail closed rather than replay or duplicate.
   $q=db()->prepare('SELECT token FROM api_idempotency WHERE token=?');$q->execute([$GLOBALS['api_idempotency_token']]);$legacy=$q->fetchColumn();$q->closeCursor();
   if($legacy!==false)api_replay_idempotent_response(['request_hash'=>null]);
   if($type==='order'){$q=db()->prepare('SELECT request_hash,response_json,response_status FROM api_order_requests WHERE token=?');$q->execute([$GLOBALS['api_idempotency_token']]);$cached=$q->fetch();$q->closeCursor();if($cached&&$cached['response_json']!==null)api_replay_idempotent_response($cached);}
   if($type==='booking'){$q=db()->prepare('SELECT request_hash,response_json,response_status FROM api_booking_requests WHERE token=?');$q->execute([$GLOBALS['api_idempotency_token']]);$cached=$q->fetch();$q->closeCursor();if($cached&&$cached['response_json']!==null)api_replay_idempotent_response($cached);}
  }else{
   $q=db()->prepare('SELECT response_json,response_status FROM api_idempotency WHERE token=?');$q->execute([$GLOBALS['api_idempotency_token']]);$cached=$q->fetch();$q->closeCursor();
   if($cached){http_response_code((int)$cached['response_status']);echo $cached['response_json'];exit;}
  }
}
$sessionUserId=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;
$sessionAdminId=isset($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null;
$sessionSecretCode=(string)($_SESSION['secret_menu_code']??'');
// Let SQLite serialize duplicate order requests rather than relying on the PHP session lock.
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
$name=trim((string)($_POST['name']??'')); $phone=trim((string)($_POST['phone']??''));
if(!in_array($type,['offer_quote','secret_unlock','gift_preview'],true)&&!preg_match('/^[\p{L} .\'-]{2,80}$/u',$name)) answer(422,['ok'=>false,'message'=>'Укажите имя от 2 до 80 символов.']);
if(in_array($type,['booking','order'],true) && !preg_match('/^[+0-9() \\-]{7,30}$/',$phone)) answer(422,['ok'=>false,'message'=>'Проверьте номер телефона.']);
try {
 if($type==='offer_quote'){
  $items=json_decode((string)($_POST['items']??'[]'),true);$customSets=json_decode((string)($_POST['custom_sets']??'[]'),true);
  if(!is_array($items)||!array_is_list($items)||!is_array($customSets)||!array_is_list($customSets))answer(422,['ok'=>false,'message'=>'Не удалось прочитать состав корзины.']);
  $points=filter_var($_POST['points_to_redeem']??0,FILTER_VALIDATE_INT);if($points===false||$points<0)answer(422,['ok'=>false,'message'=>'Проверьте количество бонусов.']);
  $giftId=isset($_POST['gift_dish_id'])&&$_POST['gift_dish_id']!==''?filter_var($_POST['gift_dish_id'],FILTER_VALIDATE_INT):null;if($giftId===false)answer(422,['ok'=>false,'message'=>'Проверьте выбранный подарок.']);
  $giftQuantity=filter_var($_POST['gift_quantity']??1,FILTER_VALIDATE_INT);if($giftQuantity===false||$giftQuantity<1||$giftQuantity>10)answer(422,['ok'=>false,'message'=>'Количество подарка должно быть от 1 до 10.']);
  $quote=commerce_quote(db(),$items,$customSets,(string)($_POST['promo_code']??''),(string)($_POST['fulfillment']??'delivery'),$sessionUserId,commerce_actor_key($sessionUserId,session_id()),$giftId===null?null:(int)$giftId,(int)$points,(string)($_POST['secret_code']??$sessionSecretCode) ?: $sessionSecretCode,$sessionAdminId>0,(int)$giftQuantity);
  if(!$quote['ok'])answer(422,['ok'=>false,'message'=>$quote['message'],'errors'=>$quote['errors']??[]]);
  answer(200,['ok'=>true,'demo_only'=>true,'subtotal'=>$quote['subtotal'],'discount_total'=>$quote['discount_total'],'delivery_fee'=>$quote['delivery_fee'],'total'=>$quote['total'],'promo_code'=>$quote['promo_code'],'points_to_redeem'=>$quote['points_to_redeem'],'points_discount'=>$quote['points_discount'],'requires_gift_choice'=>$quote['requires_gift_choice'],'gift_options'=>array_map(static fn(array $row):array=>['id'=>(int)$row['dish_id'],'name'=>$row['name'],'image_url'=>$row['image_url'],'price'=>(int)$row['price'],'remaining_quantity'=>(int)$row['remaining_quantity'],'max_per_order'=>(int)$row['max_per_order']],$quote['gift_options']),'gift_items'=>array_map(static fn(array $row):array=>['id'=>$row['dish']['id'],'name'=>$row['dish']['name'],'image_url'=>$row['dish']['image_url'],'quantity'=>$row['qty']],$quote['gift_items']),'adjustments'=>$quote['adjustments'],'promotion'=>$quote['promotion']]);
 }
 if($type==='gift_preview'){
  $recipient=trim((string)($_POST['recipient_name']??''));
  $amount=filter_var($_POST['amount']??null,FILTER_VALIDATE_INT);$theme=(string)($_POST['theme']??'');$greeting=(string)($_POST['greeting']??'');
  if($amount===false)answer(422,['ok'=>false,'message'=>'Выберите доступный номинал сертификата.']);
  $actor=commerce_actor_key($sessionUserId,session_id());
  $preview=commerce_create_certificate_preview(db(),$sessionUserId,$actor,$recipient,'',(int)$amount,$theme,$greeting);
  answer(201,['ok'=>true,'demo_only'=>true,'payment_status'=>'unpaid','redeemable'=>false,'message'=>'Сохранён только демонстрационный макет. Сертификат не оплачен, не отправлен и не может быть погашен.','preview'=>$preview]);
 }
 if($type==='booking'){
  $email=strtolower(trim((string)($_POST['email']??'')));
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190) answer(422,['ok'=>false,'message'=>'Укажите корректный email для ответа по бронированию.']);
  $guests=filter_var($_POST['guests']??null,FILTER_VALIDATE_INT);$date=(string)($_POST['date']??'');$time=(string)($_POST['time']??'');
  if(!$guests||$guests<1||$guests>12||!booking_visit_time_is_valid($date,$time)) answer(422,['ok'=>false,'message'=>'Проверьте дату, время и количество гостей.']);
  $preferences=booking_table_preferences((string)($_POST['zone']??''),(string)($_POST['table']??''));$zone=$preferences['zone'];$table=$preferences['table'];$format=trim((string)($_POST['format']??''));if($format!==''&&!in_array($format,['Начало','Сезонный','Премиальный'],true)) answer(422,['ok'=>false,'message'=>'Выберите формат вечера из списка.']);
  $pdo=db();$idempotencyToken=(string)($GLOBALS['api_idempotency_token']??'');$pdo->exec('BEGIN IMMEDIATE TRANSACTION');
  try{
   if($idempotencyToken!==''){
    $reserve=$pdo->prepare('INSERT OR IGNORE INTO api_booking_requests(token,request_hash) VALUES(?,?)');$reserve->execute([$idempotencyToken,(string)($GLOBALS['api_request_fingerprint']??'')]);
    if($reserve->rowCount()===0){$q=$pdo->prepare('SELECT request_hash,response_json,response_status FROM api_booking_requests WHERE token=?');$q->execute([$idempotencyToken]);$cached=$q->fetch();$pdo->commit();if($cached&&$cached['response_json']!==null)api_replay_idempotent_response($cached);answer(409,['ok'=>false,'message'=>'Заявка с этим ключом ещё обрабатывается. Повторите попытку.']);}
   }
   $pdo->exec("DELETE FROM booking_email_rate_limits WHERE created_at<datetime('now','-2 days')");
   $rateBuckets=[['email',hash('sha256','booking-email|'.$email),3,'-1 day']];$remoteIp=trim((string)($_SERVER['REMOTE_ADDR']??''));
   if(filter_var($remoteIp,FILTER_VALIDATE_IP))$rateBuckets[]=['ip',hash('sha256','booking-ip|'.$remoteIp),5,'-1 hour'];
   $rateQuery=$pdo->prepare("SELECT COUNT(*) FROM booking_email_rate_limits WHERE bucket_hash=? AND created_at>=datetime('now',?)");$rateInsert=$pdo->prepare('INSERT INTO booking_email_rate_limits(bucket_hash) VALUES(?)');
   foreach($rateBuckets as [$kind,$bucketHash,$limit,$window]){$rateQuery->execute([$bucketHash,$window]);if((int)$rateQuery->fetchColumn()>=$limit){$pdo->rollBack();header('Retry-After: 3600');answer(429,['ok'=>false,'message'=>'Слишком много заявок за короткое время. Попробуйте позже или свяжитесь с рестораном другим способом.']);}$rateInsert->execute([$bucketHash]);}
   $q=$pdo->prepare('INSERT INTO bookings(user_id,guest_name,guest_email,phone,guest_count,booking_date,booking_time,zone,preferred_table,format,message) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
   $q->execute([$sessionUserId,$name,$email,$phone,$guests,$date,$time,$zone,$table,$format,substr(trim((string)($_POST['message']??'')),0,240)]);
   $bookingId=(int)$pdo->lastInsertId();$mailIds=booking_mail_queue_request($pdo,$bookingId);
   $pendingResponse=['ok'=>true,'message'=>booking_mail_delivery_message('pending'),'mail_status'=>'pending','id'=>$bookingId];
   if($idempotencyToken!==''){$q=$pdo->prepare('UPDATE api_booking_requests SET booking_id=?,response_json=?,response_status=201 WHERE token=?');$q->execute([$bookingId,json_encode($pendingResponse,JSON_UNESCAPED_UNICODE),$idempotencyToken]);}
   $pdo->commit();
  }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
  $mailStatuses=[];foreach($mailIds as $mailId)$mailStatuses[]=booking_mail_deliver($pdo,$mailId);
  $mailStatus=in_array('failed',$mailStatuses,true)?'failed':($mailStatuses&&count(array_filter($mailStatuses,static fn($status)=>$status==='sent'))===count($mailStatuses)?'sent':'pending');
  $response=['ok'=>true,'message'=>booking_mail_delivery_message($mailStatus),'mail_status'=>$mailStatus,'id'=>$bookingId];
  if($idempotencyToken!==''){$q=$pdo->prepare('UPDATE api_booking_requests SET response_json=?,response_status=201 WHERE token=?');$q->execute([json_encode($response,JSON_UNESCAPED_UNICODE),$idempotencyToken]);}
  answer(201,$response);
 }
 if($type==='order'){
  $address=trim((string)($_POST['address']??''));$fulfillment=(string)($_POST['fulfillment']??'delivery');$items=json_decode((string)($_POST['items']??'[]'),true);$customSets=json_decode((string)($_POST['custom_sets']??'[]'),true);
  if(!in_array($fulfillment,['delivery','pickup'],true)||($fulfillment==='delivery'&&(strlen($address)<5||strlen($address)>180))||!is_array($items)||!array_is_list($items)||!is_array($customSets)||!array_is_list($customSets)) answer(422,['ok'=>false,'message'=>'Укажите способ получения, состав заказа и адрес доставки.']);
  $points=filter_var($_POST['points_to_redeem']??0,FILTER_VALIDATE_INT);if($points===false||$points<0)answer(422,['ok'=>false,'message'=>'Проверьте количество бонусов.']);
  $giftId=isset($_POST['gift_dish_id'])&&$_POST['gift_dish_id']!==''?filter_var($_POST['gift_dish_id'],FILTER_VALIDATE_INT):null;if($giftId===false)answer(422,['ok'=>false,'message'=>'Проверьте выбранный подарок.']);
  $giftQuantity=filter_var($_POST['gift_quantity']??1,FILTER_VALIDATE_INT);if($giftQuantity===false||$giftQuantity<1||$giftQuantity>10)answer(422,['ok'=>false,'message'=>'Количество подарка должно быть от 1 до 10.']);
  $pdo=db();$idempotencyToken=(string)($GLOBALS['api_idempotency_token']??'');$pdo->exec('BEGIN IMMEDIATE TRANSACTION');
  if($idempotencyToken!==''){
   $reserve=$pdo->prepare('INSERT OR IGNORE INTO api_order_requests(token,request_hash) VALUES(?,?)');$reserve->execute([$idempotencyToken,(string)($GLOBALS['api_request_fingerprint']??'')]);
   if($reserve->rowCount()===0){$q=$pdo->prepare('SELECT request_hash,response_json,response_status FROM api_order_requests WHERE token=?');$q->execute([$idempotencyToken]);$cached=$q->fetch();$pdo->commit();if($cached&&$cached['response_json']!==null)api_replay_idempotent_response($cached);answer(409,['ok'=>false,'message'=>'Запрос с этим ключом ещё обрабатывается. Повторите попытку.']);}
  }
  $input=['name'=>$name,'phone'=>$phone,'address'=>$address,'fulfillment'=>$fulfillment,'comment'=>(string)($_POST['comment']??''),'items'=>$items,'custom_sets'=>$customSets,'promo_code'=>(string)($_POST['promo_code']??''),'secret_code'=>(string)($_POST['secret_code']??$sessionSecretCode) ?: $sessionSecretCode,'is_admin'=>$sessionAdminId>0,'gift_dish_id'=>$giftId===null?null:(int)$giftId,'gift_quantity'=>(int)$giftQuantity,'points_to_redeem'=>(int)$points];
  $response=commerce_persist_order($pdo,$input,$sessionUserId,commerce_actor_key($sessionUserId,session_id()));
  if($paymentMethod==='freedompay'){
   if((int)$response['total']<1)throw new DomainException('Для нулевой суммы онлайн-оплата не требуется.');
   $paymentToken=bin2hex(random_bytes(32));
   $updatePayment=$pdo->prepare("UPDATE orders SET payment_status='pending',payment_provider='freedompay',payment_access_hash=? WHERE id=?");
   $updatePayment->execute([hash('sha256',$paymentToken),(int)$response['id']]);
   $response['payment_method']='freedompay';$response['payment_status']='pending';$response['payment_token']=$paymentToken;
   $response['payment_test_mode']=(bool)$paymentConfig['test_mode'];$response['demo_only']=(bool)$paymentConfig['test_mode'];
   $response['message']='Заказ №'.(int)$response['id'].' подготовлен. Открываем защищённую страницу оплаты FreedomPay.'.($paymentConfig['test_mode']?' Используется тестовый режим — деньги не списываются.':'');
  }else{$response['payment_method']='upon_receipt';$response['payment_status']='unpaid';}
  if($idempotencyToken!==''){$json=json_encode($response,JSON_UNESCAPED_UNICODE);$q=$pdo->prepare('UPDATE api_order_requests SET order_id=?,response_json=?,response_status=201 WHERE token=?');$q->execute([$response['id'],$json,$idempotencyToken]);}
  $pdo->commit();answer(201,$response);
 }
 if($type==='contact'){
  $email=trim((string)($_POST['email']??''));$subject=trim((string)($_POST['subject']??''));$message=trim((string)($_POST['message']??''));$messageLength=preg_match_all('/./us',$message);
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>160||!in_array($subject,['Общий вопрос','Учебное бронирование','Учебное меню'],true)||$messageLength===false||$messageLength<5||$messageLength>1000) answer(422,['ok'=>false,'message'=>'Проверьте email, тему и сообщение (от 5 до 1000 символов).']);
  $q=db()->prepare('INSERT INTO contact_messages(guest_name,email,subject,message) VALUES(?,?,?,?)');$q->execute([$name,$email,$subject,$message]);answer(201,['ok'=>true,'message'=>'Сообщение сохранено в учебной базе данных.']);
 }
 if($type==='review'){
  $rating=filter_var($_POST['rating']??null,FILTER_VALIDATE_INT);$body=trim((string)($_POST['body']??''));$theme=(string)($_POST['theme']??'Кухня');$bodyLength=preg_match_all('/./us',$body);if(!$rating||$rating<1||$rating>5||$bodyLength===false||$bodyLength<20||$bodyLength>1000||!in_array($theme,['Кухня','Сервис','Атмосфера','Омакасе'],true)) answer(422,['ok'=>false,'message'=>'Проверьте оценку, категорию и текст отзыва.']);$q=db()->prepare('INSERT INTO reviews(guest_name,rating,body,theme) VALUES(?,?,?,?)');$q->execute([$name,$rating,$body,$theme]);answer(201,['ok'=>true,'message'=>'Отзыв сохранён в учебной базе. Публичная оценка не изменена, уведомление не отправлялось.']);
 }
 answer(400,['ok'=>false,'message'=>'Неизвестная форма.']);
} catch(DomainException $ex){if(db()->inTransaction())db()->rollBack();answer(422,['ok'=>false,'message'=>$ex->getMessage()]);}catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();error_log($ex->getMessage());answer(500,['ok'=>false,'message'=>'Не удалось сохранить заявку. Попробуйте позже.']);}
