<?php
declare(strict_types=1);

/** The optional clock is for internal callers/tests, never request input. */
function booking_visit_time_is_valid(string $date, string $time, ?DateTimeImmutable $now = null): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
        || !preg_match('/^(12|13|14|17|18|19|20|21):00$/D', $time)) return false;

    $zone = new DateTimeZone('Asia/Qyzylorda');
    $now = $now === null ? new DateTimeImmutable('now', $zone) : $now->setTimezone($zone);
    $visit = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, $zone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$visit || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
        || $visit->format('Y-m-d H:i') !== $date.' '.$time) return false;

    return $visit > $now
        && $visit->setTime(0, 0) <= $now->setTime(0, 0)->modify('+365 days');
}
