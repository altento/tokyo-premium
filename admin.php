<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();
$error = '';
$adminPairingTypes = ['drink'=>'Напиток','sauce'=>'Соус','dish'=>'Блюдо','dessert'=>'Десерт'];

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

function admin_add_filter(array $filter, string $column, string $value): array
{
    $filter[0] .= $filter[0] === '' ? ' WHERE ' : ' AND ';
    $filter[0] .= $column . '=?';
    $filter[1][] = $value;
    return $filter;
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

function admin_page_url(string $table, int $page, string $notificationTarget, string $recordFilterTable, string $recordFilterStatus): string
{
    $query = [];
    if ($notificationTarget !== '') {
        $query['notification'] = $notificationTarget;
    } elseif ($recordFilterTable === $table && $recordFilterStatus !== '') {
        $query['filter_table'] = $table;
        $query['filter_status'] = $recordFilterStatus;
    }
    $query['page_' . $table] = max(1, $page);
    return 'admin.php?' . http_build_query($query) . '#' . rawurlencode($table);
}

function admin_export_url(string $table, string $notificationTarget, string $recordFilterTable, string $recordFilterStatus): string
{
    $query = ['export' => $table];
    if ($notificationTarget === $table || ($table === 'bookings' && $notificationTarget === 'booking_emails')) {
        $query['notification'] = $notificationTarget;
    } elseif ($recordFilterTable === $table && $recordFilterStatus !== '') {
        $query['filter_table'] = $table;
        $query['filter_status'] = $recordFilterStatus;
    }
    return 'admin.php?' . http_build_query($query);
}

function admin_post_section(string $action, array $post): string
{
    $section = match ($action) {
        'dish-save', 'dish-delete' => 'menu',
        'pairing-save', 'pairing-delete' => 'pairings',
        'settings' => 'settings',
        'mail-retry' => 'bookings',
        'status' => (string)($post['table'] ?? ''),
        default => '',
    };
    $tables = ['orders', 'bookings', 'reviews', 'contact_messages'];
    return in_array($section, array_merge($tables, ['menu', 'pairings', 'settings']), true) ? $section : '';
}

function admin_post_return_url(string $action, array $query, array $post): string
{
    $section = admin_post_section($action, $post);
    if ($section === '') return 'admin.php';
    $tables = ['orders', 'bookings', 'reviews', 'contact_messages'];

    $safeQuery = [];
    if (in_array($section, $tables, true)) {
        $notification = (string)($query['notification'] ?? '');
        if ($notification === $section || ($section === 'bookings' && $notification === 'booking_emails')) {
            $safeQuery['notification'] = $notification;
        } elseif (($query['filter_table'] ?? '') === $section) {
            $status = (string)($query['filter_status'] ?? '');
            $allowedStatuses = [
                'orders' => ['new','confirmed','preparing','ready','completed','cancelled'],
                'bookings' => ['new','confirmed','completed','cancelled'],
                'reviews' => ['pending','published','hidden'],
                'contact_messages' => ['new','read','archived'],
            ];
            if (in_array($status, $allowedStatuses[$section], true)) {
                $safeQuery['filter_table'] = $section;
                $safeQuery['filter_status'] = $status;
            }
        }

        $pageKey = 'page_' . $section;
        $page = filter_var($query[$pageKey] ?? null, FILTER_VALIDATE_INT);
        if ($page !== false && $page !== null && $page > 1) {
            $safeQuery[$pageKey] = $page;
        }
    }

    $url = 'admin.php';
    if ($safeQuery !== []) $url .= '?' . http_build_query($safeQuery);
    return $url . '#' . rawurlencode($section);
}

function admin_csv_text(mixed $value): string
{
    if (!is_scalar($value) && $value !== null) return '';
    $text = (string)($value ?? '');
    // Prefix spreadsheet formulas and leading control/space characters with a
    // literal apostrophe. Numeric columns are emitted as integers by the query.
    return preg_match('/^[\x00-\x20\x7f]*[=+\-@]/', $text) ? "'" . $text : $text;
}

function admin_download_csv(string $filename, array $headers, array $columns, PDOStatement $statement): void
{
    if (headers_sent()) {
        http_response_code(500);
        exit;
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    $stream = fopen('php://output', 'wb');
    if (!is_resource($stream)) {
        http_response_code(500);
        exit;
    }
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, array_map('admin_csv_text', $headers));
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $values = [];
        foreach ($columns as $column) $values[] = admin_csv_text($row[$column] ?? '');
        fputcsv($stream, $values);
    }
    fclose($stream);
    exit;
}

function admin_count(PDO $pdo, string $sql, array $values = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($values);
    return (int)$statement->fetchColumn();
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
        $adminId = admin_authenticate(
            $pdo,
            (string)($_POST['email'] ?? ''),
            (string)($_POST['password'] ?? ''),
            (string)($_SERVER['REMOTE_ADDR'] ?? '')
        );
        if ($adminId !== null) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $adminId;
            header('Location: admin.php');
            exit;
        }
        $error = 'Неверный email или пароль.';
    } elseif (!empty($_SESSION['admin_id'])) {
        try {
            if ($action === 'logout') {
                unset($_SESSION['admin_id'], $_SESSION['admin_search'], $_SESSION['admin_flash'], $_SESSION['admin_flash_section']);
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
            } elseif ($action === 'pairing-save') {
                $dishId = filter_var($_POST['dish_id'] ?? null, FILTER_VALIDATE_INT);
                $pairedDishId = filter_var($_POST['paired_dish_id'] ?? null, FILTER_VALIDATE_INT);
                $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
                $pairingType = (string)($_POST['pairing_type'] ?? '');
                $oldType = (string)($_POST['old_type'] ?? '');
                if (!$dishId || !$pairedDishId || $dishId === $pairedDishId
                    || $sortOrder === false || $sortOrder < -10000 || $sortOrder > 10000
                    || !isset($adminPairingTypes[$pairingType])
                    || ($oldType !== '' && !isset($adminPairingTypes[$oldType]))) {
                    throw new RuntimeException('Выберите два разных блюда и допустимый тип сочетания.');
                }
                $dishCheck = $pdo->prepare('SELECT COUNT(*) FROM dishes WHERE id IN (?,?)');
                $dishCheck->execute([$dishId, $pairedDishId]);
                if ((int)$dishCheck->fetchColumn() !== 2) throw new RuntimeException('Одно из выбранных блюд не найдено.');
                $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
                try {
                    if ($oldType !== '') {
                        $removeOld = $pdo->prepare('DELETE FROM dish_pairings WHERE dish_id=? AND paired_dish_id=? AND pairing_type=?');
                        $removeOld->execute([$dishId, $pairedDishId, $oldType]);
                        if ($removeOld->rowCount() !== 1) throw new RuntimeException('Сочетание уже изменено или удалено. Обновите страницу.');
                    }
                    $savePairing = $pdo->prepare('INSERT INTO dish_pairings(dish_id,paired_dish_id,pairing_type,sort_order) VALUES(?,?,?,?) ON CONFLICT(dish_id,paired_dish_id,pairing_type) DO UPDATE SET sort_order=excluded.sort_order');
                    $savePairing->execute([$dishId, $pairedDishId, $pairingType, $sortOrder]);
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $exception;
                }
                $_SESSION['admin_flash'] = $oldType === '' ? 'Сочетание добавлено.' : 'Сочетание обновлено.';
            } elseif ($action === 'pairing-delete') {
                $dishId = filter_var($_POST['dish_id'] ?? null, FILTER_VALIDATE_INT);
                $pairedDishId = filter_var($_POST['paired_dish_id'] ?? null, FILTER_VALIDATE_INT);
                $pairingType = (string)($_POST['pairing_type'] ?? '');
                if (!$dishId || !$pairedDishId || !isset($adminPairingTypes[$pairingType])) throw new RuntimeException('Сочетание не найдено.');
                $removePairing = $pdo->prepare('DELETE FROM dish_pairings WHERE dish_id=? AND paired_dish_id=? AND pairing_type=?');
                $removePairing->execute([$dishId, $pairedDishId, $pairingType]);
                if ($removePairing->rowCount() !== 1) throw new RuntimeException('Сочетание уже удалено. Обновите страницу.');
                $_SESSION['admin_flash'] = 'Сочетание удалено.';
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
                if ($table === 'bookings') {
                    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
                    try {
                        $bookingQuery = $pdo->prepare('SELECT id,user_id,status FROM bookings WHERE id=?');
                        $bookingQuery->execute([$recordId]);
                        $booking = $bookingQuery->fetch(PDO::FETCH_ASSOC);
                        if (!$booking) throw new RuntimeException('Запись не найдена.');
                        $previousStatus = (string)$booking['status'];
                        if ($previousStatus !== $status) {
                            $pdo->prepare('UPDATE bookings SET status=? WHERE id=?')->execute([$status,$recordId]);
                            $pdo->prepare('INSERT INTO booking_status_events(booking_id,previous_status,new_status) VALUES(?,?,?)')->execute([$recordId,$previousStatus,$status]);
                            $statusEventId = (int)$pdo->lastInsertId();
                            if (!empty($booking['user_id'])) commerce_notify_booking_status($pdo,(int)$booking['user_id'],(int)$recordId,$statusEventId,$status);
                        }
                        // Confirmation email keeps its existing unique event key. A repeated
                        // confirmation can safely retry the same outbox event without a
                        // second account notification or a duplicate message.
                        if ($status === 'confirmed') $confirmationMailId = booking_mail_queue_confirmation($pdo,(int)$recordId);
                        $pdo->commit();
                    } catch (Throwable $error) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $error;
                    }
                    if ($status === 'confirmed') {
                        $mailState = $confirmationMailId ? booking_mail_deliver($pdo,$confirmationMailId) : 'missing';
                        $_SESSION['admin_flash'] = match ($mailState) {
                            'sent' => 'Бронь подтверждена. Письмо гостю отправлено.',
                            'failed' => 'Бронь подтверждена. Email не отправлено; запись доступна для повтора.',
                            'pending' => 'Бронь подтверждена. Email в очереди; настройте SMTP и запустите отправку.',
                            default => 'Бронь подтверждена. Email гостя не указан, письмо не отправлялось.',
                        };
                    } else {
                        $_SESSION['admin_flash'] = $previousStatus === $status ? 'Этот статус брони уже установлен.' : 'Статус брони обновлён.';
                    }
                } else {
                    if ($table === 'orders') {
                        try {
                            $changed = commerce_set_order_status($pdo, (int)$recordId, $status);
                        } catch (DomainException $exception) {
                            throw new RuntimeException($exception->getMessage(), 0, $exception);
                        }
                        $_SESSION['admin_flash'] = $changed ? 'Статус заказа обновлён.' : 'Этот статус заказа уже установлен.';
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
                $deliveryFee = null;
                if (array_key_exists('delivery_fee', $_POST)) {
                    $deliveryFee = filter_var($_POST['delivery_fee'], FILTER_VALIDATE_INT);
                    if ($deliveryFee === false || $deliveryFee < 0 || $deliveryFee > 100000) {
                        throw new RuntimeException('Стоимость доставки должна быть целым числом от 0 до 100 000 ₸.');
                    }
                }
                $keys = ['site_name','meta_title','meta_description','hero_eyebrow','hero_title','hero_text','address','phone','hours','instagram','delivery_fee'];
                foreach ($keys as $key) {
                    if ($key === 'delivery_fee') {
                        if ($deliveryFee === null) continue;
                        $value = (string)$deliveryFee;
                    } else {
                        $value = trim((string)($_POST[$key] ?? ''));
                    }
                    $pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')
                        ->execute([$key, admin_unicode_slice($value, 500)]);
                }
                $_SESSION['admin_flash'] = 'Настройки сайта сохранены.';
            }
            if (isset($_SESSION['admin_flash'])) {
                $_SESSION['admin_flash_section'] = admin_post_section($action, $_POST);
                header('Location: ' . admin_post_return_url($action, $_GET, $_POST));
                exit;
            }
        } catch (Throwable $exception) {
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Не удалось сохранить изменения.';
            error_log($exception->getMessage());
        }
    }
}

$notificationTarget = (string)($_GET['notification'] ?? '');
if (!in_array($notificationTarget, ['orders','bookings','reviews','contact_messages','booking_emails'], true)) {
    $notificationTarget = '';
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['admin_id']) && $notificationTarget !== '') {
    // Notifications are global, while search is session-wide. Drop the old query
    // and use the selected notification as a real status filter below.
    unset($_SESSION['admin_search']);
}

$logged = !empty($_SESSION['admin_id']);
$notice = (string)($_SESSION['admin_flash'] ?? '');
$noticeSection = (string)($_SESSION['admin_flash_section'] ?? '');
unset($_SESSION['admin_flash']);
unset($_SESSION['admin_flash_section']);
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
$pairingWhere = admin_search_where([
    'CAST(source.id AS TEXT)','source.name','CAST(paired.id AS TEXT)','paired.name',
    "CASE dp.pairing_type WHEN 'drink' THEN 'Напиток' WHEN 'sauce' THEN 'Соус' WHEN 'dish' THEN 'Блюдо' WHEN 'dessert' THEN 'Десерт' ELSE dp.pairing_type END",
], $search);
$pairingRows = admin_rows($pdo,
    'SELECT dp.dish_id,dp.paired_dish_id,dp.pairing_type,dp.sort_order,source.name AS source_name,paired.name AS paired_name
     FROM dish_pairings dp JOIN dishes source ON source.id=dp.dish_id JOIN dishes paired ON paired.id=dp.paired_dish_id'
     . $pairingWhere[0] . ' ORDER BY source.sort_order,source.id,dp.sort_order,paired.name LIMIT 200',
    $pairingWhere[1]);
$pairingsByDish = [];
foreach ($pairingRows as $pairingRow) $pairingsByDish[(int)$pairingRow['dish_id']][] = $pairingRow;
$pairingDishOptions = $pdo->query('SELECT id,name,active FROM dishes ORDER BY active DESC,sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
$statusOptions = [
    'orders'=>['new'=>'Новый','confirmed'=>'Подтверждён','preparing'=>'Готовится','ready'=>'Готов','completed'=>'Завершён','cancelled'=>'Отменён'],
    'bookings'=>['new'=>'Новая заявка','confirmed'=>'Подтверждена','completed'=>'Завершена','cancelled'=>'Отменена'],
    'reviews'=>['pending'=>'На модерации','published'=>'Опубликован','hidden'=>'Скрыт'],
    'contact_messages'=>['new'=>'Новое','read'=>'Прочитано','archived'=>'В архиве'],
];
$recordFilterTable = (string)($_GET['filter_table'] ?? '');
$recordFilterStatus = (string)($_GET['filter_status'] ?? '');
if (!isset($statusOptions[$recordFilterTable]) || !isset($statusOptions[$recordFilterTable][$recordFilterStatus])) {
    $recordFilterTable = '';
    $recordFilterStatus = '';
}
$orderFilter = admin_search_where(['CAST(o.id AS TEXT)','o.customer_name','o.phone','o.address','o.fulfillment','o.status','CAST(o.total AS TEXT)','oi.item_name'], $search);
if ($notificationTarget === 'orders') $orderFilter = [' WHERE o.status=?', ['new']];
elseif ($recordFilterTable === 'orders') $orderFilter = admin_add_filter($orderFilter, 'o.status', $recordFilterStatus);
$bookingFilter = admin_search_where(['CAST(b.id AS TEXT)','b.guest_name','b.guest_email','b.phone','b.booking_date','b.booking_time','b.zone','b.preferred_table','b.format','b.message','b.status'], $search);
if ($notificationTarget === 'bookings') $bookingFilter = [' WHERE b.status=?', ['new']];
elseif ($notificationTarget === 'booking_emails') {
    $bookingFilter[0] .= $bookingFilter[0] === '' ? ' WHERE ' : ' AND ';
    $bookingFilter[0] .= "EXISTS (SELECT 1 FROM email_outbox attention_mail WHERE attention_mail.booking_id=b.id AND attention_mail.status IN('pending','failed'))";
}
elseif ($recordFilterTable === 'bookings') $bookingFilter = admin_add_filter($bookingFilter, 'b.status', $recordFilterStatus);
$reviewFilter = admin_search_where(['CAST(r.id AS TEXT)','r.guest_name','r.theme','r.body','CAST(r.rating AS TEXT)','r.status'], $search);
if ($notificationTarget === 'reviews') $reviewFilter = [' WHERE r.status=?', ['pending']];
elseif ($recordFilterTable === 'reviews') $reviewFilter = admin_add_filter($reviewFilter, 'r.status', $recordFilterStatus);
$messageFilter = admin_search_where(['CAST(m.id AS TEXT)','m.guest_name','m.email','m.subject','m.message','m.status'], $search);
if ($notificationTarget === 'contact_messages') $messageFilter = [' WHERE m.status=?', ['new']];
elseif ($recordFilterTable === 'contact_messages') $messageFilter = admin_add_filter($messageFilter, 'm.status', $recordFilterStatus);
$adminPageSize = 25;
$recordTotalCounts = [
    'orders'=>admin_count($pdo, 'SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id' . $orderFilter[0], $orderFilter[1]),
    'bookings'=>admin_count($pdo, 'SELECT COUNT(*) FROM bookings b' . $bookingFilter[0], $bookingFilter[1]),
    'reviews'=>admin_count($pdo, 'SELECT COUNT(*) FROM reviews r' . $reviewFilter[0], $reviewFilter[1]),
    'contact_messages'=>admin_count($pdo, 'SELECT COUNT(*) FROM contact_messages m' . $messageFilter[0], $messageFilter[1]),
];
$recordPageCounts = [];
$recordPages = [];
$recordPageOffsets = [];
foreach ($recordTotalCounts as $table=>$total) {
    $pageCount = max(1, (int)ceil($total / $adminPageSize));
    $requestedPage = filter_var($_GET['page_' . $table] ?? 1, FILTER_VALIDATE_INT);
    $recordPageCounts[$table] = $pageCount;
    $recordPages[$table] = min(max(1, $requestedPage === false ? 1 : $requestedPage), $pageCount);
    $recordPageOffsets[$table] = ($recordPages[$table] - 1) * $adminPageSize;
}
$orders = admin_rows($pdo,
    "SELECT o.*,GROUP_CONCAT(oi.item_name || ' × ' || oi.quantity,' · ') AS items_summary
     FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id" . $orderFilter[0] . '
     GROUP BY o.id ORDER BY o.id DESC LIMIT ' . $adminPageSize . ' OFFSET ' . $recordPageOffsets['orders'], $orderFilter[1]);
$bookings = admin_rows($pdo, "SELECT b.*,
    (SELECT m.id FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':request:guest' ORDER BY m.id DESC LIMIT 1) AS request_mail_id,
    (SELECT m.status FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':request:guest' ORDER BY m.id DESC LIMIT 1) AS request_mail_status,
    (SELECT m.id FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':request:restaurant' ORDER BY m.id DESC LIMIT 1) AS restaurant_mail_id,
    (SELECT m.status FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':request:restaurant' ORDER BY m.id DESC LIMIT 1) AS restaurant_mail_status,
    (SELECT m.id FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':confirmed:guest' ORDER BY m.id DESC LIMIT 1) AS confirmation_mail_id,
    (SELECT m.status FROM email_outbox m WHERE m.booking_id=b.id AND m.event_key='booking:'||b.id||':confirmed:guest' ORDER BY m.id DESC LIMIT 1) AS confirmation_mail_status
    FROM bookings b" . $bookingFilter[0] . ' ORDER BY b.booking_date DESC,b.booking_time DESC,b.id DESC LIMIT ' . $adminPageSize . ' OFFSET ' . $recordPageOffsets['bookings'], $bookingFilter[1]);
$reviews = admin_rows($pdo, 'SELECT r.* FROM reviews r' . $reviewFilter[0] . ' ORDER BY r.id DESC LIMIT ' . $adminPageSize . ' OFFSET ' . $recordPageOffsets['reviews'], $reviewFilter[1]);
$contactMessages = admin_rows($pdo, 'SELECT m.* FROM contact_messages m' . $messageFilter[0] . ' ORDER BY m.id DESC LIMIT ' . $adminPageSize . ' OFFSET ' . $recordPageOffsets['contact_messages'], $messageFilter[1]);
$collections = [
    'orders'=>['label'=>'Заказы','rows'=>$orders],
    'bookings'=>['label'=>'Бронирования','rows'=>$bookings],
    'reviews'=>['label'=>'Отзывы','rows'=>$reviews],
    'contact_messages'=>['label'=>'Сообщения','rows'=>$contactMessages],
];
$notificationCounts = [
    'orders'=>(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
    'bookings'=>(int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn(),
    'reviews'=>(int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn(),
    'contact_messages'=>(int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn(),
];
$restaurantZone = new DateTimeZone('Asia/Qyzylorda');
$utcZone = new DateTimeZone('UTC');
$localDayStart = (new DateTimeImmutable('now', $restaurantZone))->setTime(0, 0, 0);
$localDayEnd = $localDayStart->modify('+1 day');
$ordersTodayQuery = $pdo->prepare("SELECT COUNT(*) AS orders_count,COALESCE(SUM(CASE WHEN status<>'cancelled' THEN total ELSE 0 END),0) AS order_volume FROM orders WHERE created_at>=? AND created_at<?");
$ordersTodayQuery->execute([$localDayStart->setTimezone($utcZone)->format('Y-m-d H:i:s'),$localDayEnd->setTimezone($utcZone)->format('Y-m-d H:i:s')]);
$ordersToday = $ordersTodayQuery->fetch(PDO::FETCH_ASSOC) ?: ['orders_count'=>0,'order_volume'=>0];
$bookingsTodayQuery = $pdo->prepare("SELECT COUNT(*) AS bookings_count,COALESCE(SUM(CASE WHEN status='new' THEN 1 ELSE 0 END),0) AS new_count FROM bookings WHERE booking_date=?");
$bookingsTodayQuery->execute([$localDayStart->format('Y-m-d')]);
$bookingsToday = $bookingsTodayQuery->fetch(PDO::FETCH_ASSOC) ?: ['bookings_count'=>0,'new_count'=>0];
$bookingMailQueue = $pdo->query("SELECT COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END),0) AS pending_count,COALESCE(SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END),0) AS failed_count FROM email_outbox WHERE booking_id IS NOT NULL")->fetch(PDO::FETCH_ASSOC) ?: ['pending_count'=>0,'failed_count'=>0];
$notificationLabels = ['orders'=>'Новые заказы','bookings'=>'Новые брони','reviews'=>'Отзывы на проверке','contact_messages'=>'Новые сообщения'];
$bookingMailState = booking_mail_configuration_state();
$bookingNotifyConfigured = filter_var(trim((string)booking_mail_env('TOKYO_BOOKING_NOTIFY_EMAIL', '')), FILTER_VALIDATE_EMAIL) !== false;
$bookingMailReadiness = match ($bookingMailState) {
    'ready' => ['class'=>'ready', 'title'=>'Параметры SMTP заполнены', 'text'=>'Письмо о получении заявки ставится в очередь сразу; подтверждение брони отправляется после ручного решения администратора. Фактический статус смотрите в карточке бронирования.'],
    'invalid' => ['class'=>'invalid', 'title'=>'Настройки SMTP неполные или некорректны', 'text'=>'Заявки сохраняются, но письмо не будет отправлено, пока настройки сервера не исправлены.'],
    default => ['class'=>'missing', 'title'=>'SMTP не настроен', 'text'=>'Заявки сохраняются, но письма останутся в очереди. Инструкция по настройке находится в документации.'],
};

$exportTable = (string)($_GET['export'] ?? '');
if ($exportTable !== '') {
    $exportDefinitions = [
        'orders' => [
            'headers'=>['№ заказа','Создан','Статус','Гость','Телефон','Получение','Адрес','Состав','Сумма, ₸','Комментарий'],
            'columns'=>['id','created_at','status','customer_name','phone','fulfillment_label','address','items_summary','total','comment'],
            'sql'=>"SELECT o.id,o.created_at,o.status,o.customer_name,o.phone,CASE WHEN o.fulfillment='pickup' THEN 'Самовывоз' ELSE 'Доставка' END AS fulfillment_label,o.address,GROUP_CONCAT(oi.item_name || ' × ' || oi.quantity,' · ') AS items_summary,o.total,o.comment FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id" . $orderFilter[0] . ' GROUP BY o.id ORDER BY o.created_at DESC,o.id DESC',
            'params'=>$orderFilter[1],
        ],
        'bookings' => [
            'headers'=>['№ брони','Создана','Статус','Гость','Email','Телефон','Дата','Время','Гостей','Зона','Стол предпочтения','Формат','Комментарий'],
            'columns'=>['id','created_at','status','guest_name','guest_email','phone','booking_date','booking_time','guest_count','zone','preferred_table','format','message'],
            'sql'=>'SELECT b.id,b.created_at,b.status,b.guest_name,b.guest_email,b.phone,b.booking_date,b.booking_time,b.guest_count,b.zone,b.preferred_table,b.format,b.message FROM bookings b' . $bookingFilter[0] . ' ORDER BY b.booking_date DESC,b.booking_time DESC,b.id DESC',
            'params'=>$bookingFilter[1],
        ],
        'reviews' => [
            'headers'=>['№ отзыва','Создан','Статус','Гость','Оценка','Тема','Текст'],
            'columns'=>['id','created_at','status','guest_name','rating','theme','body'],
            'sql'=>'SELECT r.id,r.created_at,r.status,r.guest_name,r.rating,r.theme,r.body FROM reviews r' . $reviewFilter[0] . ' ORDER BY r.id DESC',
            'params'=>$reviewFilter[1],
        ],
        'contact_messages' => [
            'headers'=>['№ обращения','Создано','Статус','Гость','Email','Тема','Сообщение'],
            'columns'=>['id','created_at','status','guest_name','email','subject','message'],
            'sql'=>'SELECT m.id,m.created_at,m.status,m.guest_name,m.email,m.subject,m.message FROM contact_messages m' . $messageFilter[0] . ' ORDER BY m.id DESC',
            'params'=>$messageFilter[1],
        ],
    ];
    if (!isset($exportDefinitions[$exportTable])) {
        http_response_code(400);
        exit('Неизвестный формат экспорта.');
    }
    $export = $exportDefinitions[$exportTable];
    $exportQuery = $pdo->prepare($export['sql']);
    $exportQuery->execute($export['params']);
    admin_download_csv('tokyo-' . $exportTable . '-' . $localDayStart->format('Ymd') . '.csv', $export['headers'], $export['columns'], $exportQuery);
}
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
  <?php if ($notice !== '' && $noticeSection === ''): ?><p class="form-status success admin-notice" role="status"><?=e($notice)?></p><?php endif ?>
  <?php if ($error !== ''): ?><p class="form-status error admin-notice" role="alert"><?=e($error)?></p><?php endif ?>

  <nav class="admin-jump-nav" aria-label="Разделы панели">
    <a href="#search">Поиск</a><a href="#notifications">Уведомления</a><a href="#menu">Меню</a><a href="#pairings">Сочетания</a>
    <?php foreach ([['orders','Заказы'],['bookings','Брони'],['reviews','Отзывы'],['contact_messages','Сообщения']] as [$navKey,$navLabel]): $navCount=$notificationCounts[$navKey]??0; ?>
      <a href="#<?=$navKey?>" aria-label="<?=$navLabel?>, новых: <?=$navCount?>"><?=$navLabel?><span class="admin-jump-count<?=$navCount>0?' has-unread':''?>" aria-hidden="true"><?=$navCount?></span></a>
    <?php endforeach ?>
    <a href="#settings">Настройки</a>
  </nav>

  <section class="admin-kpi-grid" aria-label="Оперативная сводка · <?=e($localDayStart->format('d.m.Y'))?>, Asia/Qyzylorda">
    <article class="admin-kpi"><span>Заказы сегодня</span><strong><?= (int)$ordersToday['orders_count']?></strong><p><?=number_format((int)$ordersToday['order_volume'],0,'.',' ')?> ₸ · без отменённых</p><a href="#orders">Открыть заказы ↗</a></article>
    <article class="admin-kpi"><span>Брони сегодня</span><strong><?= (int)$bookingsToday['bookings_count']?></strong><p><?= (int)$bookingsToday['new_count']?> ждут подтверждения</p><a href="#bookings">Открыть брони ↗</a></article>
    <article class="admin-kpi<?= (int)$bookingMailQueue['failed_count'] > 0 ? ' is-alert' : ''?>"><span>Почта по броням</span><strong><?= (int)$bookingMailQueue['pending_count'] + (int)$bookingMailQueue['failed_count']?></strong><p><?= (int)$bookingMailQueue['pending_count']?> в очереди · <?= (int)$bookingMailQueue['failed_count']?> с ошибкой</p><a href="?notification=booking_emails#bookings">Открыть письма для обработки ↗</a></article>
  </section>

  <section class="admin-card" id="search" aria-labelledby="admin-search-title">
    <h2 id="admin-search-title">Поиск по панели</h2>
    <p class="admin-muted">Ищите блюда, заказы, бронирования, отзывы и обращения по имени, телефону, тексту или номеру записи.</p>
    <form method="post" class="admin-search-form" role="search">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="action" value="admin-search">
      <label class="sr-only" for="admin-search-q">Поиск по панели</label>
      <input id="admin-search-q" name="q" type="search" maxlength="120" value="<?=e($search)?>" placeholder="Например: нигири, имя гостя, телефон…" aria-describedby="admin-search-hint">
      <button class="button" type="submit">Найти</button>
      <?php if ($search !== ''): ?>
        <button class="button secondary" type="submit" name="clear_search" value="1">Сбросить</button>
      <?php endif ?>
    </form>
    <p class="admin-search-hint" id="admin-search-hint">Быстрый доступ: нажмите <kbd>/</kbd> вне полей ввода.</p>
    <?php if ($search !== ''): ?><p class="admin-search-summary" aria-live="polite">Результаты по запросу «<?=e($search)?>». Поиск охватывает все записи; каждый раздел показывает до 25 результатов на странице.</p><?php endif ?>
  </section>

  <section class="admin-card" id="notifications" aria-labelledby="admin-notifications-title">
    <h2 id="admin-notifications-title">Уведомления</h2>
    <p class="admin-muted">Счётчики показывают новые записи в базе и обновляются при открытии панели. SMS не подключены. Email подтверждается только после ручного решения администратора.</p>
    <div class="admin-mail-readiness is-<?=e($bookingMailReadiness['class'])?>" role="status" data-smtp-state="<?=e($bookingMailState)?>">
      <strong><?=e($bookingMailReadiness['title'])?></strong>
      <p><?=e($bookingMailReadiness['text'])?></p>
      <p>Уведомление ресторану: <b><?=$bookingNotifyConfigured ? 'адрес настроен' : 'адрес не настроен'?></b>. Адрес и SMTP-пароль панель не показывает. <a href="project-docs/booking-email.md">Как настроить почту</a></p>
    </div>
    <div class="admin-notification-grid">
      <?php foreach ($notificationCounts as $key=>$count): ?>
        <a class="admin-notification" href="?notification=<?=e($key)?>#<?=$key?>"<?=$notificationTarget === $key ? ' aria-current="page"' : ''?>>
          <span><?=e($notificationLabels[$key])?></span><strong class="<?=$count > 0 ? 'has-unread' : ''?>"><?=$count?></strong>
        </a>
      <?php endforeach ?>
    </div>
    <?php if ($notificationTarget !== ''): ?>
      <p class="admin-notification-filter" role="status">
        Показаны только <?=match ($notificationTarget) {
          'reviews' => 'отзывы на модерации',
          'bookings' => 'новые заявки на бронь',
          'contact_messages' => 'новые обращения',
          'booking_emails' => 'брони с письмами в очереди или ошибкой отправки',
          default => 'новые заказы',
        }?>.
        <a href="admin.php#<?=$notificationTarget === 'booking_emails' ? 'bookings' : $notificationTarget?>">Показать последние записи</a>
      </p>
    <?php endif ?>
  </section>

  <section class="admin-card" id="menu" aria-labelledby="admin-menu-title">
    <?php if ($notice !== '' && $noticeSection === 'menu'): ?><p class="form-status success admin-notice" role="status"><?=e($notice)?></p><?php endif ?>
    <h2 id="admin-menu-title">Позиции меню <span class="admin-result-count"><?=$search !== '' ? count($items) . ' найдено' : count($items) . ' показано'?></span></h2>
    <form method="post" class="admin-form admin-create-form">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="action" value="dish-save">
      <label>Название<input name="name" required maxlength="120"></label>
      <label>Категория<select name="category" required><?php foreach ($cats as $category): ?><option value="<?=e($category['slug'])?>"><?=e($category['name'])?></option><?php endforeach ?></select></label>
      <label>Цена, ₸<input name="price" type="number" min="1" max="500000" required></label>
      <label>Вес / объём<input name="weight" maxlength="30"></label>
      <label>Порядок<input name="sort_order" type="number" value="0"></label>
      <label class="admin-span-all">Фото · локальный WebP или HTTPS Unsplash
        <input name="image_url" type="text" required placeholder="assets/food/03-nigiri-salmon.webp" data-admin-image-source>
        <span class="admin-image-preview" aria-live="polite"><img alt="" data-admin-image-preview hidden><span data-admin-image-hint>Предпросмотр появится после ввода адреса фото.</span></span>
      </label>
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
              <label class="admin-span-all">Фото · локальный WebP или HTTPS Unsplash
                <input name="image_url" value="<?=e($dish['image_url'])?>" required data-admin-image-source>
                <span class="admin-image-preview" aria-live="polite"><img src="<?=e($dish['image_url'])?>" alt="" data-admin-image-preview hidden><span data-admin-image-hint>Загружаем предпросмотр…</span></span>
              </label>
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

  <section class="admin-card" id="pairings" aria-labelledby="admin-pairings-title">
    <?php if ($notice !== '' && $noticeSection === 'pairings'): ?><p class="form-status success admin-notice" role="status"><?=e($notice)?></p><?php endif ?>
    <h2 id="admin-pairings-title">Гастрономические сочетания <span class="admin-result-count"><?=$search !== '' ? count($pairingRows) . ' найдено' : count($pairingRows) . ' настроено'?></span></h2>
    <p class="admin-muted">Связанные активные блюда появятся в рекомендациях на страницах товаров. Сочетания доступны гостям только вместе с блюдами, которые разрешено показывать в их режиме доступа.</p>
    <form method="post" class="admin-form admin-create-form admin-pairing-create">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="action" value="pairing-save">
      <label>Основное блюдо<select name="dish_id" required><?php foreach ($pairingDishOptions as $option): ?><option value="<?=(int)$option['id']?>"><?=e($option['name'])?><?=$option['active'] ? '' : ' · скрыто'?></option><?php endforeach ?></select></label>
      <label>Рекомендуемое блюдо<select name="paired_dish_id" required><?php foreach ($pairingDishOptions as $option): ?><option value="<?=(int)$option['id']?>"><?=e($option['name'])?><?=$option['active'] ? '' : ' · скрыто'?></option><?php endforeach ?></select></label>
      <label>Тип сочетания<select name="pairing_type" required><?php foreach ($adminPairingTypes as $key=>$label): ?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach ?></select></label>
      <label>Порядок показа<input type="number" name="sort_order" value="10" min="-10000" max="10000"></label>
      <button class="button" type="submit">Добавить сочетание</button>
    </form>
    <?php if (!$pairingsByDish): ?><p class="admin-empty">Сочетания пока не настроены.</p><?php endif ?>
    <div class="admin-pairing-groups">
      <?php foreach ($pairingsByDish as $sourceId=>$dishPairings): ?>
        <details class="admin-pairing-group">
          <summary><?=e($dishPairings[0]['source_name'])?><span><?=count($dishPairings)?> сочет.</span></summary>
          <div class="admin-pairing-rows">
            <?php foreach ($dishPairings as $pairing): ?>
              <article class="admin-record admin-pairing-row">
                <h3><?=e($pairing['source_name'])?> <span aria-hidden="true">→</span> <?=e($pairing['paired_name'])?></h3>
                <form method="post" class="admin-form admin-edit-form">
                  <input type="hidden" name="csrf" value="<?=e(csrf())?>">
                  <input type="hidden" name="action" value="pairing-save">
                  <input type="hidden" name="dish_id" value="<?=(int)$pairing['dish_id']?>">
                  <input type="hidden" name="paired_dish_id" value="<?=(int)$pairing['paired_dish_id']?>">
                  <input type="hidden" name="old_type" value="<?=e($pairing['pairing_type'])?>">
                  <label>Тип сочетания<select name="pairing_type"><?php foreach ($adminPairingTypes as $key=>$label): ?><option value="<?=e($key)?>" <?=$pairing['pairing_type'] === $key ? 'selected' : ''?>><?=e($label)?></option><?php endforeach ?></select></label>
                  <label>Порядок<input type="number" name="sort_order" value="<?=(int)$pairing['sort_order']?>" min="-10000" max="10000"></label>
                  <button class="button secondary" type="submit">Сохранить</button>
                </form>
                <form method="post" onsubmit="return confirm('Удалить это сочетание?')">
                  <input type="hidden" name="csrf" value="<?=e(csrf())?>">
                  <input type="hidden" name="action" value="pairing-delete">
                  <input type="hidden" name="dish_id" value="<?=(int)$pairing['dish_id']?>">
                  <input type="hidden" name="paired_dish_id" value="<?=(int)$pairing['paired_dish_id']?>">
                  <input type="hidden" name="pairing_type" value="<?=e($pairing['pairing_type'])?>">
                  <button class="admin-danger" type="submit">Удалить</button>
                </form>
              </article>
            <?php endforeach ?>
          </div>
        </details>
      <?php endforeach ?>
    </div>
  </section>

  <?php foreach ($collections as $table=>$collection): ?>
  <section class="admin-card" id="<?=$table?>" aria-labelledby="admin-<?=$table?>-title">
    <?php if ($notice !== '' && $noticeSection === $table): ?><p class="form-status success admin-notice" role="status"><?=e($notice)?></p><?php endif ?>
    <h2 id="admin-<?=$table?>-title"><?=e($collection['label'])?> <span class="admin-result-count"><?=number_format($recordTotalCounts[$table], 0, '.', ' ')?> записей · <?=$recordPages[$table]?> из <?=$recordPageCounts[$table]?> страниц</span></h2>
    <?php $selectedRecordStatus = $recordFilterTable === $table ? $recordFilterStatus : ($notificationTarget === $table ? 'new' : ''); ?>
    <form method="get" action="admin.php#<?=$table?>" class="admin-record-filter" role="search">
      <input type="hidden" name="filter_table" value="<?=$table?>">
      <label for="filter-status-<?=$table?>">Фильтр по статусу</label>
      <select id="filter-status-<?=$table?>" name="filter_status">
        <option value="">Все статусы</option>
        <?php foreach ($statusOptions[$table] as $statusValue=>$statusLabel): ?>
          <option value="<?=e($statusValue)?>" <?=$selectedRecordStatus === $statusValue ? 'selected' : ''?>><?=e($statusLabel)?></option>
        <?php endforeach ?>
      </select>
      <button class="button secondary" type="submit">Показать</button>
      <?php if ($recordFilterTable === $table): ?><a class="admin-filter-reset" href="admin.php#<?=$table?>">Сбросить фильтр</a><?php endif ?>
    </form>
    <p class="admin-export-action"><a class="button secondary" href="<?=e(admin_export_url($table,$notificationTarget,$recordFilterTable,$recordFilterStatus))?>" aria-label="Скачать все найденные записи раздела «<?=e($collection['label'])?>» в CSV">Скачать CSV · все <?=number_format($recordTotalCounts[$table],0,'.',' ')?> записей</a></p>
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
            <?php foreach ([['Заявка гостю',$row['request_mail_id']??null,$row['request_mail_status']??null],['Уведомление ресторану',$row['restaurant_mail_id']??null,$row['restaurant_mail_status']??null],['Подтверждение гостю',$row['confirmation_mail_id']??null,$row['confirmation_mail_status']??null]] as [$mailLabel,$mailId,$mailStatus]): if ($mailStatus !== null): ?>
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
      <?php if (!$collection['rows']): ?><p class="admin-empty"><?php if ($notificationTarget === $table): ?>Новых записей сейчас нет.<?php elseif ($recordFilterTable === $table): ?>Записей с выбранным статусом<?= $search !== '' ? ' и поисковым запросом «'.e($search).'»' : '' ?> нет.<?php else: ?>Записей пока нет<?php if ($search !== ''): ?> по запросу «<?=e($search)?>»<?php endif ?>.<?php endif ?></p><?php endif ?>
    </div>
    <?php if ($recordPageCounts[$table] > 1): ?>
      <nav class="admin-pagination" aria-label="Страницы раздела «<?=e($collection['label'])?>»">
        <span>Показано <?=count($collection['rows'])?> из <?=number_format($recordTotalCounts[$table], 0, '.', ' ')?> записей</span>
        <div class="admin-pagination-links">
          <?php if ($recordPages[$table] > 1): ?><a class="button secondary" href="<?=e(admin_page_url($table, $recordPages[$table] - 1, $notificationTarget, $recordFilterTable, $recordFilterStatus))?>" rel="prev">← Назад</a><?php endif ?>
          <?php if ($recordPages[$table] < $recordPageCounts[$table]): ?><a class="button secondary" href="<?=e(admin_page_url($table, $recordPages[$table] + 1, $notificationTarget, $recordFilterTable, $recordFilterStatus))?>" rel="next">Далее →</a><?php endif ?>
        </div>
      </nav>
    <?php endif ?>
  </section>
  <?php endforeach ?>

  <section class="admin-card" id="settings" aria-labelledby="admin-settings-title">
    <?php if ($notice !== '' && $noticeSection === 'settings'): ?><p class="form-status success admin-notice" role="status"><?=e($notice)?></p><?php endif ?>
    <h2 id="admin-settings-title">Контакты и SEO</h2>
    <form method="post" class="admin-form admin-settings-form">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="action" value="settings">
      <?php foreach (['site_name'=>'Название сайта','meta_title'=>'SEO title','meta_description'=>'SEO description','hero_eyebrow'=>'Надзаголовок первого экрана','hero_title'=>'Заголовок первого экрана','hero_text'=>'Текст первого экрана','address'=>'Адрес','phone'=>'Телефон','hours'=>'График','instagram'=>'Instagram URL','delivery_fee'=>'Стоимость доставки, ₸'] as $key=>$label): ?>
        <label><?=$label?>
          <?php if (in_array($key, ['meta_description','hero_text'], true)): ?>
            <textarea name="<?=e($key)?>" maxlength="500" rows="3"><?=e(setting($key))?></textarea>
          <?php elseif ($key === 'delivery_fee'): ?>
            <input type="number" name="delivery_fee" min="0" max="100000" step="1" value="<?=e(setting('delivery_fee','0'))?>">
            <small>0 — доставка бесплатна; скидка «Бесплатная доставка» применяется только при тарифе выше нуля.</small>
          <?php else: ?>
            <input name="<?=e($key)?>" value="<?=e(setting($key))?>" maxlength="500">
          <?php endif ?>
        </label>
      <?php endforeach ?>
      <button class="button" type="submit">Сохранить настройки</button>
    </form>
  </section>
  <p class="admin-demo-note">Учебная панель PHP + SQLite. Бронь считается подтверждённой только после действия администратора. Email отправляется только при настроенном SMTP; GitHub Pages не отправляет письма. Реальные платежи и SMS не подключены.</p>
</main>
<?php endif; ?>
<?php if (!empty($_SESSION['admin_id'])): ?><script src="admin-dashboard.js" defer></script><script src="admin-form-guard.js" defer></script><?php endif ?>
<?php footer_html(); ?>
