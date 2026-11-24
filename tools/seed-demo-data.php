<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

if (in_array('--help', $argv, true) || !in_array('--apply', $argv, true)) {
    fwrite(STDOUT, "Заполнение создаёт один фиктивный аккаунт, 12 заказов, 8 броней, 10 сообщений и 6 отзывов для админ-проверки.\n" .
        "Для записи укажите защищённую SQLite за пределами web-каталога через RESTAURANT_DB и запустите: php tools/seed-demo-data.php --apply\n" .
        "Данные создаются один раз, атомарно; никаких писем и платежей команда не отправляет. Пароль генерируется случайно и показывается только в выводе команды.\n");
    exit(in_array('--help', $argv, true) ? 0 : 64);
}

$databasePath = trim((string)(getenv('RESTAURANT_DB') ?: ''));
if ($databasePath === '') {
    throw new RuntimeException('Перед записью задайте RESTAURANT_DB на защищённый файл вне web-каталога.');
}
$projectRoot = realpath(dirname(__DIR__));
$databaseDirectory = realpath(dirname($databasePath));
if ($databaseDirectory === false) {
    throw new RuntimeException('Для SQLite укажите существующую директорию вне каталога сайта.');
}
if ($projectRoot) {
    $normalizedRoot = strtolower(str_replace('\\', '/', rtrim($projectRoot, '/\\')) . '/');
    $normalizedDatabase = strtolower(str_replace('\\', '/', rtrim($databaseDirectory, '/\\')) . '/' . basename($databasePath));
    if (str_starts_with($normalizedDatabase, $normalizedRoot)) {
        throw new RuntimeException('Файл SQLite должен находиться за пределами каталога сайта.');
    }
}

$pdo = db();
$markerKey = 'demo_fixture_seed_v1';
$markerQuery = $pdo->prepare('SELECT value FROM settings WHERE key=?');
$markerQuery->execute([$markerKey]);
$existingMarker = $markerQuery->fetchColumn();
if ($existingMarker !== false) {
    $marker = json_decode((string)$existingMarker, true) ?: [];
    fwrite(STDOUT, 'Демо-данные уже добавлены. Учебный аккаунт: ' . (string)($marker['email'] ?? 'не найден') . ". Повторных записей не создано.\n");
    exit(0);
}

$publicDishes = [];
foreach (dishes() as $dish) $publicDishes[(int)$dish['id']] = $dish;
$requiredDishIds = [1,3,4,6,8,10,12,13,14,15,19,20];
foreach ($requiredDishIds as $dishId) {
    if (!isset($publicDishes[$dishId])) throw new RuntimeException('Не найдено обязательное публичное блюдо ID ' . $dishId . '.');
}

$email = 'demo.' . bin2hex(random_bytes(4)) . '@example.test';
$password = bin2hex(random_bytes(18));
$fullName = 'Демо-гость Tokyo';
$phone = '+70000000000';
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Qyzylorda'));
$orderStatuses = ['new','confirmed','preparing','ready','completed','completed','cancelled','preparing','ready','completed','cancelled','new'];
$bookingStatuses = ['completed','cancelled','completed','confirmed','new','confirmed','new','new'];
$zones = ['Основной зал','Суши-бар','Приватная комната','Летняя терраса'];
$messageSamples = [
    ['Уточнение по меню','Подскажите, какие блюда в учебном каталоге самые мягкие по вкусу?'],
    ['Пожелание к брони','Просьба показать тихую зону для вымышленной встречи.'],
    ['Вопрос о составе','Интересует демонстрационный состав сезонной подачи.'],
    ['Вопрос о сертификате','Можно ли изменить оформление учебного макета?'],
    ['Уточнение по времени','Проверяю, как отображается ответ на запрос о времени.'],
    ['Благодарность','Спасибо за аккуратное описание блюд и учебных условий.'],
    ['Доставка','Проверка отображения вымышленного адреса доставки.'],
    ['Общий вопрос','Тестовая запись для проверки сортировки обращений.'],
    ['Предложение','Для демонстрации панели оставлено примерное сообщение.'],
    ['Вопрос по сету','Как меняется состав набора на странице конструктора?']
];
$reviewSamples = [
    ['Акира Тестов',5,'Нежный вкус, красивая подача и понятное описание.','Подача'],
    ['Мира Демонова',5,'Сочетание матча и цитруса звучит неожиданно и интересно.','Десерт'],
    ['Рен Примеров',4,'Сбалансированная последовательность для учебного сценария.','Omakase'],
    ['Юна Макетова',5,'Удобно сравнивать состав и размер порции.','Меню'],
    ['Такэши Фиктивный',4,'Тёплая атмосфера и спокойное оформление карточек.','Атмосфера'],
    ['Наоми Учебная',5,'Хорошо читается история каждого блюда.','Кухня']
];

sqlite_begin_immediate($pdo);
try {
    $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute([$email,$fullName,password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO user_profiles(user_id,preferences_json) VALUES(?,?)")->execute([$userId,json_encode(['favorite_categories'=>['sushi','desserts'],'notes'=>'Только демонстрационный профиль'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    $pdo->prepare('INSERT INTO user_addresses(user_id,label,address,is_default) VALUES(?,?,?,1)')->execute([$userId,'Учебный адрес','Учебная доставка, дом 1']);

    $insertOrder = $pdo->prepare('INSERT INTO orders(user_id,customer_name,phone,address,fulfillment,comment,total,status,payment_status,created_at,subtotal) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $insertLine = $pdo->prepare('INSERT INTO order_items(order_id,dish_id,item_name,price,quantity) VALUES(?,?,?,?,?)');
    $insertOrderEvent = $pdo->prepare('INSERT INTO order_status_events(order_id,previous_status,new_status,created_at) VALUES(?,?,?,?)');
    $insertNotification = $pdo->prepare('INSERT OR IGNORE INTO user_notifications(user_id,event_key,notification_type,title,message,target_url) VALUES(?,?,?,?,?,?)');
    $insertLedger = $pdo->prepare('INSERT OR IGNORE INTO loyalty_ledger(user_id,order_id,event_key,transaction_type,points,note) VALUES(?,?,?,\'earn\',?,?)');
    for ($i=0; $i<12; $i++) {
        $firstId = $requiredDishIds[$i % count($requiredDishIds)];
        $secondId = $requiredDishIds[($i + 4) % count($requiredDishIds)];
        $quantities = [1 + ($i % 2), 1];
        $orderLines = [[$firstId,$quantities[0]],[$secondId,$quantities[1]]];
        $subtotal = 0;
        foreach ($orderLines as [$dishId,$quantity]) $subtotal += (int)$publicDishes[$dishId]['price'] * $quantity;
        $status = $orderStatuses[$i];
        $paymentStatus = 'unpaid';
        $fulfillment = $i % 2 === 0 ? 'pickup' : 'delivery';
        $address = $fulfillment === 'delivery' ? 'Учебная доставка, дом ' . (1 + ($i % 9)) : '';
        $created = $today->modify('-' . (2 + $i * 3) . ' days')->format('Y-m-d H:i:s');
        $insertOrder->execute([$userId,$fullName,$phone,$address,$fulfillment,'Вымышленный учебный заказ №' . ($i+1),$subtotal,$status,$paymentStatus,$created,$subtotal]);
        $orderId = (int)$pdo->lastInsertId();
        foreach ($orderLines as [$dishId,$quantity]) {
            $dish = $publicDishes[$dishId];
            $insertLine->execute([$orderId,$dishId,(string)$dish['name'],(int)$dish['price'],$quantity]);
        }
        if ($status !== 'new') {
            $insertOrderEvent->execute([$orderId,'new',$status,$created]);
            $insertNotification->execute([$userId,'demo-fixture:order:' . $orderId . ':' . $status,'order_status','Статус учебного заказа изменён','Заказ №' . $orderId . ': ' . $status . '. Это вымышленная запись.','account.php#account-orders']);
        }
        if ($status === 'completed') {
            $points = max(1,(int)floor($subtotal / 20000));
            $insertLedger->execute([$userId,$orderId,'demo-fixture:order:' . $orderId . ':points',$points,'Вымышленное учебное начисление']);
        }
    }

    $insertBooking = $pdo->prepare('INSERT INTO bookings(user_id,guest_name,guest_email,phone,guest_count,booking_date,booking_time,zone,preferred_table,format,message,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insertBookingEvent = $pdo->prepare('INSERT INTO booking_status_events(booking_id,previous_status,new_status,created_at) VALUES(?,?,?,?)');
    for ($i=0; $i<8; $i++) {
        $offset = $i < 4 ? -14 + ($i * 3) : 1 + (($i - 4) * 3);
        $status = $bookingStatuses[$i];
        $bookingDate = $today->modify(($offset < 0 ? '-' : '+') . abs($offset) . ' days')->format('Y-m-d');
        $createdDaysAgo = $offset < 0 ? abs($offset) + 1 : 1 + $i;
        $created = $today->modify('-' . $createdDaysAgo . ' days')->format('Y-m-d') . ' 12:00:00';
        $bookingTime = ['18:00','19:00','20:00','17:00'][$i % 4];
        $insertBooking->execute([$userId,$fullName,$email,$phone,1 + (($i * 3) % 8),$bookingDate,$bookingTime,$zones[$i % count($zones)],$i % 4 === 0 ? (string)(10 + $i) : '',$i % 3 === 0 ? 'Сезонный' : '', 'Учебное пожелание к визиту №' . ($i+1),$status,$created]);
        $bookingId = (int)$pdo->lastInsertId();
        if ($status !== 'new') {
            $insertBookingEvent->execute([$bookingId,'new',$status,$created]);
            $insertNotification->execute([$userId,'demo-fixture:booking:' . $bookingId . ':' . $status,'booking_status','Статус учебной брони изменён','Запрос на ' . $bookingDate . ' · ' . $status . '. Это вымышленная запись.','account.php#account-bookings']);
        }
    }

    $insertMessage = $pdo->prepare('INSERT INTO contact_messages(guest_name,email,subject,message,status,created_at) VALUES(?,?,?,?,?,?)');
    foreach ($messageSamples as $i=>$sample) {
        $insertMessage->execute(['Демо-гость ' . ($i+1),'guest' . ($i+1) . '@example.test',$sample[0],$sample[1],['new','read','archived'][$i % 3],$today->modify('-' . ($i+1) . ' days')->format('Y-m-d H:i:s')]);
    }

    $insertReview = $pdo->prepare('INSERT INTO reviews(guest_name,rating,body,theme,status,created_at) VALUES(?,?,?,?,\'pending\',?)');
    foreach ($reviewSamples as $i=>$sample) {
        $insertReview->execute([$sample[0],$sample[1],$sample[2],$sample[3],$today->modify('-' . ($i+2) . ' days')->format('Y-m-d H:i:s')]);
    }

    $pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?)')->execute([$markerKey,json_encode(['version'=>1,'email'=>$email,'created_at'=>$today->format('Y-m-d')],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    sqlite_commit($pdo);
} catch (Throwable $error) {
    sqlite_rollback($pdo);
    throw $error;
}

fwrite(STDOUT, "Демо-набор создан без отправки сообщений и оплаты.\n" .
    'Логин личного кабинета: ' . $email . "\n" .
    'Случайный пароль (сохраните сейчас): ' . $password . "\n" .
    "Создано: 1 аккаунт, 12 заказов, 8 броней, 10 обращений, 6 отзывов на проверке; добавлены учебные адрес, уведомления и записи лояльности.\n");
