<?php
declare(strict_types=1);

/**
 * Account-scoped notification helpers. The account route must call check_csrf()
 * before mark-read actions; ownership is enforced again in every SQL write.
 */
function commerce_create_account_notification(
    PDO $pdo,
    int $userId,
    string $eventKey,
    string $notificationType,
    string $title,
    string $message,
    string $targetUrl
): void {
    if ($userId < 1 || $eventKey === '' || strlen($eventKey) > 160) throw new InvalidArgumentException('Invalid account notification event.');
    if (!in_array($notificationType, ['order_status', 'booking_status'], true)) throw new InvalidArgumentException('Invalid account notification type.');
    if (!in_array($targetUrl, ['account.php#account-orders', 'account.php#account-bookings'], true)) throw new InvalidArgumentException('Invalid account notification destination.');
    $title = trim($title);
    $message = trim($message);
    $titleLength = preg_match_all('/./us', $title, $ignoredTitleMatches);
    $messageLength = preg_match_all('/./us', $message, $ignoredMessageMatches);
    if ($titleLength === false || $title === '' || $titleLength > 120 || $messageLength === false || $message === '' || $messageLength > 600) {
        throw new InvalidArgumentException('Account notification text is outside its allowed size.');
    }
    $insert = $pdo->prepare('INSERT OR IGNORE INTO user_notifications(user_id,event_key,notification_type,title,message,target_url) VALUES(?,?,?,?,?,?)');
    $insert->execute([$userId, $eventKey, $notificationType, $title, $message, $targetUrl]);
}

function commerce_notify_order_status(PDO $pdo, int $userId, int $orderId, int $statusEventId, string $status): void
{
    $labels = [
        'new' => 'новый',
        'confirmed' => 'подтверждён',
        'preparing' => 'готовится',
        'ready' => 'готов к выдаче',
        'completed' => 'выполнен',
        'cancelled' => 'отменён',
    ];
    if (!isset($labels[$status])) throw new InvalidArgumentException('Unknown order status notification.');
    $titles = [
        'confirmed' => 'Заказ подтверждён',
        'preparing' => 'Заказ готовится',
        'ready' => 'Заказ готов к выдаче',
        'completed' => 'Заказ выполнен',
        'cancelled' => 'Заказ отменён',
        'new' => 'Статус заказа обновлён',
    ];
    commerce_create_account_notification(
        $pdo,
        $userId,
        'order-status:' . $statusEventId,
        'order_status',
        $titles[$status],
        'Заказ №' . $orderId . ': статус изменён на «' . $labels[$status] . '».',
        'account.php#account-orders'
    );
}

function commerce_notify_booking_status(PDO $pdo, int $userId, int $bookingId, int $statusEventId, string $status): void
{
    $copy = [
        'new' => ['Заявка получена', 'заявка получена'],
        'confirmed' => ['Бронь подтверждена', 'бронь подтверждена'],
        'completed' => ['Визит завершён', 'визит завершён'],
        'cancelled' => ['Бронь отменена', 'бронь отменена'],
    ];
    if (!isset($copy[$status])) throw new InvalidArgumentException('Unknown booking status notification.');
    [$title, $label] = $copy[$status];
    commerce_create_account_notification(
        $pdo,
        $userId,
        'booking-status:' . $statusEventId,
        'booking_status',
        $title,
        'Бронирование №' . $bookingId . ': ' . $label . '.',
        'account.php#account-bookings'
    );
}

/** @return list<array{id:int,title:string,message:string,target_url:string,created_at:string,read_at:?string,notification_type:string}> */
function commerce_account_notifications(PDO $pdo, int $userId, int $limit = 30): array
{
    if ($userId < 1) return [];
    $limit = max(1, min(100, $limit));
    $query = $pdo->prepare('SELECT id,title,message,target_url,created_at,read_at,notification_type FROM user_notifications WHERE user_id=? ORDER BY id DESC LIMIT ?');
    $query->bindValue(1, $userId, PDO::PARAM_INT);
    $query->bindValue(2, $limit, PDO::PARAM_INT);
    $query->execute();
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mark one notification read only for its owner. Returns false for missing,
 * invalid, or foreign IDs; repeated owner requests remain successful.
 */
function commerce_mark_account_notification_read(PDO $pdo, int $userId, int $notificationId): bool
{
    if ($userId < 1 || $notificationId < 1) return false;
    $update = $pdo->prepare('UPDATE user_notifications SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP) WHERE id=? AND user_id=?');
    $update->execute([$notificationId, $userId]);
    return $update->rowCount() === 1;
}
