<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$action = (string)($_GET['action'] ?? '');

if ($action === 'notify') {
    try { $config = freedompay_config(); }
    catch (Throwable) { http_response_code(503); exit('Payment service is not configured.'); }
    if ($config === null || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
    $fields = freedompay_callback_fields();
    if (!freedompay_signature_is_valid('payment.php', $fields, $config['secret'])) { http_response_code(403); exit('Invalid signature.'); }
    $providerOrderId = trim((string)($fields['pg_order_id'] ?? ''));
    $providerPaymentId = trim((string)($fields['pg_payment_id'] ?? ''));
    $result = (string)($fields['pg_result'] ?? '');
    $amountMinor = freedompay_amount_to_minor_units((string)($fields['pg_amount'] ?? ''));
    if ($providerOrderId === '' || strlen($providerOrderId) > 50 || $providerPaymentId === '' || strlen($providerPaymentId) > 80
        || !in_array($result, ['0','1'], true) || $amountMinor === null || (string)($fields['pg_currency'] ?? '') !== 'KZT') {
        http_response_code(422); exit('Invalid payment notification.');
    }

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $query = $pdo->prepare('SELECT p.id AS payment_id,p.order_id,p.amount,p.provider_payment_id,p.status AS attempt_status,o.status AS order_status,o.payment_status FROM order_payments p JOIN orders o ON o.id=p.order_id WHERE p.provider_order_id=?');
        $query->execute([$providerOrderId]);
        $payment = $query->fetch();
        if (!$payment || $amountMinor !== (int)$payment['amount'] * 100) {
            $pdo->rollBack();
            freedompay_callback_response('error', 'Order or amount mismatch.', $config['secret']);
        }
        if (!empty($payment['provider_payment_id']) && !hash_equals((string)$payment['provider_payment_id'], $providerPaymentId)) {
            $pdo->rollBack();
            http_response_code(409); exit('Payment identifier mismatch.');
        }
        $duplicate = $pdo->prepare('SELECT id FROM order_payments WHERE provider_payment_id=?');
        $duplicate->execute([$providerPaymentId]);
        $duplicateId = $duplicate->fetchColumn();
        if ($duplicateId !== false && (int)$duplicateId !== (int)$payment['payment_id']) {
            $pdo->rollBack();
            freedompay_callback_response('error', 'Payment identifier is already in use.', $config['secret']);
        }
        if ($result === '1' && $payment['order_status'] === 'cancelled' && (string)($fields['pg_can_reject'] ?? '0') === '1') {
            $pdo->rollBack();
            freedompay_callback_response('rejected', 'The order was cancelled.', $config['secret']);
        }
        if ($result === '1') {
            $update = $pdo->prepare("UPDATE order_payments SET provider_payment_id=?,status='paid',last_error='',updated_at=CURRENT_TIMESTAMP,paid_at=COALESCE(paid_at,CURRENT_TIMESTAMP) WHERE id=?");
            $update->execute([$providerPaymentId,(int)$payment['payment_id']]);
            $pdo->prepare("UPDATE orders SET payment_status='paid',payment_provider='freedompay',payment_reference=? WHERE id=? AND payment_status<>'paid'")->execute([$providerPaymentId,(int)$payment['order_id']]);
        } elseif ($payment['payment_status'] !== 'paid') {
            $update = $pdo->prepare("UPDATE order_payments SET provider_payment_id=?,status='failed',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status<>'paid'");
            $update->execute([$providerPaymentId,(int)$payment['payment_id']]);
            $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=? AND payment_status<>'paid'")->execute([(int)$payment['order_id']]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('FreedomPay notification could not be processed: ' . $error->getMessage());
        http_response_code(500); exit('Notification processing failed.');
    }
    freedompay_callback_response('ok', 'Payment status saved.', $config['secret']);
}

if ($action === 'start') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Метод не поддерживается.']); exit; }
    check_csrf();
    try { $config = freedompay_config(); }
    catch (Throwable) { http_response_code(503); echo json_encode(['ok'=>false,'message'=>'Онлайн-оплата временно недоступна.']); exit; }
    if ($config === null) { http_response_code(503); echo json_encode(['ok'=>false,'message'=>'Онлайн-оплата ещё не подключена.']); exit; }
    $token = strtolower(trim((string)($_POST['payment_token'] ?? '')));
    $requestedOrderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $returnSig = trim((string)($_POST['return_sig'] ?? ''));
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    if (!preg_match('/^[a-f0-9]{64}$/', $token) && !($requestedOrderId && $userId) && !($requestedOrderId && hash_equals(freedompay_return_signature((int)$requestedOrderId,$config['secret']),$returnSig))) {
        http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Не удалось проверить заказ для оплаты.']); exit;
    }

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $query = $pdo->prepare('SELECT id,total,status,payment_status FROM orders WHERE payment_access_hash=?');
            $query->execute([hash('sha256',$token)]);
        } elseif ($requestedOrderId && $userId) {
            $query = $pdo->prepare('SELECT id,total,status,payment_status FROM orders WHERE id=? AND user_id=?');
            $query->execute([(int)$requestedOrderId,$userId]);
        } else {
            $query = $pdo->prepare("SELECT id,total,status,payment_status FROM orders WHERE id=? AND payment_provider='freedompay'");
            $query->execute([(int)$requestedOrderId]);
        }
        $order = $query->fetch();
        if (!$order) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'message'=>'Заказ для оплаты не найден.']); exit; }
        if ((int)$order['total'] < 1 || $order['status'] === 'cancelled') { $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'message'=>'Этот заказ нельзя оплатить.']); exit; }
        if ($order['payment_status'] === 'paid') { $pdo->commit(); echo json_encode(['ok'=>true,'paid'=>true,'message'=>'Заказ уже оплачен.']); exit; }

        $latestQuery = $pdo->prepare('SELECT * FROM order_payments WHERE order_id=? ORDER BY attempt_no DESC LIMIT 1');
        $latestQuery->execute([(int)$order['id']]);
        $latest = $latestQuery->fetch();
        if ($latest && $latest['status'] === 'pending' && $latest['checkout_url'] !== '') {
            $pdo->commit(); echo json_encode(['ok'=>true,'redirect_url'=>$latest['checkout_url'],'test_mode'=>$config['test_mode']]); exit;
        }
        if ($latest && $latest['status'] === 'creating') {
            $pdo->commit(); http_response_code(409); echo json_encode(['ok'=>false,'message'=>'Результат создания платежа ещё не подтверждён. Новая попытка заблокирована до проверки, чтобы исключить двойную оплату. Обратитесь к администратору с номером заказа.']); exit;
        }
        $attempt = $latest ? (int)$latest['attempt_no'] + ((string)$latest['status'] === 'failed' ? 1 : 0) : 1;
        $payment = $latest && $latest['status'] !== 'failed' ? $latest : null;
        $providerOrderId = 'TP' . (int)$order['id'] . 'A' . $attempt;
        if (!$payment) {
            $insert = $pdo->prepare("INSERT INTO order_payments(order_id,attempt_no,provider,provider_order_id,amount,currency,status) VALUES(?,?,'freedompay',?,?,'KZT','creating')");
            $insert->execute([(int)$order['id'],$attempt,$providerOrderId,(int)$order['total']]);
            $paymentId = (int)$pdo->lastInsertId();
        } else {
            $paymentId = (int)$payment['id'];
            $providerOrderId = (string)$payment['provider_order_id'];
        }
        $pdo->prepare("UPDATE orders SET payment_status='pending',payment_provider='freedompay' WHERE id=?")->execute([(int)$order['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('FreedomPay checkout preparation failed: ' . $error->getMessage());
        http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Не удалось подготовить оплату. Попробуйте ещё раз.']); exit;
    }

    try {
        $returnSig = freedompay_return_signature((int)$order['id'], $config['secret']);
        $returnUrl = $config['public_base'] . '/payment.php?' . http_build_query(['action'=>'return','order'=>(int)$order['id'],'sig'=>$returnSig], '', '&', PHP_QUERY_RFC3986);
        $fields = [
            'pg_merchant_id'=>$config['merchant_id'],
            'pg_order_id'=>$providerOrderId,
            'pg_amount'=>(string)(int)$order['total'],
            'pg_currency'=>'KZT',
            'pg_description'=>'Tokyo Premium order #' . (int)$order['id'],
            'pg_salt'=>bin2hex(random_bytes(8)),
            'pg_result_url'=>$config['public_base'] . '/payment.php?action=notify',
            'pg_success_url'=>$returnUrl,
            'pg_failure_url'=>$returnUrl,
            'pg_lifetime'=>'3600',
            'pg_language'=>'ru',
        ];
        if ($config['test_mode']) $fields['pg_testing_mode']='1';
        $fields['pg_sig'] = freedompay_signature('payment_page', $fields, $config['secret']);
        $raw = freedompay_post_form('https://api.freedompay.kz/g2g/payment_page', $fields);
        $gateway = freedompay_parse_payment_page_response($raw, $config['secret']);
        if (($gateway['pg_status'] ?? '') !== 'ok') {
            $pdo->prepare("UPDATE order_payments SET status='failed',last_error='Gateway rejected payment request.',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='creating'")->execute([$paymentId]);
            $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=? AND payment_status<>'paid'")->execute([(int)$order['id']]);
            http_response_code(502); echo json_encode(['ok'=>false,'message'=>'Платёжный сервис отклонил запрос. Заказ сохранён; попробуйте ещё раз.']); exit;
        }
        $redirect = (string)($gateway['pg_redirect_url'] ?? '');
        $redirectParts = parse_url($redirect);
        if (!$redirectParts || strtolower((string)($redirectParts['scheme'] ?? '')) !== 'https' || strtolower((string)($redirectParts['host'] ?? '')) !== 'customer.freedompay.kz' || empty($gateway['pg_payment_id'])) {
            throw new RuntimeException('FreedomPay returned an unexpected checkout destination.');
        }
        $pdo->prepare("UPDATE order_payments SET provider_payment_id=?,checkout_url=?,status='pending',last_error='',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='creating'")->execute([(string)$gateway['pg_payment_id'],$redirect,$paymentId]);
        $pdo->prepare("UPDATE orders SET payment_status='pending',payment_provider='freedompay',payment_reference=? WHERE id=? AND payment_status<>'paid'")->execute([(string)$gateway['pg_payment_id'],(int)$order['id']]);
        echo json_encode(['ok'=>true,'redirect_url'=>$redirect,'test_mode'=>$config['test_mode']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    } catch (Throwable $error) {
        $pdo->prepare("UPDATE order_payments SET last_error='Payment link result is uncertain; provider reconciliation required.',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='creating'")->execute([$paymentId]);
        error_log('FreedomPay checkout link was not created: ' . $error->getMessage());
        http_response_code(502); echo json_encode(['ok'=>false,'message'=>'Результат платежного запроса пока неизвестен. Заказ сохранён. Не создавайте новый заказ; обратитесь к администратору для проверки оплаты.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'return' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try { $config = freedompay_config(); }
    catch (Throwable) { $config = null; }
    $orderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
    $returnSig = trim((string)($_GET['sig'] ?? ''));
    if ($config === null || !$orderId || $orderId < 1 || !hash_equals(freedompay_return_signature((int)$orderId, $config['secret']), $returnSig)) {
        http_response_code(404); exit('Страница статуса оплаты недоступна.');
    }
    $query = db()->prepare('SELECT id,total,payment_status FROM orders WHERE id=? AND payment_provider=\'freedompay\'');
    $query->execute([(int)$orderId]);
    $order = $query->fetch();
    if (!$order) { http_response_code(404); exit('Заказ не найден.'); }
    $labels = ['paid'=>'Платёж подтверждён','pending'=>'Проверяем платёж','failed'=>'Платёж не завершён','unpaid'=>'Ожидается оплата'];
    header('Cache-Control: private, no-store');
    header('Referrer-Policy: no-referrer');
    page_start('Статус оплаты · Tokyo Premium','Статус платежа по заказу Tokyo Premium.');
    header_html('');
    ?>
    <main class="editorial-page"><section class="section"><div class="editorial-heading"><div class="kicker">TOKYO PREMIUM · <?= $config['test_mode'] ? 'ТЕСТОВАЯ ОПЛАТА' : 'ОПЛАТА ЗАКАЗА' ?></div><h1><?=e($labels[(string)$order['payment_status']] ?? 'Статус оплаты')?></h1><p>Заказ №<?=(int)$order['id']?> · <?=number_format((int)$order['total'],0,'.',' ')?> ₸</p></div>
      <?php if ($order['payment_status'] === 'paid'): ?><p class="form-status success" role="status">Платёж подтверждён серверным уведомлением FreedomPay. Сам заказ отдельно обрабатывается рестораном.</p>
      <?php elseif ($order['payment_status'] === 'failed'): ?><p class="form-status error" role="status">Платёж не завершился. Вернитесь в корзину и повторите попытку либо выберите оплату при получении.</p>
      <?php else: ?><p class="form-status" role="status">Платёж ещё обрабатывается. Не создавайте заказ повторно; обновите эту страницу через несколько секунд.</p><?php endif ?>
      <div class="button-row"><a class="button secondary" href="pages.php?page=cart">Вернуться в корзину ↗</a><a class="button secondary" href="account.php#account-orders">Личный кабинет ↗</a><a class="button secondary" href="payment.php?action=return&amp;order=<?=(int)$order['id']?>&amp;sig=<?=e($returnSig)?>">Обновить статус</a></div>
      <?php if ($order['payment_status'] !== 'paid'): ?><form class="payment-retry-form" data-payment-retry><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="order_id" value="<?=(int)$order['id']?>"><input type="hidden" name="return_sig" value="<?=e($returnSig)?>"><button class="button" type="submit">Продолжить оплату ↗</button><p class="form-status" role="status" aria-live="polite"></p></form><?php endif ?>
    </section></main>
    <?php footer_html();
    exit;
}

http_response_code(404);
echo 'Not found.';
