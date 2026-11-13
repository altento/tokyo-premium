<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();
$error = '';

function admin_valid_dish_image(string $image): bool
{
    if (preg_match('~\Aassets/food/[A-Za-z0-9][A-Za-z0-9._-]*\.webp\z~D', $image)) {
        $root = realpath(__DIR__ . '/assets/food');
        $file = realpath(__DIR__ . '/' . $image);
        return $root !== false && $file !== false && is_file($file)
            && str_starts_with($file, $root . DIRECTORY_SEPARATOR);
    }

    $parts = parse_url($image);
    if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['fragment'])
        || (isset($parts['port']) && $parts['port'] !== 443)) {
        return false;
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($host, ['images.unsplash.com', 'plus.unsplash.com'], true)) {
        return false;
    }
    $path = (string)($parts['path'] ?? '');
    $decodedPath = rawurldecode($path);
    if ($path === '' || str_contains($decodedPath, '\\')
        || preg_match('~%2f|%5c~i', $path)) {
        return false;
    }
    foreach (explode('/', $decodedPath) as $segment) {
        if ($segment === '.' || $segment === '..') return false;
    }
    return true;
}

function admin_lower(string $value): string
{
    static $map = ['А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я'];
    return strtr(strtolower($value), $map);
}

function admin_unicode_length(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $length = preg_match_all('/./us', $value, $matches);
    return $length === false ? strlen($value) : $length;
}

function admin_unicode_slice(string $value, int $length): string
{
    if (function_exists('mb_substr')) return mb_substr($value, 0, $length, 'UTF-8');
    return preg_match('/^.{0,' . max(0, $length) . '}/us', $value, $match) ? $match[0] : '';
}

function admin_search_where(array $columns, string $search): array
{
    $search = trim($search);
    if ($search === '') return ['', []];
    $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], admin_lower($search)) . '%';
    $conditions = [];
    $values = [];
    foreach ($columns as $column) {
        $conditions[] = 'tp_unicode_lower(' . $column . ") LIKE ? ESCAPE '!'";
        $values[] = $pattern;
    }
    return [' WHERE (' . implode(' OR ', $conditions) . ')', $values];
}

function admin_rows(PDO $pdo, string $sql, array $values = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($values);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function admin_status_label(string $status): string
{
    return [
        'new'=>'Новая','confirmed'=>'Подтверждена','preparing'=>'Готовится',
        'ready'=>'Готов','completed'=>'Завершена','cancelled'=>'Отменена',
        'pending'=>'На модерации','published'=>'Опубликован','hidden'=>'Скрыт',
        'read'=>'Прочитано','archived'=>'В архиве',
    ][$status] ?? $status;
}

function admin_record_name(string $table, array $row): string
{
    return (string)($row['customer_name'] ?? $row['guest_name'] ?? $row['name'] ?? ('Запись №' . $row['id']));
}

if (method_exists($pdo, 'createFunction')) {
    $pdo->createFunction('tp_unicode_lower', static fn($value): string => admin_lower((string)($value ?? '')), 1);
} elseif (method_exists($pdo, 'sqliteCreateFunction')) {
    // Compatibility with PHP versions before PDO SQLite exposed createFunction().
    $pdo->sqliteCreateFunction('tp_unicode_lower', static fn($value): string => admin_lower((string)($value ?? '')), 1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'login') {
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $query = $pdo->prepare('SELECT id,password_hash FROM admins WHERE email=?');
        $query->execute([$email ?: '']);
        $admin = $query->fetch(PDO::FETCH_ASSOC);
        if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            header('Location: admin.php');
            exit;
        }
        $error = 'Неверный email или пароль.';
    } elseif (!empty($_SESSION['admin_id'])) {
        try {
            if ($action === 'logout') {
                unset($_SESSION['admin_id'], $_SESSION['admin_search'], $_SESSION['admin_flash']);
                session_regenerate_id(true);
                header('Location: admin.php');
                exit;
            }
            if ($action === 'admin-search') {
                $_SESSION['admin_search'] = isset($_POST['clear_search'])
                    ? ''
                    : admin_unicode_slice(trim((string)($_POST['q'] ?? '')), 120);
                header('Location: admin.php#search');
                exit;
            }
            if ($action === 'dish-save') {
                $id = (int)($_POST['id'] ?? 0);
                $category = trim((string)($_POST['category'] ?? ''));
                $name = trim((string)($_POST['name'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $weight = trim((string)($_POST['weight'] ?? ''));
                $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_INT);
                $image = trim((string)($_POST['image_url'] ?? ''));
                $sort = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
                $active = isset($_POST['active']) ? 1 : 0;
                $featured = isset($_POST['featured']) ? 1 : 0;
                $categoryQuery = $pdo->prepare('SELECT 1 FROM categories WHERE slug=?');
                $categoryQuery->execute([$category]);
                if ($name === '' || admin_unicode_length($name) > 120
                    || admin_unicode_length($description) > 1000
                    || admin_unicode_length($weight) > 30
                    || $price === false || $price < 1 || $price > 500000
                    || $sort === false || !$categoryQuery->fetchColumn()
                    || !admin_valid_dish_image($image)) {
                    throw new RuntimeException('Проверьте название, категорию, цену и фото; порядок должен быть целым числом.');
                }
                if ($id > 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM dishes WHERE id=?');
                    $exists->execute([$id]);
                    if (!$exists->fetchColumn()) throw new RuntimeException('Блюдо не найдено.');
                    $query = $pdo->prepare('UPDATE dishes SET category=?,name=?,description=?,weight=?,price=?,image_url=?,featured=?,active=?,sort_order=? WHERE id=?');
                    $query->execute([$category,$name,$description,$weight,$price,$image,$featured,$active,$sort,$id]);
                } else {
                    $query = $pdo->prepare('INSERT INTO dishes(category,name,description,weight,price,image_url,featured,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');
                    $query->execute([$category,$name,$description,$weight,$price,$image,$featured,$active,$sort]);
                }
                $_SESSION['admin_flash'] = $id > 0 ? 'Изменения блюда сохранены.' : 'Блюдо добавлено в меню.';
            } elseif ($action === 'dish-delete') {
                $query = $pdo->prepare('DELETE FROM dishes WHERE id=?');
                $query->execute([(int)($_POST['id'] ?? 0)]);
                if ($query->rowCount() === 0) throw new RuntimeException('Блюдо уже удалено или не найдено.');
                $_SESSION['admin_flash'] = 'Блюдо удалено.';
            } elseif ($action === 'status') {
                $table = (string)($_POST['table'] ?? '');
                $status = (string)($_POST['status'] ?? '');
                $allowed = [
                    'orders'=>['new','confirmed','preparing','ready','completed','cancelled'],
                    'bookings'=>['new','confirmed','completed','cancelled'],
                    'reviews'=>['pending','published','hidden'],
                    'contact_messages'=>['new','read','archived'],
                ];
                if (!isset($allowed[$table]) || !in_array($status, $allowed[$table], true)) {
                    throw new RuntimeException('Недопустимый статус.');
                }
                $recordId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$recordId || $recordId < 1) throw new RuntimeException('Запись не найдена.');
                if ($table === 'bookings' && $status === 'confirmed') {
                    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
                    try {
                        $exists = $pdo->prepare('SELECT 1 FROM bookings WHERE id=?');
                        $exists->execute([$recordId]);
                        if (!$exists->fetchColumn()) throw new RuntimeException('Запись не найдена.');
                        $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=?")->execute([$recordId]);
                        $confirmationMailId = booking_mail_queue_confirmation($pdo,(int)$recordId);
                        $pdo->commit();
                    } catch (Throwable $error) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $error;
                    }
                    $mailState = $confirmationMailId ? booking_mail_deliver($pdo,$confirmationMailId) : 'missing';
                    $_SESSION['admin_flash'] = match ($mailState) {
                        'sent' => 'Бронь подтверждена. Письмо гостю отправлено.',
                        'failed' => 'Бронь подтверждена. Email не отправлено; запись доступна для повтора.',
                        'pending' => 'Бронь подтверждена. Email в очереди; настройте SMTP и запустите отправку.',
                        default => 'Бронь подтверждена. Email гостя не указан, письмо не отправлялось.',
                    };
                } else {
                    $query = $pdo->prepare('UPDATE ' . $table . ' SET status=? WHERE id=?');
                    $query->execute([$status, $recordId]);
                    if ($query->rowCount() === 0) {
                        $exists = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE id=?');
                        $exists->execute([$recordId]);
                        if (!$exists->fetchColumn()) throw new RuntimeException('Запись не найдена.');
                    }
                    $_SESSION['admin_flash'] = 'Статус обновлён.';
                }
            } elseif ($action === 'mail-retry') {
                $mailId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$mailId || $mailId < 1) throw new RuntimeException('Письмо не найдено.');
                $mailQuery = $pdo->prepare('SELECT 1 FROM email_outbox WHERE id=? AND booking_id IS NOT NULL');
                $mailQuery->execute([$mailId]);
                if (!$mailQuery->fetchColumn()) throw new RuntimeException('Письмо бронирования не найдено.');
                $mailState = booking_mail_retry($pdo,(int)$mailId);
                $_SESSION['admin_flash'] = match ($mailState) {
                    'sent' => 'Письмо отправлено.',
                    'pending' => 'Письмо осталось в очереди. Проверьте настройки SMTP.',
                    'failed' => 'Повтор не удался. Письмо оставлено в очереди ошибок.',
                    'sending' => 'Письмо уже отправляется.',
                    default => 'Не удалось найти письмо для повтора.',
                };
            } elseif ($action === 'settings') {
                $keys = ['site_name','meta_title','meta_description','hero_eyebrow','hero_title','hero_text','address','phone','hours','instagram'];
                foreach ($keys as $key) {
                    $value = trim((string)($_POST[$key] ?? ''));
                    $pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')
                        ->execute([$key, admin_unicode_slice($value, 500)]);
                }
                $_SESSION['admin_flash'] = 'Настройки сайта сохранены.';
            }
            if (isset($_SESSION['admin_flash'])) {
                header('Location: admin.php');
                exit;
            }
        } catch (Throwable $exception) {
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Не удалось сохранить изменения.';
            error_log($exception->getMessage());
        }
    }
}

$logged = !empty($_SESSION['admin_id']);
$notice = (string)($_SESSION['admin_flash'] ?? '');
unset($_SESSION['admin_flash']);
$search = $logged ? trim((string)($_SESSION['admin_search'] ?? '')) : '';

page_start('Управление сайтом — Tokyo Premium', 'Защищённая панель управления сайтом.');
header_html();
if (!$logged):
?>
<main class="admin-login">
  <div class="kicker">Tokyo Premium · управление</div>
  <h1 class="page-hero-title">Вход в панель</h1>
  <?php if ($error !== ''): ?><p class="form-status error" role="alert"><?=e($error)?></p><?php endif ?>
  <p>Создайте первую учётную запись командой <code>php setup_admin.php</code>, затем войдите здесь.</p>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?=e(csrf())?>">
    <input type="hidden" name="action" value="login">
    <label class="field">Email<input name="email" type="email" autocomplete="username" required></label>
    <label class="field">Пароль<input name="password" type="password" autocomplete="current-password" required></label>
    <button class="button">Войти ↗</button>
  </form>
</main>
<?php
else:
$cats = $pdo->query('SELECT slug,name FROM categories ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC);
$dishFilter = admin_search_where(['d.name','d.category','c.name','d.description','d.weight','CAST(d.id AS TEXT)'], $search);
$items = admin_rows($pdo,
    'SELECT d.*,c.name AS category_name FROM dishes d LEFT JOIN categories c ON c.slug=d.category'
    . $dishFilter[0] . ' ORDER BY d.sort_order,d.id DESC LIMIT 100',
    $dishFilter[1]);
$orderFilter = admin_search_where(['CAST(o.id AS TEXT)','o.customer_name','o.phone','o.address','o.fulfillment','o.status','CAST(o.total AS TEXT)','oi.item_name'], $search);
$orders = admin_rows($pdo,
    "SELECT o.*,GROUP_CONCAT(oi.item_name || ' × ' || oi.quantity,' · ') AS items_summary
     FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id" . $orderFilter[0] . '
     GROUP BY o.id ORDER BY o.id DESC LIMIT 100', $orderFilter[1]);
$bookingFilter = admin_search_where(['CAST(b.id AS TEXT)','b.guest_name','b.guest_email','b.phone','b.booking_date','b.booking_time','b.zone','b.preferred_table','b.format','b.message','b.status'], $search);
$bookings = admin_rows($pdo, "SELECT b.*,
    (SELECT m.id FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':request:guest' ORDER BY m.id DESC LIMIT 1) AS request_mail_id,
    (SELECT m.status FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':request:guest' ORDER BY m.id DESC LIMIT 1) AS request_mail_status,
    (SELECT m.id FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':confirmed:guest' ORDER BY m.id DESC LIMIT 1) AS confirmation_mail_id,
    (SELECT m.status FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':confirmed:guest' ORDER BY m.id DESC LIMIT 1) AS confirmation_mail_status
    FROM bookings b" . $bookingFilter[0] . ' ORDER BY b.booking_date DESC,b.booking_time DESC,b.id DESC LIMIT 100', $bookingFilter[1]);
$reviewFilter = admin_search_where(['CAST(r.id AS TEXT)','r.guest_name','r.theme','r.body','CAST(r.rating AS TEXT)','r.status'], $search);
$reviews = admin_rows($pdo, 'SELECT r.* FROM reviews r' . $reviewFilter[0] . ' ORDER BY r.id DESC LIMIT 100', $reviewFilter[1]);
$messageFilter = admin_search_where(['CAST(m.id AS TEXT)','m.guest_name','m.email','m.subject','m.message','m.status'], $search);
$contactMessages = admin_rows($pdo, 'SELECT m.* FROM contact_messages m' . $messageFilter[0] . ' ORDER BY m.id DESC LIMIT 100', $messageFilter[1]);
$collections = [
    'orders'=>['label'=>'Заказы','rows'=>$orders],
    'bookings'=>['label'=>'Бронирования','rows'=>$bookings],
    'reviews'=>['label'=>'Отзывы','rows'=>$reviews],
    'contact_messages'=>['label'=>'Сообщения','rows'=>$contactMessages],
];
$statusOptions = [
    'orders'=>['new'=>'Новая','confirmed'=>'Подтверждён','preparing'=>'Готовится','ready'=>'Готов','completed'=>'Завершён','cancelled'=>'Отменён'],
    'bookings'=>['new'=>'Новая заявка','confirmed'=>'Подтверждена','completed'=>'Завершена','cancelled'=>'Отменена'],
    'reviews'=>['pending'=>'На модерации','published'=>'Опубликован','hidden'=>'Скрыт'],
    'contact_messages'=>['new'=>'Новое','read'=>'Прочитано','archived'=>'В архиве'],
];
$notificationCounts = [
    'orders'=>(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
    'bookings'=>(int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn(),
    'reviews'=>(int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn(),
    'contact_messages'=>(int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn(),
];
$notificationLabels = ['orders'=>'Новые заказы','bookings'=>'Новые брони','reviews'=>'Отзывы на проверке','contact_messages'=>'Новые сообщения'];
?>
<main class="admin-shell admin-dashboard">
  <div class="admin-topline">
    <div><div class="kicker">Tokyo Premium · управление</div><h1>Панель сайта</h1><a class="button secondary" href="admin-marketing.php">Offers &amp; Loyalty ↗</a></div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="action" value="logout">
      <button class="button secondary" type="submit">Выйти</button>
    </form>
  </div>
  <?php if ($notice !== ''): ?><p class="form-status success admin-notice" role="status"><?=e($notice)?></p><?php endif ?>
  <?php if ($error !== ''): ?><p class="form-status error admin-notice" role="alert"><?=e($error)?></p><?php endif ?>

  <nav class="admin-jump-nav" aria-label="Разделы панели">
    <a href="#notifications">Уведомления</a><a href="#menu">Меню</a><a href="#orders">Заказы</a>
    <a href="#bookings">Брони</a><a href="#reviews">Отзывы</a><a href="#contact_messages">Сообщения</a><a href="#settings">Настройки</a>
  </nav>

  <section class="admin-card" id="search" aria-labelledby="admin-search-title">
    <h2 id="admin-search-title">Поиск по панели</h2>
    <p class="admin-muted">Ищите блюда, заказы, бронирования, отзывы и обращения по имени, телефону, тексту или номеру записи.</p>
    <form method="post" class="admin-search-form" role="search">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="action" value="admin-search">
      <label class="sr-only" for="admin-search-q">Поиск по панели</label>
      <input id="admin-search-q" name="q" type="search" maxlength="120" value="<?=e($search)?>" placeholder="Например: нигири, имя гостя, телефон…">
      <button class="button" type="submit">Найти</button>
      <?php if ($search !== ''): ?>
        <button class="button secondary" type="submit" name="clear_search" value="1">Сбросить</button>
      <?php endif ?>
    </form>
    <?php if ($search !== ''): ?><p class="admin-search-summary" aria-live="polite">Результаты по запросу «<?=e($search)?>». Показано не более 100 записей в каждом разделе.</p><?php endif ?>
  </section>

  <section class="admin-card" id="notifications" aria-labelledby="admin-notifications-title">
    <h2 id="admin-notifications-title">Уведомления</h2>
    <p class="admin-muted">Счётчики показывают новые записи в базе. Уведомления обновляются при открытии панели; письма и SMS не отправляются.</p>
    <div class="admin-notification-grid">
      <?php foreach ($notificationCounts as $key=>$count): ?>
        <a class="admin-notification" href="#<?=$key?>">
          <span><?=e($notificationLabels[$key])?></span><strong class="<?=$count > 0 ? 'has-unread' : ''?>"><?=$count?></strong>
        </a>
      <?php endforeach ?>
    </div>
  </section>

  <section class="admin-card" id="menu" aria-labelledby="admin-menu-title">
    <h2 id="admin-menu-title">Позиции меню <span class="admin-result-count"><?=$search !== '' ? count($items) . ' найдено' : count($items) . ' показано'?></span></h2>
    <form method="post" class="admin-form admin-create-form">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="action" value="dish-save">
      <label>Название<input name="name" required maxlength="120"></label>
      <label>Категория<select name="category" required><?php foreach ($cats as $category): ?><option value="<?=e($category['slug'])?>"><?=e($category['name'])?></option><?php endforeach ?></select></label>
      <label>Цена, ₸<input name="price" type="number" min="1" max="500000" required></label>
      <label>Вес / объём<input name="weight" maxlength="30"></label>
      <label>Порядок<input name="sort_order" type="number" value="0"></label>
      <label>Фото: assets/food/*.webp или HTTPS Unsplash<input name="image_url" type="text" required></label>
      <label class="admin-span-all">Описание<textarea name="description" maxlength="1000" rows="2"></textarea></label>
      <label class="admin-check"><input type="checkbox" name="active" checked> В меню</label>
      <label class="admin-check"><input type="checkbox" name="featured"> Выбор шефа</label>
      <button class="button" type="submit">Добавить блюдо</button>
    </form>
    <div class="admin-dish-list">
      <?php foreach ($items as $dish): ?>
        <article class="admin-dish-row">
          <img src="<?=e($dish['image_url'])?>" alt="" loading="lazy">
          <div class="admin-dish-summary">
            <strong><?=e($dish['name'])?></strong><span><?=e($dish['category_name'] ?? $dish['category'])?> · <?=e($dish['weight'])?></span>
            <b><?=number_format((int)$dish['price'], 0, '.', ' ')?> ₸</b>
            <small class="<?=$dish['active'] ? 'is-active' : 'is-inactive'?>"><?=$dish['active'] ? 'В меню' : 'Скрыто'?><?=$dish['featured'] ? ' · Выбор шефа' : ''?></small>
          </div>
          <details class="admin-edit-details">
            <summary>Редактировать</summary>
            <form method="post" class="admin-form admin-edit-form">
              <input type="hidden" name="csrf" value="<?=e(csrf())?>">
              <input type="hidden" name="action" value="dish-save">
              <input type="hidden" name="id" value="<?=(int)$dish['id']?>">
              <label>Название<input name="name" value="<?=e($dish['name'])?>" required maxlength="120"></label>
              <label>Категория<select name="category"><?php foreach ($cats as $category): ?><option value="<?=e($category['slug'])?>" <?=$dish['category'] === $category['slug'] ? 'selected' : ''?>><?=e($category['name'])?></option><?php endforeach ?></select></label>
              <label>Цена, ₸<input name="price" type="number" min="1" max="500000" value="<?=(int)$dish['price']?>" required></label>
              <label>Вес / объём<input name="weight" value="<?=e($dish['weight'])?>" maxlength="30"></label>
              <label>Порядок<input name="sort_order" type="number" value="<?=(int)$dish['sort_order']?>"></label>
              <label>Фото<input name="image_url" value="<?=e($dish['image_url'])?>" required></label>
              <label class="admin-span-all">Описание<textarea name="description" maxlength="1000" rows="2"><?=e($dish['description'])?></textarea></label>
              <label class="admin-check"><input type="checkbox" name="active" <?=$dish['active'] ? 'checked' : ''?>> В меню</label>
              <label class="admin-check"><input type="checkbox" name="featured" <?=$dish['featured'] ? 'checked' : ''?>> Выбор шефа</label>
              <button class="button" type="submit">Сохранить</button>
            </form>
          </details>
          <form method="post" onsubmit="return confirm('Удалить блюдо?')">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            <input type="hidden" name="action" value="dish-delete">
            <input type="hidden" name="id" value="<?=(int)$dish['id']?>">
            <button class="admin-danger" type="submit">Удалить</button>
          </form>
        </article>
      <?php endforeach ?>
      <?php if (!$items): ?><p class="admin-empty">По этому запросу блюда не найдены.</p><?php endif ?>
    </div>
  </section>

  <?php foreach ($collections as $table=>$collection): ?>
  <section class="admin-card" id="<?=$table?>" aria-labelledby="admin-<?=$table?>-title">
    <h2 id="admin-<?=$table?>-title"><?=e($collection['label'])?> <span class="admin-result-count"><?=count($collection['rows'])?> <?=$search !== '' ? 'найдено' : 'последних записей'?></span></h2>
    <div class="admin-record-list">
      <?php foreach ($collection['rows'] as $row):
        $confirmedBooking = $table === 'bookings' && $row['status'] === 'confirmed';
        $recordName = admin_record_name($table, $row);
      ?>
      <article class="admin-record">
        <div class="admin-record-top">
          <span class="admin-record-number">#<?=(int)$row['id']?></span>
          <?php if ($confirmedBooking): ?>
            <span class="admin-status is-confirmed" role="status">✓ Бронь подтверждена</span>
          <?php else: ?>
            <span class="admin-status status-<?=e($row['status'])?>"><?=e(admin_status_label((string)$row['status']))?></span>
          <?php endif ?>
        </div>
        <h3><?=e($recordName)?></h3>
        <div class="admin-record-meta">
          <?php if (!empty($row['phone'])): ?><span>Телефон: <?=e($row['phone'])?></span><?php endif ?>
          <?php if ($table === 'orders'): ?>
            <span><?=$row['fulfillment'] === 'pickup' ? 'Самовывоз' : 'Доставка'?><?=$row['address'] !== '' ? ' · ' . e($row['address']) : ''?></span>
            <span>Сумма: <?=number_format((int)$row['total'], 0, '.', ' ')?> ₸</span>
            <?php if (!empty($row['items_summary'])): ?><span>Состав: <?=e($row['items_summary'])?></span><?php endif ?>
            <?php if (!empty($row['comment'])): ?><span>Комментарий: <?=e($row['comment'])?></span><?php endif ?>
          <?php elseif ($table === 'bookings'): ?>
            <span><?=e($row['booking_date'])?> · <?=e($row['booking_time'])?> · <?=(int)$row['guest_count']?> гостей</span>
            <?php if ($row['guest_email'] !== ''): ?><span>Email гостя: <?=e($row['guest_email'])?></span><?php endif ?>
            <?php if ($row['zone'] !== ''): ?><span>Зона: <?=e($row['zone'])?></span><?php endif ?>
            <?php if ($row['preferred_table'] !== ''): ?><span>Стол: <?=e($row['preferred_table'])?></span><?php endif ?>
            <?php if ($row['format'] !== ''): ?><span>Формат Omakase: <?=e($row['format'])?></span><?php endif ?>
            <?php if ($row['message'] !== ''): ?><span><?=e($row['message'])?></span><?php endif ?>
          <?php elseif ($table === 'reviews'): ?>
            <span><?=str_repeat('★', (int)$row['rating'])?> · <?=e($row['theme'])?></span><p><?=e($row['body'])?></p>
          <?php else: ?>
            <span><?=e($row['email'])?> · <?=e($row['subject'])?></span><p><?=e($row['message'])?></p>
          <?php endif ?>
          <time datetime="<?=e(str_replace(' ', 'T', (string)$row['created_at']))?>"><?=e($row['created_at'])?></time>
        </div>
        <?php if ($table === 'bookings'): ?>
        <div class="admin-booking-emails">
            <?php foreach ([['Заявка',$row['request_mail_id']??null,$row['request_mail_status']??null],['Подтверждение',$row['confirmation_mail_id']??null,$row['confirmation_mail_status']??null]] as [$mailLabel,$mailId,$mailStatus]): if ($mailStatus !== null): ?>
              <div class="admin-booking-email-row"><span class="admin-status status-<?=e($mailStatus)?>">Email · <?=e($mailLabel)?> · <?=e(booking_mail_status_label((string)$mailStatus))?></span>
                <?php if (in_array($mailStatus,['pending','failed'],true)): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="mail-retry"><input type="hidden" name="id" value="<?=(int)$mailId?>"><button class="button secondary admin-mail-retry" type="submit">Повторить email</button></form><?php endif ?>
              </div>
            <?php endif; endforeach ?>
          </div>
        <?php endif ?>
          <div class="admin-record-actions">
          <form method="post" class="admin-status-form">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="table" value="<?=e($table)?>">
            <input type="hidden" name="id" value="<?=(int)$row['id']?>">
            <label class="sr-only" for="status-<?=$table?>-<?=(int)$row['id']?>">Статус записи №<?=(int)$row['id']?></label>
            <select id="status-<?=$table?>-<?=(int)$row['id']?>" name="status">
              <?php foreach ($statusOptions[$table] as $value=>$label): ?>
                <option value="<?=e($value)?>" <?=$row['status'] === $value ? 'selected' : ''?>><?=e($label)?></option>
              <?php endforeach ?>
            </select>
            <button class="button secondary" type="submit">Обновить</button>
          </form>
          <?php if ($table === 'bookings' && $row['status'] === 'new'): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?=e(csrf())?>">
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="table" value="bookings">
              <input type="hidden" name="id" value="<?=(int)$row['id']?>">
              <input type="hidden" name="status" value="confirmed">
              <button class="button admin-confirm-button" type="submit">Подтвердить бронь</button>
            </form>
          <?php endif ?>
        </div>
      </article>
      <?php endforeach ?>
      <?php if (!$collection['rows']): ?><p class="admin-empty">Записей пока нет<?php if ($search !== ''): ?> по запросу «<?=e($search)?>»<?php endif ?>.</p><?php endif ?>
    </div>
  </section>
  <?php endforeach ?>

  <section class="admin-card" id="settings" aria-labelledby="admin-settings-title">
    <h2 id="admin-settings-title">Контакты и SEO</h2>
    <form method="post" class="admin-form admin-settings-form">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="action" value="settings">
      <?php foreach (['site_name'=>'Название сайта','meta_title'=>'SEO title','meta_description'=>'SEO description','hero_eyebrow'=>'Надзаголовок первого экрана','hero_title'=>'Заголовок первого экрана','hero_text'=>'Текст первого экрана','address'=>'Адрес','phone'=>'Телефон','hours'=>'График','instagram'=>'Instagram URL'] as $key=>$label): ?>
        <label><?=$label?>
          <?php if (in_array($key, ['meta_description','hero_text'], true)): ?>
            <textarea name="<?=e($key)?>" maxlength="500" rows="3"><?=e(setting($key))?></textarea>
          <?php else: ?>
            <input name="<?=e($key)?>" value="<?=e(setting($key))?>" maxlength="500">
          <?php endif ?>
        </label>
      <?php endforeach ?>
      <button class="button" type="submit">Сохранить настройки</button>
    </form>
  </section>
  <p class="admin-demo-note">Учебная панель PHP + SQLite. Подтверждение меняет статус в базе и личном кабинете; отправки email/SMS здесь нет.</p>
</main>
<?php endif; ?>
<?php footer_html(); ?>
