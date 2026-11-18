<?php
require __DIR__ . '/bootstrap.php';
$pdo = db();
$err = '';
$profileNotice = '';
$accountDetailsDraft = null;
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
            $redirect = $incomingReferralCode !== '' ? 'account.php?ref='.rawurlencode($incomingReferralCode).'#account-referrals' : 'account.php';
            header('Location: '.$redirect);
            exit;
        }
        $err = 'Неверный email или пароль.';
    } elseif ($action === 'account-details-save' && !empty($_SESSION['user_id'])) {
        $rawName = $_POST['full_name'] ?? '';
        $rawEmail = $_POST['email'] ?? '';
        $name = is_string($rawName) ? trim($rawName) : '';
        $email = is_string($rawEmail) ? trim($rawEmail) : '';
        $accountDetailsDraft = ['full_name'=>$name, 'email'=>$email];
        $nameLength = preg_match_all('/./us', $name);
        $validName = $nameLength !== false && $nameLength >= 2 && $nameLength <= 80
            && preg_match('/\\p{L}/u', $name) === 1
            && preg_match('/[\\p{Cc}\\p{Cf}]/u', $name) !== 1;
        $validEmail = strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        if (!$validName) {
            $err = 'Введите имя длиной от 2 до 80 символов без управляющих знаков.';
        } elseif (!$validEmail) {
            $err = 'Введите корректный email длиной не более 254 символов.';
        } else {
            $userId = (int)$_SESSION['user_id'];
            $query = $pdo->prepare('SELECT email,password_hash FROM users WHERE id=?');
            $query->execute([$userId]);
            $currentAccount = $query->fetch();
            if (!$currentAccount) {
                $err = 'Аккаунт не найден. Обновите страницу и войдите снова.';
            } else {
                $emailChanged = strcasecmp($email, (string)$currentAccount['email']) !== 0;
                $rawCurrentPassword = $_POST['current_password'] ?? '';
                $currentPassword = is_string($rawCurrentPassword) ? $rawCurrentPassword : '';
                if ($emailChanged && !password_verify($currentPassword, (string)$currentAccount['password_hash'])) {
                    $err = 'Введите текущий пароль, чтобы изменить email.';
                } else {
                    // The users.email UNIQUE COLLATE NOCASE index is the final
                    // authority, including when two profile updates race.
                    $duplicate = $pdo->prepare('SELECT 1 FROM users WHERE email COLLATE NOCASE=? COLLATE NOCASE AND id<>? LIMIT 1');
                    $duplicate->execute([$email, $userId]);
                    if ($duplicate->fetchColumn()) {
                        $err = 'Этот email уже используется другим аккаунтом.';
                    } else {
                        try {
                            $update = $pdo->prepare('UPDATE users SET full_name=?,email=? WHERE id=?');
                            $update->execute([$name, $email, $userId]);
                            $profileNotice = 'Имя и контактный email сохранены.';
                        } catch (PDOException $exception) {
                            if (str_contains($exception->getMessage(), 'UNIQUE constraint failed: users.email')) {
                                $err = 'Этот email уже используется другим аккаунтом.';
                            } else {
                                throw $exception;
                            }
                        }
                    }
                }
            }
        }
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
    } elseif ($action === 'address-save' && !empty($_SESSION['user_id'])) {
        try {
            $rawAddressId = $_POST['address_id'] ?? '';
            $addressId = $rawAddressId === '' ? null : filter_var($rawAddressId, FILTER_VALIDATE_INT);
            if ($addressId === false || ($addressId !== null && $addressId < 1)) throw new DomainException('Выберите сохранённый адрес.');
            commerce_save_user_address($pdo, (int)$_SESSION['user_id'], (string)($_POST['label'] ?? ''), (string)($_POST['address'] ?? ''), $addressId === null ? null : (int)$addressId);
            $profileNotice = $addressId === null ? 'Адрес сохранён в вашем аккаунте.' : 'Адрес обновлён.';
        } catch (DomainException $exception) { $err = $exception->getMessage(); }
    } elseif ($action === 'address-default' && !empty($_SESSION['user_id'])) {
        $addressId = filter_var($_POST['address_id'] ?? null, FILTER_VALIDATE_INT);
        try {
            if ($addressId === false || $addressId === null || $addressId < 1) throw new DomainException('Выберите сохранённый адрес.');
            commerce_set_user_address_default($pdo, (int)$_SESSION['user_id'], (int)$addressId);
            $profileNotice = 'Основной адрес доставки обновлён.';
        } catch (DomainException $exception) { $err = $exception->getMessage(); }
    } elseif ($action === 'address-delete' && !empty($_SESSION['user_id'])) {
        $addressId = filter_var($_POST['address_id'] ?? null, FILTER_VALIDATE_INT);
        try {
            if ($addressId === false || $addressId === null || $addressId < 1) throw new DomainException('Выберите сохранённый адрес.');
            commerce_delete_user_address($pdo, (int)$_SESSION['user_id'], (int)$addressId);
            $profileNotice = 'Адрес удалён из вашего аккаунта.';
        } catch (DomainException $exception) { $err = $exception->getMessage(); }
    } elseif ($action === 'notification-read' && !empty($_SESSION['user_id'])) {
        $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
        if ($notificationId === false || $notificationId === null || $notificationId < 1) {
            $err = 'Выберите корректное уведомление.';
        } elseif (commerce_mark_account_notification_read($pdo, (int)$_SESSION['user_id'], (int)$notificationId)) {
            $profileNotice = 'Уведомление отмечено как прочитанное.';
        } else {
            $err = 'Уведомление не найдено в вашем аккаунте.';
        }
    }
}

$user = null;
$orders = [];
$bookings = [];
$accountNotifications = [];
$unreadNotifications = 0;
$profile = null;
$addresses = [];
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
        $accountNotifications = commerce_account_notifications($pdo, $userId);
        $unreadNotifications = count(array_filter($accountNotifications, static fn(array $notification): bool => empty($notification['read_at'])));
        $addresses = commerce_user_addresses($pdo, $userId);
        $query = $pdo->prepare('SELECT birth_date,birthday_offer_consent,birthday_consent_at FROM user_profiles WHERE user_id=?');
        $query->execute([$userId]); $profile = $query->fetch() ?: ['birth_date'=>'','birthday_offer_consent'=>0,'birthday_consent_at'=>null];
        $birthdayPointsAwarded=commerce_award_birthday_points($pdo,$userId);
        if ($birthdayPointsAwarded>0) {
            $birthdayNotice='С днём рождения — начислено '.number_format($birthdayPointsAwarded,0,'.',' ').' учебных бонусов Tokyo Privilege.';
            $profileNotice=$profileNotice===''?$birthdayNotice:$profileNotice.' '.$birthdayNotice;
        }
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
        $query = $pdo->prepare("SELECT po.id AS offer_id,po.expires_at,c.id AS campaign_id,c.campaign_type,c.title,c.description,c.active,c.starts_at,c.ends_at,c.weekday_mask,c.starts_time,c.ends_time,c.timezone,c.required_tier,pc.code,pc.starts_at AS code_starts_at,pc.ends_at AS code_ends_at,pc.uses_count,pc.maximum_uses FROM personalized_offers po JOIN promotion_campaigns c ON c.id=po.campaign_id LEFT JOIN promo_codes pc ON pc.campaign_id=c.id AND pc.active=1 AND (pc.maximum_uses IS NULL OR pc.uses_count<pc.maximum_uses) WHERE po.user_id=? AND po.used_order_id IS NULL AND (po.expires_at IS NULL OR po.expires_at>=CURRENT_TIMESTAMP) AND c.active=1 ORDER BY po.assigned_at DESC,pc.created_at DESC");
        $query->execute([$userId]); $personalOfferRows=$query->fetchAll(); $personalOffers=[];
        foreach($personalOfferRows as $offerRow){
            if(!commerce_campaign_is_active($offerRow)||!commerce_campaign_tier_is_eligible($pdo,$offerRow,$userId)||($offerRow['campaign_type']==='birthday'&&!commerce_birthday_eligible($pdo,$userId)))continue;
            $offerId=(int)$offerRow['offer_id'];
            if(!isset($personalOffers[$offerId])){$personalOffers[$offerId]=['title'=>$offerRow['title'],'description'=>$offerRow['description'],'expires_at'=>$offerRow['expires_at'],'codes'=>[]];if($offerRow['expires_at'])$personalOffers[$offerId]['expires_at']=(new DateTimeImmutable((string)$offerRow['expires_at'],new DateTimeZone('UTC')))->setTimezone(commerce_timezone())->format('d.m.Y H:i');}
            if($offerRow['code']&&!isset($personalOffers[$offerId]['codes'][$offerRow['code']])&&commerce_date_window_is_active($offerRow['code_starts_at']?:null,$offerRow['code_ends_at']?:null,(string)$offerRow['timezone']))$personalOffers[$offerId]['codes'][$offerRow['code']]=$offerRow['code'];
        }
        $personalOffers=array_values(array_filter($personalOffers,static fn(array $offer):bool=>(bool)$offer['codes']));
        $query = $pdo->prepare('SELECT id,total,status,fulfillment,created_at FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 50');
        $query->execute([$_SESSION['user_id']]);
        $orders = $query->fetchAll();
        if ($orders) {
            $ids = array_column($orders, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $itemsQuery = $pdo->prepare("SELECT oi.order_id,oi.dish_id,oi.item_name,oi.price,oi.quantity,COALESCE(d.image_url,'') AS image_url FROM order_items oi LEFT JOIN dishes d ON d.id=oi.dish_id WHERE oi.order_id IN ($placeholders) ORDER BY oi.id");
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

page_start('Личный кабинет — Tokyo Premium', 'Ваши заказы и бронирования Tokyo Premium.', 'assets/generated/contact-exterior.webp');
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
          <input type="hidden" name="referral_code" value="<?=e($incomingReferralCode)?>">
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
      <header class="account-dashboard-hero" aria-labelledby="account-dashboard-title">
        <div class="account-dashboard-identity">
          <span class="kicker">Tokyo Premium · личное пространство</span>
          <h1 id="account-dashboard-title">Личный кабинет</h1>
          <p class="account-dashboard-hero-lead">Ваше пространство в Tokyo Premium</p>
          <p class="account-dashboard-hero-copy">Управляйте заказами, бронированиями, бонусами и личными данными в одном месте.</p>
          <div class="account-dashboard-member">
            <strong>Здравствуйте, <?=e($user['full_name'])?></strong>
            <span class="account-dashboard-member-email" title="<?=e($user['email'])?>"><?=e($user['email'])?></span>
            <small>Участник с <?=e(substr((string)$user['created_at'], 0, 10))?> · данные видны только после входа в этот аккаунт.</small>
          </div>
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
          <a href="#account-notifications"><span class="account-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg></span><span class="account-nav-label">Уведомления</span><small><?=$unreadNotifications?></small></a>
          <a href="#account-orders"><span class="account-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 8h14l1 12H4L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg></span><span class="account-nav-label">Мои заказы</span><small><?=$orderCount?></small></a>
          <a href="#account-bookings"><span class="account-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4m8-4v4M4 10h16m-12 4h3m-3 3h7"/></svg></span><span class="account-nav-label">Бронирования</span><small><?=$bookingCount?></small></a>
          <a href="#account-browser-favorites"><span class="account-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20.8 8.7c0 5.1-8.8 11-8.8 11s-8.8-5.9-8.8-11A4.7 4.7 0 0 1 12 6.2a4.7 4.7 0 0 1 8.8 2.5Z"/></svg></span><span class="account-nav-label">Избранное</span><small>в браузере</small></a>
          <a href="#account-addresses"><span class="account-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 10c0 5.4-8 11-8 11S4 15.4 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><span class="account-nav-label">Адреса</span><small><?=count($addresses)?></small></a>
          <a href="#account-referrals"><span class="account-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1m2-10h5m-2.5-2.5v5"/></svg></span><span class="account-nav-label">Приглашения</span></a>
          <a href="#account-settings"><span class="account-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/><circle cx="12" cy="12" r="5"/></svg></span><span class="account-nav-label">Настройки</span></a>
        </nav>
        <div class="account-dashboard-content">
          <div class="account-dashboard-primary">
            <section id="account-notifications" class="account-section account-dashboard-panel" aria-labelledby="account-notifications-title">
              <div class="account-dashboard-section-heading"><div><span class="kicker">СТАТУСЫ · ТОЛЬКО ВАШ АККАУНТ</span><h2 id="account-notifications-title">Уведомления</h2></div><span class="account-section-count"><?=$unreadNotifications?> непрочитано</span></div>
              <?php if (!$accountNotifications): ?>
                <div class="account-empty-state"><p>Здесь появятся обновления статуса ваших заказов и бронирований.</p></div>
              <?php else: ?>
                <div class="account-notification-list">
                  <?php foreach ($accountNotifications as $notification): $notificationRead = !empty($notification['read_at']); $notificationTarget = $notification['notification_type'] === 'booking_status' ? '#account-bookings' : '#account-orders'; ?>
                    <article class="account-notification-card <?=$notificationRead ? 'is-read' : 'is-unread'?>">
                      <div class="account-notification-copy"><div class="account-notification-heading"><h3><?=e($notification['title'])?></h3><span class="account-notification-state"><?=$notificationRead ? 'Прочитано' : 'Новое'?></span></div><p><?=e($notification['message'])?></p><time datetime="<?=e($notification['created_at'])?>"><?=e($notification['created_at'])?></time></div>
                      <div class="account-notification-actions"><a class="account-inline-link" href="account.php<?=$notificationTarget?>">Открыть запись ↗</a>
                        <?php if (!$notificationRead): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="notification-read"><input type="hidden" name="notification_id" value="<?=(int)$notification['id']?>"><button class="button secondary" type="submit">Отметить прочитанным</button></form><?php endif ?>
                      </div>
                    </article>
                  <?php endforeach ?>
                </div>
              <?php endif ?>
            </section>
            <section id="account-orders" class="account-section account-dashboard-panel" aria-labelledby="account-orders-title">
              <div class="account-dashboard-section-heading"><div><span class="kicker">ИСТОРИЯ · ТОЛЬКО ВАШ АККАУНТ</span><h2 id="account-orders-title">Мои заказы</h2></div><span class="account-section-count"><?=$orderCount?> записей</span></div>
              <?php if (!$orders): ?>
                <div class="account-empty-state"><p>Заказы, оформленные после входа в аккаунт, появятся здесь.</p><a class="button secondary" href="menu.php">Перейти к меню ↗</a></div>
              <?php else: ?>
                <div class="account-order-list">
                  <?php foreach ($orders as $order): $orderStatus = (string)$order['status']; $previewItem = null; foreach ($order['items'] as $orderItem) { if ($orderItem['image_url'] !== '') { $previewItem = $orderItem; break; } } ?>
                    <article class="account-order-card account-order-card--purchase">
                      <?php if ($previewItem): ?><img class="account-order-preview" src="<?=e($previewItem['image_url'])?>" alt="<?=e($previewItem['item_name'])?>" loading="lazy" decoding="async"><?php endif ?>
                      <div class="account-order-content">
                        <div class="account-order-heading"><span>Заказ №<?= (int)$order['id']?></span><strong><?=number_format((int)$order['total'],0,'.',' ')?> ₸</strong></div>
                        <p class="account-order-meta"><span class="account-status-label" data-status="<?=e($orderStatus)?>"><?=e($orderStatusLabels[$orderStatus] ?? $orderStatus)?></span><span class="account-order-meta-separator" aria-hidden="true">·</span> <?=$order['fulfillment']==='pickup'?'Самовывоз':'Доставка'?> <span class="account-order-meta-separator" aria-hidden="true">·</span> <?=e($order['created_at'])?></p>
                        <details class="order-item-details">
                          <summary>Состав заказа · <?=count($order['items'])?> <?=count($order['items'])===1?'позиция':'позиций'?></summary>
                          <ul><?php foreach ($order['items'] as $item): ?><li><?=e($item['item_name'])?> · <?= (int)$item['quantity']?> × <?=number_format((int)$item['price'],0,'.',' ')?> ₸ = <?=number_format((int)$item['price']*(int)$item['quantity'],0,'.',' ')?> ₸</li><?php endforeach ?></ul>
                        </details>
                      </div>
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

            <section id="account-addresses" class="account-section account-dashboard-panel" aria-labelledby="account-addresses-title">
              <div class="account-dashboard-section-heading"><div><span class="kicker">ТОЛЬКО ВАШ АККАУНТ</span><h2 id="account-addresses-title">Адреса доставки</h2></div><span class="account-section-count"><?=count($addresses)?> / 10</span></div>
              <p class="account-section-copy">Адреса доступны только после входа в ваш аккаунт. Добавление адреса не отправляет заказ.</p>
              <form method="post" class="tp20-account-form tp20-address-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="address-save"><label>Название адреса<input name="label" minlength="2" maxlength="60" required autocomplete="off" placeholder="Дом, работа"></label><label>Адрес<input name="address" minlength="5" maxlength="240" required autocomplete="street-address" placeholder="Улица, дом, квартира"></label><button class="button secondary" type="submit" <?=count($addresses)>=10?'disabled':''?>>Сохранить адрес ↗</button></form>
              <?php if (!$addresses): ?><p class="account-empty-copy">Сохранённых адресов пока нет.</p><?php else: ?>
                <div class="tp20-address-list">
                  <?php foreach ($addresses as $savedAddress): ?>
                    <article class="tp20-saved-address"><div><span class="kicker"><?=e($savedAddress['label'])?></span><address><?=e($savedAddress['address'])?></address><?php if (!empty($savedAddress['is_default'])): ?><span class="tp20-address-default">Основной адрес</span><?php endif ?><details class="tp20-address-edit"><summary>Изменить адрес</summary><form method="post" class="tp20-account-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="address-save"><input type="hidden" name="address_id" value="<?= (int)$savedAddress['id']?>"><label>Название адреса<input name="label" minlength="2" maxlength="60" required value="<?=e($savedAddress['label'])?>"></label><label>Адрес<input name="address" minlength="5" maxlength="240" required autocomplete="street-address" value="<?=e($savedAddress['address'])?>"></label><button class="button secondary" type="submit">Сохранить изменения</button></form></details></div><div class="tp20-address-actions">
                      <?php if (empty($savedAddress['is_default'])): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="address-default"><input type="hidden" name="address_id" value="<?= (int)$savedAddress['id']?>"><button class="button secondary" type="submit">Сделать основным</button></form><?php endif ?>
                      <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="address-delete"><input type="hidden" name="address_id" value="<?= (int)$savedAddress['id']?>"><button class="text-button" type="submit" aria-label="Удалить адрес <?=e($savedAddress['label'])?>">Удалить</button></form>
                    </div></article>
                  <?php endforeach ?>
                </div>
              <?php endif ?>
            </section>

            <section class="account-section account-dashboard-panel" aria-labelledby="tp20-personal-offers">
              <div class="account-dashboard-section-heading"><div><span class="kicker">PRIVATE INVITATIONS</span><h2 id="tp20-personal-offers">Персональные предложения</h2></div></div>
              <?php if (!$personalOffers): ?>
                <p class="account-section-copy">Персональных предложений пока нет. Здесь появятся только адресно назначенные действующие кампании.</p>
              <?php else: ?>
                <div class="tp20-offer-grid"><?php foreach ($personalOffers as $offer): ?><article class="tp20-offer-card"><div class="tp20-offer-copy"><span class="kicker">PERSONAL INVITATION</span><h3><?=e($offer['title'])?></h3><p><?=e($offer['description'])?></p><?php if ($offer['expires_at']): ?><small>Действует до <?=e($offer['expires_at'])?> · время Кызылорды</small><?php endif ?><?php foreach($offer['codes'] as $code): ?><div class="tp20-private-code"><strong><?=e($code)?></strong><button class="button secondary" type="button" data-copy-code="<?=e($code)?>" data-label="Скопировать код">Скопировать</button><a class="text-link" href="pages.php?page=cart&amp;promo=<?=e(rawurlencode($code))?>">Перейти к заказу ↗</a></div><?php endforeach ?></div></article><?php endforeach ?></div>
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
              <p class="account-section-copy">Личные данные используются для вашего аккаунта и оформления заказов.</p>
              <form method="post" class="tp20-account-form account-personal-details-form">
                <input type="hidden" name="csrf" value="<?=e(csrf())?>">
                <input type="hidden" name="action" value="account-details-save">
                <label>Имя<input name="full_name" value="<?=e($accountDetailsDraft['full_name'] ?? $user['full_name'])?>" minlength="2" maxlength="320" autocomplete="name" required></label>
                <label>Email<input type="email" name="email" value="<?=e($accountDetailsDraft['email'] ?? $user['email'])?>" maxlength="254" autocomplete="email" required></label>
                <label>Текущий пароль <small>(нужен только при изменении email)</small><input type="password" name="current_password" autocomplete="current-password" maxlength="200"></label>
                <button class="button secondary" type="submit">Сохранить личные данные ↗</button>
              </form>
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
<script src="account-dashboard.js" defer></script>
<?php footer_html(); ?>
