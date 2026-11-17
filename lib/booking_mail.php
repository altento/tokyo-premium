<?php
declare(strict_types=1);

/** Optional SMTP outbox for booking receipts and administrator confirmations.
 * Credentials are read only from the PHP process environment. Never log config.
 */
function booking_mail_env(string $key, ?string $fallback = null): ?string
{
    $value = getenv($key);
    return $value === false ? $fallback : trim((string)$value);
}

function booking_mail_smtp_config(): ?array
{
    $host = booking_mail_env('TOKYO_SMTP_HOST', '');
    $from = booking_mail_env('TOKYO_MAIL_FROM', '');
    $username = booking_mail_env('TOKYO_SMTP_USER', '');
    $password = booking_mail_env('TOKYO_SMTP_PASSWORD', '');
    $rawPort = booking_mail_env('TOKYO_SMTP_PORT', '');
    $rawEncryption = strtolower((string)booking_mail_env('TOKYO_SMTP_ENCRYPTION', booking_mail_env('TOKYO_SMTP_SECURE', 'starttls')));
    $encryption = $rawEncryption === 'tls' ? 'starttls' : $rawEncryption;
    $anySetting = $host !== '' || $from !== '' || $username !== '' || $password !== '' || $rawPort !== '';
    if (!$anySetting) return null;

    $port = $rawPort === '' ? ($encryption === 'ssl' ? 465 : ($encryption === 'none' ? 25 : 587)) : filter_var($rawPort, FILTER_VALIDATE_INT);
    $fromName = preg_replace('/[\r\n\x00-\x1f\x7f]/', '', (string)booking_mail_env('TOKYO_MAIL_FROM_NAME', 'Tokyo Premium')) ?? 'Tokyo Premium';
    if ($host === '' || strlen($host) > 253 || !preg_match('/^[a-zA-Z0-9.-]+$/', $host)
        || !is_int($port) || $port < 1 || $port > 65535
        || !in_array($encryption, ['starttls', 'ssl', 'none'], true)
        || !filter_var($from, FILTER_VALIDATE_EMAIL) || strlen($from) > 190
        || (($username === '') !== ($password === ''))
        || strlen($username) > 250 || strlen($password) > 500) {
        throw new RuntimeException('SMTP configuration is incomplete or invalid.');
    }
    if ($encryption === 'none') {
        $isLoopback = $host === 'localhost' || (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (ip2long($host) & 0xff000000) === 0x7f000000);
        if (!$isLoopback || $username !== '') throw new RuntimeException('Unencrypted SMTP is allowed only on loopback without authentication.');
    }
    return ['host'=>$host,'port'=>$port,'encryption'=>$encryption,'username'=>$username,'password'=>$password,'from'=>$from,'from_name'=>$fromName];
}

function booking_mail_configuration_state(): string
{
    try { return booking_mail_smtp_config() === null ? 'not_configured' : 'ready'; }
    catch (Throwable) { return 'invalid'; }
}

function booking_mail_queue(PDO $pdo, int $bookingId, string $eventKey, string $recipient, string $subject, string $body): int
{
    if ($bookingId < 1 || $eventKey === '' || strlen($eventKey) > 180 || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || strlen($recipient) > 190) {
        throw new InvalidArgumentException('Booking email recipient or event is invalid.');
    }
    $subject = trim(preg_replace('/[\r\n\x00-\x1f\x7f]/', ' ', $subject) ?? '');
    if ($subject === '' || strlen($subject) > 220 || strlen($body) > 20000) throw new InvalidArgumentException('Booking email content is invalid.');
    $insert = $pdo->prepare("INSERT OR IGNORE INTO email_outbox(event_key,booking_id,recipient,subject,body) VALUES(?,?,?,?,?)");
    $insert->execute([$eventKey,$bookingId,strtolower($recipient),$subject,$body]);
    $query = $pdo->prepare('SELECT id FROM email_outbox WHERE event_key=?');
    $query->execute([$eventKey]);
    $id = $query->fetchColumn();
    if ($id === false) throw new RuntimeException('Could not queue booking email.');
    return (int)$id;
}

function booking_mail_text_value(?string $value): string
{
    $value = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', (string)$value) ?? '';
    return trim($value);
}

function booking_mail_queue_request(PDO $pdo, int $bookingId): array
{
    $query = $pdo->prepare('SELECT guest_name,guest_email,phone,guest_count,booking_date,booking_time,zone,preferred_table,format,message FROM bookings WHERE id=?');
    $query->execute([$bookingId]);
    $booking = $query->fetch();
    if (!$booking) throw new RuntimeException('Booking was not found for email notification.');
    $guestName = booking_mail_text_value((string)$booking['guest_name']);
    $details = "Дата: {$booking['booking_date']}\nВремя: {$booking['booking_time']} (Asia/Qyzylorda)\nГостей: {$booking['guest_count']}\nЗона: " . (booking_mail_text_value((string)$booking['zone']) ?: 'не указана') . "\nСтол-пожелание: " . (booking_mail_text_value((string)$booking['preferred_table']) ?: 'не выбран') . "\nФормат: " . (booking_mail_text_value((string)$booking['format']) ?: 'обычный ужин') . "\nПожелания: " . (booking_mail_text_value((string)$booking['message']) ?: '—');
    $ids = [];
    $guestEmail = trim((string)$booking['guest_email']);
    if ($guestEmail !== '') {
        $body = "Здравствуйте, {$guestName}!\n\nМы получили вашу заявку на посещение Tokyo Premium.\n\n{$details}\n\nСтатус: заявка получена, бронь пока не подтверждена. Сотрудник должен отдельно подтвердить возможность визита. Это учебная концепция; столик не резервируется автоматически.\n";
        $ids['guest_request'] = booking_mail_queue($pdo,$bookingId,"booking:{$bookingId}:request:guest",$guestEmail,"Заявка на бронирование Tokyo Premium №{$bookingId}",$body);
    }
    $notify = trim((string)booking_mail_env('TOKYO_BOOKING_NOTIFY_EMAIL', ''));
    if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL) && strcasecmp($notify, $guestEmail) !== 0) {
        $body = "Новая учебная заявка на бронирование Tokyo Premium.\n\nГость: {$guestName}\nEmail: {$guestEmail}\nТелефон: " . booking_mail_text_value((string)$booking['phone']) . "\n{$details}\n\nЗаявка не является подтверждённой бронью. Обработайте её в административной панели.\n";
        $ids['restaurant_request'] = booking_mail_queue($pdo,$bookingId,"booking:{$bookingId}:request:restaurant",$notify,"Новая заявка на бронь №{$bookingId}",$body);
    }
    return $ids;
}

function booking_mail_queue_confirmation(PDO $pdo, int $bookingId): ?int
{
    $query = $pdo->prepare('SELECT guest_name,guest_email,phone,guest_count,booking_date,booking_time,zone,preferred_table,format FROM bookings WHERE id=?');
    $query->execute([$bookingId]);
    $booking = $query->fetch();
    if (!$booking || trim((string)$booking['guest_email']) === '') return null;
    $name = booking_mail_text_value((string)$booking['guest_name']);
    $body = "Здравствуйте, {$name}!\n\nАдминистратор подтвердил вашу бронь Tokyo Premium.\n\nДата: {$booking['booking_date']}\nВремя: {$booking['booking_time']} (Asia/Qyzylorda)\nГостей: {$booking['guest_count']}\nЗона: " . (booking_mail_text_value((string)$booking['zone']) ?: 'не указана') . "\nСтол-пожелание: " . (booking_mail_text_value((string)$booking['preferred_table']) ?: 'не выбран') . "\nФормат: " . (booking_mail_text_value((string)$booking['format']) ?: 'обычный ужин') . "\n\nЭто подтверждение учебной концепции Tokyo Premium. Условия и контакты проекта вымышлены.\n";
    return booking_mail_queue($pdo,$bookingId,"booking:{$bookingId}:confirmed:guest",(string)$booking['guest_email'],"Бронь подтверждена · Tokyo Premium №{$bookingId}",$body);
}

function booking_mail_read_response($socket): array
{
    $lines = [];
    $code = 0;
    for ($i=0; $i<20; $i++) {
        $line = fgets($socket, 2048);
        if ($line === false) throw new RuntimeException('SMTP server closed the connection unexpectedly.');
        if (!preg_match('/^(\d{3})([- ])(.*)$/', rtrim($line, "\r\n"), $match)) throw new RuntimeException('SMTP server returned an invalid response.');
        $code = (int)$match[1];
        $lines[] = $match[3];
        if ($match[2] === ' ') return [$code, implode(' ', $lines)];
    }
    throw new RuntimeException('SMTP server returned too many response lines.');
}

function booking_mail_expect($socket, array $allowed): void
{
    [$code] = booking_mail_read_response($socket);
    if (!in_array($code, $allowed, true)) throw new RuntimeException('SMTP server rejected a mail operation (code ' . $code . ').');
}

function booking_mail_command($socket, string $command, array $allowed): void
{
    if (fwrite($socket, $command . "\r\n") !== strlen($command) + 2) throw new RuntimeException('Could not write to the SMTP server.');
    booking_mail_expect($socket, $allowed);
}

function booking_mail_smtp_send(array $config, string $recipient, string $subject, string $body): void
{
    $target = ($config['encryption'] === 'ssl' ? 'ssl://' : 'tcp://') . $config['host'] . ':' . $config['port'];
    $context = stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$config['host'],'SNI_enabled'=>true]]);
    $socket = @stream_socket_client($target,$errorCode,$errorMessage,12,STREAM_CLIENT_CONNECT,$context);
    if (!is_resource($socket)) throw new RuntimeException('Could not connect to the SMTP server.');
    stream_set_timeout($socket, 12);
    try {
        booking_mail_expect($socket,[220]);
        booking_mail_command($socket,'EHLO tokyo-premium.local',[250]);
        if ($config['encryption'] === 'starttls') {
            booking_mail_command($socket,'STARTTLS',[220]);
            $crypto = @stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) throw new RuntimeException('Could not establish verified SMTP TLS.');
            booking_mail_command($socket,'EHLO tokyo-premium.local',[250]);
        }
        if ($config['username'] !== '') {
            booking_mail_command($socket,'AUTH LOGIN',[334]);
            booking_mail_command($socket,base64_encode($config['username']),[334]);
            booking_mail_command($socket,base64_encode($config['password']),[235]);
        }
        booking_mail_command($socket,'MAIL FROM:<'.$config['from'].'>',[250]);
        booking_mail_command($socket,'RCPT TO:<'.$recipient.'>',[250,251]);
        booking_mail_command($socket,'DATA',[354]);
        $encodedName = '=?UTF-8?B?' . base64_encode($config['from_name']) . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $message = 'From: ' . $encodedName . ' <' . $config['from'] . ">\r\n"
            . 'To: <' . $recipient . ">\r\n"
            . 'Subject: ' . $encodedSubject . "\r\n"
            . 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@tokyo-premium.local>' . "\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . rtrim(chunk_split(base64_encode(str_replace(["\r\n","\r"],"\n",$body)),76,"\r\n"),"\r\n");
        if (fwrite($socket,$message . "\r\n.\r\n") !== strlen($message) + 5) throw new RuntimeException('Could not write the booking email.');
        booking_mail_expect($socket,[250]);
        if (fwrite($socket,"QUIT\r\n") !== 6) throw new RuntimeException('Could not close the SMTP session cleanly.');
        booking_mail_expect($socket,[221]);
    } finally {
        fclose($socket);
    }
}

function booking_mail_deliver(PDO $pdo, int $outboxId): string
{
    $query = $pdo->prepare('SELECT status,attempts FROM email_outbox WHERE id=?');
    $query->execute([$outboxId]);
    $state = $query->fetch();
    if (!$state) return 'missing';
    if ($state['status'] === 'sent') return 'sent';
    try { $config = booking_mail_smtp_config(); }
    catch (Throwable) {
        $pdo->prepare("UPDATE email_outbox SET status='failed',last_error='SMTP configuration is invalid',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status<>'sent'")->execute([$outboxId]);
        return 'failed';
    }
    if ($config === null) return (string)$state['status'];

    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $query = $pdo->prepare('SELECT * FROM email_outbox WHERE id=?');
        $query->execute([$outboxId]);
        $message = $query->fetch();
        if (!$message) { $pdo->commit(); return 'missing'; }
        if ($message['status'] === 'sent') { $pdo->commit(); return 'sent'; }
        if ($message['status'] === 'sending' && $message['locked_at'] !== null) {
            // SQLite CURRENT_TIMESTAMP is UTC; do not parse this timezone-less value
            // using PHP's Asia/Qyzylorda default or a fresh lease looks five hours old.
            $lease = $pdo->prepare("SELECT datetime(?) > datetime('now','-10 minutes')");
            $lease->execute([(string)$message['locked_at']]);
            if ((int)$lease->fetchColumn() === 1) { $pdo->commit(); return 'sending'; }
        }
        if ((int)$message['attempts'] >= 8) { $pdo->commit(); return 'failed'; }
        $claim = $pdo->prepare("UPDATE email_outbox SET status='sending',attempts=attempts+1,locked_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status IN('pending','failed','sending')");
        $claim->execute([$outboxId]);
        if ($claim->rowCount() !== 1) { $pdo->commit(); return 'sending'; }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    try {
        booking_mail_smtp_send($config,(string)$message['recipient'],(string)$message['subject'],(string)$message['body']);
        $pdo->prepare("UPDATE email_outbox SET status='sent',sent_at=CURRENT_TIMESTAMP,locked_at=NULL,last_error='',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='sending'")->execute([$outboxId]);
        return 'sent';
    } catch (Throwable $error) {
        $safeError = preg_match('/code (\d{3})/', $error->getMessage(), $match) ? 'SMTP rejected message (code ' . $match[1] . ')' : 'SMTP delivery failed; safe retry is available.';
        $pdo->prepare("UPDATE email_outbox SET status='failed',locked_at=NULL,last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='sending'")->execute([$safeError,$outboxId]);
        return 'failed';
    }
}

function booking_mail_retry(PDO $pdo, int $outboxId): string
{
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $query = $pdo->prepare("SELECT status FROM email_outbox WHERE id=? AND status IN('pending','failed')");
        $query->execute([$outboxId]);
        if ($query->fetchColumn() === false) {
            $query = $pdo->prepare('SELECT status FROM email_outbox WHERE id=?');
            $query->execute([$outboxId]);
            $status = $query->fetchColumn();
            $pdo->commit();
            return $status === false ? 'missing' : (string)$status;
        }
        $pdo->prepare("UPDATE email_outbox SET status='pending',attempts=0,last_error='',locked_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$outboxId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return booking_mail_deliver($pdo,$outboxId);
}

function booking_mail_process_pending(PDO $pdo, int $limit = 25): array
{
    $limit = max(1,min(100,$limit));
    $query = $pdo->query("SELECT id FROM email_outbox WHERE (status IN('pending','failed') AND attempts<8) OR (status='sending' AND locked_at IS NOT NULL AND datetime(locked_at)<datetime('now','-10 minutes')) ORDER BY id LIMIT " . $limit);
    $results = [];
    foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $id) $results[(int)$id] = booking_mail_deliver($pdo,(int)$id);
    return $results;
}

function booking_mail_status_label(?string $status): string
{
    return match ($status) {
        'sent' => 'Отправлено', 'pending' => 'В очереди', 'sending' => 'Отправляется',
        'failed' => 'Не отправлено', 'missing' => 'Запись не найдена', default => 'Нет письма',
    };
}

function booking_mail_delivery_message(string $status): string
{
    return match ($status) {
        'sent' => 'Уведомление отправлено. Бронь ожидает отдельного подтверждения администратора.',
        'failed' => 'Заявка сохранена, но письмо пока не отправлено. Оно осталось в очереди для повторной отправки.',
        default => 'Заявка сохранена. Email находится в очереди; для отправки настройте SMTP. Бронь пока не подтверждена.',
    };
}
