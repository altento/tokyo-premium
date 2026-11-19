<?php
declare(strict_types=1);

/** Read only checked-in floor-plan metadata; no XML/DOM extension is required. */
function booking_tables(): array
{
    static $tables = null;
    if ($tables !== null) return $tables;
    $svg = file_get_contents(dirname(__DIR__).'/assets/seat-plan.svg');
    if ($svg === false) throw new RuntimeException('Booking floor plan could not be read.');
    preg_match_all('/<g\b[^>]*>/u', $svg, $tags);
    $result = [];
    foreach ($tags[0] as $tag) {
        preg_match_all('/\s(data-tp-table|data-tp-zone|data-capacity)\s*=\s*([\'"])(.*?)\2/su', $tag, $matches, PREG_SET_ORDER);
        $attributes = [];
        foreach ($matches as $match) $attributes[$match[1]] = html_entity_decode($match[3], ENT_QUOTES | ENT_XML1, 'UTF-8');
        if (!isset($attributes['data-tp-table'])) continue;
        $id = $attributes['data-tp-table'];
        $zone = trim($attributes['data-tp-zone'] ?? '');
        $capacity = $attributes['data-capacity'] ?? '';
        if (!preg_match('/^[1-9]\d*$/D', $id) || $zone === '' || !preg_match('/^[1-9]\d*$/D', $capacity) || isset($result[$id])) {
            throw new RuntimeException('Booking floor plan has invalid table metadata.');
        }
        $result[$id] = ['id'=>(int)$id, 'zone'=>$zone, 'capacity'=>(int)$capacity];
    }
    if (!$result) throw new RuntimeException('Booking floor plan contains no tables.');
    ksort($result, SORT_NUMERIC);
    return $tables = $result;
}

/** A table is a preference, so guest count does not enforce its drawn capacity. */
function booking_table_preferences(string $zone, string $table): array
{
    $zone = trim($zone, " \t\n\r\v");
    $table = trim($table, " \t\n\r\v");
    $aliases = ['Суши-стойка'=>'Суши-бар', 'Камерная комната'=>'Приватная комната', 'Зал дегустации'=>'Летняя терраса'];
    $zone = $aliases[$zone] ?? $zone;
    $tables = booking_tables();
    if ($zone !== '' && !in_array($zone, array_column($tables, 'zone'), true)) throw new DomainException('Выберите зону из списка.');
    if ($table !== '') {
        if (!preg_match('/^[1-9]\d*$/D', $table) || !isset($tables[$table])) throw new DomainException('Проверьте предпочтительный стол.');
        $tableZone = $tables[$table]['zone'];
        if ($zone !== '' && $zone !== $tableZone) throw new DomainException('Выбранный стол не относится к указанной зоне.');
        $zone = $tableZone;
    }
    return ['zone'=>$zone, 'table'=>$table];
}
