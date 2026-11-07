<?php
require __DIR__.'/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
function answer(int $status,array $body): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') answer(405,['ok'=>false,'message'=>'Метод не поддерживается.']);
check_csrf();
$type=(string)($_POST['type']??'');
$name=trim((string)($_POST['name']??'')); $phone=trim((string)($_POST['phone']??''));
if(!preg_match('/^[\p{L} .\'-]{2,80}$/u',$name)) answer(422,['ok'=>false,'message'=>'Укажите имя от 2 до 80 символов.']);
if(!preg_match('/^[+0-9() \-]{7,30}$/',$phone)) answer(422,['ok'=>false,'message'=>'Проверьте номер телефона.']);
try {
 if($type==='booking'){
  $guests=filter_var($_POST['guests']??null,FILTER_VALIDATE_INT);$date=(string)($_POST['date']??'');$time=(string)($_POST['time']??'');$d=DateTime::createFromFormat('!Y-m-d',$date);$today=new DateTime('today');
  if(!$guests||$guests<1||$guests>12||!$d||$d<$today||$d>$today->modify('+365 days')||!preg_match('/^(12|13|14|17|18|19|20|21):00$/',$time)) answer(422,['ok'=>false,'message'=>'Проверьте дату, время и количество гостей.']);
  $q=db()->prepare('INSERT INTO bookings(user_id,guest_name,phone,guest_count,booking_date,booking_time,message) VALUES(?,?,?,?,?,?,?)');$q->execute([$_SESSION['user_id']??null,$name,$phone,$guests,$date,$time,substr(trim((string)($_POST['message']??'')),0,240)]);
  answer(201,['ok'=>true,'message'=>'Заявка принята. Команда подтвердит бронь по телефону.','id'=>(int)db()->lastInsertId()]);
 }
 if($type==='order'){
  $address=trim((string)($_POST['address']??''));$items=json_decode((string)($_POST['items']??''),true);
  if(strlen($address)<5||strlen($address)>180||!is_array($items)||count($items)<1||count($items)>30) answer(422,['ok'=>false,'message'=>'Укажите адрес и добавьте блюда в корзину.']);
  $pdo=db();$pdo->beginTransaction();$valid=[];$total=0;
  foreach($items as $item){$id=filter_var($item['id']??null,FILTER_VALIDATE_INT);$qty=filter_var($item['qty']??null,FILTER_VALIDATE_INT);if(!$id||!$qty||$qty<1||$qty>20) throw new DomainException('Некорректный состав заказа.');$q=$pdo->prepare('SELECT id,name,price FROM dishes WHERE id=? AND active=1');$q->execute([$id]);$dish=$q->fetch();if(!$dish) throw new DomainException('Одно из блюд больше недоступно. Обновите меню.');$line=(int)$dish['price']*$qty;$total+=$line;$valid[]=[$dish,$qty];}
  if($total<1||$total>500000) throw new DomainException('Проверьте сумму заказа.');
  $q=$pdo->prepare('INSERT INTO orders(user_id,customer_name,phone,address,comment,total) VALUES(?,?,?,?,?,?)');$q->execute([$_SESSION['user_id']??null,$name,$phone,$address,substr(trim((string)($_POST['comment']??'')),0,300),$total]);$orderId=(int)$pdo->lastInsertId();$q=$pdo->prepare('INSERT INTO order_items(order_id,dish_id,item_name,price,quantity) VALUES(?,?,?,?,?)');foreach($valid as [$dish,$qty])$q->execute([$orderId,$dish['id'],$dish['name'],$dish['price'],$qty]);$pdo->commit();answer(201,['ok'=>true,'message'=>'Заказ №'.$orderId.' принят. Оплата не списывается: команда подтвердит детали по телефону.','id'=>$orderId]);
 }
 if($type==='review'){
  $rating=filter_var($_POST['rating']??null,FILTER_VALIDATE_INT);$body=trim((string)($_POST['body']??''));if(!$rating||$rating<1||$rating>5||strlen($body)<20||strlen($body)>2000) answer(422,['ok'=>false,'message'=>'Оценка от 1 до 5, отзыв — от 20 до 1000 символов.']);$q=db()->prepare('INSERT INTO reviews(guest_name,rating,body) VALUES(?,?,?)');$q->execute([$name,$rating,$body]);answer(201,['ok'=>true,'message'=>'Спасибо. Отзыв появится после проверки.']);
 }
 answer(400,['ok'=>false,'message'=>'Неизвестная форма.']);
} catch(DomainException $ex){if(db()->inTransaction())db()->rollBack();answer(422,['ok'=>false,'message'=>$ex->getMessage()]);}catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();error_log($ex->getMessage());answer(500,['ok'=>false,'message'=>'Не удалось сохранить заявку. Попробуйте позже.']);}
