<?php
require __DIR__ . '/bootstrap.php';
$pdo = db();
$err = '';
$profileNotice = '';
$incomingReferralCode = strtoupper(trim((string)($_POST['referral_code'] ?? $_GET['ref'] ?? '')));
if (!preg_match('/^TP[A-F0-9]{10}$/',$incomingReferralCode)) $incomingReferralCode = '';

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
                $redirect = $incomingReferralCode !== '' ? 'account.php?ref='.rawurlencode($incomingReferralCode).'#account-referrals' : 'account.php';
                header('Location: '.$redirect);
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
$referralInviteHref = '';
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
        $referralInviteHref = 'account.php?ref='.rawurlencode($referralCode).'#account-referrals';
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
$orderStatusLabels = [
    'new' => 'Записан · учебный заказ',
    'processing' => 'В обработке',
    'completed' => 'Завершён',
    'cancelled' => 'Отменён',
];
$orderCount = count($orders);
$bookingCount = count($bookings);

page_start('Личный кабинет — Tokyo Premium', 'Ваши заказы и бронирования Tokyo Premium.');
header_html();
?>
<main class="admin-shell account-shell account-dashboard">
  <section class="admin-card account-card account-dashboard-card">
    <?php if (!$user): ?>
      <h1>Личный кабинет</h1>
      <p>Сохраняйте историю заказов и бронирований в одном месте.</p>
      <?php if ($err): ?><p class="form-status error"><?=e($err)?></p><?php endif ?>
      <?php if ($incomingReferralCode): ?><p class="form-status" role="status">Вас пригласили в Tokyo Premium. После регистрации вы сможете привязать код <?=e($incomingReferralCode)?>.</p><?php endif ?>
      <div class="intro-grid account-auth-grid account-dashboard-auth">
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
          <input type="hidden" name="referral_code" value="<?=e($incomingReferralCode)?>">
          <label>Имя<input name="name" required minlength="2" maxlength="80" autocomplete="name"></label>
          <label>Email<input type="email" name="email" required autocomplete="email"></label>
          <label>Пароль от 10 символов<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
          <button class="button">Регистрация</button>
        </form>
      </div>
    <?php else: ?>
      <header class="account-dashboard-hero">
        <div class="account-dashboard-identity">
          <span class="kicker">Tokyo Premium · личное пространство</span>
          <h1>Здравствуйте, <?=e($user['full_name'])?></h1>
          <p><?=e($user['email'])?></p>
          <small>Участник с <?=e(substr((string)$user['created_at'], 0, 10))?> · история и бонусы видны только после входа в этот аккаунт.</small>
        </div>
        <form method="post" class="account-logout-form">
          <input type="hidden" name="csrf" value="<?=e(csrf())?>">
          <input type="hidden" name="action" value="logout">
          <button class="button secondary" type="submit">Выйти</button>
        </form>
      </header>
      <?php if ($err): ?><p class="form-status error" role="alert"><?=e($err)?></p><?php endif ?>
      <?php if ($profileNotice): ?><p class="booking-confirmation-notice" role="status"><?=e($profileNotice)?></p><?php endif ?>

      <section class="account-dashboard-overview" aria-label="Краткая сводка аккаунта">
        <div class="account-dashboard-metrics">
          <article><span>История заказов</span><strong><?=number_format($orderCount,0,'.',' ')?></strong><small>серверные записи</small></article>
          <article><span>Бронирования</span><strong><?=number_format($bookingCount,0,'.',' ')?></strong><small>запросы этого аккаунта</small></article>
          <article><span>Tokyo Privilege</span><strong><?=number_format($loyaltyBalance,0,'.',' ')?></strong><small>учебных баллов · не деньги</small></article>
          <article><span>Завершённые заказы</span><strong><?=number_format($loyaltySpent,0,'.',' ')?></strong><small>₸ в учебном журнале</small></article>
        </div>
        <div class="account-dashboard-loyalty tp20-loyalty-card" id="account-loyalty">
          <span class="kicker">TOKYO PRIVILEGE · УЧЕБНАЯ ПРОГРАММА</span>
          <div class="account-tier-row"><h2><?=e($tier['name']??'Уровень не присвоен')?></h2><strong><?=number_format($loyaltyBalance,0,'.',' ')?> <small>баллов</small></strong></div>
          <p>Баланс складывается из серверного журнала завершённых заказов и не имеет денежной стоимости.</p>
          <?php if ($nextTier): ?>
            <div class="tp20-tier-progress" role="progressbar" aria-label="Прогресс до уровня <?=e($nextTier['name'])?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=$tierProgress?>"><span style="width:<?=$tierProgress?>%"></span></div>
            <small><?=$tierProgress?>% до уровня <?=e($nextTier['name'])?> · ещё <?=number_format(max(0,(int)$nextTier['minimum_completed_spend']-$loyaltySpent),0,'.',' ')?> ₸ завершённых заказов</small>
          <?php else: ?>
            <small>Прогресс определяется активными правилами учебной программы.</small>
          <?php endif ?>
          <a class="account-inline-link" href="offers.php">Условия привилегий ↗</a>
        </div>
      </section>

      <div class="account-dashboard-actions" aria-label="Быстрые действия">
        <a href="menu.php">Открыть меню <span>↗</span></a>
        <a href="set-builder.php">Собрать сет <span>↗</span></a>
        <a href="pages.php?page=booking">Выбрать вечер <span>↗</span></a>
        <a href="offers.php">Смотреть привилегии <span>↗</span></a>
      </div>

      <div class="account-dashboard-layout">
        <nav class="account-dashboard-nav" aria-label="Разделы личного кабинета">
          <a href="#account-orders"><span>01</span>Мои заказы<small><?=$orderCount?></small></a>
          <a href="#account-bookings"><span>02</span>Бронирования<small><?=$bookingCount?></small></a>
          <a href="#account-browser-favorites"><span>03</span>Избранное<small>в браузере</small></a>
          <a href="#account-referrals"><span>04</span>Приглашения</a>
          <a href="#account-settings"><span>05</span>Настройки</a>
        </nav>
        <div class="account-dashboard-content">
          <div class="account-dashboard-primary">
            <section id="account-orders" class="account-section account-dashboard-panel" aria-labelledby="account-orders-title">
              <div class="account-dashboard-section-heading"><div><span class="kicker">ИСТОРИЯ · ТОЛЬКО ВАШ АККАУНТ</span><h2 id="account-orders-title">Мои заказы</h2></div><span class="account-section-count"><?=$orderCount?> записей</span></div>
              <?php if (!$orders): ?>
                <div class="account-empty-state"><p>Заказы, оформленные после входа в аккаунт, появятся здесь.</p><a class="button secondary" href="menu.php">Перейти к меню ↗</a></div>
              <?php else: ?>
                <div class="account-order-list">
                  <?php foreach ($orders as $order): $orderStatus = (string)$order['status']; ?>
                    <article class="account-order-card">
                      <div class="account-order-heading"><span>Заказ №<?= (int)$order['id']?></span><strong><?=number_format((int)$order['total'],0,'.',' ')?> ₸</strong></div>
                      <p class="account-order-meta"><span class="account-status-label"><?=e($orderStatusLabels[$orderStatus] ?? $orderStatus)?></span> · <?=$order['fulfillment']==='pickup'?'Самовывоз':'Доставка'?> · <?=e($order['created_at'])?></p>
                      <details class="order-item-details">
                        <summary>Состав заказа · <?=count($order['items'])?> <?=count($order['items'])===1?'позиция':'позиций'?></summary>
                        <ul><?php foreach ($order['items'] as $item): ?><li><?=e($item['item_name'])?> · <?= (int)$item['quantity']?> × <?=number_format((int)$item['price'],0,'.',' ')?> ₸ = <?=number_format((int)$item['price']*(int)$item['quantity'],0,'.',' ')?> ₸</li><?php endforeach ?></ul>
                      </details>
                    </article>
                  <?php endforeach ?>
                </div>
              <?php endif ?>
            </section>

            <section id="account-bookings" class="account-section account-dashboard-panel" aria-labelledby="account-bookings-title">
              <div class="account-dashboard-section-heading"><div><span class="kicker">ЗАПРОСЫ · СТАТУС ИЗ БАЗЫ</span><h2 id="account-bookings-title">Мои бронирования</h2></div><span class="account-section-count"><?=$bookingCount?> записей</span></div>
              <?php if (count(array_filter($bookings, static fn(array $booking): bool => $booking['status'] === 'confirmed')) > 0): ?>
                <p class="booking-confirmation-notice" role="status">✓ Бронь подтверждена рестораном. Проверьте дату и время визита ниже.</p>
              <?php endif ?>
              <?php if (!$bookings): ?>
                <div class="account-empty-state"><p>Запросов на бронирование пока нет.</p><a class="button secondary" href="pages.php?page=booking">Выбрать дату и зону ↗</a></div>
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
          </div>

          <aside class="account-dashboard-aside">
            <section id="account-browser-favorites" class="account-section account-dashboard-panel account-browser-favorites" aria-labelledby="account-browser-favorites-title" data-account-browser-favorites>
              <div class="account-dashboard-section-heading"><div><span class="kicker">ИЗБРАННОЕ · ЭТОТ БРАУЗЕР</span><h2 id="account-browser-favorites-title">Сохранённые блюда</h2></div><a class="account-inline-link" href="menu.php">Меню ↗</a></div>
              <p class="account-local-note">Избранное хранится локально в этом браузере и не привязывается к серверному аккаунту.</p>
              <div id="account-browser-favorites-list" class="account-browser-favorites-grid" aria-live="polite"></div>
              <p id="account-browser-favorites-empty" class="account-empty-copy">Здесь появятся блюда, сохранённые сердцем в меню.</p>
            </section>

            <section class="account-section account-dashboard-panel" aria-labelledby="tp20-personal-offers">
              <div class="account-dashboard-section-heading"><div><span class="kicker">PRIVATE INVITATIONS</span><h2 id="tp20-personal-offers">Персональные предложения</h2></div></div>
              <?php if (!$personalOffers): ?>
                <p class="account-section-copy">Персональных предложений пока нет. Здесь появятся только адресно назначенные действующие кампании.</p>
              <?php else: ?>
                <div class="tp20-offer-grid"><?php foreach ($personalOffers as $offer): ?><article class="tp20-offer-card"><div class="tp20-offer-copy"><span class="kicker">PERSONAL INVITATION</span><h3><?=e($offer['title'])?></h3><p><?=e($offer['description'])?></p><?php if ($offer['expires_at']): ?><small>Действует до <?=e($offer['expires_at'])?></small><?php endif ?></div></article><?php endforeach ?></div>
              <?php endif ?>
            </section>

            <section id="account-referrals" class="account-section account-dashboard-panel" aria-labelledby="tp20-referral-title">
              <div class="account-dashboard-section-heading"><div><span class="kicker">TOKYO PRIVILEGE · ПРИГЛАШЕНИЯ</span><h2 id="tp20-referral-title">Пригласить друга</h2></div></div>
              <p class="account-section-copy">Награда начисляется только после подходящего завершённого заказа. Код не создаёт денежного вознаграждения.</p>
              <div class="tp20-referral-code"><span>Ваш персональный код</span><strong><?=e($referralCode)?></strong><button class="button secondary" type="button" data-copy-code="<?=e($referralCode)?>" data-label="Скопировать код">Скопировать</button></div>
              <div class="tp20-referral-actions"><a class="button secondary" href="<?=e($referralInviteHref)?>">Открыть приглашение ↗</a><button class="button secondary" type="button" data-copy-invite="<?=e($referralInviteHref)?>" data-label="Скопировать ссылку">Скопировать ссылку</button></div>
              <p class="account-section-copy">Ссылка ведёт на регистрацию. Друг сможет привязать приглашение до первого заказа; награда появится только после выполнения настроенного серверного условия.</p>
              <form method="post" class="tp20-account-form account-referral-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="referral-attach"><label>Код того, кто пригласил вас<input name="referral_code" value="<?=e($incomingReferralCode)?>" maxlength="12" pattern="TP[A-Fa-f0-9]{10}" placeholder="TP0123ABCDEF" required></label><button class="button secondary" type="submit">Привязать код</button></form>
              <div class="tp20-referral-history"><strong>Приглашения</strong><?php if (!$invitedUsers): ?><p>Пока нет привязанных приглашений.</p><?php else: ?><?php foreach ($invitedUsers as $invite): ?><p><?=e($invite['referred_name']?:'Ожидает регистрации')?> · <?=e($invite['status'])?></p><?php endforeach ?><?php endif ?></div>
            </section>

            <section id="account-settings" class="account-section account-dashboard-panel" aria-labelledby="tp20-profile-settings">
              <div class="account-dashboard-section-heading"><div><span class="kicker">PRIVACY & SETTINGS</span><h2 id="tp20-profile-settings">Настройки профиля</h2></div></div>
              <p class="account-section-copy">Дата рождения необязательна. Согласие на персональные предложения можно отозвать, сняв отметку.</p>
              <form method="post" class="tp20-account-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="profile-save"><label>Дата рождения<input type="date" name="birth_date" value="<?=e((string)$profile['birth_date'])?>" max="<?=e(date('Y-m-d'))?>"></label><label class="tp20-account-consent"><input type="checkbox" name="birthday_offer_consent" value="1" <?=!empty($profile['birthday_offer_consent'])?'checked':''?>> Даю отдельное согласие на предложения ко дню рождения в учебной системе</label><button class="button secondary" type="submit">Сохранить настройки ↗</button></form>
            </section>

            <details class="account-section account-dashboard-panel account-ledger-details" id="account-ledger">
              <summary><span><span class="kicker">VERIFIED EVENTS</span><strong>История бонусов</strong></span><span class="account-section-count"><?=count($loyaltyEntries)?> операций</span></summary>
              <p class="account-section-copy">Записи создаются только серверными событиями. За неподтверждённые заказы баллы не начисляются.</p>
              <?php if (!$loyaltyEntries): ?>
                <p class="account-empty-copy">Операций пока нет.</p>
              <?php else: ?>
                <div class="tp20-ledger-list"><?php foreach ($loyaltyEntries as $entry): ?><article><span><?=e($entry['transaction_type'])?> · <?=e($entry['created_at'])?></span><strong class="<?=((int)$entry['points']<0?'is-negative':'')?>"><?=((int)$entry['points']>0?'+':'')?><?= (int)$entry['points']?></strong><p><?=e($entry['note'])?></p></article><?php endforeach ?></div>
              <?php endif ?>
            </details>
          </aside>
        </div>
      </div>
      <div class="account-dashboard-note">Учебный сценарий: заказ в базе не означает оплату или передачу ресторану. Контактные и ресторанные данные вымышлены.</div>
    <?php endif ?>
  </section>
</main>
<?php if ($user): ?><script src="account-demo.js" defer></script><?php endif ?>
<?php footer_html(); ?>
