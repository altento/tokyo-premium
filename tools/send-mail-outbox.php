<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

$limit = isset($argv[1]) && filter_var($argv[1], FILTER_VALIDATE_INT) !== false
    ? max(1, min(100, (int)$argv[1]))
    : 25;
$configuration = booking_mail_configuration_state();
if ($configuration === 'not_configured') {
    echo "SMTP is not configured; queued booking emails were left untouched.\n";
    exit(0);
}
if ($configuration !== 'ready') {
    fwrite(STDERR, "SMTP configuration is invalid; check the deployment environment variables.\n");
    exit(2);
}
$results = booking_mail_process_pending(db(), $limit);
$counts = ['sent'=>0,'failed'=>0,'pending'=>0,'sending'=>0,'missing'=>0];
foreach ($results as $status) $counts[$status] = ($counts[$status] ?? 0) + 1;
echo sprintf("Processed %d booking emails: sent=%d failed=%d pending=%d sending=%d.\n", count($results), $counts['sent'], $counts['failed'], $counts['pending'], $counts['sending']);
exit($counts['failed'] > 0 ? 1 : 0);
