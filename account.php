<?php
require __DIR__ . '/bootstrap.php';
$pdo = db();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'logout') {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        header('Location: account.php');
        exit;
    }
    if ($action === 'register') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string)($_POST['password'] ?? '');
        if (strlen($name) < 2 || strlen($name) > 80 || !$email || strlen($password) < 10) {
            $err = 'Заполните имя, корректный email и пароль от 10 символов.';
        } else {
            try {
                $insert = $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)');
                $insert->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
                $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                session_regenerate_id(true);
                header('Location: account.php');
                exit;
            } catch (Throwable $exception) {
                $err = 'Этот email уже зарегистрирован.';
            }
        }
    } elseif ($action === 'login') {
        $query = $pdo->prepare('SELECT id,password_hash FROM users WHERE email=?');
        $query->execute([trim((string)($_POST['email'] ?? ''))]);
        $account = $query->fetch();
        if ($account && password_verify((string)($_POST['password'] ?? ''), $account['password_hash'])) {
            $_SESSION['user_id'] = (int)$account['id'];
            session_regenerate_id(true);
            header('Location: account.php');
            exit;
        }
        $err = 'Неверный email или пароль.';
    }
}

$user = null;
$orders = [];
$bookings = [];
if (!empty($_SESSION['user_id'])) {
    $query = $pdo->prepare('SELECT full_name,email,created_at FROM users WHERE id=?');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();

    if ($user) {
        $query = $pdo->prepare('SELECT id,total,status,fulfillment,created_at FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 50');
        $query->execute([$_SESSION['user_id']]);
        $orders = $query->fetchAll();
        if ($orders) {
            $ids = array_column($orders, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $itemsQuery = $pdo->prepare("SELECT order_id,item_name,price,quantity FROM order_items WHERE order_id IN ($placeholders) ORDER BY id");
            $itemsQuery->execute($ids);
            $itemsByOrder = [];
            foreach ($itemsQuery->fetchAll() as $item) $itemsByOrder[$item['order_id']][] = $item;
            foreach ($orders as &$order) $order['items'] = $itemsByOrder[$order['id']] ?? [];
            unset($order);
        }

        $query = $pdo->prepare('SELECT booking_date,booking_time,guest_count,status,format FROM bookings WHERE user_id=? ORDER BY booking_date DESC LIMIT 50');
        $query->execute([$_SESSION['user_id']]);
        $bookings = $query->fetchAll();
    } else {
        unset($_SESSION['user_id']);
    }
}

$bookingStatusLabels = [
    'new' => 'Ожидает подтверждения',
    'confirmed' => 'Бронь подтверждена',
    'completed' => 'Визит завершён',
    'cancelled' => 'Бронирование отменено',
];

page_start('Личный кабинет — Tokyo Premium', 'Ваши заказы и бронирования Tokyo Premium.');
header_html();
?>
<main class="admin-shell account-shell">
  <section class="admin-card account-card">
    <div class="kicker">Tokyo Premium · личное пространство</div>
    <?php if (!$user): ?>
      <h1>Личный кабинет</h1>
      <p>Сохраняйте историю заказов и бронирований в одном месте.</p>
      <?php if ($err): ?><p class="form-status error"><?=e($err)?></p><?php endif ?>
      <div class="intro-grid account-auth-grid">
        <form method="post" class="admin-form">
          <h2>Войти</h2>
          <input type="hidden" name="csrf" value="<?=e(csrf())?>">
          <input type="hidden" name="action" value="login">
          <label>Email<input type="email" name="email" required autocomplete="email"></label>
          <label>Пароль<input type="password" name="password" required autocomplete="current-password"></label>
          <button class="button">Войти</button>
        </form>
        <form method="post" class="admin-form">
          <h2>Создать аккаунт</h2>
          <input type="hidden" name="csrf" value="<?=e(csrf())?>">
          <input type="hidden" name="action" value="register">
          <label>Имя<input name="name" required minlength="2" maxlength="80" autocomplete="name"></label>
          <label>Email<input type="email" name="email" required autocomplete="email"></label>
          <label>Пароль от 10 символов<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
          <button class="button">Регистрация</button>
        </form>
      </div>
    <?php else: ?>
      <div class="account-welcome">
        <div><span class="kicker">Ваш профиль</span><h1>Здравствуйте, <?=e($user['full_name'])?></h1><p><?=e($user['email'])?></p></div>
        <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="logout"><button class="button secondary">Выйти</button></form>
      </div>
      <section class="account-section" aria-labelledby="account-orders-title">
        <h2 id="account-orders-title">История заказов</h2>
        <?php if (!$orders): ?>
          <p>Заказы, оформленные после входа в аккаунт, появятся здесь.</p>
        <?php else: ?>
          <div class="account-order-list">
            <?php foreach ($orders as $order): ?>
              <article class="account-order-card">
                <div class="account-order-heading"><span>Заказ №<?= (int)$order['id']?></span><strong><?=number_format((int)$order['total'],0,'.',' ')?> ₸</strong></div>
                <p class="account-order-meta"><?=$order['fulfillment']==='pickup'?'Самовывоз':'Доставка'?> · <?=e($order['status'])?> · <?=e($order['created_at'])?></p>
                <details class="order-item-details">
                  <summary>Состав заказа · <?=count($order['items'])?> <?=count($order['items'])===1?'позиция':'позиций'?></summary>
                  <ul><?php foreach ($order['items'] as $item): ?><li><?=e($item['item_name'])?> · <?= (int)$item['quantity']?> × <?=number_format((int)$item['price'],0,'.',' ')?> ₸ = <?=number_format((int)$item['price']*(int)$item['quantity'],0,'.',' ')?> ₸</li><?php endforeach ?></ul>
                </details>
              </article>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </section>
      <section class="account-section" aria-labelledby="account-bookings-title">
        <h2 id="account-bookings-title">Бронирования</h2>
        <?php if (count(array_filter($bookings, static fn(array $booking): bool => $booking['status'] === 'confirmed')) > 0): ?>
          <p class="booking-confirmation-notice" role="status">✓ Бронь подтверждена. Проверьте дату и время визита ниже.</p>
        <?php endif ?>
        <?php if (!$bookings): ?>
          <p>Ваши бронирования появятся здесь.</p>
        <?php else: ?>
          <div class="account-order-list">
            <?php foreach ($bookings as $booking): ?>
              <?php $bookingStatus = (string)$booking['status']; $confirmed = $bookingStatus === 'confirmed'; ?>
              <article class="account-order-card">
                <div class="account-order-heading">
                  <strong><?=e($booking['booking_date'])?> · <?=e($booking['booking_time'])?></strong>
                  <span class="booking-status-pill <?=$confirmed ? 'is-confirmed' : ''?>" <?=$confirmed ? 'role="status"' : ''?>><?=$confirmed ? '✓ ' : ''?><?=e($bookingStatusLabels[$bookingStatus] ?? $bookingStatus)?></span>
                </div>
                <p class="account-order-meta"><?= (int)$booking['guest_count']?> гостей<?= !empty($booking['format'])?' · Omakase «'.e($booking['format']).'»':'' ?>
                  <?php if ($bookingStatus === 'new'): ?> · Заявка ожидает подтверждения
                  <?php elseif ($confirmed): ?> · До встречи в Tokyo Premium
                  <?php elseif ($bookingStatus === 'cancelled'): ?> · Свяжитесь с рестораном, если хотите выбрать другое время
                  <?php endif ?>
                </p>
              </article>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </section>
    <?php endif ?>
  </section>
</main>
<?php footer_html(); ?>
