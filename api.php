<?php
require __DIR__.'/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
function answer(int $status,array $body): never {$json=json_encode($body,JSON_UNESCAPED_UNICODE);if($status===201&&!empty($GLOBALS['api_idempotency_token'])&&($GLOBALS['api_idempotency_type']??'')!=='order'){try{$q=db()->prepare('INSERT OR IGNORE INTO api_idempotency(token,response_json,response_status) VALUES(?,?,?)');$q->execute([$GLOBALS['api_idempotency_token'],$json,$status]);}catch(Throwable $ignored){error_log('Idempotency cache write failed: '.$ignored->getMessage());}}http_response_code($status);echo $json;exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') answer(405,['ok'=>false,'message'=>'Метод не поддерживается.']);
check_csrf();
$type=(string)($_POST['type']??'');
$GLOBALS['api_idempotency_type']=$type;
// Callers may provide a stable key to safely retry a request after a network timeout.
$requestKey=(string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??'');
if($requestKey!==''){
 if(!preg_match('/^[A-Za-z0-9._:-]{16,128}$/',$requestKey))answer(422,['ok'=>false,'message'=>'Ключ повтора имеет неверный формат.']);
 $GLOBALS['api_idempotency_token']=hash('sha256',session_id().'|'.$type.'|'.$requestKey);
 $q=db()->prepare('SELECT response_json,response_status FROM api_idempotency WHERE token=?');$q->execute([$GLOBALS['api_idempotency_token']]);$cached=$q->fetch();
 if($cached){http_response_code((int)$cached['response_status']);echo $cached['response_json'];exit;}
 if($type==='order'){$q=db()->prepare('SELECT response_json,response_status FROM api_order_requests WHERE token=?');$q->execute([$GLOBALS['api_idempotency_token']]);$cached=$q->fetch();if($cached&&$cached['response_json']!==null){http_response_code((int)$cached['response_status']);echo $cached['response_json'];exit;}}
}
$sessionUserId=$_SESSION['user_id']??null;
// Let SQLite serialize duplicate order requests rather than relying on the PHP session lock.
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
$name=trim((string)($_POST['name']??'')); $phone=trim((string)($_POST['phone']??''));
if(!preg_match('/^[\p{L} .\'-]{2,80}$/u',$name)) answer(422,['ok'=>false,'message'=>'Укажите имя от 2 до 80 символов.']);
if(in_array($type,['booking','order'],true) && !preg_match('/^[+0-9() \\-]{7,30}$/',$phone)) answer(422,['ok'=>false,'message'=>'Проверьте номер телефона.']);
try {
 if($type==='booking'){
  $guests=filter_var($_POST['guests']??null,FILTER_VALIDATE_INT);$date=(string)($_POST['date']??'');$time=(string)($_POST['time']??'');$d=DateTime::createFromFormat('!Y-m-d',$date);$dateErrors=DateTime::getLastErrors();$today=new DateTime('today');
  if(!$guests||$guests<1||$guests>12||!$d||($dateErrors!==false&&($dateErrors['warning_count']||$dateErrors['error_count']))||$d->format('Y-m-d')!==$date||$d<$today||$d>$today->modify('+365 days')||!preg_match('/^(12|13|14|17|18|19|20|21):00$/',$time)) answer(422,['ok'=>false,'message'=>'Проверьте дату, время и количество гостей.']);
  $zone=trim((string)($_POST['zone']??''));$table=trim((string)($_POST['table']??''));$format=trim((string)($_POST['format']??''));if($zone!==''&&!in_array($zone,['Основной зал','Суши-стойка','Камерная комната','Зал дегустации'],true)) answer(422,['ok'=>false,'message'=>'Выберите зону из списка.']);if($table!==''&&!preg_match('/^(?:[1-9]|1[0-2])$/',$table)) answer(422,['ok'=>false,'message'=>'Проверьте предпочтительный стол.']);if($format!==''&&!in_array($format,['Начало','Сезонный','Премиальный'],true)) answer(422,['ok'=>false,'message'=>'Выберите формат вечера из списка.']);
  $q=db()->prepare('INSERT INTO bookings(user_id,guest_name,phone,guest_count,booking_date,booking_time,zone,preferred_table,format,message) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([$sessionUserId,$name,$phone,$guests,$date,$time,$zone,$table,$format,substr(trim((string)($_POST['message']??'')),0,240)]);
  answer(201,['ok'=>true,'message'=>'Заявка сохранена в учебной базе данных. Стол не зарезервирован, уведомление ресторану не отправлялось.','id'=>(int)db()->lastInsertId()]);
 }
 if($type==='order'){
  $address=trim((string)($_POST['address']??''));$fulfillment=(string)($_POST['fulfillment']??'delivery');$items=json_decode((string)($_POST['items']??''),true);
  if(!in_array($fulfillment,['delivery','pickup'],true)||($fulfillment==='delivery'&&(strlen($address)<5||strlen($address)>180))||!is_array($items)||!array_is_list($items)||count($items)<1||count($items)>30) answer(422,['ok'=>false,'message'=>'Укажите адрес доставки и добавьте блюда в корзину.']);
  if($fulfillment==='pickup') $address='';
  $pdo=db();$idempotencyToken=(string)($GLOBALS['api_idempotency_token']??'');
  if($idempotencyToken!==''){
   // Reserve the unique key under an immediate writer lock before any order side effect.
   $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
   $reserve=$pdo->prepare('INSERT OR IGNORE INTO api_order_requests(token) VALUES(?)');$reserve->execute([$idempotencyToken]);
   if($reserve->rowCount()===0){$q=$pdo->prepare('SELECT response_json,response_status FROM api_order_requests WHERE token=?');$q->execute([$idempotencyToken]);$cached=$q->fetch();$pdo->commit();if($cached&&$cached['response_json']!==null){http_response_code((int)$cached['response_status']);echo $cached['response_json'];exit;}answer(409,['ok'=>false,'message'=>'Запрос с этим ключом ещё обрабатывается. Повторите попытку.']);}
  }else{$pdo->beginTransaction();}
  $valid=[];$total=0;$quantities=[];
  foreach($items as $item){if(!is_array($item)||array_is_list($item))throw new DomainException('Некорректный состав заказа.');$id=filter_var($item['id']??null,FILTER_VALIDATE_INT);$qty=filter_var($item['qty']??null,FILTER_VALIDATE_INT);if($id===false||$id===null||$id<1||$qty===false||$qty===null||$qty<1||$qty>20) throw new DomainException('Некорректный состав заказа.');$quantities[$id]=($quantities[$id]??0)+$qty;if($quantities[$id]>20)throw new DomainException('Количество одной позиции не может превышать 20.');}
  foreach($quantities as $id=>$qty){$q=$pdo->prepare('SELECT id,name,price FROM dishes WHERE id=? AND active=1');$q->execute([$id]);$dish=$q->fetch();if(!$dish) throw new DomainException('Одно из блюд больше недоступно. Обновите меню.');$line=(int)$dish['price']*$qty;$total+=$line;$valid[]=[$dish,$qty];}
  if($total<1||$total>500000) throw new DomainException('Проверьте сумму заказа.');
  $q=$pdo->prepare('INSERT INTO orders(user_id,customer_name,phone,address,fulfillment,comment,total) VALUES(?,?,?,?,?,?,?)');$q->execute([$sessionUserId,$name,$phone,$address,$fulfillment,substr(trim((string)($_POST['comment']??'')),0,300),$total]);$orderId=(int)$pdo->lastInsertId();$q=$pdo->prepare('INSERT INTO order_items(order_id,dish_id,item_name,price,quantity) VALUES(?,?,?,?,?)');foreach($valid as [$dish,$qty])$q->execute([$orderId,$dish['id'],$dish['name'],$dish['price'],$qty]);$response=['ok'=>true,'message'=>'Учебный заказ №'.$orderId.' сохранён в базе данных. Оплата не списывается, в ресторан он не передаётся.','id'=>$orderId];if($idempotencyToken!==''){$json=json_encode($response,JSON_UNESCAPED_UNICODE);$q=$pdo->prepare('UPDATE api_order_requests SET order_id=?,response_json=?,response_status=201 WHERE token=?');$q->execute([$orderId,$json,$idempotencyToken]);} $pdo->commit();answer(201,$response);
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
