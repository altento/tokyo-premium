<?php
require __DIR__.'/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
function answer(int $status,array $body): never {$json=json_encode($body,JSON_UNESCAPED_UNICODE);if($status===201&&!empty($GLOBALS['api_idempotency_token'])&&($GLOBALS['api_idempotency_type']??'')!=='order'){try{$q=db()->prepare('INSERT OR IGNORE INTO api_idempotency(token,response_json,response_status) VALUES(?,?,?)');$q->execute([$GLOBALS['api_idempotency_token'],$json,$status]);}catch(Throwable $ignored){error_log('Idempotency cache write failed: '.$ignored->getMessage());}}http_response_code($status);echo $json;exit;}
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
tokyo_init_trace('api-after-csrf');
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
 $q=db()->prepare('SELECT response_json,response_status FROM api_idempotency WHERE token=?');$q->execute([$GLOBALS['api_idempotency_token']]);$cached=$q->fetch();
 if($cached){http_response_code((int)$cached['response_status']);echo $cached['response_json'];exit;}
 if($type==='order'){$q=db()->prepare('SELECT response_json,response_status FROM api_order_requests WHERE token=?');$q->execute([$GLOBALS['api_idempotency_token']]);$cached=$q->fetch();if($cached&&$cached['response_json']!==null){http_response_code((int)$cached['response_status']);echo $cached['response_json'];exit;}}
}
tokyo_init_trace('api-after-idempotency-read');
$sessionUserId=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;
$sessionAdminId=isset($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null;
$sessionSecretCode=(string)($_SESSION['secret_menu_code']??'');
// Let SQLite serialize duplicate order requests rather than relying on the PHP session lock.
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
tokyo_init_trace('api-session-closed');
$name=trim((string)($_POST['name']??'')); $phone=trim((string)($_POST['phone']??''));
if(!in_array($type,['offer_quote','secret_unlock','gift_preview'],true)&&!preg_match('/^[\p{L} .\'-]{2,80}$/u',$name)) answer(422,['ok'=>false,'message'=>'Укажите имя от 2 до 80 символов.']);
if(in_array($type,['booking','order'],true) && !preg_match('/^[+0-9() \\-]{7,30}$/',$phone)) answer(422,['ok'=>false,'message'=>'Проверьте номер телефона.']);
try {
 if($type==='offer_quote'){
  $items=json_decode((string)($_POST['items']??'[]'),true);$customSets=json_decode((string)($_POST['custom_sets']??'[]'),true);
  if(!is_array($items)||!array_is_list($items)||!is_array($customSets)||!array_is_list($customSets))answer(422,['ok'=>false,'message'=>'Не удалось прочитать состав корзины.']);
  $points=filter_var($_POST['points_to_redeem']??0,FILTER_VALIDATE_INT);if($points===false||$points<0)answer(422,['ok'=>false,'message'=>'Проверьте количество бонусов.']);
  $giftId=isset($_POST['gift_dish_id'])&&$_POST['gift_dish_id']!==''?filter_var($_POST['gift_dish_id'],FILTER_VALIDATE_INT):null;if($giftId===false)answer(422,['ok'=>false,'message'=>'Проверьте выбранный подарок.']);
  $quote=commerce_quote(db(),$items,$customSets,(string)($_POST['promo_code']??''),(string)($_POST['fulfillment']??'delivery'),$sessionUserId,commerce_actor_key($sessionUserId,session_id()),$giftId===null?null:(int)$giftId,(int)$points,(string)($_POST['secret_code']??$sessionSecretCode) ?: $sessionSecretCode,$sessionAdminId>0);
  if(!$quote['ok'])answer(422,['ok'=>false,'message'=>$quote['message'],'errors'=>$quote['errors']??[]]);
  answer(200,['ok'=>true,'demo_only'=>true,'subtotal'=>$quote['subtotal'],'discount_total'=>$quote['discount_total'],'delivery_fee'=>$quote['delivery_fee'],'total'=>$quote['total'],'promo_code'=>$quote['promo_code'],'points_to_redeem'=>$quote['points_to_redeem'],'points_discount'=>$quote['points_discount'],'requires_gift_choice'=>$quote['requires_gift_choice'],'gift_options'=>array_map(static fn(array $row):array=>['id'=>(int)$row['dish_id'],'name'=>$row['name'],'image_url'=>$row['image_url'],'price'=>(int)$row['price']],$quote['gift_options']),'gift_items'=>array_map(static fn(array $row):array=>['id'=>$row['dish']['id'],'name'=>$row['dish']['name'],'image_url'=>$row['dish']['image_url'],'quantity'=>$row['qty']],$quote['gift_items']),'adjustments'=>$quote['adjustments'],'promotion'=>$quote['promotion']]);
 }
 if($type==='gift_preview'){
  $recipient=trim((string)($_POST['recipient_name']??''));$email=trim((string)($_POST['recipient_email']??''));
  $amount=filter_var($_POST['amount']??null,FILTER_VALIDATE_INT);$theme=(string)($_POST['theme']??'');$greeting=(string)($_POST['greeting']??'');
  if($amount===false)answer(422,['ok'=>false,'message'=>'Выберите доступный номинал сертификата.']);
  $actor=commerce_actor_key($sessionUserId,session_id());
  $preview=commerce_create_certificate_preview(db(),$sessionUserId,$actor,$recipient,$email,(int)$amount,$theme,$greeting);
  answer(201,['ok'=>true,'demo_only'=>true,'payment_status'=>'unpaid','redeemable'=>false,'message'=>'Сохранён только демонстрационный макет. Сертификат не оплачен, не отправлен и не может быть погашен.','preview'=>$preview]);
 }
 if($type==='booking'){
  $guests=filter_var($_POST['guests']??null,FILTER_VALIDATE_INT);$date=(string)($_POST['date']??'');$time=(string)($_POST['time']??'');$d=DateTime::createFromFormat('!Y-m-d',$date);$dateErrors=DateTime::getLastErrors();$today=new DateTime('today');
  if(!$guests||$guests<1||$guests>12||!$d||($dateErrors!==false&&($dateErrors['warning_count']||$dateErrors['error_count']))||$d->format('Y-m-d')!==$date||$d<$today||$d>$today->modify('+365 days')||!preg_match('/^(12|13|14|17|18|19|20|21):00$/',$time)) answer(422,['ok'=>false,'message'=>'Проверьте дату, время и количество гостей.']);
  $zone=trim((string)($_POST['zone']??''));$table=trim((string)($_POST['table']??''));$format=trim((string)($_POST['format']??''));if($zone!==''&&!in_array($zone,['Основной зал','Суши-стойка','Камерная комната','Зал дегустации'],true)) answer(422,['ok'=>false,'message'=>'Выберите зону из списка.']);if($table!==''&&!preg_match('/^(?:[1-9]|1[0-2])$/',$table)) answer(422,['ok'=>false,'message'=>'Проверьте предпочтительный стол.']);if($format!==''&&!in_array($format,['Начало','Сезонный','Премиальный'],true)) answer(422,['ok'=>false,'message'=>'Выберите формат вечера из списка.']);
  $q=db()->prepare('INSERT INTO bookings(user_id,guest_name,phone,guest_count,booking_date,booking_time,zone,preferred_table,format,message) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([$sessionUserId,$name,$phone,$guests,$date,$time,$zone,$table,$format,substr(trim((string)($_POST['message']??'')),0,240)]);
  answer(201,['ok'=>true,'message'=>'Заявка сохранена в учебной базе данных. Стол не зарезервирован, уведомление ресторану не отправлялось.','id'=>(int)db()->lastInsertId()]);
 }
 if($type==='order'){
  $address=trim((string)($_POST['address']??''));$fulfillment=(string)($_POST['fulfillment']??'delivery');$items=json_decode((string)($_POST['items']??'[]'),true);$customSets=json_decode((string)($_POST['custom_sets']??'[]'),true);
  if(!in_array($fulfillment,['delivery','pickup'],true)||($fulfillment==='delivery'&&(strlen($address)<5||strlen($address)>180))||!is_array($items)||!array_is_list($items)||!is_array($customSets)||!array_is_list($customSets)) answer(422,['ok'=>false,'message'=>'Укажите способ получения, состав заказа и адрес доставки.']);
  $points=filter_var($_POST['points_to_redeem']??0,FILTER_VALIDATE_INT);if($points===false||$points<0)answer(422,['ok'=>false,'message'=>'Проверьте количество бонусов.']);
  $giftId=isset($_POST['gift_dish_id'])&&$_POST['gift_dish_id']!==''?filter_var($_POST['gift_dish_id'],FILTER_VALIDATE_INT):null;if($giftId===false)answer(422,['ok'=>false,'message'=>'Проверьте выбранный подарок.']);
  $pdo=db();$idempotencyToken=(string)($GLOBALS['api_idempotency_token']??'');$pdo->exec('BEGIN IMMEDIATE TRANSACTION');tokyo_init_trace('order-transaction-begun');
  if($idempotencyToken!==''){
   $reserve=$pdo->prepare('INSERT OR IGNORE INTO api_order_requests(token) VALUES(?)');$reserve->execute([$idempotencyToken]);
   if($reserve->rowCount()===0){$q=$pdo->prepare('SELECT response_json,response_status FROM api_order_requests WHERE token=?');$q->execute([$idempotencyToken]);$cached=$q->fetch();$pdo->commit();if($cached&&$cached['response_json']!==null){http_response_code((int)$cached['response_status']);echo $cached['response_json'];exit;}answer(409,['ok'=>false,'message'=>'Запрос с этим ключом ещё обрабатывается. Повторите попытку.']);}
  }
  $input=['name'=>$name,'phone'=>$phone,'address'=>$address,'fulfillment'=>$fulfillment,'comment'=>(string)($_POST['comment']??''),'items'=>$items,'custom_sets'=>$customSets,'promo_code'=>(string)($_POST['promo_code']??''),'secret_code'=>(string)($_POST['secret_code']??$sessionSecretCode) ?: $sessionSecretCode,'is_admin'=>$sessionAdminId>0,'gift_dish_id'=>$giftId===null?null:(int)$giftId,'points_to_redeem'=>(int)$points];
  tokyo_init_trace('order-before-persist');
  $response=commerce_persist_order($pdo,$input,$sessionUserId,commerce_actor_key($sessionUserId,session_id()));
  tokyo_init_trace('order-after-persist');
  if($idempotencyToken!==''){$json=json_encode($response,JSON_UNESCAPED_UNICODE);$q=$pdo->prepare('UPDATE api_order_requests SET order_id=?,response_json=?,response_status=201 WHERE token=?');$q->execute([$response['id'],$json,$idempotencyToken]);}
  $pdo->commit();tokyo_init_trace('order-after-commit');answer(201,$response);
 }
 if($type==='contact'){
  $email=trim((string)($_POST['email']??''));$subject=trim((string)($_POST['subject']??''));$message=trim((string)($_POST['message']??''));
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>160||!in_array($subject,['Общий вопрос','Учебное бронирование','Учебное меню'],true)||strlen($message)<5||strlen($message)>1000) answer(422,['ok'=>false,'message'=>'Проверьте email, тему и сообщение (от 5 до 1000 символов).']);
  $q=db()->prepare('INSERT INTO contact_messages(guest_name,email,subject,message) VALUES(?,?,?,?)');$q->execute([$name,$email,$subject,$message]);answer(201,['ok'=>true,'message'=>'Сообщение сохранено в учебной базе данных.']);
 }
 if($type==='review'){
  $rating=filter_var($_POST['rating']??null,FILTER_VALIDATE_INT);$body=trim((string)($_POST['body']??''));$theme=(string)($_POST['theme']??'Кухня');if(!$rating||$rating<1||$rating>5||strlen($body)<20||strlen($body)>2000||!in_array($theme,['Кухня','Сервис','Атмосфера','Омакасе'],true)) answer(422,['ok'=>false,'message'=>'Проверьте оценку, категорию и текст отзыва.']);$q=db()->prepare('INSERT INTO reviews(guest_name,rating,body,theme) VALUES(?,?,?,?)');$q->execute([$name,$rating,$body,$theme]);answer(201,['ok'=>true,'message'=>'Отзыв сохранён в учебной базе. Публичная оценка не изменена, уведомление не отправлялось.']);
 }
 answer(400,['ok'=>false,'message'=>'Неизвестная форма.']);
} catch(DomainException $ex){if(db()->inTransaction())db()->rollBack();answer(422,['ok'=>false,'message'=>$ex->getMessage()]);}catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();error_log($ex->getMessage());answer(500,['ok'=>false,'message'=>'Не удалось сохранить заявку. Попробуйте позже.']);}
