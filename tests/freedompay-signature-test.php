<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/freedompay.php';

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$secret = 'test_secret_key';
$fields = [
    'pg_merchant_id'=>'12345','pg_order_id'=>'order_1001','pg_amount'=>'100',
    'pg_currency'=>'KZT','pg_description'=>'Order #1001','pg_salt'=>'a1b2c3d4',
];
$check(freedompay_signature('init_payment.php',$fields,$secret)==='b9ad75871668ca040f80f5f6b530e6ed','matches FreedomPay published signature vector');
$fields['pg_sig'] = freedompay_signature('init_payment.php',$fields,$secret);
$check(freedompay_signature_is_valid('init_payment.php',$fields,$secret),'accepts a valid callback signature');
$fields['pg_amount'] = '101';
$check(!freedompay_signature_is_valid('init_payment.php',$fields,$secret),'rejects a modified signed amount');
$check(freedompay_amount_to_minor_units('125.5')===12550&&freedompay_amount_to_minor_units('125.55')===12555,'converts KZT decimal values without float rounding');
$check(freedompay_amount_to_minor_units('-1')===null&&freedompay_amount_to_minor_units('1.001')===null,'rejects malformed amounts');
fwrite(STDOUT, 'FreedomPay signature checks passed: ' . $checks . PHP_EOL);
