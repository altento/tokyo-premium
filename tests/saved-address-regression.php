<?php
declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SKIP: pdo_sqlite extension is required.\n");
    exit(77);
}
$dbPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-saved-address-'.bin2hex(random_bytes(6)).'.sqlite';
$upgradeDbPath = null;
$originalRestaurantDb = getenv('RESTAURANT_DB');
$pdo = null;
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    $assertions++;
};

try {
    putenv('RESTAURANT_DB='.$dbPath);
    require dirname(__DIR__).'/bootstrap.php';
    $pdo = db();
    $assert((bool)$pdo->query('SELECT 1 FROM app_migrations WHERE version=8')->fetchColumn(), 'bootstrap records the additive saved-address migration');
    $assert((bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='index' AND name='idx_user_addresses_one_default'")->fetchColumn(), 'database enforces one default address per user');

    $create = $pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)');
    $create->execute(['address-owner+'.bin2hex(random_bytes(4)).'@example.test','Address Owner',password_hash('test-only',PASSWORD_DEFAULT)]);
    $owner = (int)$pdo->lastInsertId();
    $create->execute(['address-other+'.bin2hex(random_bytes(4)).'@example.test','Other Owner',password_hash('test-only',PASSWORD_DEFAULT)]);
    $other = (int)$pdo->lastInsertId();

    $home = commerce_save_user_address($pdo,$owner,'Дом','Кызылорда, улица Тестовая, 10');
    $first = commerce_user_addresses($pdo,$owner);
    $assert(count($first)===1&&(int)$first[0]['id']===$home&&(int)$first[0]['is_default']===1, 'first saved address becomes the account default');
    $work = commerce_save_user_address($pdo,$owner,'Работа','Кызылорда, улица Садовая, 24');
    $assert(count(commerce_user_addresses($pdo,$owner))===2&&(int)commerce_user_addresses($pdo,$owner)[1]['is_default']===0, 'additional address is stored without replacing the default');
    commerce_set_user_address_default($pdo,$owner,$work);
    $assert((int)commerce_user_addresses($pdo,$owner)[0]['id']===$work&&(int)commerce_user_addresses($pdo,$owner)[0]['is_default']===1&&(int)$pdo->query('SELECT COUNT(*) FROM user_addresses WHERE user_id='.$owner.' AND is_default=1')->fetchColumn()===1, 'default selection is atomic and unique');

    $crossAccountRejected=false;
    try { commerce_set_user_address_default($pdo,$other,$home); } catch (DomainException) { $crossAccountRejected=true; }
    $assert($crossAccountRejected&&commerce_user_addresses($pdo,$other)===[], 'another account cannot select or view the owner’s saved address');
    commerce_delete_user_address($pdo,$owner,$work);
    $remaining=commerce_user_addresses($pdo,$owner);
    $assert(count($remaining)===1&&(int)$remaining[0]['id']===$home&&(int)$remaining[0]['is_default']===1, 'deleting the default promotes the remaining address');
    commerce_save_user_address($pdo,$owner,'Дом','Кызылорда, улица Новая, 5',$home);
    $assert(commerce_user_addresses($pdo,$owner)[0]['address']==='Кызылорда, улица Новая, 5', 'an address can be edited without creating a duplicate');

    $wrongOwnerEditRejected=false;
    try { commerce_save_user_address($pdo,$other,'Чужой адрес','Кызылорда, улица Чужая, 1',$home); } catch (DomainException) { $wrongOwnerEditRejected=true; }
    $assert($wrongOwnerEditRejected&&count(commerce_user_addresses($pdo,$owner))===1, 'an address ID cannot be edited by another account');
    for ($i=1;$i<10;$i++) commerce_save_user_address($pdo,$owner,'Адрес '.$i,'Кызылорда, улица Тестовая, '.($i+20));
    $limitRejected=false;
    try { commerce_save_user_address($pdo,$owner,'Адрес 11','Кызылорда, улица Тестовая, 99'); } catch (DomainException) { $limitRejected=true; }
    $assert($limitRejected&&count(commerce_user_addresses($pdo,$owner))===10, 'account address count is capped at ten');
    $invalidRejected=false;
    try { commerce_save_user_address($pdo,$owner,'X','A'); } catch (DomainException) { $invalidRejected=true; }
    $assert($invalidRejected&&count(commerce_user_addresses($pdo,$owner))===10, 'short invalid address values are rejected without a write');

    $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)');
    $pdo = null;
    $upgradeDbPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tokyo-address-v6-upgrade-'.bin2hex(random_bytes(6)).'.sqlite';
    if (!copy($dbPath,$upgradeDbPath)) throw new RuntimeException('Could not create a v6 schema-upgrade fixture.');
    $fixture = new PDO('sqlite:'.$upgradeDbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $fixture->exec('DROP TABLE user_addresses');
    $fixture->exec('DROP TABLE api_booking_requests');
    $fixture->exec('DROP TABLE booking_email_rate_limits');
    $fixture->exec('DELETE FROM app_migrations WHERE version>=7');
    $fixture = null;
    $upgradeCommand = [PHP_BINARY];
    if (PHP_OS_FAMILY === 'Windows') {
        array_push($upgradeCommand,'-d','extension_dir='.ini_get('extension_dir'));
        array_push($upgradeCommand,'-d','extension=php_sqlite3.dll','-d','extension=php_pdo_sqlite.dll');
    }
    $upgradeBootstrap=var_export(dirname(__DIR__).'/bootstrap.php',true);
    $upgradeCode='require '.$upgradeBootstrap.'; $db=db(); $q=$db->prepare("SELECT 1 FROM sqlite_master WHERE type=? AND name=?"); $q->execute(["table","user_addresses"]); echo (int)$db->query("SELECT 1 FROM app_migrations WHERE version=8")->fetchColumn().(int)$q->fetchColumn();';
    array_push($upgradeCommand,'-r',$upgradeCode);
    putenv('RESTAURANT_DB='.$upgradeDbPath);
    $upgradePipes=[];
    $upgradeProcess=proc_open($upgradeCommand,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$upgradePipes,dirname(__DIR__),null);
    putenv('RESTAURANT_DB='.$dbPath);
    if (!is_resource($upgradeProcess)) throw new RuntimeException('Could not start a migration-v6 fixture check.');
    fclose($upgradePipes[0]);
    $upgradeOutput=trim(stream_get_contents($upgradePipes[1]));
    $upgradeError=trim(stream_get_contents($upgradePipes[2]));
    fclose($upgradePipes[1]); fclose($upgradePipes[2]);
    $upgradeExit=proc_close($upgradeProcess);
    $assert($upgradeExit===0&&$upgradeOutput==='11','an existing v6 database receives migrations 7 and 8 during bootstrap (exit='.$upgradeExit.', output='.$upgradeOutput.', error='.$upgradeError.')');

    echo "PASS: {$assertions} focused saved-address assertions.\n";
} finally {
    $pdo = null;
    if ($originalRestaurantDb === false) putenv('RESTAURANT_DB'); else putenv('RESTAURANT_DB='.$originalRestaurantDb);
    foreach ([$dbPath,$dbPath.'-wal',$dbPath.'-shm',$upgradeDbPath,$upgradeDbPath ? $upgradeDbPath.'-wal' : null,$upgradeDbPath ? $upgradeDbPath.'-shm' : null] as $file) if ($file && is_file($file)) @unlink($file);
}
