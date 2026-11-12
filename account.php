<?php
require __DIR__ . '/bootstrap.php';
$pdo = db();
$err = '';
$profileNotice = '';

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
    } elseif ($action === 'profile-save' && !empty($_SESSION['user_id'])) {
        $birthDate = trim((string)($_POST['birth_date'] ?? ''));
        $consent = isset($_POST['birthday_offer_consent']) ? 1 : 0;
        $validDate = true;
        if ($birthDate !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate, commerce_timezone());
            $dateErrors = DateTimeImmutable::getLastErrors();
            $validDate = $date && $date->format('Y-m-d') === $birthDate && ($dateErrors === false || (!$dateErrors['warning_count'] && !$dateErrors['error_count'])) && $date <= new DateTimeImmutable('today', commerce_timezone()) && $date >= new DateTimeImmutable('1900-01-01', commerce_timezone());
        }
        if (!$validDate || ($consent && $birthDate === '')) {
            $err = 'Укажите корректную дату рождения. Для персонального предложения требуется отдельное согласие.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO user_profiles(user_id,birth_date,birthday_offer_consent,birthday_consent_at,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(user_id) DO UPDATE SET birth_date=excluded.birth_date,birthday_offer_consent=excluded.birthday_offer_consent,birthday_consent_at=excluded.birthday_consent_at,updated_at=CURRENT_TIMESTAMP');
            $stmt->execute([(int)$_SESSION['user_id'], $birthDate ?: null, $consent, $consent ? date('Y-m-d H:i:s') : null]);
            $profileNotice = 'Настройки персональных предложений сохранены.';
        }
    } elseif ($action === 'referral-attach' && !empty($_SESSION['user_id'])) {
        try {
            commerce_attach_referral($pdo, (int)$_SESSION['user_id'], (string)($_POST['referral_code'] ?? ''));
            $profileNotice = 'Код приглашения сохранён. Вознаграждение появится только после подходящего завершённого заказа.';
        } catch (DomainException $exception) { $err = $exception->getMessage(); }
    }
}

$user = null;
$orders = [];
$bookings = [];
$profile = null;
$tier = null;
$loyaltyBalance = 0;
$loyaltyEntries = [];
$referralCode = '';
$invitedUsers = [];
$personalOffers = [];
$loyaltySpent = 0;
$nextTier = null;
$tierProgress = 0;
if (!empty($_SESSION['user_id'])) {
    $query = $pdo->prepare('SELECT id,full_name,email,created_at FROM users WHERE id=?');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();

    if ($user) {
        $userId = (int)$user['id'];
        $query = $pdo->prepare('SELECT birth_date,birthday_offer_consent,birthday_consent_at FROM user_profiles WHERE user_id=?');
        $query->execute([$userId]); $profile = $query->fetch() ?: ['birth_date'=>'','birthday_offer_consent'=>0,'birthday_consent_at'=>null];
        $tier = commerce_user_tier($pdo, $userId);
        $loyaltyBalance = commerce_get_loyalty_balance($pdo, $userId);
        $query = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=? AND status='completed'");
        $query->execute([$userId]); $loyaltySpent = (int)$query->fetchColumn();
        $query = $pdo->prepare('SELECT * FROM loyalty_tiers WHERE active=1 AND minimum_completed_spend>? ORDER BY minimum_completed_spend,sort_order LIMIT 1');
        $query->execute([$loyaltySpent]); $nextTier = $query->fetch() ?: null;
        if ($nextTier) {
            $currentFloor = (int)($tier['minimum_completed_spend'] ?? 0);
            $tierProgress = max(0, min(100, (int)round(($loyaltySpent - $currentFloor) * 100 / max(1, (int)$nextTier['minimum_completed_spend'] - $currentFloor))));
        }
        $query = $pdo->prepare('SELECT transaction_type,points,note,created_at FROM loyalty_ledger WHERE user_id=? ORDER BY id DESC LIMIT 12');
        $query->execute([$userId]); $loyaltyEntries = $query->fetchAll();
        $referralCode = commerce_referral_code_for_user($pdo, $userId);
        $query = $pdo->prepare('SELECT r.code,r.status,r.created_at,u.full_name AS referred_name FROM referrals r LEFT JOIN users u ON u.id=r.referred_user_id WHERE r.inviter_user_id=? ORDER BY r.id DESC LIMIT 20');
        $query->execute([$userId]); $invitedUsers = $query->fetchAll();
        $query = $pdo->prepare('SELECT c.title,c.description,po.expires_at FROM personalized_offers po JOIN promotion_campaigns c ON c.id=po.campaign_id WHERE po.user_id=? AND po.used_order_id IS NULL AND (po.expires_at IS NULL OR po.expires_at>=CURRENT_TIMESTAMP) AND c.active=1 ORDER BY po.assigned_at DESC');
        $query->execute([$userId]); $personalOffers = $query->fetchAll();
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
      <?php if ($err): ?><p class="form-status error" role="alert"><?=e($err)?></p><?php endif ?><?php if ($profileNotice): ?><p class="booking-confirmation-notice" role="status"><?=e($profileNotice)?></p><?php endif ?>
      <section class="tp20-account-overview" aria-labelledby="tp20-privilege-title">
        <div class="tp20-loyalty-card"><span class="kicker">TOKYO PRIVILEGE · ДЕМО-РЕЖИМ</span><h2 id="tp20-privilege-title"><?=e($tier['name']??'Уровень не присвоен')?></h2><p>Бонусный баланс рассчитывается по серверному журналу и не является денежной суммой.</p><strong><?=number_format($loyaltyBalance,0,'.',' ')?> баллов</strong><small>Завершённые заказы: <?=number_format($loyaltySpent,0,'.',' ')?> ₸</small><?php if($nextTier): ?><div class="tp20-tier-progress" role="progressbar" aria-label="Прогресс до уровня <?=e($nextTier['name'])?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=$tierProgress?>"><span style="width:<?=$tierProgress?>%"></span></div><small><?=$tierProgress?>% до уровня <?=e($nextTier['name'])?> · ещё <?=number_format(max(0,(int)$nextTier['minimum_completed_spend']-$loyaltySpent),0,'.',' ')?> ₸ завершённых заказов</small><?php else: ?><small>Достигнут высший активный уровень в настройках учебной программы.</small><?php endif ?></div>
        <div class="tp20-account-quick"><a href="set-builder.php"><span>01</span><strong>Собрать сет</strong><i>↗</i></a><a href="perfect-evening.php"><span>02</span><strong>Подобрать вечер</strong><i>↗</i></a><a href="offers.php"><span>03</span><strong>Привилегии</strong><i>↗</i></a><a href="gift-certificates.php"><span>04</span><strong>Эскиз подарка</strong><i>↗</i></a></div>
      </section>
      <div class="tp20-account-grid">
        <section class="account-section tp20-account-section" aria-labelledby="tp20-profile-settings"><div class="editorial-heading"><div class="kicker">PERSONAL SETTINGS</div><h2 id="tp20-profile-settings">Профиль и дата рождения</h2><p>Дата рождения необязательна. Согласие нужно только для персональных предложений и может быть отозвано здесь же.</p></div><form method="post" class="tp20-account-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="profile-save"><label>Дата рождения<input type="date" name="birth_date" value="<?=e((string)$profile['birth_date'])?>" max="<?=e(date('Y-m-d'))?>"></label><label class="tp20-account-consent"><input type="checkbox" name="birthday_offer_consent" value="1" <?=!empty($profile['birthday_offer_consent'])?'checked':''?>> Согласен получать персональные предложения ко дню рождения в учебной системе</label><button class="button secondary" type="submit">Сохранить настройки ↗</button></form></section>
        <section class="account-section tp20-account-section" aria-labelledby="tp20-referral-title"><div class="editorial-heading"><div class="kicker">INVITE WITH CARE</div><h2 id="tp20-referral-title">Пригласить друга</h2><p>Код привязывается к новому аккаунту до его первого заказа. Награда начисляется только после квалифицирующего завершённого заказа.</p></div><div class="tp20-referral-code"><span>Ваш код</span><strong><?=e($referralCode)?></strong><button class="button secondary" type="button" data-copy-code="<?=e($referralCode)?>" data-label="Скопировать код">Скопировать</button></div><form method="post" class="tp20-account-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="referral-attach"><label>Код того, кто пригласил вас<input name="referral_code" maxlength="12" pattern="TP[A-Fa-f0-9]{10}" placeholder="TP0123ABCDEF" required></label><button class="button secondary" type="submit">Привязать код</button></form><div class="tp20-referral-history"><strong>Приглашения</strong><?php if(!$invitedUsers): ?><p>Пока нет привязанных приглашений.</p><?php else: ?><?php foreach($invitedUsers as $invite): ?><p><?=e($invite['referred_name']?:'Ожидает регистрации')?> · <?=e($invite['status'])?></p><?php endforeach ?><?php endif ?></div></section>
      </div>
      <section class="account-section tp20-account-section" aria-labelledby="tp20-loyalty-history"><div class="editorial-heading"><div class="kicker">LEDGER · VERIFIED EVENTS</div><h2 id="tp20-loyalty-history">История бонусов</h2><p>Записи журнала создаются при применённых серверных событиях; изменение статуса заказа вызывает начисление или обратную запись.</p></div><?php if(!$loyaltyEntries): ?><p>Операций пока нет. Бонусы не начисляются за неподтверждённые заказы.</p><?php else: ?><div class="tp20-ledger-list"><?php foreach($loyaltyEntries as $entry): ?><article><span><?=e($entry['transaction_type'])?> · <?=e($entry['created_at'])?></span><strong class="<?=((int)$entry['points']<0?'is-negative':'')?>"><?=((int)$entry['points']>0?'+':'')?><?= (int)$entry['points']?></strong><p><?=e($entry['note'])?></p></article><?php endforeach ?></div><?php endif ?></section>
      <section class="account-section tp20-account-section" aria-labelledby="tp20-personal-offers"><div class="editorial-heading"><div class="kicker">INVITATIONS · PRIVATE</div><h2 id="tp20-personal-offers">Персональные предложения</h2></div><?php if(!$personalOffers): ?><p>Персональных предложений пока нет. Здесь появятся только адресно назначенные активные кампании.</p><?php else: ?><div class="tp20-offer-grid"><?php foreach($personalOffers as $offer): ?><article class="tp20-offer-card"><div class="tp20-offer-copy"><span class="kicker">PERSONAL INVITATION</span><h3><?=e($offer['title'])?></h3><p><?=e($offer['description'])?></p><?php if($offer['expires_at']): ?><small>Действует до <?=e($offer['expires_at'])?></small><?php endif ?></div></article><?php endforeach ?></div><?php endif ?></section>
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
