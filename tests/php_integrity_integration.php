<?php
declare(strict_types=1);

// Repeatable HTTP integration checks. Uses a uniquely named SQLite database in the OS temp dir.
$dbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tokyo-integrity-' . bin2hex(random_bytes(6)) . '.sqlite';
// Never let developer or CI mail credentials escape into this integration test.
foreach (["TOKYO_SMTP_HOST","TOKYO_SMTP_PORT","TOKYO_SMTP_USER","TOKYO_SMTP_PASSWORD","TOKYO_SMTP_ENCRYPTION","TOKYO_SMTP_SECURE","TOKYO_MAIL_FROM","TOKYO_MAIL_FROM_NAME","TOKYO_BOOKING_NOTIFY_EMAIL"] as $mailEnvironmentKey) putenv($mailEnvironmentKey);
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n"); exit(77); }
putenv('RESTAURANT_DB=' . $dbPath);
$checks = 0;
$initGatePath = $dbPath . '.start';
$initScript = 'for ($wait=0; !is_file($argv[2]) && $wait<500; $wait++) usleep(10000); if (!is_file($argv[2])) exit(70); require $argv[1]; db(); echo "db-ready";';
$initLockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tokyo-db-init-' . hash('sha256', realpath(dirname($dbPath)) . DIRECTORY_SEPARATOR . basename($dbPath)) . '.lock';
$initializers = [];
for ($i = 0; $i < 6; $i++) {
    $workerPipes = [];
    $workerCommand = [PHP_BINARY];
    if (PHP_OS_FAMILY === 'Windows') {
        $workerCommand[] = '-d';
        $workerCommand[] = 'extension_dir=' . ini_get('extension_dir');
        if (!extension_loaded('sqlite3')) { $workerCommand[] = '-d'; $workerCommand[] = 'extension=php_sqlite3.dll'; }
        if (!extension_loaded('pdo_sqlite')) { $workerCommand[] = '-d'; $workerCommand[] = 'extension=php_pdo_sqlite.dll'; }
    }
    array_push($workerCommand, '-r', $initScript, dirname(__DIR__) . '/bootstrap.php', $initGatePath);
    $worker = proc_open($workerCommand, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $workerPipes, dirname(__DIR__), null);
    if (!is_resource($worker)) throw new RuntimeException('Could not start concurrent SQLite initializer ' . ($i + 1) . '.');
    fclose($workerPipes[0]);
    $initializers[] = [$worker, $workerPipes, $i + 1];
}
if (file_put_contents($initGatePath, 'start') === false) throw new RuntimeException('Could not release concurrent SQLite initializers.');
foreach ($initializers as [$worker, $workerPipes, $number]) {
    $stdout = stream_get_contents($workerPipes[1]);
    $stderr = stream_get_contents($workerPipes[2]);
    fclose($workerPipes[1]);
    fclose($workerPipes[2]);
    $exitCode = proc_close($worker);
    if ($exitCode !== 0 || !str_contains($stdout, 'db-ready')) {
        throw new RuntimeException('Concurrent SQLite initializer ' . $number . ' failed (exit ' . $exitCode . '): ' . trim((string)$stdout . ' ' . (string)$stderr));
    }
    $checks++;
}
require dirname(__DIR__) . '/bootstrap.php';
$pdo = db();
check(strtolower((string)$pdo->query('PRAGMA journal_mode')->fetchColumn()) === 'wal', 'SQLite uses WAL for concurrent read and order commits');
$upgradeDbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tokyo-v6-upgrade-' . bin2hex(random_bytes(6)) . '.sqlite';
$pdo->query('PRAGMA wal_checkpoint(TRUNCATE)');
if (!copy($dbPath, $upgradeDbPath)) throw new RuntimeException('Could not create a migration-v6 fixture.');
$v6Fixture = new PDO('sqlite:' . $upgradeDbPath, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$v6Fixture->exec('DROP TABLE api_booking_requests');
$v6Fixture->exec('DROP TABLE booking_email_rate_limits');
$v6Fixture->exec('DELETE FROM app_migrations WHERE version=7');
$v6Fixture = null;
$oldRestaurantDb = getenv('RESTAURANT_DB');
putenv('RESTAURANT_DB=' . $upgradeDbPath);
$upgradeCommand = [PHP_BINARY];
if (PHP_OS_FAMILY === 'Windows') {
    array_push($upgradeCommand, '-d', 'extension_dir=' . ini_get('extension_dir'));
    if (!extension_loaded('sqlite3')) array_push($upgradeCommand, '-d', 'extension=php_sqlite3.dll');
    if (!extension_loaded('pdo_sqlite')) array_push($upgradeCommand, '-d', 'extension=php_pdo_sqlite.dll');
}
array_push($upgradeCommand, '-r', 'require $argv[1]; db(); echo (int)db()->query("SELECT 1 FROM app_migrations WHERE version=7")->fetchColumn();', dirname(__DIR__) . '/bootstrap.php');
$upgradePipes = [];
$upgradeProcess = proc_open($upgradeCommand, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $upgradePipes, dirname(__DIR__), null);
if ($oldRestaurantDb === false) putenv('RESTAURANT_DB'); else putenv('RESTAURANT_DB=' . $oldRestaurantDb);
if (!is_resource($upgradeProcess)) throw new RuntimeException('Could not start a migration-v6 upgrade check.');
fclose($upgradePipes[0]);
$upgradeOutput = trim(stream_get_contents($upgradePipes[1]));
$upgradeError = trim(stream_get_contents($upgradePipes[2]));
fclose($upgradePipes[1]);
fclose($upgradePipes[2]);
$upgradeExit = proc_close($upgradeProcess);
check($upgradeExit === 0 && $upgradeOutput === '1', 'an existing migration-v6 database receives the v7 booking tables during bootstrap' . ($upgradeError !== '' ? ' (' . $upgradeError . ')' : ''));
foreach ([$upgradeDbPath,$upgradeDbPath.'-wal',$upgradeDbPath.'-shm'] as $fixtureFile) if (is_file($fixtureFile)) @unlink($fixtureFile);
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
$serverCommand = [PHP_BINARY, '-d', 'extension_dir=' . ini_get('extension_dir')];
if (PHP_OS_FAMILY === 'Windows') {
    if (!extension_loaded('sqlite3')) array_push($serverCommand, '-d', 'extension=php_sqlite3.dll');
    if (!extension_loaded('pdo_sqlite')) array_push($serverCommand, '-d', 'extension=php_pdo_sqlite.dll');
}
array_push($serverCommand, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__));
$server = proc_open($serverCommand, [0 => ['pipe','r'], 1 => ['file',$serverLog,'a'], 2 => ['file',$serverLog,'a']], $pipes, dirname(__DIR__), $env);
if ($previousWorkers === false) putenv('PHP_CLI_SERVER_WORKERS'); else putenv('PHP_CLI_SERVER_WORKERS=' . $previousWorkers);
if (!is_resource($server)) throw new RuntimeException('Could not start PHP test server.');
$cookie = '';
function check(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException('FAIL: ' . $message); $checks++; }
function request(string $path, array $fields = [], array $headers = []): array {
 global $base, $cookie;
 $headerLines = ['Accept: text/html,application/json'];
 if ($cookie !== '') $headerLines[] = 'Cookie: ' . $cookie;
 foreach ($headers as $k => $v) $headerLines[] = $k . ': ' . $v;
 $options = ['http' => ['method' => $fields ? 'POST' : 'GET', 'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0, 'header' => implode("\r\n", $headerLines), 'content' => $fields ? http_build_query($fields) : '', 'timeout' => 30]];
 if ($fields) $options['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
 $body = @file_get_contents($base . $path, false, stream_context_create($options));
 $responseHeaders = $http_response_header ?? [];
 foreach ($responseHeaders as $line) if (stripos($line, 'Set-Cookie: tokyo_session=') === 0) $cookie = explode(';', substr($line, 12))[0];
 preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $m);
 return [(int)($m[1] ?? 0), (string)$body, $responseHeaders];
}
function adminPost(array $fields): array {
 [$postedStatus] = request('/admin.php', $fields);
 if (in_array($postedStatus, [302,303], true)) {
  [$status,$body,$headers] = request('/admin.php');
  return [$status,$body,$headers,$postedStatus];
 }
 [$status,$body,$headers] = request('/admin.php', [], []);
 return [$status,$body,$headers,$postedStatus];
}
function startFakeSmtpServer(string $mode='accept'): array {
  $portFile=tempnam(sys_get_temp_dir(),'tokyo-smtp-port-'); $captureFile=tempnam(sys_get_temp_dir(),'tokyo-smtp-message-'); $connectedFile=$mode==='slow'?tempnam(sys_get_temp_dir(),'tokyo-smtp-connected-'):''; $releaseFile=$mode==='slow'?tempnam(sys_get_temp_dir(),'tokyo-smtp-release-'):''; @unlink($portFile); if($connectedFile!=='')@unlink($connectedFile); if($releaseFile!=='')@unlink($releaseFile);
  $pipes=[]; $process=proc_open([PHP_BINARY,dirname(__DIR__).'/tests/fake-smtp-server.php',$portFile,$captureFile,$mode,$connectedFile,$releaseFile],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),null);
  if(!is_resource($process)) throw new RuntimeException('Could not start the local fake SMTP server.'); fclose($pipes[0]);
  $port=''; for($wait=0;$wait<300;$wait++){if(is_file($portFile)){$port=trim((string)file_get_contents($portFile));if($port!=='')break;} usleep(10000);}
  if($port===''||!ctype_digit($port)){proc_terminate($process);throw new RuntimeException('The local fake SMTP server did not become ready.');}
  return ['process'=>$process,'pipes'=>$pipes,'port'=>(int)$port,'port_file'=>$portFile,'capture_file'=>$captureFile,'connected_file'=>$connectedFile,'release_file'=>$releaseFile];
}
function stopFakeSmtpServer(array $server): string {
  if (($server['release_file']??'')!=='') @file_put_contents($server['release_file'],'release');
  foreach($server['pipes'] as $pipe) if(is_resource($pipe)) fclose($pipe);
  $exit=proc_close($server['process']); $capture=is_file($server['capture_file'])?(string)file_get_contents($server['capture_file']):'';
  foreach([$server['port_file'],$server['capture_file'],$server['connected_file']??'',$server['release_file']??''] as $file) if($file!==''&&is_file($file)) @unlink($file);
  if($exit!==0) throw new RuntimeException('The local fake SMTP server exited unexpectedly.');
  return $capture;
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
function parallelBookingRequests(array $fields, string $idempotencyKey, int $count): array {
 global $base, $cookie, $pdo;
 $body=http_build_query($fields);$sockets=[];$pdo->exec('BEGIN IMMEDIATE TRANSACTION');
 try {
  for($i=0;$i<$count;$i++){
   $errno=0;$error='';$socket=@stream_socket_client('tcp://'.parse_url($base,PHP_URL_HOST).':'.parse_url($base,PHP_URL_PORT),$errno,$error,5,STREAM_CLIENT_CONNECT);
   if(!$socket)throw new RuntimeException('Could not open concurrent booking socket: '.$error);
   $sockets[]=$socket;stream_set_timeout($socket,20);
   $headers="POST /api.php HTTP/1.1\r\nHost: 127.0.0.1\r\nAccept: application/json\r\nConnection: close\r\nContent-Type: application/x-www-form-urlencoded\r\nIdempotency-Key: {$idempotencyKey}\r\nContent-Length: ".strlen($body)."\r\n";
   if($cookie!=='')$headers.='Cookie: '.$cookie."\r\n";$request=$headers."\r\n".$body;$offset=0;
   while($offset<strlen($request)){$written=fwrite($socket,substr($request,$offset));if($written===false||$written===0)throw new RuntimeException('Could not write concurrent booking request.');$offset+=$written;}
  }
  if(PHP_OS_FAMILY!=='Windows')usleep(200000);$pdo->exec('COMMIT');
 }catch(Throwable $error){try{$pdo->exec('ROLLBACK');}catch(Throwable $ignored){}foreach($sockets as $socket)if(is_resource($socket))fclose($socket);throw $error;}
 $responses=[];foreach($sockets as $socket){$raw=stream_get_contents($socket);fclose($socket);if(!preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/m',$raw,$match))throw new RuntimeException('Malformed concurrent booking response: '.substr($raw,0,200));$body=substr($raw,strpos($raw,"\r\n\r\n")+4);$responses[]=[(int)$match[1],$body];}
 return $responses;
}
function parallelMailDeliveries(int $outboxId, int $count, array $fakeSmtp): array {
 if ($count !== 2 || ($fakeSmtp['connected_file'] ?? '') === '' || ($fakeSmtp['release_file'] ?? '') === '') {
  throw new RuntimeException('Parallel SMTP delivery requires two workers and a gated fake server.');
 }
 $script = 'require $argv[1]; db(); echo booking_mail_deliver(db(),(int)$argv[2]);';
 $startWorker = static function () use ($script, $outboxId): array {
  $command = [PHP_BINARY];
  if (PHP_OS_FAMILY === 'Windows') {
   array_push($command, '-d', 'extension_dir=' . ini_get('extension_dir'));
   if (!extension_loaded('sqlite3')) array_push($command, '-d', 'extension=php_sqlite3.dll');
   if (!extension_loaded('pdo_sqlite')) array_push($command, '-d', 'extension=php_pdo_sqlite.dll');
  }
  array_push($command, '-r', $script, dirname(__DIR__) . '/bootstrap.php', (string)$outboxId);
  $pipes = [];
  $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__), null);
  if (!is_resource($process)) throw new RuntimeException('Could not start parallel SMTP worker.');
  fclose($pipes[0]);
  return ['process'=>$process,'pipes'=>$pipes,'stdout'=>'','stderr'=>''];
 };
 $waitForWorker = static function (array &$worker, int $timeoutMs): bool {
  stream_set_blocking($worker['pipes'][1], false);
  $deadline = microtime(true) + ($timeoutMs / 1000);
  while (!feof($worker['pipes'][1]) && microtime(true) < $deadline) {
   $chunk = fread($worker['pipes'][1], 4096);
   if ($chunk !== false) $worker['stdout'] .= $chunk;
   usleep(10000);
  }
  return feof($worker['pipes'][1]);
 };
 $collectWorker = static function (array &$worker): array {
  foreach ([1=>'stdout',2=>'stderr'] as $index=>$key) {
   if (is_resource($worker['pipes'][$index])) {
    $worker[$key] .= stream_get_contents($worker['pipes'][$index]);
    fclose($worker['pipes'][$index]);
   }
  }
  return [proc_close($worker['process']), trim($worker['stdout']), trim($worker['stderr'])];
 };
 $workers = [$startWorker()];
 $connected = false;
 for ($wait=0; $wait<500; $wait++) {
  if (is_file($fakeSmtp['connected_file'])) { $connected = true; break; }
  usleep(10000);
 }
 if (!$connected) {
  @file_put_contents($fakeSmtp['release_file'], 'release');
  [$exit,$stdout,$stderr] = $collectWorker($workers[0]);
  throw new RuntimeException('First SMTP worker did not reach the fake server (exit '.$exit.'; stdout='.$stdout.'; stderr='.$stderr.').');
 }
 $workers[] = $startWorker();
 $secondFinished = $waitForWorker($workers[1], 5000);
 @file_put_contents($fakeSmtp['release_file'], 'release');
 $results = [];
 foreach ($workers as $index=>&$worker) {
  $workerFinished = $index === 1 && $secondFinished ? true : $waitForWorker($worker, 10000);
  [$exit,$stdout,$stderr] = $collectWorker($worker);
  if (!$workerFinished || $exit !== 0) throw new RuntimeException('Parallel SMTP worker '.$index.' failed (finished='.($workerFinished?'yes':'no').'; exit='.$exit.'; stdout='.$stdout.'; stderr='.$stderr.').');
  $results[] = $stdout;
 }
 unset($worker);
 if (count(array_filter($results, static fn(string $state): bool => $state === 'sending')) !== 1) {
  throw new RuntimeException('Second SMTP worker did not observe the active lease before release (results='.json_encode($results).').');
 }
 return $results;
}
function tokenFrom(string $html): string { if (!preg_match('/name="csrf" value="([^"]+)"/', $html, $m)) throw new RuntimeException('CSRF token not found.'); return html_entity_decode($m[1], ENT_QUOTES); }
function freshTestDatabase(string $path): PDO {
 $connection = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $connection->exec('PRAGMA foreign_keys=ON'); $connection->exec('PRAGMA busy_timeout=15000');
 return $connection;
}
function testDatabaseValue(string $path, string $sql, array $parameters=[]): mixed {
 $connection=freshTestDatabase($path); $query=$connection->prepare($sql); $query->execute($parameters); $value=$query->fetchColumn(); $query->closeCursor();
 return $value;
}
function setTestOrderStatus(string $path, int $orderId, string $status): void {
 commerce_set_order_status(freshTestDatabase($path), $orderId, $status);
}
function testLoyaltyBalance(string $path, int $userId): int {
 return commerce_get_loyalty_balance(freshTestDatabase($path), $userId);
}
try {
 $ready = false;
 for ($i=0; $i<30; $i++) { [$status] = request('/admin.php'); if ($status === 200) { $ready = true; break; } usleep(100000); }
 check($ready, 'PHP server starts');
 [$status,$html] = request('/admin.php'); check($status===200 && str_contains($html,'Вход в панель'), 'admin login page available'); $csrf=tokenFrom($html);
 [$status,$loginBody,$loginHeaders] = request('/admin.php',['csrf'=>$csrf,'action'=>'login','email'=>'integration@example.test','password'=>$password]);
 check($status===302, 'admin login succeeds' . ($status===302 ? '' : ' (HTTP '.$status.'; response '.substr(strip_tags($loginBody),0,180).'; headers '.implode(' | ',$loginHeaders).')'));
 [$status,$html] = request('/admin.php'); check($status===200 && str_contains($html,'Панель сайта') && str_contains($html,'Поиск по панели') && str_contains($html,'Уведомления'), 'authenticated admin loads search and notification center'); $csrf=tokenFrom($html);
 $save=['csrf'=>$csrf,'action'=>'dish-save','category'=>'rolls','name'=>'Тестовый ролл','description'=>'Описание для проверки','weight'=>'200 г','price'=>'3450','image_url'=>'assets/food/01-philadelphia.webp','sort_order'=>'99','active'=>'on'];
 [$status,$html,$headers,$postStatus]=adminPost($save); check($status===200 && $postStatus===302 && str_contains($html,'Блюдо добавлено в меню'), 'dish create uses PRG and accepts an existing local webp');
 $dish=(int)$pdo->query("SELECT id FROM dishes WHERE name='Тестовый ролл'")->fetchColumn(); check($dish>0, 'created dish persisted');
 $csrf=tokenFrom($html);
 [$status,$html]=adminPost(['csrf'=>$csrf,'action'=>'admin-search','q'=>'тЕСТовый']);
 check($status===200 && str_contains($html,'Тестовый ролл') && str_contains($html,'Результаты по запросу'), 'admin search finds Russian text regardless of case');
 $csrf=tokenFrom($html); $edit=$save; $edit['id']=(string)$dish; $edit['description']='Обновлённое описание'; $edit['price']='3750'; $edit['image_url']='https://images.unsplash.com/photo-1?auto=format&fit=crop&w=800'; $edit['active']='on';
 [$status,$html]=adminPost($edit); check(str_contains($html,'Изменения блюда сохранены'), 'dish edit accepts approved HTTPS image');
 $row=$pdo->query('SELECT description,price,image_url FROM dishes WHERE id='.$dish)->fetch(PDO::FETCH_ASSOC); check($row['description']==='Обновлённое описание' && (int)$row['price']===3750 && str_starts_with($row['image_url'],'https://images.unsplash.com/'), 'description, price, image update persisted');
 foreach (['/../bootstrap.php','//evil.example/x.webp','javascript:alert(1)','https://evil.example/x.webp','https://images.unsplash.com/../../x.webp'] as $bad) { $csrf=tokenFrom($html); $badSave=$edit; $badSave['csrf']=$csrf; $badSave['image_url']=$bad; [$status,$html]=request('/admin.php',$badSave); check(str_contains($html,'Проверьте название, категорию, цену и фото'), 'reject unsafe image: '.$bad); }
 $csrf=tokenFrom($html); $hide=$edit; $hide['csrf']=$csrf; unset($hide['active']); [$status,$html]=adminPost($hide); check(str_contains($html,'Изменения блюда сохранены'), 'dish can be hidden'); check((int)$pdo->query('SELECT active FROM dishes WHERE id='.$dish)->fetchColumn()===0, 'hidden flag persisted');
 [$status,$json]=request('/api.php',['csrf'=>$csrf,'type'=>'order','name'=>'Тестовый гость','phone'=>'+77010000000','fulfillment'=>'pickup','items'=>json_encode([['id'=>$dish,'qty'=>1,'price'=>1]])]); check($status===422, 'hidden dish cannot be ordered');
 $csrf=tokenFrom($html); $edit['csrf']=$csrf; $edit['image_url']='assets/food/01-philadelphia.webp'; $edit['active']='on'; [$status,$html]=adminPost($edit); check(str_contains($html,'Изменения блюда сохранены'), 'dish can be reactivated'); $csrf=tokenFrom($html);
 $demoQuote=commerce_quote($pdo,[['id'=>$dish,'qty'=>2]],[],'DEMO10','pickup',null,commerce_actor_key(null,session_id())); check($demoQuote['ok']&&$demoQuote['discount_total']===750&&$demoQuote['total']===6750, 'server promo quote recalculates the current catalog price and cap');
 $invalidQuote=commerce_quote($pdo,[['id'=>$dish,'qty'=>1]],[],'NOT-A-CODE','pickup',null,commerce_actor_key(null,session_id())); check(!$invalidQuote['ok']&&str_contains($invalidQuote['message'],'Промокод не найден'), 'unknown promo code is rejected by server quote validation');
 $setCandidates=$pdo->query('SELECT id,price FROM dishes WHERE active=1 AND id NOT IN (17,18) ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_ASSOC); check(count($setCandidates)===3,'custom-set integration fixture has three active public dishes');
 $setItems=[]; $setExpectedSubtotal=0; foreach($setCandidates as $index=>$candidate){$quantity=$index===0?2:1;$setItems[]=['id'=>(int)$candidate['id'],'qty'=>$quantity,'price'=>1];$setExpectedSubtotal+=(int)$candidate['price']*$quantity;}
 $serverSet=['variant'=>'signature','items'=>$setItems]; $setQuote=commerce_quote($pdo,[],[$serverSet],'','pickup',null,commerce_actor_key(null,session_id())); check($setQuote['ok']&&$setQuote['subtotal']===$setExpectedSubtotal&&$setQuote['custom_sets'][0]['subtotal']===$setExpectedSubtotal,'custom-set quote ignores forged client prices and recomputes each component from the active catalog');
 $tooFewSet=$serverSet; $tooFewSet['items']=array_slice($setItems,0,2); $tooManySet=$serverSet; $tooManySet['items'][0]['qty']=5; $duplicateSet=$serverSet; $duplicateSet['items'][1]['id']=$duplicateSet['items'][0]['id']; $unknownVariantSet=$serverSet; $unknownVariantSet['variant']='client-invented';
 foreach([['fewer than minimum distinct dishes',$tooFewSet],['quantity above the per-item limit',$tooManySet],['duplicate dish IDs',$duplicateSet],['unknown set variant',$unknownVariantSet]] as [$case,$invalidSet]){$quote=commerce_quote($pdo,[],[$invalidSet],'','pickup',null,commerce_actor_key(null,session_id()));check(!$quote['ok'],'server rejects custom set with '.$case);}
 $inactiveSetId=(int)$setCandidates[0]['id']; $pdo->prepare('UPDATE dishes SET active=0 WHERE id=?')->execute([$inactiveSetId]); $hiddenSetQuote=commerce_quote($pdo,[],[$serverSet],'','pickup',null,commerce_actor_key(null,session_id())); $pdo->prepare('UPDATE dishes SET active=1 WHERE id=?')->execute([$inactiveSetId]); check(!$hiddenSetQuote['ok']&&str_contains($hiddenSetQuote['message'],'недоступно'),'custom set cannot include a hidden or unavailable dish');
 $pdo->exec("UPDATE custom_set_variants SET active=0 WHERE slug='signature'"); $inactiveVariantQuote=commerce_quote($pdo,[],[$serverSet],'','pickup',null,commerce_actor_key(null,session_id())); $pdo->exec("UPDATE custom_set_variants SET active=1 WHERE slug='signature'"); check(!$inactiveVariantQuote['ok'],'custom set cannot use a server-disabled format');
 $setOrderFields=['csrf'=>$csrf,'type'=>'order','name'=>'Гость сета','phone'=>'+77010000000','fulfillment'=>'pickup','items'=>'[]','custom_sets'=>json_encode([$serverSet],JSON_UNESCAPED_UNICODE)];
 [$setStatus,$setBody]=request('/api.php',$setOrderFields,['Idempotency-Key'=>'integration-custom-set-order-0001']); $setOrderResult=json_decode($setBody,true); check($setStatus===201&&($setOrderResult['ok']??false)&&($setOrderResult['subtotal']??null)===$setExpectedSubtotal,'checkout accepts the valid custom set with a server-calculated total');
 $setOrderId=(int)$setOrderResult['id']; $setReadPdo=freshTestDatabase($dbPath); $setLineQuery=$setReadPdo->prepare('SELECT id,price,quantity FROM order_items WHERE order_id=? AND dish_id IS NULL'); $setLineQuery->execute([$setOrderId]); $setLine=$setLineQuery->fetch(PDO::FETCH_ASSOC); $setLineQuery->closeCursor(); check($setLine&&(int)$setLine['price']===$setExpectedSubtotal&&(int)$setLine['quantity']===1,'order stores the custom set as one line at the server-calculated price (expected '.(int)$setExpectedSubtotal.'; stored '.json_encode($setLine,JSON_UNESCAPED_UNICODE).')');
 $setComponentQuery=$setReadPdo->prepare('SELECT dish_id,price,quantity FROM order_custom_set_components WHERE order_item_id=? ORDER BY dish_id'); $setComponentQuery->execute([(int)$setLine['id']]); $setComponents=$setComponentQuery->fetchAll(PDO::FETCH_ASSOC); $setComponentQuery->closeCursor(); $setComponentSubtotal=array_sum(array_map(static fn(array $component):int=>(int)$component['price']*(int)$component['quantity'],$setComponents)); check(count($setComponents)===3&&$setComponentSubtotal===$setExpectedSubtotal,'order preserves all custom-set component price and quantity snapshots');
 $ordersBeforeInvalidSet=(int)freshTestDatabase($dbPath)->query('SELECT COUNT(*) FROM orders')->fetchColumn(); $invalidSetOrder=$setOrderFields; $invalidSetOrder['custom_sets']=json_encode([$tooFewSet],JSON_UNESCAPED_UNICODE); [$invalidSetStatus]=request('/api.php',$invalidSetOrder,['Idempotency-Key'=>'integration-invalid-custom-set-0001']); check($invalidSetStatus===422&&(int)freshTestDatabase($dbPath)->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$ordersBeforeInvalidSet,'checkout rejects an invalid custom set without creating a partial order');
 $orderFields=['csrf'=>$csrf,'type'=>'order','name'=>'Тестовый гость','phone'=>'+77010000000','fulfillment'=>'pickup','items'=>json_encode([['id'=>$dish,'qty'=>2,'price'=>1]])];
 [$status,$body]=request('/api.php',$orderFields,['Idempotency-Key'=>'integration-order-key-0001']); $first=json_decode($body,true); check($status===201 && $first['ok'], 'valid pickup order succeeds with no address');
 [$status,$body2]=request('/api.php',$orderFields,['Idempotency-Key'=>'integration-order-key-0001']); $second=json_decode($body2,true); check($status===201 && $second['id']===$first['id'], 'same idempotency key replays original order');
 $orderReadPdo=freshTestDatabase($dbPath); $order=$orderReadPdo->query('SELECT total FROM orders WHERE id='.(int)$first['id'])->fetchColumn(); check((int)$order===7500, 'server price used, client price ignored'); check((int)$orderReadPdo->query('SELECT COUNT(*) FROM orders WHERE id='.(int)$first['id'])->fetchColumn()===1, 'retry created no second order');
 unset($setReadPdo,$orderReadPdo,$pdo); gc_collect_cycles(); $pdo=freshTestDatabase($dbPath);
 $concurrentKey='integration-concurrent-order-'.bin2hex(random_bytes(8));
 $parallel=parallelOrderRequests($orderFields,$concurrentKey,6); $parallelIds=[];
 foreach($parallel as [$parallelStatus,$parallelBody]){$parallelResult=json_decode($parallelBody,true);check($parallelStatus===201&&$parallelResult['ok'],'concurrent same-key order request returns the committed result');$parallelIds[]=(int)$parallelResult['id'];}
 check(count(array_unique($parallelIds))===1,'six concurrent requests with one key resolve to one order id');
 $concurrentId=$parallelIds[0];$concurrentReadPdo=freshTestDatabase($dbPath);check((int)$concurrentReadPdo->query('SELECT COUNT(*) FROM orders WHERE id='.$concurrentId)->fetchColumn()===1,'concurrent key creates exactly one order');check((int)$concurrentReadPdo->query('SELECT COUNT(*) FROM order_items WHERE order_id='.$concurrentId)->fetchColumn()===1,'concurrent key creates one order line snapshot');
 $concurrentToken=hash('sha256',substr($cookie,strlen('tokyo_session=')).'|order|'.$concurrentKey);$idem=$concurrentReadPdo->prepare('SELECT order_id,response_status FROM api_order_requests WHERE token=?');$idem->execute([$concurrentToken]);$idemRow=$idem->fetch();$idem->closeCursor();check((int)$idemRow['order_id']===$concurrentId&&(int)$idemRow['response_status']===201,'idempotency reservation and replay result point to the one committed order');
 $rollbackKey='integration-rollback-order-'.bin2hex(random_bytes(8));$pdo->exec("CREATE TRIGGER test_fail_order_line BEFORE INSERT ON order_items BEGIN SELECT RAISE(ABORT, 'forced test rollback'); END");[$failedStatus]=request('/api.php',$orderFields,['Idempotency-Key'=>$rollbackKey]);check($failedStatus===500,'forced item insert error reports failure');$pdo->exec('DROP TRIGGER test_fail_order_line');
 $rollbackToken=hash('sha256',substr($cookie,strlen('tokyo_session=')).'|order|'.$rollbackKey);$rollbackReadPdo=freshTestDatabase($dbPath);$q=$rollbackReadPdo->prepare('SELECT COUNT(*) FROM api_order_requests WHERE token=?');$q->execute([$rollbackToken]);$reservationCount=(int)$q->fetchColumn();$q->closeCursor();check($reservationCount===0,'failed transaction leaves no idempotency reservation');check((int)$rollbackReadPdo->query("SELECT COUNT(*) FROM orders WHERE id NOT IN (".(int)$first['id'].','.$concurrentId.','.$setOrderId.')')->fetchColumn()===0,'failed transaction leaves no partial order');
 [$status,$rollbackRetryBody]=request('/api.php',$orderFields,['Idempotency-Key'=>$rollbackKey]);$rollbackRetry=json_decode($rollbackRetryBody,true);check($status===201&&$rollbackRetry['ok'],'same key can succeed after the failed transaction rolled back (HTTP '.$status.'): '.$rollbackRetryBody);check((int)freshTestDatabase($dbPath)->query('SELECT COUNT(*) FROM order_items WHERE order_id='.(int)$rollbackRetry['id'])->fetchColumn()===1,'successful retry persists exactly one item');
 $independentKey='integration-independent-order-'.bin2hex(random_bytes(8));[$status,$independentBody]=request('/api.php',$orderFields,['Idempotency-Key'=>$independentKey]);$independent=json_decode($independentBody,true);check($status===201&&$independent['ok']&&(int)$independent['id']!==$concurrentId,'independent idempotency key creates a separate order');
 $giftSlug='qa-threshold-'.bin2hex(random_bytes(4)); $q=$pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,description,reward_type,minimum_subtotal,maximum_discount,active,stackable,demo_only) VALUES(?,'gift_threshold','QA gift threshold','Integration-only gift','gift_dish',7500,0,1,0,1)"); $q->execute([$giftSlug]); $giftCampaignId=(int)$pdo->lastInsertId(); $pdo->prepare('INSERT INTO campaign_gift_options(campaign_id,dish_id,remaining_quantity,max_per_order,active) VALUES(?,?,1,1,1)')->execute([$giftCampaignId,2]);
 $suppressedGiftQuote=commerce_quote($pdo,[['id'=>$dish,'qty'=>2]],[],'DEMO10','pickup',null,commerce_actor_key(null,session_id())); check($suppressedGiftQuote['ok']&&$suppressedGiftQuote['discount_total']===750&&!$suppressedGiftQuote['gift_items'],'non-stackable promo does not silently combine with a threshold gift');
 $explicitGiftQuote=commerce_quote($pdo,[['id'=>$dish,'qty'=>2]],[],'DEMO10','pickup',null,commerce_actor_key(null,session_id()),2); check(!$explicitGiftQuote['ok']&&str_contains($explicitGiftQuote['message'],'нельзя сочетать'),'client cannot force a non-stackable gift with a promo');
 $pdo->exec("UPDATE promotion_campaigns SET stackable=1 WHERE slug='demo-privilege'"); $promoStackOnly=commerce_quote($pdo,[['id'=>$dish,'qty'=>2]],[],'DEMO10','pickup',null,commerce_actor_key(null,session_id())); check($promoStackOnly['ok']&&!$promoStackOnly['gift_items'],'both campaigns must allow stacking before a threshold gift is offered');
 $pdo->prepare('UPDATE promotion_campaigns SET stackable=1 WHERE id=?')->execute([$giftCampaignId]); $stackableGiftQuote=commerce_quote($pdo,[['id'=>$dish,'qty'=>2]],[],'DEMO10','pickup',null,commerce_actor_key(null,session_id())); check($stackableGiftQuote['ok']&&count($stackableGiftQuote['gift_items'])===1&&$stackableGiftQuote['discount_total']===750,'compatible campaigns can combine only when both explicitly allow stacking');
 $giftOrderFields=$orderFields; $giftOrderFields['promo_code']='DEMO10'; $giftOrderFields['gift_dish_id']='2'; [$status,$giftOrderBody]=request('/api.php',$giftOrderFields,['Idempotency-Key'=>'integration-gift-order-0001']); $giftOrder=json_decode($giftOrderBody,true); $giftDiscount=$giftOrder['discount_total']??null; check($status===201&&($giftOrder['ok']??false)&&is_numeric($giftDiscount)&&(float)$giftDiscount===750.0,'order persists a server-validated compatible promo and gift (HTTP '.$status.'; body '.substr((string)$giftOrderBody,0,500).')');
 // Read committed state through a new handle rather than a long-lived test
 // connection, which may retain a WAL snapshot from earlier quote assertions.
 unset($q, $pdo);
 $pdo = freshTestDatabase($dbPath);
 $stockQuery=$pdo->prepare('SELECT remaining_quantity FROM campaign_gift_options WHERE campaign_id=? AND dish_id=2'); $stockQuery->execute([$giftCampaignId]); check((int)$stockQuery->fetchColumn()===0,'gift stock decrements exactly once inside the committed order transaction'); $stockQuery->closeCursor(); $outOfStock=commerce_quote($pdo,[['id'=>$dish,'qty'=>2]],[],'','pickup',null,commerce_actor_key(null,session_id()),2); check(!$outOfStock['ok'],'gift cannot be selected after its stock is exhausted'); $pdo->exec("UPDATE promotion_campaigns SET stackable=0 WHERE slug='demo-privilege'");
 $savedCookie=$cookie; $cookie=''; [$status,$offersPage]=request('/offers.php'); check($status===200&&str_contains($offersPage,'data-tp-secret-unlock'),'unauthenticated guest can reach the Secret Menu access form'); $guestCsrf=tokenFrom($offersPage);
 [$status,$publicMenu]=request('/menu.php'); check($status===200&&!str_contains($publicMenu,'data-id="17"')&&!str_contains($publicMenu,'data-id="18"'),'locked Secret Menu positions are absent from public menu cards');
 [$status,$lockedDetail]=request('/pages.php?page=dish&id=17'); check($status===404&&str_contains($lockedDetail,'Блюдо не найдено'),'direct dish route does not reveal a locked Secret Menu item');
 $collectionId=(int)$pdo->query("SELECT id FROM seasonal_collections WHERE slug='autumn-in-kyoto'")->fetchColumn(); $pdo->prepare('INSERT OR IGNORE INTO seasonal_collection_items(collection_id,dish_id,sort_order) VALUES(?,?,999)')->execute([$collectionId,17]);
 [$status,$publicCollection]=request('/collection.php?slug=autumn-in-kyoto'); check($status===200&&!str_contains($publicCollection,'data-id="17"'),'public collection page filters a linked Secret Menu dish');
 [$status,$publicOffersJson]=request('/api.php?type=offers'); $publicOffersData=json_decode($publicOffersJson,true); $collectionItems=array_merge(...array_map(static fn(array $row):array=>$row['items']??[], $publicOffersData['collections']??[[]])); check($status===200&&!in_array(17,array_map(static fn(array $row):int=>(int)$row['id'],$collectionItems),true)&&!in_array(18,array_map(static fn(array $row):int=>(int)$row['id'],$collectionItems),true),'public offers API omits locked dishes nested in seasonal collections');
 $secretOrder=['csrf'=>$guestCsrf,'type'=>'order','name'=>'Гость меню','phone'=>'+77010000000','fulfillment'=>'pickup','items'=>json_encode([['id'=>17,'qty'=>1]]),'custom_sets'=>'[]'];
 [$status,$lockedSecretBody]=request('/api.php',$secretOrder); check($status===422&&str_contains($lockedSecretBody,'Secret Menu'),'direct dish ID cannot bypass locked Secret Menu access');
 [$status,$unlockBody]=request('/api.php',['csrf'=>$guestCsrf,'type'=>'secret_unlock','code'=>'SECRET']); $unlockResult=json_decode($unlockBody,true); check($status===200&&$unlockResult['ok']&&count($unlockResult['items'])>0,'valid Secret Menu code opens the server-validated selection');
 [$status,$unlockedMenu]=request('/menu.php'); check($status===200&&str_contains($unlockedMenu,'data-id="17"'),'authorized Secret Menu grant follows the browser through PHP page navigation');
 [$status,$secretDetail]=request('/pages.php?page=dish&id=17'); check($status===200&&str_contains($secretDetail,'Блюдо не найдено')===false,'server-granted Secret Menu item opens its detail route');
 $secretOrder['csrf']=$guestCsrf; [$status,$secretBody]=request('/api.php',$secretOrder,['Idempotency-Key'=>'integration-secret-order-0001']); $secretResult=json_decode($secretBody,true); check($status===201&&$secretResult['ok'],'server-side Secret Menu grant authorizes the restricted dish order without a client token');
 $categorySecretSlug='qa-secret-category-'.bin2hex(random_bytes(4)); $categorySecretDish=19; $categorySecretCategory=(string)$pdo->query('SELECT category FROM dishes WHERE id=19')->fetchColumn(); $pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,description,reward_type,active,demo_only) VALUES(?,'secret_menu','Category Secret QA','Category-targeted Secret Menu integration case','access',1,1)")->execute([$categorySecretSlug]); $categoryCampaign=(int)$pdo->lastInsertId(); $pdo->prepare("INSERT INTO promotion_targets(campaign_id,target_kind,target_key,excluded) VALUES(?,'category',?,0)")->execute([$categoryCampaign,$categorySecretCategory]); $pdo->prepare("INSERT INTO secret_menu_rules(campaign_id,access_kind,required_value,active) VALUES(?,'code','CATEGORY',1)")->execute([$categoryCampaign]); $pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active) VALUES(?,?,1)')->execute(['CATEGORY',$categoryCampaign]);
 check(in_array($categorySecretDish,commerce_secret_menu_restricted_dish_ids($pdo),true),'category-targeted Secret Menu campaign restricts every matching active dish');
 $categoryOrder=['csrf'=>$guestCsrf,'type'=>'order','name'=>'Гость категории','phone'=>'+77010000000','fulfillment'=>'pickup','items'=>json_encode([['id'=>$categorySecretDish,'qty'=>1]]),'custom_sets'=>'[]']; [$status,$categoryLocked]=request('/api.php',$categoryOrder); check($status===422&&str_contains($categoryLocked,'Secret Menu'),'category-targeted Secret Menu dish is rejected without its grant');
 check(in_array($categorySecretDish,array_map(static fn(array $row):int=>(int)$row['id'],commerce_secret_menu_dishes($pdo,null,'CATEGORY')),true),'category-targeted Secret Menu rule returns its allowed dish after valid code');
 $cookie=$savedCookie;
 [$status,$accountHtml]=request('/account.php'); $accountCsrf=tokenFrom($accountHtml); $accountEmail='qa+'.bin2hex(random_bytes(4)).'@example.test';
 [$status]=request('/account.php',['csrf'=>$accountCsrf,'action'=>'register','name'=>'Demo QA','email'=>$accountEmail,'password'=>'Strong-test-password-123']);
 [$status,$accountHtml]=request('/account.php');
 check($status===200 && str_contains($accountHtml,'Здравствуйте, Demo QA'), 'account registration creates a logged-in profile');
 $profileOrder=$orderFields; $profileOrder['csrf']=tokenFrom($accountHtml); $profileOrder['name']='Demo QA';
 [$status,$profileOrderBody]=request('/api.php',$profileOrder,['Idempotency-Key'=>'integration-profile-order-0001']); $profileOrderResult=json_decode($profileOrderBody,true);
 check($status===201 && $profileOrderResult['ok'], 'authenticated profile order succeeds');
 $userLookup=$pdo->prepare('SELECT id FROM users WHERE email=?'); $userLookup->execute([$accountEmail]); $profileUserId=(int)$userLookup->fetchColumn(); $userLookup->closeCursor(); check($profileUserId>0&&testLoyaltyBalance($dbPath,$profileUserId)===0,'new order does not earn loyalty points before completion');
 setTestOrderStatus($dbPath,(int)$profileOrderResult['id'],'completed'); $earnedBalance=testLoyaltyBalance($dbPath,$profileUserId); check($earnedBalance>0,'completed order earns server-configured loyalty points');
 setTestOrderStatus($dbPath,(int)$profileOrderResult['id'],'preparing'); setTestOrderStatus($dbPath,(int)$profileOrderResult['id'],'completed'); check(testLoyaltyBalance($dbPath,$profileUserId)===$earnedBalance,'re-entering completed without cancellation does not double-earn points');
 $nonStackableQuote=commerce_quote($pdo,[['id'=>$dish,'qty'=>2]],[],'DEMO10','pickup',$profileUserId,commerce_actor_key($profileUserId,session_id()),null,1); check(!$nonStackableQuote['ok']&&str_contains($nonStackableQuote['message'],'нельзя сочетать'), 'non-stackable discount cannot combine with loyalty redemption');
 $bonusSlug='qa-bonus-'.bin2hex(random_bytes(4)); $q=$pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,description,reward_type,reward_value,minimum_subtotal,maximum_discount,active,stackable,demo_only) VALUES(?,'exclusive','QA bonus points','Integration-only offer','bonus_points',50,0,0,1,1,1)"); $q->execute([$bonusSlug]); $bonusCampaignId=(int)$pdo->lastInsertId(); $bonusCode='QA'.strtoupper(bin2hex(random_bytes(4))); $pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active,maximum_uses,maximum_uses_per_user) VALUES(?,?,1,10,1)')->execute([$bonusCode,$bonusCampaignId]);
 $bonusQuote=commerce_quote($pdo,[['id'=>$dish,'qty'=>1]],[],$bonusCode,'pickup',$profileUserId,commerce_actor_key($profileUserId,session_id())); check($bonusQuote['ok']&&array_filter($bonusQuote['adjustments'],static fn(array $row):bool=>$row['type']==='bonus_points'&&(int)$row['amount']===50),'bonus-points promo quote discloses the points to be earned after completion');
 setTestOrderStatus($dbPath,(int)$profileOrderResult['id'],'cancelled'); check(testLoyaltyBalance($dbPath,$profileUserId)===0,'cancelling a completed order reverses its earned balance');
 setTestOrderStatus($dbPath,(int)$profileOrderResult['id'],'completed'); $restoredBalance=testLoyaltyBalance($dbPath,$profileUserId); check($restoredBalance===$earnedBalance,'restoring a cancelled order can re-earn points once');
 setTestOrderStatus($dbPath,(int)$profileOrderResult['id'],'ready'); setTestOrderStatus($dbPath,(int)$profileOrderResult['id'],'completed'); check(testLoyaltyBalance($dbPath,$profileUserId)===$restoredBalance,'restored completed order remains idempotent across later status toggles');
 $bonusOrder=$profileOrder; $bonusOrder['items']=json_encode([['id'=>$dish,'qty'=>1]]); $bonusOrder['promo_code']=$bonusCode; [$status,$bonusOrderBody]=request('/api.php',$bonusOrder,['Idempotency-Key'=>'integration-bonus-order-0001']); $bonusOrderResult=json_decode($bonusOrderBody,true); check($status===201&&$bonusOrderResult['ok'],'eligible signed-in guest can place an order using the bonus-points promo');
 setTestOrderStatus($dbPath,(int)$bonusOrderResult['id'],'completed'); $afterBonusBalance=testLoyaltyBalance($dbPath,$profileUserId); check($afterBonusBalance===$restoredBalance+50+(int)floor((int)$pdo->query('SELECT price FROM dishes WHERE id='.$dish)->fetchColumn()*5/100),'completed promo order awards both base and advertised bonus points');
 setTestOrderStatus($dbPath,(int)$bonusOrderResult['id'],'ready'); setTestOrderStatus($dbPath,(int)$bonusOrderResult['id'],'completed'); check(testLoyaltyBalance($dbPath,$profileUserId)===$afterBonusBalance,'bonus-points promo is not re-awarded after a completed status toggle');
 setTestOrderStatus($dbPath,(int)$bonusOrderResult['id'],'cancelled'); check(testLoyaltyBalance($dbPath,$profileUserId)===$restoredBalance,'cancelling the bonus-points order reverses base and campaign points');
 [$status,$history]=request('/account.php');
 check($status===200 && str_contains($history,'Тестовый ролл') && str_contains($history,'2 × 3 750') && str_contains($history,'Самовывоз'), 'profile history shows snapshot items, quantities, unit prices, line totals, and fulfillment');
 foreach ([0,21] as $qty) { $badOrder=$orderFields; $badOrder['items']=json_encode([['id'=>$dish,'qty'=>$qty]]); [$status]=request('/api.php',$badOrder); check($status===422, 'invalid quantity rejected: '.$qty); }
 $badOrder=$orderFields; $badOrder['items']='{"id":1,"qty":2}'; [$status]=request('/api.php',$badOrder); check($status===422, 'non-list item payload rejected');
 $badOrder=$orderFields; $badOrder['items']=json_encode([['id'=>999999,'qty'=>1]]); [$status]=request('/api.php',$badOrder); check($status===422, 'unknown dish rejected');
 $future=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
 $booking=['csrf'=>$csrf,'type'=>'booking','name'=>'Тестовый гость','email'=>'guest@example.test','phone'=>'+77010000000','guests'=>'2','date'=>'2026-02-31','time'=>'19:00','format'=>'Сезонный']; [$status]=request('/api.php',$booking); check($status===422, 'impossible booking date rejected');
  $badBooking=$booking;$badBooking['email']='not-an-email';[$status]=request('/api.php',$badBooking);check($status===422,'booking rejects an invalid guest email');
  $booking['date']=$future; [$status,$body]=request('/api.php',$booking,['Idempotency-Key'=>'integration-booking-key-0001']); $book=json_decode($body,true); check($status===201 && $book['ok'] && $book['mail_status']==='pending' && str_contains($book['message'],'SMTP'), 'valid booking is explicitly unconfirmed and queues email when SMTP is not configured'); check((string)testDatabaseValue($dbPath,'SELECT format FROM bookings WHERE id=?',[(int)$book['id']])==='Сезонный', 'selected Omakase format is saved with booking request');check((string)testDatabaseValue($dbPath,'SELECT guest_email FROM bookings WHERE id=?',[(int)$book['id']])==='guest@example.test','booking email is stored with the request');$requestMailId=(int)testDatabaseValue($dbPath,'SELECT id FROM email_outbox WHERE event_key=?',['booking:'.$book['id'].':request:guest']);check($requestMailId>0&&(string)testDatabaseValue($dbPath,'SELECT status FROM email_outbox WHERE id=?',[$requestMailId])==='pending','guest receipt is durably queued without SMTP');
 [$status,$adminHtml]=request('/admin.php'); $adminCsrf=tokenFrom($adminHtml);
 [$status,$adminHtml]=adminPost(['csrf'=>$adminCsrf,'action'=>'admin-search','q'=>'тЕСТовый гость']);
 check($status===200 && str_contains($adminHtml,'Тестовый гость') && str_contains($adminHtml,'Новые брони'), 'admin search finds a booking and shows actionable notification counts');
 $bookingCountBeforeRetry=(int)testDatabaseValue($dbPath,'SELECT COUNT(*) FROM bookings');$mailCountBeforeRetry=(int)testDatabaseValue($dbPath,'SELECT COUNT(*) FROM email_outbox WHERE booking_id=?',[(int)$book['id']]);[$status,$body]=request('/api.php',$booking,['Idempotency-Key'=>'integration-booking-key-0001']);check($status===201&&json_decode($body,true)['id']===$book['id']&&(int)testDatabaseValue($dbPath,'SELECT COUNT(*) FROM bookings')===$bookingCountBeforeRetry&&(int)testDatabaseValue($dbPath,'SELECT COUNT(*) FROM email_outbox WHERE booking_id=?',[(int)$book['id']])===$mailCountBeforeRetry,'booking retry atomically replays the original record and outbox without duplicates');
 $parallelBookingResponses=parallelBookingRequests($booking,'integration-booking-concurrent-0001',4);$parallelBookingIds=[];foreach($parallelBookingResponses as [$parallelStatus,$parallelBody]){$parallelResult=json_decode($parallelBody,true);if($parallelStatus!==201||empty($parallelResult['ok']))throw new RuntimeException('Concurrent booking request failed: HTTP '.$parallelStatus.' '.$parallelBody);$parallelBookingIds[]=(int)$parallelResult['id'];}$parallelBookingCount=(int)testDatabaseValue($dbPath,'SELECT COUNT(*) FROM bookings WHERE booking_date=? AND guest_email=?',[$future,'guest@example.test']);preg_match('/tokyo_session=([^;]+)/',$cookie,$sessionMatch);$parallelBookingToken=hash('sha256',($sessionMatch[1]??'').'|booking|integration-booking-concurrent-0001');$parallelBookingId=(int)testDatabaseValue($dbPath,'SELECT booking_id FROM api_booking_requests WHERE token=?',[$parallelBookingToken]);$parallelOutboxCount=(int)testDatabaseValue($dbPath,'SELECT COUNT(*) FROM email_outbox WHERE booking_id=?',[$parallelBookingId]);check(count(array_unique($parallelBookingIds))===1&&$parallelBookingCount===2&&$parallelBookingId===$parallelBookingIds[0]&&$parallelOutboxCount===1,'parallel duplicate booking submissions create one booking and one receipt (ids='.implode(',',$parallelBookingIds).'; rows='.$parallelBookingCount.'; outbox='.$parallelOutboxCount.')');
 $pdo->exec('DELETE FROM booking_email_rate_limits');$rateBooking=$booking;$rateBooking['email']='rate-limit@example.test';for($i=1;$i<=3;$i++){[$rateStatus,$rateBody]=request('/api.php',$rateBooking,['Idempotency-Key'=>'integration-rate-limit-'.$i.'-0001']);if($rateStatus!==201)throw new RuntimeException('Booking email limit rejected allowed request '.$i.': '.$rateBody);}$rateBooking['email']='rate-limit@example.test';[$rateStatus,$rateBody]=request('/api.php',$rateBooking,['Idempotency-Key'=>'integration-rate-limit-4-0001']);check($rateStatus===429&&str_contains($rateBody,'Слишком много заявок'),'booking email rate limit blocks repeat mail abuse after three requests per recipient per day');$pdo->exec('DELETE FROM booking_email_rate_limits');
  [$status,$adminHtml]=request('/admin.php'); $adminCsrf=tokenFrom($adminHtml);
  [$status,$adminHtml]=adminPost(['csrf'=>$adminCsrf,'action'=>'status','table'=>'bookings','id'=>(string)$book['id'],'status'=>'confirmed']);
  check($status===200 && str_contains($adminHtml,'Бронь подтверждена') && str_contains($adminHtml,'Email в очереди'), 'admin confirms a booking, shows its badge and reports that mail is queued without SMTP');$confirmationMailId=(int)testDatabaseValue($dbPath,'SELECT id FROM email_outbox WHERE event_key=?',['booking:'.$book['id'].':confirmed:guest']);check($confirmationMailId>0&&(string)testDatabaseValue($dbPath,'SELECT status FROM email_outbox WHERE id=?',[$confirmationMailId])==='pending','confirmation email is queued in the same status transition');check(str_contains($adminHtml,'Email · Подтверждение · В очереди')&&str_contains($adminHtml,'Повторить email'),'admin shows queue status and a CSRF-protected retry control');
  $retryCsrf=tokenFrom($adminHtml);[$status,$retryHtml]=adminPost(['csrf'=>$retryCsrf,'action'=>'mail-retry','id'=>(string)$confirmationMailId]);check($status===200&&str_contains($retryHtml,'Письмо осталось в очереди')&&(string)testDatabaseValue($dbPath,'SELECT status FROM email_outbox WHERE id=?',[$confirmationMailId])==='pending','manual retry remains queued and reports SMTP is not configured');
  $fake=startFakeSmtpServer();foreach(['TOKYO_SMTP_HOST'=>'127.0.0.1','TOKYO_SMTP_PORT'=>(string)$fake['port'],'TOKYO_SMTP_ENCRYPTION'=>'none','TOKYO_MAIL_FROM'=>'tokyo@example.test','TOKYO_MAIL_FROM_NAME'=>'Tokyo Premium'] as $key=>$value)putenv($key.'='.$value);check(booking_mail_configuration_state()==='ready','SMTP configuration accepts local test transport without external credentials');$sentState=booking_mail_deliver(freshTestDatabase($dbPath),$confirmationMailId);$captured=stopFakeSmtpServer($fake);check($sentState==='sent'&&(string)testDatabaseValue($dbPath,'SELECT status FROM email_outbox WHERE id=?',[$confirmationMailId])==='sent','SMTP worker marks confirmed booking email as sent only after 250 response');check(str_contains($captured,'To: <guest@example.test>')&&str_contains($captured,'Subject: =?UTF-8?B?')&&!str_contains($captured,'SMTP_PASSWORD'),'SMTP message contains the guest and encoded subject, never credentials');
  $fake=startFakeSmtpServer();putenv('TOKYO_SMTP_PORT='.(string)$fake['port']);$requestResults=booking_mail_process_pending(freshTestDatabase($dbPath),10);$captured=stopFakeSmtpServer($fake);check(($requestResults[$requestMailId]??'')==='sent'&&(string)testDatabaseValue($dbPath,'SELECT status FROM email_outbox WHERE id=?',[$requestMailId])==='sent','outbox worker sends the queued receipt after SMTP becomes available');
  $raceMailId=booking_mail_queue(freshTestDatabase($dbPath),(int)$book['id'],'qa:smtp-lock-race','guest@example.test','SMTP lock integration','Concurrent workers must claim this message once.');$fake=startFakeSmtpServer('slow');putenv('TOKYO_SMTP_PORT='.(string)$fake['port']);$raceStates=parallelMailDeliveries($raceMailId,2,$fake);$captured=stopFakeSmtpServer($fake);$raceFinalState=(string)testDatabaseValue($dbPath,'SELECT status FROM email_outbox WHERE id=?',[$raceMailId]);$raceAttempts=(int)testDatabaseValue($dbPath,'SELECT attempts FROM email_outbox WHERE id=?',[$raceMailId]);check($raceFinalState==='sent'&&$raceAttempts===1&&count(array_filter($raceStates,static fn(string $state):bool=>$state==='sent'))===1&&count(array_filter($raceStates,static fn(string $state):bool=>$state==='sending'))===1,'parallel SMTP workers respect the fresh UTC lock and make one delivery attempt (workers='.json_encode($raceStates).'; state='.$raceFinalState.'; attempts='.$raceAttempts.'; bytes='.strlen($captured).')');
  $failureMailId=booking_mail_queue(freshTestDatabase($dbPath),(int)$book['id'],'qa:smtp-failure','guest@example.test','SMTP retry integration','Test failure then retry.');$fake=startFakeSmtpServer('reject');putenv('TOKYO_SMTP_PORT='.(string)$fake['port']);$failed=booking_mail_deliver(freshTestDatabase($dbPath),$failureMailId);$captured=stopFakeSmtpServer($fake);check($failed==='failed'&&(string)testDatabaseValue($dbPath,'SELECT status FROM email_outbox WHERE id=?',[$failureMailId])==='failed','SMTP rejection leaves a failed, retryable outbox record');check((string)testDatabaseValue($dbPath,'SELECT last_error FROM email_outbox WHERE id=?',[$failureMailId])==='SMTP rejected message (code 550)','SMTP failure stores a safe response without raw server data');$fake=startFakeSmtpServer();putenv('TOKYO_SMTP_PORT='.(string)$fake['port']);$retried=booking_mail_retry(freshTestDatabase($dbPath),$failureMailId);$captured=stopFakeSmtpServer($fake);check($retried==='sent'&&(string)testDatabaseValue($dbPath,'SELECT status FROM email_outbox WHERE id=?',[$failureMailId])==='sent','failed booking email can be explicitly retried successfully');foreach(['TOKYO_SMTP_HOST','TOKYO_SMTP_PORT','TOKYO_SMTP_USER','TOKYO_SMTP_PASSWORD','TOKYO_SMTP_ENCRYPTION','TOKYO_SMTP_SECURE','TOKYO_MAIL_FROM','TOKYO_MAIL_FROM_NAME','TOKYO_BOOKING_NOTIFY_EMAIL'] as $key)putenv($key);
  [$status,$history]=request('/account.php');
  check($status===200 && str_contains($history,'Бронь подтверждена') && str_contains($history,'role="status"'), 'confirmed booking is clearly shown in the guest account');
  [$status,$marketingHtml]=request('/admin-marketing.php'); $marketingCsrf=tokenFrom($marketingHtml);
  check($status===200 && str_contains($marketingHtml,'Цели включения') && str_contains($marketingHtml,'dish:12, category:rolls'), 'marketing admin exposes campaign include and exclusion targeting');
  $campaignSlug='qa-target-'.bin2hex(random_bytes(4));
  $campaignFields=['csrf'=>$marketingCsrf,'action'=>'campaign-save','slug'=>$campaignSlug,'campaign_type'=>'exclusive','reward_type'=>'percent','title'=>'Проверка целей QA','description'=>'Проверка ограничений кампании.','reward_value'=>'10','gift_dish_id'=>'','gift_quantity'=>'0','minimum_subtotal'=>'0','maximum_discount'=>'1000','starts_at'=>'','ends_at'=>'','starts_time'=>'','ends_time'=>'','image_url'=>'assets/generated/room-main-hall.webp','required_tier'=>'','sort_order'=>'50','active'=>'on','target_include'=>'category:rolls','target_exclude'=>'dish:1'];
  [$status,$campaignResponse]=request('/admin-marketing.php',$campaignFields); check($status===200 && str_contains($campaignResponse,'Кампания создана как учебная демонстрация'), 'campaign create accepts validated target rules');
  $targetCampaignId=(int)testDatabaseValue($dbPath,'SELECT id FROM promotion_campaigns WHERE slug=?',[$campaignSlug]);
  $targetPdo=freshTestDatabase($dbPath); $targets=commerce_campaign_targets($targetPdo,$targetCampaignId); check(count($targets)===2, 'campaign target include and exclusion persist transactionally');
  check(commerce_item_is_targeted(['dish'=>['id'=>$dish,'category'=>'rolls']],$targets), 'included category allows matching catalog item');
  check(!commerce_item_is_targeted(['dish'=>['id'=>1,'category'=>'rolls']],$targets), 'excluded dish overrides an included category');
  [$status,$marketingHtml]=request('/admin-marketing.php'); $marketingCsrf=tokenFrom($marketingHtml);
  $invalidTargets=$campaignFields; $invalidTargets['csrf']=$marketingCsrf; $invalidTargets['id']=(string)$targetCampaignId; $invalidTargets['target_include']='dish:999999';
  [$status,$invalidCampaignHtml]=request('/admin-marketing.php',$invalidTargets); check($status===200 && str_contains($invalidCampaignHtml,'Цель кампании не найдена'), 'unknown campaign targets are rejected by server validation');
  check(count(commerce_campaign_targets(freshTestDatabase($dbPath),$targetCampaignId))===2, 'invalid target update leaves existing campaign rules unchanged');
  [$status,$adminHtml]=request('/admin.php'); $csrf=tokenFrom($adminHtml);
  adminPost(['csrf'=>$csrf,'action'=>'dish-delete','id'=>(string)$dish]); check((int)testDatabaseValue($dbPath,'SELECT COUNT(*) FROM dishes WHERE id=?',[$dish])===0, 'dish deleted'); check((int)testDatabaseValue($dbPath,'SELECT COUNT(*) FROM order_items WHERE order_id=? AND item_name=?',[(int)$first['id'],'Тестовый ролл'])===1, 'order item snapshot survives dish deletion');
 echo 'Concurrent HTTP mode: ' . (PHP_OS_FAMILY==='Windows'?'client requests queued by single-worker PHP CLI server':'4-worker PHP CLI server') . "\n";
 echo "PASS: {$checks} integration assertions; isolated DB: {$dbPath}\n";
} catch (Throwable $ex) {
 if (is_file($serverLog)) fwrite(STDERR, "\nPHP test server log:\n" . file_get_contents($serverLog) . "\n");
 throw $ex;
} finally {
 proc_terminate($server); foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe); proc_close($server);
 foreach ([$dbPath,$dbPath.'-wal',$dbPath.'-shm',$serverLog,$initLockPath,$initGatePath] as $file) if (is_file($file)) @unlink($file);
}
