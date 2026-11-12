<?php
declare(strict_types=1);

// Repeatable HTTP integration checks. Uses a uniquely named SQLite database in the OS temp dir.
$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tokyo-integrity-' . bin2hex(random_bytes(6)) . '.sqlite';
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n"); exit(77); }
putenv('RESTAURANT_DB=' . $dbPath);
require dirname(__DIR__) . '/bootstrap.php';
$pdo = db();
$password = 'Integration-Only-' . bin2hex(random_bytes(8));
$pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)')->execute(['integration@example.test', password_hash($password, PASSWORD_DEFAULT)]);
$port = random_int(21000, 45000);
$base = 'http://127.0.0.1:' . $port;
$env = null; // Inherit SystemRoot and PHP configuration on Windows as well as PATH on Unix.
$pipes = [];
$serverLog = $dbPath . '.server.log';
// The PHP CLI server supports multiple workers on Unix; this makes HTTP requests genuinely concurrent in CI.
$previousWorkers = getenv('PHP_CLI_SERVER_WORKERS');
if (PHP_OS_FAMILY !== 'Windows') putenv('PHP_CLI_SERVER_WORKERS=4');
$server = proc_open([PHP_BINARY, '-d', 'extension_dir=' . ini_get('extension_dir'), '-d', 'extension=php_sqlite3.dll', '-d', 'extension=php_pdo_sqlite.dll', '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__)], [0 => ['pipe','r'], 1 => ['file',$serverLog,'a'], 2 => ['file',$serverLog,'a']], $pipes, dirname(__DIR__), $env);
if ($previousWorkers === false) putenv('PHP_CLI_SERVER_WORKERS'); else putenv('PHP_CLI_SERVER_WORKERS=' . $previousWorkers);
if (!is_resource($server)) throw new RuntimeException('Could not start PHP test server.');
$cookie = '';
$checks = 0;
function check(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: ' . $message); $checks++; }
function request(string $path, array $fields = [], array $headers = []): array {
 global $base, $cookie;
 $headerLines = ['Accept: text/html,application/json'];
 if ($cookie !== '') $headerLines[] = 'Cookie: ' . $cookie;
 foreach ($headers as $k => $v) $headerLines[] = $k . ': ' . $v;
 $options = ['http' => ['method' => $fields ? 'POST' : 'GET', 'ignore_errors' => true, 'header' => implode("\r\n", $headerLines), 'content' => $fields ? http_build_query($fields) : '', 'timeout' => 15]];
 if ($fields) $options['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
 $body = @file_get_contents($base . $path, false, stream_context_create($options));
 $responseHeaders = $http_response_header ?? [];
 foreach ($responseHeaders as $line) if (stripos($line, 'Set-Cookie: tokyo_session=') === 0) $cookie = explode(';', substr($line, 12))[0];
 preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $m);
 return [(int)($m[1] ?? 0), (string)$body, $responseHeaders];
}
function parallelOrderRequests(array $fields, string $idempotencyKey, int $count): array {
 global $base, $cookie, $pdo;
 $body = http_build_query($fields);
 $sockets = [];
 // Hold the SQLite writer lock while all HTTP requests are sent. With the four-worker
 // CI server, requests then contend at BEGIN IMMEDIATE before one is allowed to commit.
 // This makes the race reproducible instead of hoping fast connections overlap.
 $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
 try {
  for ($i = 0; $i < $count; $i++) {
   $errno = 0; $error = '';
   $socket = @stream_socket_client('tcp://' . parse_url($base, PHP_URL_HOST) . ':' . parse_url($base, PHP_URL_PORT), $errno, $error, 5, STREAM_CLIENT_CONNECT);
   if (!$socket) throw new RuntimeException('Could not open concurrent request socket: ' . $error);
   $sockets[] = $socket;
   stream_set_timeout($socket, 15);
   $headers = "POST /api.php HTTP/1.1\r\nHost: 127.0.0.1\r\nAccept: application/json\r\nConnection: close\r\nContent-Type: application/x-www-form-urlencoded\r\nIdempotency-Key: {$idempotencyKey}\r\nContent-Length: " . strlen($body) . "\r\n";
   if ($cookie !== '') $headers .= 'Cookie: ' . $cookie . "\r\n";
   $request = $headers . "\r\n" . $body;
   $offset = 0;
   while ($offset < strlen($request)) { $written = fwrite($socket, substr($request, $offset)); if ($written === false || $written === 0) throw new RuntimeException('Could not write concurrent request.'); $offset += $written; }
  }
  // Give the server workers time to reach the contested transaction boundary.
  // Windows' built-in server is single-worker, so it exercises the serialized fallback.
  if (PHP_OS_FAMILY !== 'Windows') usleep(200000);
  $pdo->exec('COMMIT');
 } catch (Throwable $ex) {
  try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignored) {}
  foreach ($sockets as $socket) if (is_resource($socket)) fclose($socket);
  throw $ex;
 }
 $responses = [];
 foreach ($sockets as $socket) {
  $raw = stream_get_contents($socket);
  fclose($socket);
  if (!preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/m', $raw, $match)) throw new RuntimeException('Malformed concurrent HTTP response: ' . substr($raw, 0, 200));
  $body = substr($raw, strpos($raw, "\r\n\r\n") + 4);
  $responses[] = [(int)$match[1], $body];
 }
 return $responses;
}
function tokenFrom(string $html): string { if (!preg_match('/name="csrf" value="([^"]+)"/', $html, $m)) throw new RuntimeException('CSRF token not found.'); return html_entity_decode($m[1], ENT_QUOTES); }
try {
 $ready = false;
 for ($i=0; $i<30; $i++) { [$status] = request('/admin.php'); if ($status === 200) { $ready = true; break; } usleep(100000); }
 check($ready, 'PHP server starts');
 [$status,$html] = request('/admin.php'); check($status===200 && str_contains($html,'Вход в панель'), 'admin login page available'); $csrf=tokenFrom($html);
 [$status] = request('/admin.php',['csrf'=>$csrf,'action'=>'login','email'=>'integration@example.test','password'=>$password]); check($status===302, 'admin login succeeds');
 [$status,$html] = request('/admin.php'); check($status===200 && str_contains($html,'Панель сайта'), 'authenticated admin page loads'); $csrf=tokenFrom($html);
 $save=['csrf'=>$csrf,'action'=>'dish-save','category'=>'rolls','name'=>'Тестовый ролл','description'=>'Описание для проверки','weight'=>'200 г','price'=>'3450','image_url'=>'assets/food/01-philadelphia.webp','sort_order'=>'99','active'=>'on'];
 [$status,$html]=request('/admin.php',$save); check($status===200 && str_contains($html,'Блюдо сохранено'), 'dish create with existing local webp');
 $dish=(int)$pdo->query("SELECT id FROM dishes WHERE name='Тестовый ролл'")->fetchColumn(); check($dish>0, 'created dish persisted');
 $csrf=tokenFrom($html); $edit=$save; $edit['id']=(string)$dish; $edit['description']='Обновлённое описание'; $edit['price']='3750'; $edit['image_url']='https://images.unsplash.com/photo-1?auto=format&fit=crop&w=800'; $edit['active']='on';
 [$status,$html]=request('/admin.php',$edit); check(str_contains($html,'Блюдо сохранено'), 'dish edit accepts approved HTTPS image');
 $row=$pdo->query('SELECT description,price,image_url FROM dishes WHERE id='.$dish)->fetch(PDO::FETCH_ASSOC); check($row['description']==='Обновлённое описание' && (int)$row['price']===3750 && str_starts_with($row['image_url'],'https://images.unsplash.com/'), 'description, price, image update persisted');
 foreach (['/../bootstrap.php','//evil.example/x.webp','javascript:alert(1)','https://evil.example/x.webp','https://images.unsplash.com/../../x.webp'] as $bad) { $csrf=tokenFrom($html); $badSave=$edit; $badSave['csrf']=$csrf; $badSave['image_url']=$bad; [$status,$html]=request('/admin.php',$badSave); check(str_contains($html,'Проверьте название, категорию, цену и фото'), 'reject unsafe image: '.$bad); }
 $csrf=tokenFrom($html); $hide=$edit; $hide['csrf']=$csrf; unset($hide['active']); [$status,$html]=request('/admin.php',$hide); check(str_contains($html,'Блюдо сохранено'), 'dish can be hidden'); check((int)$pdo->query('SELECT active FROM dishes WHERE id='.$dish)->fetchColumn()===0, 'hidden flag persisted');
 [$status,$json]=request('/api.php',['csrf'=>$csrf,'type'=>'order','name'=>'Тестовый гость','phone'=>'+77010000000','fulfillment'=>'pickup','items'=>json_encode([['id'=>$dish,'qty'=>1,'price'=>1]])]); check($status===422, 'hidden dish cannot be ordered');
 $csrf=tokenFrom($html); $edit['csrf']=$csrf; $edit['image_url']='assets/food/01-philadelphia.webp'; $edit['active']='on'; [$status,$html]=request('/admin.php',$edit); check(str_contains($html,'Блюдо сохранено'), 'dish can be reactivated'); $csrf=tokenFrom($html);
 $orderFields=['csrf'=>$csrf,'type'=>'order','name'=>'Тестовый гость','phone'=>'+77010000000','fulfillment'=>'pickup','items'=>json_encode([['id'=>$dish,'qty'=>2,'price'=>1]])];
 [$status,$body]=request('/api.php',$orderFields,['Idempotency-Key'=>'integration-order-key-0001']); $first=json_decode($body,true); check($status===201 && $first['ok'], 'valid pickup order succeeds with no address');
 [$status,$body2]=request('/api.php',$orderFields,['Idempotency-Key'=>'integration-order-key-0001']); $second=json_decode($body2,true); check($status===201 && $second['id']===$first['id'], 'same idempotency key replays original order');
 $order=$pdo->query('SELECT total FROM orders WHERE id='.(int)$first['id'])->fetchColumn(); check((int)$order===7500, 'server price used, client price ignored'); check((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id='.(int)$first['id'])->fetchColumn()===1, 'retry created no second order');
 $concurrentKey='integration-concurrent-order-'.bin2hex(random_bytes(8));
 $parallel=parallelOrderRequests($orderFields,$concurrentKey,6); $parallelIds=[];
 foreach($parallel as [$parallelStatus,$parallelBody]){$parallelResult=json_decode($parallelBody,true);check($parallelStatus===201&&$parallelResult['ok'],'concurrent same-key order request returns the committed result');$parallelIds[]=(int)$parallelResult['id'];}
 check(count(array_unique($parallelIds))===1,'six concurrent requests with one key resolve to one order id');
 $concurrentId=$parallelIds[0];check((int)$pdo->query('SELECT COUNT(*) FROM orders WHERE id='.$concurrentId)->fetchColumn()===1,'concurrent key creates exactly one order');check((int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id='.$concurrentId)->fetchColumn()===1,'concurrent key creates one order line snapshot');
 $concurrentToken=hash('sha256',substr($cookie,strlen('tokyo_session=')).'|order|'.$concurrentKey);$idem=$pdo->prepare('SELECT order_id,response_status FROM api_order_requests WHERE token=?');$idem->execute([$concurrentToken]);$idemRow=$idem->fetch();$idem->closeCursor();check((int)$idemRow['order_id']===$concurrentId&&(int)$idemRow['response_status']===201,'idempotency reservation and replay result point to the one committed order');
 $rollbackKey='integration-rollback-order-'.bin2hex(random_bytes(8));$pdo->exec("CREATE TRIGGER test_fail_order_line BEFORE INSERT ON order_items BEGIN SELECT RAISE(ABORT, 'forced test rollback'); END");[$failedStatus]=request('/api.php',$orderFields,['Idempotency-Key'=>$rollbackKey]);check($failedStatus===500,'forced item insert error reports failure');$pdo->exec('DROP TRIGGER test_fail_order_line');
 $rollbackToken=hash('sha256',substr($cookie,strlen('tokyo_session=')).'|order|'.$rollbackKey);$q=$pdo->prepare('SELECT COUNT(*) FROM api_order_requests WHERE token=?');$q->execute([$rollbackToken]);$reservationCount=(int)$q->fetchColumn();$q->closeCursor();check($reservationCount===0,'failed transaction leaves no idempotency reservation');check((int)$pdo->query("SELECT COUNT(*) FROM orders WHERE id NOT IN (".(int)$first['id'].','.$concurrentId.')')->fetchColumn()===0,'failed transaction leaves no partial order');
 [$status,$rollbackRetryBody]=request('/api.php',$orderFields,['Idempotency-Key'=>$rollbackKey]);$rollbackRetry=json_decode($rollbackRetryBody,true);check($status===201&&$rollbackRetry['ok'],'same key can succeed after the failed transaction rolled back (HTTP '.$status.'): '.$rollbackRetryBody);check((int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id='.(int)$rollbackRetry['id'])->fetchColumn()===1,'successful retry persists exactly one item');
 $independentKey='integration-independent-order-'.bin2hex(random_bytes(8));[$status,$independentBody]=request('/api.php',$orderFields,['Idempotency-Key'=>$independentKey]);$independent=json_decode($independentBody,true);check($status===201&&$independent['ok']&&(int)$independent['id']!==$concurrentId,'independent idempotency key creates a separate order');
 [$status,$accountHtml]=request('/account.php'); $accountCsrf=tokenFrom($accountHtml); $accountEmail='qa+'.bin2hex(random_bytes(4)).'@example.test';
 [$status]=request('/account.php',['csrf'=>$accountCsrf,'action'=>'register','name'=>'Demo QA','email'=>$accountEmail,'password'=>'Strong-test-password-123']);
 [$status,$accountHtml]=request('/account.php');
 check($status===200 && str_contains($accountHtml,'Здравствуйте, Demo QA'), 'account registration creates a logged-in profile');
 $profileOrder=$orderFields; $profileOrder['csrf']=tokenFrom($accountHtml); $profileOrder['name']='Demo QA';
 [$status,$profileOrderBody]=request('/api.php',$profileOrder,['Idempotency-Key'=>'integration-profile-order-0001']); $profileOrderResult=json_decode($profileOrderBody,true);
 check($status===201 && $profileOrderResult['ok'], 'authenticated profile order succeeds');
 [$status,$history]=request('/account.php');
 check($status===200 && str_contains($history,'Тестовый ролл') && str_contains($history,'2 × 3 750') && str_contains($history,'Самовывоз'), 'profile history shows snapshot items, quantities, unit prices, line totals, and fulfillment');
 foreach ([0,21] as $qty) { $badOrder=$orderFields; $badOrder['items']=json_encode([['id'=>$dish,'qty'=>$qty]]); [$status]=request('/api.php',$badOrder); check($status===422, 'invalid quantity rejected: '.$qty); }
 $badOrder=$orderFields; $badOrder['items']='{"id":1,"qty":2}'; [$status]=request('/api.php',$badOrder); check($status===422, 'non-list item payload rejected');
 $badOrder=$orderFields; $badOrder['items']=json_encode([['id'=>999999,'qty'=>1]]); [$status]=request('/api.php',$badOrder); check($status===422, 'unknown dish rejected');
 $future=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
 $booking=['csrf'=>$csrf,'type'=>'booking','name'=>'Тестовый гость','phone'=>'+77010000000','guests'=>'2','date'=>'2026-02-31','time'=>'19:00','format'=>'Сезонный']; [$status]=request('/api.php',$booking); check($status===422, 'impossible booking date rejected');
 $booking['date']=$future; [$status,$body]=request('/api.php',$booking,['Idempotency-Key'=>'integration-booking-key-0001']); $book=json_decode($body,true); check($status===201 && $book['ok'] && str_contains($book['message'],'не зарезервирован'), 'valid booking is explicitly unconfirmed demo request'); check((string)$pdo->query('SELECT format FROM bookings WHERE id='.(int)$book['id'])->fetchColumn()==='Сезонный', 'selected Omakase format is saved with booking request');
 [$status,$body]=request('/api.php',$booking,['Idempotency-Key'=>'integration-booking-key-0001']); check($status===201 && json_decode($body,true)['id']===$book['id'], 'booking retry replays original request');
 $csrf=tokenFrom((request('/admin.php'))[1]); request('/admin.php',['csrf'=>$csrf,'action'=>'dish-delete','id'=>(string)$dish]); check((int)$pdo->query('SELECT COUNT(*) FROM dishes WHERE id='.$dish)->fetchColumn()===0, 'dish deleted'); check((int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id='.(int)$first['id'].' AND item_name="Тестовый ролл"')->fetchColumn()===1, 'order item snapshot survives dish deletion');
 echo 'Concurrent HTTP mode: ' . (PHP_OS_FAMILY==='Windows'?'client requests queued by single-worker PHP CLI server':'4-worker PHP CLI server') . "\n";
 echo "PASS: {$checks} integration assertions; isolated DB: {$dbPath}\n";
} catch (Throwable $ex) {
 if (is_file($serverLog)) fwrite(STDERR, "\nPHP test server log:\n" . file_get_contents($serverLog) . "\n");
 throw $ex;
} finally {
 proc_terminate($server); foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe); proc_close($server);
 foreach ([$dbPath,$dbPath.'-wal',$dbPath.'-shm',$serverLog] as $file) if (is_file($file)) @unlink($file);
}
