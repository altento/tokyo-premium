<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pdo = db();
$adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
$notice = '';
$error = '';
$dateValue = static fn($value): string => trim((string)$value) === '' ? '' : trim((string)$value);
$localDateTime = static function ($value): ?string {
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $zone = new DateTimeZone('Asia/Qyzylorda');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $raw, $zone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d\\TH:i') !== $raw) {
        throw new DomainException('Укажите корректные местные дату и время.');
    }
    return $date->format('Y-m-d H:i:s');
};
$imageOptions = [
    'assets/generated/campaign-exclusive-v1.webp' => 'Tokyo Exclusive · сезонное нигири',
    'assets/generated/campaign-golden-hour-v1.webp' => 'Golden Hour · матча и подача',
    'assets/generated/campaign-chef-drop-v1.webp' => 'Chef’s Secret Drop · гребешок от шефа',
    'assets/generated/campaign-secret-menu-v1.webp' => 'Secret Menu · скрытая подача угря',
    'assets/generated/tokyo-seasonal-omakase-v2.webp' => 'Сезонная дегустация',
    'assets/generated/room-main-hall.webp' => 'Основной зал',
    'assets/generated/room-garden-terrace.webp' => 'Садовая терраса',
    'assets/generated/home-hero-nigiri-v2.webp' => 'Нигири · демонстрационный сюжет',
    'assets/food/17-omakase-set.webp' => 'Дегустационный сет'
];

function marketing_unicode_lower(string $value): string
{
    static $map = [
        'А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й',
        'К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф',
        'Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я',
        'Ә'=>'ә','Ғ'=>'ғ','Қ'=>'қ','Ң'=>'ң','Ө'=>'ө','Ұ'=>'ұ','Ү'=>'ү','Һ'=>'һ','І'=>'і',
    ];
    return strtr(strtolower($value), $map);
}

if (method_exists($pdo, 'createFunction')) {
    $pdo->createFunction('tp_marketing_lower', static fn($value): string => marketing_unicode_lower((string)($value ?? '')), 1);
} elseif (method_exists($pdo, 'sqliteCreateFunction')) {
    $pdo->sqliteCreateFunction('tp_marketing_lower', static fn($value): string => marketing_unicode_lower((string)($value ?? '')), 1);
}

if (!$adminId) {
    http_response_code(403);
    page_start('Marketing & Loyalty — Tokyo Premium', 'Управление учебными предложениями Tokyo Premium.');
    header_html();
    echo '<main class="admin-shell"><section class="admin-card"><span class="kicker">ДОСТУП ОГРАНИЧЕН</span><h1>Marketing &amp; Loyalty</h1><p>Войдите в административную панель, чтобы управлять кампаниями, кодами, коллекциями и демонстрационными уровнями.</p><a class="button" href="admin.php">Перейти ко входу администратора ↗</a></section></main>';
    footer_html();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['admin_marketing_notice'])) {
    $notice = (string)$_SESSION['admin_marketing_notice'];
    unset($_SESSION['admin_marketing_notice']);
}
$reopenCampaignId = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? (int)($_SESSION['admin_marketing_reopen_campaign_id'] ?? 0)
    : 0;
if ($_SERVER['REQUEST_METHOD'] === 'GET') unset($_SESSION['admin_marketing_reopen_campaign_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'campaign-save') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            $type = (string)($_POST['campaign_type'] ?? 'exclusive');
            $reward = (string)($_POST['reward_type'] ?? 'none');
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $value = filter_var($_POST['reward_value'] ?? 0, FILTER_VALIDATE_INT);
            $minimum = filter_var($_POST['minimum_subtotal'] ?? 0, FILTER_VALIDATE_INT);
            $maximum = filter_var($_POST['maximum_discount'] ?? 0, FILTER_VALIDATE_INT);
            $giftDishRaw = trim((string)($_POST['gift_dish_id'] ?? ''));
            $giftDishId = $giftDishRaw === '' ? null : filter_var($giftDishRaw, FILTER_VALIDATE_INT);
            $giftQuantity = filter_var($_POST['gift_quantity'] ?? 0, FILTER_VALIDATE_INT);
            $giftMaxPerOrder = filter_var($_POST['gift_max_per_order'] ?? 1, FILTER_VALIDATE_INT);
            $start = $localDateTime($_POST['starts_at'] ?? ''); $end = $localDateTime($_POST['ends_at'] ?? '');
            $timeStart = $dateValue($_POST['starts_time'] ?? ''); $timeEnd = $dateValue($_POST['ends_time'] ?? '');
            $image = (string)($_POST['image_url'] ?? 'assets/generated/room-main-hall.webp');
            $parseTargets = static function (string $raw, int $excluded) use ($pdo): array {
                if (strlen($raw) > 1200) throw new DomainException('Список условий для товаров слишком длинный.');
                $parts = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/u', $raw) ?: []), static fn(string $part): bool => $part !== '')));
                if (count($parts) > 40) throw new DomainException('В одной группе кампании можно указать не более 40 целей.');
                $rows = [];
                foreach ($parts as $part) {
                    if (!preg_match('/^(dish|category|set):(.+)$/', $part, $match)) throw new DomainException('Укажите цель в формате dish:12, category:rolls или set:signature.');
                    $kind = $match[1]; $key = strtolower($match[2]);
                    if (($kind === 'dish' && !preg_match('/^[1-9][0-9]{0,8}$/', $key)) || ($kind !== 'dish' && !preg_match('/^[a-z0-9][a-z0-9_-]{0,48}$/', $key))) throw new DomainException('Проверьте идентификатор товара, категорию или тип сета.');
                    if ($kind === 'dish') { $check = $pdo->prepare('SELECT 1 FROM dishes WHERE id=? AND active=1'); $check->execute([(int)$key]); }
                    elseif ($kind === 'category') { $check = $pdo->prepare('SELECT 1 FROM categories WHERE slug=?'); $check->execute([$key]); }
                    else { $check = $pdo->prepare('SELECT 1 FROM custom_set_variants WHERE slug=? AND active=1'); $check->execute([$key]); }
                    if (!$check->fetchColumn()) throw new DomainException('Цель кампании не найдена или не активна: ' . $part);
                    $rows[] = ['kind'=>$kind,'key'=>$key,'excluded'=>$excluded];
                }
                return $rows;
            };
            $targetRows = array_merge($parseTargets((string)($_POST['target_include'] ?? ''), 0), $parseTargets((string)($_POST['target_exclude'] ?? ''), 1));
            $targetKeys = [];
            foreach ($targetRows as $target) { $key = $target['kind'] . ':' . $target['key']; if (isset($targetKeys[$key])) throw new DomainException('Одна цель не может одновременно включаться и исключаться.'); $targetKeys[$key] = true; }
            if ($giftDishId === false || $giftQuantity === false || $giftQuantity < 0 || $giftQuantity > 10000 || $giftMaxPerOrder === false || $giftMaxPerOrder < 1 || $giftMaxPerOrder > 10000 || !preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/', $slug) || $title === '' || strlen($title) > 100 || strlen($description) > 1200 || !in_array($type, ['exclusive','golden_hour','chef_drop','secret_menu','birthday','personal','gift_threshold'], true) || !in_array($reward, ['percent','fixed','gift_dish','set_price','free_delivery','bonus_points','access','none'], true) || $value === false || $value < 0 || $minimum === false || $minimum < 0 || $maximum === false || $maximum < 0 || !isset($imageOptions[$image]) || ($start && $end && $end <= $start) || (($timeStart === '') !== ($timeEnd === '')) || ($type === 'golden_hour' && (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeStart) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeEnd)))) {
                throw new DomainException('Проверьте тип кампании, срок, текст, изображение и числовые значения.');
            }
            $rewardLimit = match ($reward) {
                'percent' => 100,
                'fixed', 'set_price' => 10000000,
                'bonus_points' => 1000000,
                default => 0,
            };
            if (($rewardLimit === 0 && $value !== 0)
                || ($rewardLimit > 0 && ($value < 1 || $value > $rewardLimit))
                || $maximum > 10000000
                || $giftMaxPerOrder > 10) {
                throw new DomainException('Проверьте значение награды: проценты 1–100, сумма/цена до 10 000 000 ₸, бонусы до 1 000 000; для других наград укажите 0. Максимум подарков на заказ — 10.');
            }
            $giftDishId = $giftDishId ?: null;
            if ($giftDishId) { $checkDish=$pdo->prepare('SELECT id FROM dishes WHERE id=? AND active=1'); $checkDish->execute([$giftDishId]); if (!$checkDish->fetchColumn()) throw new DomainException('Выберите активное блюдо для подарка.'); }
            if (in_array($reward, ['gift_dish'], true) && !$giftDishId) throw new DomainException('Выберите блюдо для подарка.');
            $values = [$slug,$type,$title,$description,$image,$reward,(int)$value,$giftDishId,(int)$minimum,(int)$maximum,$start ?: null,$end ?: null,$type === 'golden_hour' ? 62 : 127,$timeStart ?: null,$timeEnd ?: null,'Asia/Qyzylorda',trim((string)($_POST['required_tier'] ?? '')) ?: null,isset($_POST['active']) ? 1 : 0,isset($_POST['stackable']) ? 1 : 0,1,(int)($_POST['sort_order'] ?? 50)];
            $pdo->beginTransaction();
            if ($id) {
                $existingCampaign = $pdo->prepare('SELECT campaign_type,weekday_mask FROM promotion_campaigns WHERE id=?');
                $existingCampaign->execute([$id]);
                $existingCampaignData = $existingCampaign->fetch(PDO::FETCH_ASSOC);
                if (!$existingCampaignData) throw new DomainException('Кампания не найдена для редактирования.');
                if ($existingCampaignData['campaign_type']==='secret_menu' && $type!=='secret_menu') {
                    $ruleCheck=$pdo->prepare('SELECT 1 FROM secret_menu_rules WHERE campaign_id=? AND active=1 LIMIT 1');
                    $ruleCheck->execute([$id]);
                    if ($ruleCheck->fetchColumn()) throw new DomainException('Сначала отключите или перенесите правила Secret Menu, затем меняйте тип кампании.');
                }
                if ($existingCampaignData['campaign_type'] === $type) $values[12] = (int)$existingCampaignData['weekday_mask'];
                else $values[12] = $type === 'golden_hour' ? 62 : 127;
                $values[] = $id;
                $stmt = $pdo->prepare('UPDATE promotion_campaigns SET slug=?,campaign_type=?,title=?,description=?,image_url=?,reward_type=?,reward_value=?,gift_dish_id=?,minimum_subtotal=?,maximum_discount=?,starts_at=?,ends_at=?,weekday_mask=?,starts_time=?,ends_time=?,timezone=?,required_tier=?,active=?,stackable=?,demo_only=?,sort_order=? WHERE id=?');
                $stmt->execute($values); $savedCampaignId=$id; $notice = 'Кампания обновлена.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO promotion_campaigns(slug,campaign_type,title,description,image_url,reward_type,reward_value,gift_dish_id,minimum_subtotal,maximum_discount,starts_at,ends_at,weekday_mask,starts_time,ends_time,timezone,required_tier,active,stackable,demo_only,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute($values); $savedCampaignId=(int)$pdo->lastInsertId(); $notice = 'Кампания создана как учебная демонстрация.';
            }
            $pdo->prepare('DELETE FROM promotion_targets WHERE campaign_id=?')->execute([$savedCampaignId]);
            $insertTarget = $pdo->prepare('INSERT INTO promotion_targets(campaign_id,target_kind,target_key,excluded) VALUES(?,?,?,?)');
            foreach ($targetRows as $target) $insertTarget->execute([$savedCampaignId,$target['kind'],$target['key'],$target['excluded']]);
            if ($giftDishId && in_array($reward, ['gift_dish'], true)) {
                $stock = $pdo->prepare('INSERT INTO campaign_gift_options(campaign_id,dish_id,remaining_quantity,max_per_order,active) VALUES(?,?,?,?,1) ON CONFLICT(campaign_id,dish_id) DO UPDATE SET remaining_quantity=excluded.remaining_quantity,max_per_order=excluded.max_per_order,active=1');
                $stock->execute([$savedCampaignId,$giftDishId,(int)$giftQuantity,(int)$giftMaxPerOrder]);
            }
            $pdo->commit();
        } elseif ($action === 'campaign-toggle') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) throw new DomainException('Не найдена кампания.');
            $stmt = $pdo->prepare('UPDATE promotion_campaigns SET active=CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id=?'); $stmt->execute([$id]);
            $notice = 'Статус кампании изменён.';
        } elseif ($action === 'promo-save') {
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $campaignId = filter_var($_POST['campaign_id'] ?? null, FILTER_VALIDATE_INT);
            $maxUses = trim((string)($_POST['maximum_uses'] ?? ''));
            $maxPerUser = trim((string)($_POST['maximum_uses_per_user'] ?? ''));
            if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,30}$/', $code) || !$campaignId || ($maxUses !== '' && (!ctype_digit($maxUses) || (int)$maxUses < 1)) || ($maxPerUser !== '' && (!ctype_digit($maxPerUser) || (int)$maxPerUser < 1))) throw new DomainException('Проверьте код и лимиты применений.');
            $campaign = $pdo->prepare('SELECT id FROM promotion_campaigns WHERE id=?'); $campaign->execute([$campaignId]); if (!$campaign->fetchColumn()) throw new DomainException('Выберите существующую кампанию.');
            $start=$localDateTime($_POST['starts_at']??''); $end=$localDateTime($_POST['ends_at']??''); if ($start && $end && $end <= $start) throw new DomainException('Дата завершения должна быть позже начала.');
            $stmt=$pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active,starts_at,ends_at,maximum_uses,maximum_uses_per_user,first_order_only) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(code) DO UPDATE SET campaign_id=excluded.campaign_id,active=excluded.active,starts_at=excluded.starts_at,ends_at=excluded.ends_at,maximum_uses=excluded.maximum_uses,maximum_uses_per_user=excluded.maximum_uses_per_user,first_order_only=excluded.first_order_only');
            $stmt->execute([$code,(int)$campaignId,isset($_POST['active'])?1:0,$start?:null,$end?:null,$maxUses===''?null:(int)$maxUses,$maxPerUser===''?null:(int)$maxPerUser,isset($_POST['first_order_only'])?1:0]);
            $notice='Промокод сохранён. Ограничения проверяются сервером при оформлении заказа.';
        } elseif ($action === 'collection-save') {
            $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT)?:0; $slug=strtolower(trim((string)($_POST['slug']??''))); $name=trim((string)($_POST['name']??'')); $description=trim((string)($_POST['description']??'')); $image=(string)($_POST['image_url']??'assets/generated/room-garden-terrace.webp'); $theme=(string)($_POST['theme_key']??'sakura');
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/',$slug)||$name===''||strlen($name)>100||strlen($description)>1200||!isset($imageOptions[$image])||!preg_match('/^[a-z0-9-]{2,24}$/',$theme)) throw new DomainException('Проверьте slug, название, тему и изображение коллекции.');
            $collectionStart=$localDateTime($_POST['starts_at']??''); $collectionEnd=$localDateTime($_POST['ends_at']??''); if($collectionStart&&$collectionEnd&&$collectionEnd<=$collectionStart)throw new DomainException('Дата завершения должна быть позже начала.');
            $rawIds=trim((string)($_POST['dish_ids']??'')); $dishIds=$rawIds===''?[]:array_values(array_unique(array_filter(array_map('intval',preg_split('/\s*,\s*/',$rawIds)?:[]),static fn(int $v):bool=>$v>0)));
            if (count($dishIds)>20) throw new DomainException('Коллекция может содержать не более 20 блюд.');
            if ($dishIds) { $in=implode(',',array_fill(0,count($dishIds),'?')); $check=$pdo->prepare("SELECT COUNT(*) FROM dishes WHERE active=1 AND id IN ($in)"); $check->execute($dishIds); if ((int)$check->fetchColumn()!==count($dishIds)) throw new DomainException('В коллекцию можно добавлять только активные блюда.'); }
            $pdo->beginTransaction();
            if ($id) { $stmt=$pdo->prepare('UPDATE seasonal_collections SET slug=?,name=?,description=?,image_url=?,theme_key=?,starts_at=?,ends_at=?,active=?,demo_only=1,sort_order=? WHERE id=?'); $stmt->execute([$slug,$name,$description,$image,$theme,$collectionStart?:null,$collectionEnd?:null,isset($_POST['active'])?1:0,(int)($_POST['sort_order']??50),$id]); $collectionId=$id; }
            else { $stmt=$pdo->prepare('INSERT INTO seasonal_collections(slug,name,description,image_url,theme_key,starts_at,ends_at,active,demo_only,sort_order) VALUES(?,?,?,?,?,?,?,?,1,?)'); $stmt->execute([$slug,$name,$description,$image,$theme,$collectionStart?:null,$collectionEnd?:null,isset($_POST['active'])?1:0,(int)($_POST['sort_order']??50)]); $collectionId=(int)$pdo->lastInsertId(); }
            $pdo->prepare('DELETE FROM seasonal_collection_items WHERE collection_id=?')->execute([$collectionId]); $insert=$pdo->prepare('INSERT INTO seasonal_collection_items(collection_id,dish_id,sort_order) VALUES(?,?,?)'); foreach($dishIds as $position=>$dishId)$insert->execute([$collectionId,$dishId,$position*10]); $pdo->commit(); $notice='Сезонная коллекция и состав сохранены.';
        } elseif ($action === 'collection-toggle') {
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT); if(!$id)throw new DomainException('Не найдена коллекция.'); $pdo->prepare('UPDATE seasonal_collections SET active=CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id=?')->execute([$id]); $notice='Статус коллекции изменён.';
        } elseif ($action === 'set-variant-save') {
            $id = trim((string)($_POST['id'] ?? ''));
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $minimum = filter_var($_POST['minimum_distinct_items'] ?? null, FILTER_VALIDATE_INT);
            $maximum = filter_var($_POST['maximum_distinct_items'] ?? null, FILTER_VALIDATE_INT);
            $total = filter_var($_POST['maximum_total_quantity'] ?? null, FILTER_VALIDATE_INT);
            $sortOrder = filter_var($_POST['sort_order'] ?? 50, FILTER_VALIDATE_INT);
            $active = isset($_POST['active']) ? 1 : 0;
            if (($id !== '' && (!preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/', $id) || $slug !== $id))
                || ($id === '' && !preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/', $slug))
                || $name === '' || strlen($name) > 120
                || $minimum === false || $minimum < 3 || $minimum > 8
                || $maximum === false || $maximum < 3 || $maximum > 8 || $maximum < $minimum
                || $total === false || $total < $maximum || $total > 12
                || $sortOrder === false || $sortOrder < 0 || $sortOrder > 10000) {
                throw new DomainException('Проверьте название и формат ограничений: от 3 до 8 блюд, максимум 12 порций.');
            }
            $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
            try {
                if (!$active) {
                    $activeOtherQuery = $id !== ''
                        ? $pdo->prepare('SELECT COUNT(*) FROM custom_set_variants WHERE active=1 AND slug<>?')
                        : $pdo->prepare('SELECT COUNT(*) FROM custom_set_variants WHERE active=1');
                    $id !== '' ? $activeOtherQuery->execute([$id]) : $activeOtherQuery->execute();
                    if ((int)$activeOtherQuery->fetchColumn() < 1) throw new DomainException('Оставьте активным хотя бы один формат сета.');
                }
                if ($id !== '') {
                    $exists = $pdo->prepare('SELECT 1 FROM custom_set_variants WHERE slug=?');
                    $exists->execute([$id]);
                    if (!$exists->fetchColumn()) throw new DomainException('Формат сета не найден.');
                    $update = $pdo->prepare('UPDATE custom_set_variants SET name=?,minimum_distinct_items=?,maximum_distinct_items=?,maximum_total_quantity=?,active=?,sort_order=?,demo_only=1 WHERE slug=?');
                    $update->execute([$name,(int)$minimum,(int)$maximum,(int)$total,$active,(int)$sortOrder,$id]);
                    $notice = 'Формат сета обновлён.';
                } else {
                    $exists = $pdo->prepare('SELECT 1 FROM custom_set_variants WHERE slug=?');
                    $exists->execute([$slug]);
                    if ($exists->fetchColumn()) throw new DomainException('Такой формат уже существует. Откройте его для редактирования.');
                    $insert = $pdo->prepare('INSERT INTO custom_set_variants(slug,name,minimum_distinct_items,maximum_distinct_items,maximum_total_quantity,active,demo_only,sort_order) VALUES(?,?,?,?,?,?,1,?)');
                    $insert->execute([$slug,$name,(int)$minimum,(int)$maximum,(int)$total,$active,(int)$sortOrder]);
                    $notice = 'Формат сета создан как учебная настройка.';
                }
                $pdo->commit();
            } catch (Throwable $setVariantError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $setVariantError;
            }
        } elseif ($action === 'set-variant-toggle') {
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/', $slug)) throw new DomainException('Не найден формат сета.');
            $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
            try {
                $find = $pdo->prepare('SELECT active FROM custom_set_variants WHERE slug=?');
                $find->execute([$slug]);
                $current = $find->fetchColumn();
                if ($current === false) throw new DomainException('Формат сета не найден.');
                if ((int)$current === 1) {
                    $activeOtherQuery = $pdo->prepare('SELECT COUNT(*) FROM custom_set_variants WHERE active=1 AND slug<>?');
                    $activeOtherQuery->execute([$slug]);
                    if ((int)$activeOtherQuery->fetchColumn() < 1) throw new DomainException('Оставьте активным хотя бы один формат сета.');
                }
                $toggle = $pdo->prepare('UPDATE custom_set_variants SET active=CASE active WHEN 1 THEN 0 ELSE 1 END WHERE slug=?');
                $toggle->execute([$slug]);
                $pdo->commit();
                $notice = (int)$current === 1 ? 'Формат сета отключён.' : 'Формат сета активирован.';
            } catch (Throwable $setVariantToggleError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $setVariantToggleError;
            }
        } elseif ($action === 'tier-save') {
            $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT); $slug=strtolower(trim((string)($_POST['slug']??''))); $name=trim((string)($_POST['name']??''));
            $spend=filter_var($_POST['minimum_completed_spend']??null,FILTER_VALIDATE_INT); $multiplier=filter_var($_POST['earn_multiplier_basis_points']??null,FILTER_VALIDATE_INT); $sortOrder=filter_var($_POST['sort_order']??50,FILTER_VALIDATE_INT); $benefits=trim((string)($_POST['benefits']??''));
            if($id===false||$id<0||($id===0&&!preg_match('/^[a-z0-9-]{2,30}$/',$slug))||$name===''||strlen($name)>60||$spend===false||$spend<0||$spend>1000000000000||$multiplier===false||$multiplier<0||$multiplier>100000||$sortOrder===false||$sortOrder<0||$sortOrder>10000||strlen($benefits)>500)throw new DomainException('Проверьте уровень, порог, множитель, привилегии и порядок показа.');
            $list=array_values(array_filter(array_map('trim',preg_split('/\R/',$benefits)?:[]),static fn(string $v):bool=>$v!==''));
            if(count($list)>12||array_filter($list,static fn(string $value):bool=>strlen($value)>120))throw new DomainException('Укажите не более 12 привилегий, каждая до 120 символов.');
            $pdo->beginTransaction();
            try {
                if($id>0){
                    $current=$pdo->prepare('SELECT slug FROM loyalty_tiers WHERE id=?');$current->execute([$id]);$currentSlug=$current->fetchColumn();
                    if($currentSlug===false)throw new DomainException('Уровень клуба не найден.');
                    // Keep slugs stable: campaign and Secret Menu rules may refer to them.
                    $stmt=$pdo->prepare('UPDATE loyalty_tiers SET name=?,minimum_completed_spend=?,earn_multiplier_basis_points=?,benefits_json=?,active=?,sort_order=?,demo_only=1 WHERE id=?');
                    $stmt->execute([$name,(int)$spend,(int)$multiplier,json_encode($list,JSON_UNESCAPED_UNICODE),(int)isset($_POST['active']),(int)$sortOrder,$id]);
                    $notice='Уровень клуба обновлён.';
                } else {
                    $duplicate=$pdo->prepare('SELECT 1 FROM loyalty_tiers WHERE slug=?');$duplicate->execute([$slug]);
                    if($duplicate->fetchColumn())throw new DomainException('Такой slug уровня уже существует. Откройте существующий уровень для редактирования.');
                    $stmt=$pdo->prepare('INSERT INTO loyalty_tiers(slug,name,minimum_completed_spend,earn_multiplier_basis_points,benefits_json,active,sort_order,demo_only) VALUES(?,?,?,?,?,?,?,1)');
                    $stmt->execute([$slug,$name,(int)$spend,(int)$multiplier,json_encode($list,JSON_UNESCAPED_UNICODE),(int)isset($_POST['active']),(int)$sortOrder]);
                    $notice='Демо-уровень программы создан.';
                }
                $pdo->commit();
            } catch(Throwable $tierError) {
                if($pdo->inTransaction())$pdo->rollBack();
                throw $tierError;
            }
        } elseif ($action === 'gift-stock-save') {
            $campaignId=filter_var($_POST['campaign_id']??null,FILTER_VALIDATE_INT); $dishId=filter_var($_POST['dish_id']??null,FILTER_VALIDATE_INT); $quantity=filter_var($_POST['remaining_quantity']??null,FILTER_VALIDATE_INT); $maxPerOrder=filter_var($_POST['max_per_order']??1,FILTER_VALIDATE_INT);
            if(!$campaignId||!$dishId||$quantity===false||$quantity<0||$quantity>10000||$maxPerOrder===false||$maxPerOrder<1||$maxPerOrder>10)throw new DomainException('Проверьте кампанию, блюдо и ограничение остатка.');
            $q=$pdo->prepare("SELECT id FROM promotion_campaigns WHERE id=? AND campaign_type='gift_threshold'");$q->execute([$campaignId]);if(!$q->fetchColumn())throw new DomainException('Выберите кампанию типа «Подарок от суммы заказа».');
            $q=$pdo->prepare('SELECT id FROM dishes WHERE id=? AND active=1');$q->execute([$dishId]);if(!$q->fetchColumn())throw new DomainException('Для подарка доступно только активное блюдо.');
            $q=$pdo->prepare('INSERT INTO campaign_gift_options(campaign_id,dish_id,remaining_quantity,max_per_order,active) VALUES(?,?,?,?,?) ON CONFLICT(campaign_id,dish_id) DO UPDATE SET remaining_quantity=excluded.remaining_quantity,max_per_order=excluded.max_per_order,active=excluded.active');$q->execute([$campaignId,$dishId,$quantity,$maxPerOrder,isset($_POST['active'])?1:0]);$notice='Остаток подарка и лимит на заказ сохранены. Списание остатка выполняется в транзакции заказа.';
        } elseif ($action === 'secret-rule-save') {
            $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT)?:0; $campaignId=filter_var($_POST['campaign_id']??null,FILTER_VALIDATE_INT); $kind=(string)($_POST['access_kind']??'code'); $required=trim((string)($_POST['required_value']??'')); $start=$localDateTime($_POST['starts_at']??''); $end=$localDateTime($_POST['ends_at']??'');
            if(!$campaignId||!in_array($kind,['role','tier','code'],true)||$required===''||strlen($required)>80||($start&&$end&&$end<=$start))throw new DomainException('Проверьте правило доступа, значение и срок действия.');
            $q=$pdo->prepare("SELECT id FROM promotion_campaigns WHERE id=? AND campaign_type='secret_menu'");$q->execute([$campaignId]);if(!$q->fetchColumn())throw new DomainException('Правило можно связать только с кампанией Secret Menu.');
            if($kind==='role'&&!in_array($required,['admin','member'],true))throw new DomainException('Роль должна быть admin или member.');
            if($kind==='tier'){$q=$pdo->prepare('SELECT slug FROM loyalty_tiers WHERE slug=? AND active=1');$q->execute([$required]);if(!$q->fetchColumn())throw new DomainException('Выберите активный уровень клуба.');}
            if($kind==='code')$required=strtoupper($required);
            $pdo->beginTransaction();
            try {
                if($kind==='code'){
                    $promo=$pdo->prepare('SELECT campaign_id,active FROM promo_codes WHERE code=?');$promo->execute([$required]);$promoRow=$promo->fetch(PDO::FETCH_ASSOC);
                    if(!$promoRow){$pdo->prepare('INSERT INTO promo_codes(code,campaign_id,active) VALUES(?,?,1)')->execute([$required,$campaignId]);}
                    elseif((int)$promoRow['campaign_id']!==(int)$campaignId)throw new DomainException('Этот код уже привязан к другой кампании. Выберите уникальный код.');
                    elseif(!(int)$promoRow['active']&&isset($_POST['active']))throw new DomainException('Совпадающий промокод отключён. Сначала активируйте его в разделе «Промокоды».');
                }
                if($id){$exists=$pdo->prepare('SELECT 1 FROM secret_menu_rules WHERE id=?');$exists->execute([$id]);if(!$exists->fetchColumn())throw new DomainException('Правило Secret Menu не найдено.');$q=$pdo->prepare('UPDATE secret_menu_rules SET campaign_id=?,access_kind=?,required_value=?,starts_at=?,ends_at=?,active=? WHERE id=?');$q->execute([$campaignId,$kind,$required,$start,$end,isset($_POST['active'])?1:0,$id]);}
                else{$q=$pdo->prepare('INSERT INTO secret_menu_rules(campaign_id,access_kind,required_value,starts_at,ends_at,active) VALUES(?,?,?,?,?,?)');$q->execute([$campaignId,$kind,$required,$start,$end,isset($_POST['active'])?1:0]);}
                $pdo->commit();
            } catch(Throwable $secretRuleError) { if($pdo->inTransaction())$pdo->rollBack(); throw $secretRuleError; }
            $notice=$kind==='code'?'Правило сохранено; код доступа связан с кампанией и проверяется сервером.':'Правило Secret Menu сохранено и проверяется сервером.';
        } elseif ($action === 'secret-rule-toggle') {
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);if(!$id)throw new DomainException('Не найдено правило доступа.');$exists=$pdo->prepare('SELECT campaign_id,access_kind,required_value,active FROM secret_menu_rules WHERE id=?');$exists->execute([$id]);$ruleToToggle=$exists->fetch(PDO::FETCH_ASSOC);if(!$ruleToToggle)throw new DomainException('Правило Secret Menu не найдено.');if((int)$ruleToToggle['active']===0&&$ruleToToggle['access_kind']==='code'){$promo=$pdo->prepare('SELECT active FROM promo_codes WHERE code=? AND campaign_id=?');$promo->execute([$ruleToToggle['required_value'],$ruleToToggle['campaign_id']]);if(!(int)$promo->fetchColumn())throw new DomainException('Для включения правила активируйте совпадающий код в разделе «Промокоды».');}$pdo->prepare('UPDATE secret_menu_rules SET active=CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id=?')->execute([$id]);$notice='Статус правила доступа изменён.';
        } elseif ($action === 'loyalty-settings-save') {
            $points=filter_var($_POST['points_per_100']??null,FILTER_VALIDATE_INT);$unit=filter_var($_POST['currency_per_point']??null,FILTER_VALIDATE_INT);$percent=filter_var($_POST['maximum_redeem_percent']??null,FILTER_VALIDATE_INT);$window=filter_var($_POST['birthday_window_days']??null,FILTER_VALIDATE_INT);$birthday=filter_var($_POST['birthday_reward_points']??null,FILTER_VALIDATE_INT);$inviter=filter_var($_POST['referral_inviter_points']??null,FILTER_VALIDATE_INT);$friend=filter_var($_POST['referral_friend_points']??null,FILTER_VALIDATE_INT);$minimum=filter_var($_POST['referral_minimum_order']??null,FILTER_VALIDATE_INT);
            if($points===false||$points<0||$points>10000||$unit===false||$unit<1||$unit>100000||$percent===false||$percent<0||$percent>100||$window===false||$window<0||$window>60||$birthday===false||$birthday<0||$inviter===false||$inviter<0||$friend===false||$friend<0||$minimum===false||$minimum<0)throw new DomainException('Проверьте настройки бонусов и лимитов.');
            $q=$pdo->prepare('UPDATE loyalty_settings SET enabled=?,points_per_100=?,currency_per_point=?,maximum_redeem_percent=?,birthday_window_days=?,birthday_reward_points=?,referral_inviter_points=?,referral_friend_points=?,referral_minimum_order=?,demo_only=1,updated_at=CURRENT_TIMESTAMP WHERE id=1');$q->execute([isset($_POST['enabled'])?1:0,$points,$unit,$percent,$window,$birthday,$inviter,$friend,$minimum]);$notice='Условия Tokyo Privilege сохранены как демонстрационные.';
        } elseif ($action === 'certificate-settings-save') {
            $raw=trim((string)($_POST['denominations']??''));$values=array_values(array_unique(array_filter(array_map('intval',preg_split('/\s*,\s*/',$raw)?:[]),static fn(int $v):bool=>$v>0&&$v<=500000)));sort($values,SORT_NUMERIC);if(!$values||count($values)>12)throw new DomainException('Укажите от одного до 12 номиналов в диапазоне 1–500 000 ₸.');$q=$pdo->prepare("INSERT INTO settings(key,value) VALUES('certificate_denominations',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value");$q->execute([json_encode($values)]);$notice='Список номиналов предпросмотра обновлён.';
        } elseif ($action === 'loyalty-adjust') {
            $userId=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);$points=filter_var($_POST['points']??null,FILTER_VALIDATE_INT);$eventKey=trim((string)($_POST['event_key']??''));$note=trim((string)($_POST['note']??''));if(!$userId||$points===false||$points===0||abs($points)>100000||strlen($note)>240)throw new DomainException('Проверьте пользователя, количество баллов и примечание.');commerce_adjust_loyalty($pdo,$userId,$points,$eventKey,$note,$adminId);$notice='Операция записана в журнал бонусов. Повторный event key будет отклонён.';
        } elseif ($action === 'personal-offer-save') {
            $userId=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);$campaignId=filter_var($_POST['campaign_id']??null,FILTER_VALIDATE_INT);$expiryRaw=trim((string)($_POST['expires_at']??''));
            if(!$userId||!$campaignId)throw new DomainException('Выберите аккаунт и персональную кампанию.');
            $expiry=null;
            if($expiryRaw!==''){$localZone=new DateTimeZone('Asia/Qyzylorda');$date=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$expiryRaw,$localZone);$dateErrors=DateTimeImmutable::getLastErrors();if(!$date||($dateErrors!==false&&($dateErrors['warning_count']>0||$dateErrors['error_count']>0))||$date->format('Y-m-d\\TH:i')!==$expiryRaw)throw new DomainException('Укажите корректный срок предложения.');$expiry=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
            commerce_assign_personal_offer($pdo,(int)$userId,(int)$campaignId,$expiry);$notice='Персональное предложение назначено. Оно доступно только выбранному аккаунту до истечения срока или использования.';
        } elseif ($action === 'personal-offer-revoke') {
            $offerId=filter_var($_POST['offer_id']??null,FILTER_VALIDATE_INT);if(!$offerId)throw new DomainException('Персональное предложение не найдено.');commerce_revoke_personal_offer($pdo,(int)$offerId);$notice='Неиспользованное персональное предложение отозвано.';
        } else throw new DomainException('Неизвестное действие.');
    } catch (DomainException $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$exception->getMessage(); }
      catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('Marketing admin: '.$exception->getMessage()); $error='Изменения не сохранены. Проверьте slug, даты и уникальность кода.'; }

    if ($notice !== '') {
        $anchors = [
            'campaign-save'=>'campaigns', 'campaign-toggle'=>'campaigns',
            'promo-save'=>'codes', 'collection-save'=>'collections', 'collection-toggle'=>'collections',
            'set-variant-save'=>'set-formats', 'set-variant-toggle'=>'set-formats',
            'tier-save'=>'tiers', 'gift-stock-save'=>'secret-rules',
            'secret-rule-save'=>'secret-rules', 'secret-rule-toggle'=>'secret-rules',
            'loyalty-settings-save'=>'loyalty-settings', 'certificate-settings-save'=>'loyalty-settings',
            'loyalty-adjust'=>'loyalty-ledger', 'personal-offer-save'=>'personal-offers',
            'personal-offer-revoke'=>'personal-offers',
        ];
        $_SESSION['admin_marketing_notice'] = $notice;
        unset($_SESSION['admin_marketing_reopen_campaign_id']);
        if ($action === 'campaign-save') {
            $_SESSION['admin_marketing_reopen_campaign_id'] = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        }
        header('Location: admin-marketing.php#' . ($anchors[$action] ?? 'marketing-search'), true, 303);
        exit;
    }
}

$campaigns=$pdo->query('SELECT c.*, (SELECT COUNT(*) FROM promo_codes p WHERE p.campaign_id=c.id) AS code_count, (SELECT COUNT(*) FROM order_adjustments a WHERE a.campaign_id=c.id) AS uses_count FROM promotion_campaigns c ORDER BY c.sort_order,c.id DESC')->fetchAll();
$campaignTargetText=[]; $targetQuery=$pdo->query('SELECT campaign_id,target_kind,target_key,excluded FROM promotion_targets ORDER BY target_kind,target_key'); foreach($targetQuery->fetchAll() as $target){$field=(int)$target['excluded']===1?'exclude':'include';$campaignTargetText[(int)$target['campaign_id']][$field][]=$target['target_kind'].':'.$target['target_key'];}
$promoCodes=$pdo->query('SELECT pc.*,c.title FROM promo_codes pc JOIN promotion_campaigns c ON c.id=pc.campaign_id ORDER BY pc.created_at DESC')->fetchAll();
$collections=$pdo->query('SELECT c.*,COUNT(ci.dish_id) AS dish_count,GROUP_CONCAT(ci.dish_id,", ") AS dish_ids FROM seasonal_collections c LEFT JOIN seasonal_collection_items ci ON ci.collection_id=c.id GROUP BY c.id ORDER BY c.sort_order,c.id DESC')->fetchAll();
$setVariants=$pdo->query('SELECT * FROM custom_set_variants ORDER BY sort_order,slug')->fetchAll();
$tiers=$pdo->query('SELECT * FROM loyalty_tiers ORDER BY sort_order,minimum_completed_spend')->fetchAll();
$secretRules=$pdo->query('SELECT r.*,c.title FROM secret_menu_rules r JOIN promotion_campaigns c ON c.id=r.campaign_id ORDER BY r.id DESC')->fetchAll();
$giftOptions=$pdo->query('SELECT g.*,c.title,d.name FROM campaign_gift_options g JOIN promotion_campaigns c ON c.id=g.campaign_id JOIN dishes d ON d.id=g.dish_id ORDER BY c.id,g.dish_id')->fetchAll();
$campaignGiftInventory=[]; $campaignGiftLimits=[]; foreach($giftOptions as $giftOption) { $key=(int)$giftOption['campaign_id'].':'.(int)$giftOption['dish_id']; $campaignGiftInventory[$key]=(int)$giftOption['remaining_quantity']; $campaignGiftLimits[$key]=(int)$giftOption['max_per_order']; }
$giftStocks=[]; foreach($pdo->query('SELECT campaign_id,SUM(remaining_quantity) AS quantity FROM campaign_gift_options GROUP BY campaign_id')->fetchAll() as $stockRow) $giftStocks[(int)$stockRow['campaign_id']]=(int)$stockRow['quantity'];
$loyaltySettings=$pdo->query('SELECT * FROM loyalty_settings WHERE id=1')->fetch();
$userSearch=trim((string)($_GET['user_search']??''));if(strlen($userSearch)>240)$userSearch=substr($userSearch,0,240);
if($userSearch!==''){$userPattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],marketing_unicode_lower($userSearch)).'%';$userQuery=$pdo->prepare("SELECT id,email,full_name FROM users WHERE tp_marketing_lower(full_name) LIKE ? ESCAPE '!' OR tp_marketing_lower(email) LIKE ? ESCAPE '!' OR CAST(id AS TEXT)=? ORDER BY id DESC LIMIT 200");$userQuery->execute([$userPattern,$userPattern,$userSearch]);$users=$userQuery->fetchAll();}
else $users=$pdo->query('SELECT id,email,full_name FROM users ORDER BY id DESC LIMIT 200')->fetchAll();
$assignableCampaigns=array_values(array_filter($campaigns,static fn(array $campaign):bool=>in_array($campaign['campaign_type'],['personal','birthday'],true)&&!empty($campaign['active'])&&(int)$campaign['code_count']>0));
$personalOffers=$pdo->query('SELECT po.id,po.assigned_at,po.expires_at,po.used_order_id,u.full_name,u.email,c.title FROM personalized_offers po JOIN users u ON u.id=po.user_id JOIN promotion_campaigns c ON c.id=po.campaign_id ORDER BY po.assigned_at DESC,po.id DESC LIMIT 100')->fetchAll();
foreach($personalOffers as &$personalOffer){if(!empty($personalOffer['expires_at'])){$personalOffer['expires_at']=(new DateTimeImmutable((string)$personalOffer['expires_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Qyzylorda'))->format('Y-m-d H:i');}}
unset($personalOffer);
$certificateDenominations=commerce_certificate_denominations($pdo);
$dishes=$pdo->query('SELECT id,name FROM dishes WHERE active=1 ORDER BY sort_order,id')->fetchAll();
$metrics=$pdo->query("SELECT (SELECT COUNT(*) FROM orders) AS orders_count,(SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'cancelled') AS order_volume,(SELECT COUNT(*) FROM promotion_campaigns WHERE active=1) AS active_campaigns,(SELECT COUNT(*) FROM promo_redemptions) AS redemptions,(SELECT COALESCE(SUM(CASE WHEN points>0 THEN points ELSE 0 END),0) FROM loyalty_ledger) AS issued_points")->fetch();
$popular=$pdo->query('SELECT item_name,SUM(quantity) AS quantity FROM order_items GROUP BY item_name ORDER BY quantity DESC LIMIT 5')->fetchAll();
$ledgerTypeLabels=['earn'=>'Начисление','redeem'=>'Списание','reversal'=>'Возврат','referral'=>'Приглашение','birthday'=>'День рождения','admin_adjustment'=>'Корректировка'];
$ledgerSearch=trim((string)($_GET['ledger_search']??''));if(strlen($ledgerSearch)>120)$ledgerSearch=substr($ledgerSearch,0,120);
$ledgerType=(string)($_GET['ledger_type']??'');if(!array_key_exists($ledgerType,$ledgerTypeLabels))$ledgerType='';
$ledgerFromInput=trim((string)($_GET['ledger_from']??''));$ledgerToInput=trim((string)($_GET['ledger_to']??''));
$ledgerFilterError='';$ledgerLocalTimezone=new DateTimeZone('Asia/Qyzylorda');$ledgerUtcTimezone=new DateTimeZone('UTC');
$parseLedgerBoundary=static function(string $value,bool $exclusiveEnd) use ($ledgerLocalTimezone,$ledgerUtcTimezone):?string {
    if($value==='')return null;
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))throw new DomainException('Укажите корректный календарный диапазон дат.');
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$ledgerLocalTimezone);$dateErrors=DateTimeImmutable::getLastErrors();
    if(!$date||($dateErrors!==false&&($dateErrors['warning_count']>0||$dateErrors['error_count']>0))||$date->format('Y-m-d')!==$value)throw new DomainException('Укажите корректный календарный диапазон дат.');
    if($exclusiveEnd)$date=$date->modify('+1 day');
    return $date->setTimezone($ledgerUtcTimezone)->format('Y-m-d H:i:s');
};
$ledgerFromUtc=null;$ledgerToUtc=null;
try {
    $ledgerFromUtc=$parseLedgerBoundary($ledgerFromInput,false);$ledgerToUtc=$parseLedgerBoundary($ledgerToInput,true);
    if($ledgerFromUtc!==null&&$ledgerToUtc!==null&&$ledgerFromUtc>=$ledgerToUtc)throw new DomainException('Дата «С» должна быть не позже даты «По».');
} catch(DomainException $exception) {
    $ledgerFilterError=$exception->getMessage();$ledgerFromInput='';$ledgerToInput='';$ledgerFromUtc=null;$ledgerToUtc=null;
}
$ledgerWhere=[];$ledgerParams=[];
if($ledgerSearch!==''){
    $ledgerIdSearch=preg_replace('/^#/','',$ledgerSearch)??$ledgerSearch;
    $ledgerPattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],marketing_unicode_lower($ledgerSearch)).'%';
    $ledgerWhere[]="(CAST(l.id AS TEXT)=? OR CAST(l.user_id AS TEXT)=? OR CAST(l.order_id AS TEXT)=? OR CAST(l.referral_id AS TEXT)=? OR tp_marketing_lower(u.full_name) LIKE ? ESCAPE '!' OR tp_marketing_lower(u.email) LIKE ? ESCAPE '!' OR tp_marketing_lower(l.note) LIKE ? ESCAPE '!' OR tp_marketing_lower(l.event_key) LIKE ? ESCAPE '!' OR tp_marketing_lower(r.code) LIKE ? ESCAPE '!')";
    array_push($ledgerParams,$ledgerIdSearch,$ledgerIdSearch,$ledgerIdSearch,$ledgerIdSearch,$ledgerPattern,$ledgerPattern,$ledgerPattern,$ledgerPattern,$ledgerPattern);
}
if($ledgerType!==''){$ledgerWhere[]='l.transaction_type=?';$ledgerParams[]=$ledgerType;}
if($ledgerFromUtc!==null){$ledgerWhere[]='l.created_at>=?';$ledgerParams[]=$ledgerFromUtc;}
if($ledgerToUtc!==null){$ledgerWhere[]='l.created_at<?';$ledgerParams[]=$ledgerToUtc;}
$ledgerWhereSql=$ledgerWhere?' WHERE '.implode(' AND ',$ledgerWhere):'';
$ledgerCountQuery=$pdo->prepare('SELECT COUNT(*) FROM loyalty_ledger l JOIN users u ON u.id=l.user_id LEFT JOIN referrals r ON r.id=l.referral_id'.$ledgerWhereSql);
$ledgerCountQuery->execute($ledgerParams);$ledgerTotal=(int)$ledgerCountQuery->fetchColumn();
$ledgerPageSize=25;$ledgerPageCount=max(1,(int)ceil($ledgerTotal/$ledgerPageSize));
$ledgerPageValue=filter_var($_GET['ledger_page']??1,FILTER_VALIDATE_INT);$ledgerPage=$ledgerPageValue===false?1:max(1,min((int)$ledgerPageValue,$ledgerPageCount));
$ledgerOffset=($ledgerPage-1)*$ledgerPageSize;
$ledgerQuery=$pdo->prepare("SELECT l.id,l.user_id,l.order_id,l.referral_id,l.event_key,l.transaction_type,l.points,l.note,l.created_at,u.full_name,u.email,o.status AS order_status,r.code AS referral_code,COALESCE(b.balance,0) AS current_balance
    FROM loyalty_ledger l JOIN users u ON u.id=l.user_id
    LEFT JOIN orders o ON o.id=l.order_id LEFT JOIN referrals r ON r.id=l.referral_id
    LEFT JOIN (SELECT user_id,SUM(points) AS balance FROM loyalty_ledger GROUP BY user_id) b ON b.user_id=l.user_id
    {$ledgerWhereSql} ORDER BY l.created_at DESC,l.id DESC LIMIT {$ledgerPageSize} OFFSET {$ledgerOffset}");
$ledgerQuery->execute($ledgerParams);$loyaltyLedger=$ledgerQuery->fetchAll();
$ledgerFilterParams=array_filter(['ledger_search'=>$ledgerSearch,'ledger_type'=>$ledgerType,'ledger_from'=>$ledgerFromInput,'ledger_to'=>$ledgerToInput],static fn(string $value):bool=>$value!=='');
$ledgerPageUrl=static fn(int $target):string=>'admin-marketing.php?'.http_build_query($ledgerFilterParams+['ledger_page'=>$target]).'#loyalty-ledger';
page_start('Marketing & Loyalty — Tokyo Premium', 'Управление учебными кампаниями, промокодами, сезонными коллекциями и уровнями Tokyo Privilege.');
header_html();
?>
<main class="tp20-page tp20-admin-page"><section class="tp20-page-intro"><span class="kicker">ADMINISTRATOR · MARKETING &amp; LOYALTY</span><h1>Кампании<br>и привилегии</h1><p>Здесь управляются демонстрационные предложения. Все новые кампании и начисления сохраняются с учебной маркировкой; реальные списания и платежи не выполняются.</p><a class="text-link" href="admin.php">← Вернуться в основную админ-панель</a></section>
  <nav class="tp20-admin-shortcuts" aria-label="Разделы управления"><a href="#marketing-search">Поиск</a><a href="#campaigns">Кампании</a><a href="#codes">Промокоды</a><a href="#personal-offers">Персональные</a><a href="#collections">Коллекции</a><a href="#set-formats">Форматы сетов</a><a href="#tiers">Уровни клуба</a><a href="#secret-rules">Secret Menu и подарки</a><a href="#loyalty-settings">Условия привилегий</a><a href="#loyalty-ledger">Журнал бонусов</a></nav>
  <section class="tp20-admin-search section" id="marketing-search" aria-labelledby="marketing-search-title">
    <div class="editorial-heading"><div class="kicker">БЫСТРЫЙ ДОСТУП</div><h2 id="marketing-search-title">Поиск по управлению</h2><p>Фильтрует уже загруженные кампании, промокоды, назначения, коллекции, наборы и строки текущей страницы бонусного журнала.</p></div>
    <label for="marketing-admin-search">Название, код, участник или запись<input id="marketing-admin-search" type="search" autocomplete="off" placeholder="Например, Golden Hour или TOKYO10" aria-describedby="marketing-admin-search-help"></label>
    <small class="tp20-admin-search-help" id="marketing-admin-search-help">Нажмите <kbd>/</kbd>, чтобы перейти к поиску; <kbd>Esc</kbd> очистит запрос.</small>
    <button class="button secondary" type="button" id="marketing-admin-search-clear">Очистить</button>
    <p class="tp20-admin-search-status" id="marketing-admin-search-status" role="status" aria-live="polite">Введите запрос, чтобы отфильтровать списки.</p>
  </section>
  <section class="section tp20-admin-metrics" aria-label="Показатели из базы данных"><article><span>Заказы</span><strong><?= (int)$metrics['orders_count']?></strong></article><article><span>Сумма заказов · не отменённые</span><strong><?=number_format((int)$metrics['order_volume'],0,'.',' ')?> ₸</strong></article><article><span>Активные кампании</span><strong><?= (int)$metrics['active_campaigns']?></strong></article><article><span>Промокоды в заказах</span><strong><?= (int)$metrics['redemptions']?></strong></article><article><span>Записано положительных бонусов</span><strong><?= (int)$metrics['issued_points']?></strong></article></section>
  <?php if ($notice): ?><p class="booking-confirmation-notice" role="status"><?=e($notice)?></p><?php endif ?><?php if ($error): ?><p class="form-status error" role="alert"><?=e($error)?></p><?php endif ?>
  <section class="section tp20-admin-section" id="campaigns"><div class="editorial-heading"><div class="kicker">01 · TOKYO EXCLUSIVE / GOLDEN HOUR</div><h2>Кампании</h2><p>Даты и расписание считаются в Asia/Qyzylorda. Созданные здесь кампании по умолчанию помечены как учебные.</p></div>
    <form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="campaign-save"><h3>Новая кампания</h3><div class="tp20-admin-fields"><label>Slug<input name="slug" pattern="[a-z0-9][a-z0-9-]{1,50}" required maxlength="51" placeholder="autumn-evening"></label><label>Название<input name="title" required maxlength="100"></label><label>Тип<select name="campaign_type"><option value="exclusive">Tokyo Exclusive</option><option value="golden_hour">Golden Hour</option><option value="chef_drop">Chef’s Secret Drop</option><option value="secret_menu">Secret Menu</option><option value="birthday">Birthday offer</option><option value="personal">Персональное</option><option value="gift_threshold">Подарок от суммы</option></select></label><label>Награда<select name="reward_type"><option value="none">Без скидки</option><option value="percent">Скидка, %</option><option value="fixed">Фиксированная сумма</option><option value="gift_dish">Подарочное блюдо</option><option value="set_price">Цена сета</option><option value="free_delivery">Бесплатная доставка</option><option value="bonus_points">Бонусные баллы</option><option value="access">Доступ</option></select></label><label>Значение награды<input type="number" name="reward_value" min="0" value="0" required></label><label>Подарочное блюдо (для выбранного типа)<select name="gift_dish_id"><option value="">Не использовать</option><?php foreach($dishes as $dish): ?><option value="<?= (int)$dish['id']?>"><?=e($dish['name'])?></option><?php endforeach ?></select></label><label>Начальный запас подарка<input type="number" name="gift_quantity" min="0" max="10000" value="0"></label><label>Подарков максимум на заказ<input type="number" name="gift_max_per_order" min="1" max="10" value="1"></label><label>Минимальная сумма заказа, ₸<input type="number" name="minimum_subtotal" min="0" value="0"></label><label>Максимальная скидка, ₸<input type="number" name="maximum_discount" min="0" max="10000000" value="0"></label><label>С даты<input type="datetime-local" name="starts_at"></label><label>До даты<input type="datetime-local" name="ends_at"></label><label>Начало времени (Golden Hour)<input type="time" name="starts_time"></label><label>Конец времени<input type="time" name="ends_time"></label><label>Изображение<select name="image_url"><?php foreach($imageOptions as $url=>$label): ?><option value="<?=e($url)?>"><?=e($label)?></option><?php endforeach ?></select></label><label>Требуемый уровень (если нужен)<select name="required_tier"><option value="">Без уровня</option><?php foreach($tiers as $tier): ?><option value="<?=e($tier['slug'])?>"><?=e($tier['name'])?></option><?php endforeach ?></select></label><label>Порядок показа<input type="number" name="sort_order" value="50"></label><label>Цели включения · через запятую<textarea name="target_include" rows="2" maxlength="1200" placeholder="dish:12, category:rolls, set:signature"></textarea></label><label>Исключения · через запятую<textarea name="target_exclude" rows="2" maxlength="1200" placeholder="dish:18, category:drinks"></textarea></label><label>Описание<textarea name="description" rows="3" maxlength="1200"></textarea></label><label class="tp20-admin-check"><input type="checkbox" name="active" checked> Активна</label><label class="tp20-admin-check"><input type="checkbox" name="stackable"> Совместима с другими кампаниями</label></div><button class="button" type="submit">Создать кампанию ↗</button></form>
    <div class="tp20-admin-list">
      <?php foreach($campaigns as $campaign):
        $campaignId=(int)$campaign['id'];
        $campaignTargets=$campaignTargetText[$campaignId]??[];
        $campaignGiftDishId=(int)($campaign['gift_dish_id']??0);
        $campaignGiftQuantity=$campaignGiftInventory[$campaignId.':'.$campaignGiftDishId]??0;
        $campaignGiftMaxPerOrder=$campaignGiftLimits[$campaignId.':'.$campaignGiftDishId]??1;
        $campaignEditOpen=$reopenCampaignId===$campaignId || ($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='campaign-save'&&(int)($_POST['id']??0)===$campaignId);
      ?>
        <article class="tp20-admin-row">
          <div>
            <span class="kicker"><?=e(strtoupper((string)$campaign['campaign_type']))?> · <?=!empty($campaign['demo_only'])?'DEMO':'LIVE'?></span>
            <h3><?=e($campaign['title'])?></h3>
            <p><?=e($campaign['slug'])?> · <?= (int)$campaign['code_count']?> кодов · <?= (int)$campaign['uses_count']?> применений</p>
          </div>
          <form method="post" action="admin-marketing.php#campaigns">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            <input type="hidden" name="action" value="campaign-toggle">
            <input type="hidden" name="id" value="<?=$campaignId?>">
            <button class="button secondary" type="submit"><?=!empty($campaign['active'])?'Отключить':'Активировать'?></button>
          </form>
          <details class="tp20-admin-details" <?=$campaignEditOpen?'open':''?>>
            <summary>Редактировать условия кампании</summary>
            <form method="post" action="admin-marketing.php#campaigns" class="tp20-admin-form">
              <input type="hidden" name="csrf" value="<?=e(csrf())?>">
              <input type="hidden" name="action" value="campaign-save">
              <input type="hidden" name="id" value="<?=$campaignId?>">
              <h4>Настройки кампании</h4>
              <div class="tp20-admin-fields">
                <label>Slug<input name="slug" value="<?=e($campaign['slug'])?>" pattern="[a-z0-9][a-z0-9-]{1,50}" required maxlength="51"></label>
                <label>Название<input name="title" value="<?=e($campaign['title'])?>" required maxlength="100"></label>
                <label>Тип<select name="campaign_type">
                  <option value="exclusive" <?=$campaign['campaign_type']==='exclusive'?'selected':''?>>Tokyo Exclusive</option>
                  <option value="golden_hour" <?=$campaign['campaign_type']==='golden_hour'?'selected':''?>>Golden Hour</option>
                  <option value="chef_drop" <?=$campaign['campaign_type']==='chef_drop'?'selected':''?>>Chef’s Secret Drop</option>
                  <option value="secret_menu" <?=$campaign['campaign_type']==='secret_menu'?'selected':''?>>Secret Menu</option>
                  <option value="birthday" <?=$campaign['campaign_type']==='birthday'?'selected':''?>>Birthday offer</option>
                  <option value="personal" <?=$campaign['campaign_type']==='personal'?'selected':''?>>Персональное</option>
                  <option value="gift_threshold" <?=$campaign['campaign_type']==='gift_threshold'?'selected':''?>>Подарок от суммы</option>
                </select></label>
                <label>Награда<select name="reward_type">
                  <option value="none" <?=$campaign['reward_type']==='none'?'selected':''?>>Без скидки</option>
                  <option value="percent" <?=$campaign['reward_type']==='percent'?'selected':''?>>Скидка, %</option>
                  <option value="fixed" <?=$campaign['reward_type']==='fixed'?'selected':''?>>Фиксированная сумма</option>
                  <option value="gift_dish" <?=$campaign['reward_type']==='gift_dish'?'selected':''?>>Подарочное блюдо</option>
                  <option value="set_price" <?=$campaign['reward_type']==='set_price'?'selected':''?>>Цена сета</option>
                  <option value="free_delivery" <?=$campaign['reward_type']==='free_delivery'?'selected':''?>>Бесплатная доставка</option>
                  <option value="bonus_points" <?=$campaign['reward_type']==='bonus_points'?'selected':''?>>Бонусные баллы</option>
                  <option value="access" <?=$campaign['reward_type']==='access'?'selected':''?>>Доступ</option>
                </select></label>
                <label>Значение награды<input type="number" name="reward_value" min="0" value="<?= (int)$campaign['reward_value']?>" required></label>
                <label>Подарочное блюдо<select name="gift_dish_id"><option value="">Не использовать</option><?php foreach($dishes as $dish): ?><option value="<?= (int)$dish['id']?>" <?=(int)$dish['id']===$campaignGiftDishId?'selected':''?>><?=e($dish['name'])?></option><?php endforeach ?></select></label>
                <label>Начальный запас подарка<input type="number" name="gift_quantity" min="0" max="10000" value="<?=$campaignGiftQuantity?>"></label>
                <label>Подарков максимум на заказ<input type="number" name="gift_max_per_order" min="1" max="10" value="<?=$campaignGiftMaxPerOrder?>"></label>
                <label>Минимальная сумма, ₸<input type="number" name="minimum_subtotal" min="0" value="<?= (int)$campaign['minimum_subtotal']?>"></label>
                <label>Максимальная скидка, ₸<input type="number" name="maximum_discount" min="0" max="10000000" value="<?= (int)$campaign['maximum_discount']?>"></label>
                <label>С даты<input type="datetime-local" name="starts_at" value="<?=e(!empty($campaign['starts_at'])?str_replace(' ','T',substr((string)$campaign['starts_at'],0,16)):'')?>"></label>
                <label>До даты<input type="datetime-local" name="ends_at" value="<?=e(!empty($campaign['ends_at'])?str_replace(' ','T',substr((string)$campaign['ends_at'],0,16)):'')?>"></label>
                <label>Начало времени<input type="time" name="starts_time" value="<?=e((string)($campaign['starts_time']??''))?>"></label>
                <label>Конец времени<input type="time" name="ends_time" value="<?=e((string)($campaign['ends_time']??''))?>"></label>
                <label>Изображение<select name="image_url"><?php foreach($imageOptions as $url=>$label): ?><option value="<?=e($url)?>" <?=$campaign['image_url']===$url?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></label>
                <label>Требуемый уровень<select name="required_tier"><option value="">Без уровня</option><?php foreach($tiers as $tier): ?><option value="<?=e($tier['slug'])?>" <?=($campaign['required_tier']??'')===$tier['slug']?'selected':''?>><?=e($tier['name'])?></option><?php endforeach ?></select></label>
                <label>Порядок показа<input type="number" name="sort_order" value="<?= (int)$campaign['sort_order']?>"></label>
                <label>Цели включения<textarea name="target_include" rows="2" maxlength="1200"><?=e(implode(', ',$campaignTargets['include']??[]))?></textarea></label>
                <label>Исключения<textarea name="target_exclude" rows="2" maxlength="1200"><?=e(implode(', ',$campaignTargets['exclude']??[]))?></textarea></label>
                <label>Описание<textarea name="description" rows="3" maxlength="1200"><?=e($campaign['description'])?></textarea></label>
                <label class="tp20-admin-check"><input type="checkbox" name="active" <?=!empty($campaign['active'])?'checked':''?>> Активна</label>
                <label class="tp20-admin-check"><input type="checkbox" name="stackable" <?=!empty($campaign['stackable'])?'checked':''?>> Совместима с другими кампаниями</label>
              </div>
              <p class="tp20-note">Расписание недели сохраняется при редактировании; даты и время применяются в Asia/Qyzylorda.</p>
              <button class="button" type="submit">Сохранить кампанию ↗</button>
            </form>
          </details>
        </article>
      <?php endforeach ?>
    </div>
  </section>
  <section class="section tp20-admin-section" id="personal-offers"><div class="editorial-heading"><div class="kicker">PRIVATE INVITATIONS · ACCOUNT SCOPED</div><h2>Персональные предложения</h2><p>Назначьте активную кампанию «Персональное» или «Birthday» с действующим промокодом. Birthday-предложение доступно только если дата рождения аккаунта попадает в настроенное окно, а владелец дал отдельное согласие; сервер проверяет оба условия при назначении и оформлении заказа.</p></div>
    <form method="get" action="admin-marketing.php#personal-offers" class="tp20-admin-form"><h3>Найти аккаунт</h3><div class="tp20-admin-fields"><label>ID, имя или email<input type="search" name="user_search" value="<?=e($userSearch)?>" maxlength="120" placeholder="Например, guest@example.test" autocomplete="off"></label></div><button class="button secondary" type="submit">Найти аккаунт</button><?php if($userSearch!==''): ?><a class="text-link" href="admin-marketing.php#personal-offers">Сбросить поиск</a><?php endif ?></form>
    <?php if(!$users): ?><p class="tp20-note"><?=$userSearch!==''?'Аккаунт не найден по этому запросу.':'Зарегистрированных аккаунтов пока нет.'?></p><?php else: ?><p class="tp20-note"><?=$userSearch!==''?'Найденные аккаунты':'Последние 200 аккаунтов'?> · <?=count($users)?></p><?php if(!$assignableCampaigns): ?><p class="tp20-note">Аккаунты найдены. Для назначения сначала создайте активную персональную или birthday-кампанию и действующий промокод к ней.</p><div class="tp20-admin-list"><?php foreach($users as $user): ?><article class="tp20-admin-row"><div><h3><?=e($user['full_name'])?></h3><p>#<?= (int)$user['id']?> · <?=e($user['email'])?></p></div></article><?php endforeach ?></div><?php else: ?><form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="personal-offer-save"><div class="tp20-admin-fields"><label>Аккаунт<select name="user_id" required><?php foreach($users as $user): ?><option value="<?= (int)$user['id']?>">#<?= (int)$user['id']?> · <?=e($user['full_name'])?> · <?=e($user['email'])?></option><?php endforeach ?></select></label><label>Кампания<select name="campaign_id" required><?php foreach($assignableCampaigns as $campaign): ?><option value="<?= (int)$campaign['id']?>"><?=e($campaign['title'])?><?= $campaign['campaign_type']==='birthday'?' · BIRTHDAY':''?></option><?php endforeach ?></select></label><label>Истекает · необязательно<input type="datetime-local" name="expires_at"></label></div><button class="button" type="submit">Назначить предложение ↗</button></form><?php endif ?><?php endif ?>
    <?php if(!$personalOffers): ?><p class="tp20-note">Назначений пока нет.</p><?php else: ?><div class="tp20-admin-list"><?php foreach($personalOffers as $offer): ?><article class="tp20-admin-row"><div><span class="kicker"><?=!empty($offer['used_order_id'])?'ИСПОЛЬЗОВАНО · ЗАКАЗ №'.(int)$offer['used_order_id']:(!empty($offer['expires_at'])?'ДО '.e($offer['expires_at']):'БЕЗ СРОКА')?></span><h3><?=e($offer['title'])?></h3><p>#<?= (int)$offer['id']?> · <?=e($offer['full_name'])?> · <?=e($offer['email'])?> · назначено <?=e($offer['assigned_at'])?></p></div><?php if(empty($offer['used_order_id'])): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="personal-offer-revoke"><input type="hidden" name="offer_id" value="<?= (int)$offer['id']?>"><button class="button secondary" type="submit">Отозвать</button></form><?php endif ?></article><?php endforeach ?></div><?php endif ?>
  </section>
  <section class="section tp20-admin-section" id="codes"><div class="editorial-heading"><div class="kicker">02 · PROMO CODES</div><h2>Промокоды</h2><p>Финальную скидку рассчитывает сервер с учётом срока, кампании и лимита применений.</p></div><form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="promo-save"><h3>Создать промокод</h3><div class="tp20-admin-fields"><label>Код<input name="code" required maxlength="31" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,30}" placeholder="TOKYO10"></label><label>Кампания<select name="campaign_id" required><?php foreach($campaigns as $campaign): ?><option value="<?= (int)$campaign['id']?>"><?=e($campaign['title'])?></option><?php endforeach ?></select></label><label>С даты<input type="datetime-local" name="starts_at"></label><label>До даты<input type="datetime-local" name="ends_at"></label><label>Общий лимит<input type="number" name="maximum_uses" min="1" placeholder="Без лимита"></label><label>Лимит на пользователя<input type="number" name="maximum_uses_per_user" min="1" placeholder="Без лимита"></label><label class="tp20-admin-check"><input type="checkbox" name="first_order_only"> Только первый заказ</label><label class="tp20-admin-check"><input type="checkbox" name="active" checked> Активен</label></div><button class="button" type="submit">Сохранить промокод ↗</button></form><div class="tp20-admin-list"><?php if(!$promoCodes): ?><p class="tp20-note">Промокодов пока нет.</p><?php else: foreach($promoCodes as $code): ?><article class="tp20-admin-row tp20-promo-code-row" data-promo-code="<?=e($code['code'])?>"><div class="tp20-promo-code-summary"><div><span class="kicker"><?=!empty($code['active'])?'АКТИВЕН':'ОТКЛЮЧЁН'?></span><h3><?=e($code['code'])?></h3><p><?=e($code['title'])?></p><ul class="tp20-promo-code-facts"><li>Использован <strong><?= (int)$code['uses_count']?></strong> из <strong><?= $code['maximum_uses']===null?'без общего лимита':(int)$code['maximum_uses']?></strong></li><li>На пользователя: <strong><?= $code['maximum_uses_per_user']===null?'без лимита':(int)$code['maximum_uses_per_user']?></strong></li><li>Срок: <strong><?php if($code['starts_at']||$code['ends_at']): ?><?=e($code['starts_at']?substr((string)$code['starts_at'],0,16):'сразу')?> — <?=e($code['ends_at']?substr((string)$code['ends_at'],0,16):'без даты окончания')?> · Asia/Qyzylorda<?php else: ?>без ограничения по сроку<?php endif ?></strong></li><?php if(!empty($code['first_order_only'])): ?><li><strong>Только для первого заказа</strong></li><?php endif ?></ul></div><details class="tp20-admin-details tp20-promo-code-edit"><summary>Изменить условия</summary><form method="post" action="admin-marketing.php#codes" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="promo-save"><h4>Настройки <?=e($code['code'])?></h4><div class="tp20-admin-fields"><label>Код<input name="code" value="<?=e($code['code'])?>" readonly></label><label>Кампания<select name="campaign_id" required><?php foreach($campaigns as $campaign): ?><option value="<?= (int)$campaign['id']?>" <?=(int)$campaign['id']===(int)$code['campaign_id']?'selected':''?>><?=e($campaign['title'])?></option><?php endforeach ?></select></label><label>С даты<input type="datetime-local" name="starts_at" value="<?=e($code['starts_at']?str_replace(' ','T',substr((string)$code['starts_at'],0,16)):'')?>"></label><label>До даты<input type="datetime-local" name="ends_at" value="<?=e($code['ends_at']?str_replace(' ','T',substr((string)$code['ends_at'],0,16)):'')?>"></label><label>Общий лимит<input type="number" name="maximum_uses" min="1" value="<?=e($code['maximum_uses']===null?'':(string)$code['maximum_uses'])?>" placeholder="Без лимита"></label><label>Лимит на пользователя<input type="number" name="maximum_uses_per_user" min="1" value="<?=e($code['maximum_uses_per_user']===null?'':(string)$code['maximum_uses_per_user'])?>" placeholder="Без лимита"></label><label class="tp20-admin-check"><input type="checkbox" name="first_order_only" <?=!empty($code['first_order_only'])?'checked':''?>> Только первый заказ</label><label class="tp20-admin-check"><input type="checkbox" name="active" <?=!empty($code['active'])?'checked':''?>> Активен</label></div><button class="button" type="submit">Сохранить условия ↗</button></form></details></div></article><?php endforeach; endif ?></div></section>
  <section class="section tp20-admin-section" id="collections"><div class="editorial-heading"><div class="kicker">03 · SEASONAL STORIES</div><h2>Сезонные коллекции</h2><p>Укажите список активных dish ID через запятую, чтобы привязать реальные позиции каталога.</p></div><details class="tp20-admin-details"><summary>Открыть список активных блюд</summary><ul><?php foreach($dishes as $dish): ?><li>#<?= (int)$dish['id']?> — <?=e($dish['name'])?></li><?php endforeach ?></ul></details><form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="collection-save"><h3>Добавить или обновить коллекцию</h3><div class="tp20-admin-fields"><label>Slug<input name="slug" required pattern="[a-z0-9][a-z0-9-]{1,50}" placeholder="autumn-in-kyoto"></label><label>Название<input name="name" required maxlength="100"></label><label>Ключ темы<input name="theme_key" required value="autumn"></label><label>Изображение<select name="image_url"><?php foreach($imageOptions as $url=>$label): ?><option value="<?=e($url)?>"><?=e($label)?></option><?php endforeach ?></select></label><label>С даты<input type="datetime-local" name="starts_at"></label><label>До даты<input type="datetime-local" name="ends_at"></label><label>Порядок показа<input type="number" name="sort_order" value="50"></label><label>Блюда · ID через запятую<input name="dish_ids" inputmode="numeric" placeholder="1, 3, 17"></label><label>Описание<textarea name="description" rows="3" maxlength="1200"></textarea></label><label class="tp20-admin-check"><input type="checkbox" name="active" checked> Активна</label></div><button class="button" type="submit">Сохранить коллекцию ↗</button></form><div class="tp20-admin-list"><?php foreach($collections as $collection): ?><article class="tp20-admin-row"><div><span class="kicker"><?=!empty($collection['active'])?'АКТИВНА':'СКРЫТА'?> · <?= (int)$collection['dish_count']?> блюд</span><h3><?=e($collection['name'])?></h3><p><?=e($collection['slug'])?> · <?=e((string)$collection['dish_ids'])?></p></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="collection-toggle"><input type="hidden" name="id" value="<?= (int)$collection['id']?>"><button class="button secondary" type="submit"><?=!empty($collection['active'])?'Отключить':'Активировать'?></button></form></article><?php endforeach ?></div></section>
  <section class="section tp20-admin-section" id="set-formats"><div class="editorial-heading"><div class="kicker">BUILD YOUR TOKYO SET</div><h2>Форматы конструктора сетов</h2><p>Здесь задаётся только допустимый состав. Стоимость всегда складывается из текущих цен блюд в каталоге и повторно проверяется сервером при оформлении.</p></div>
    <form method="post" action="admin-marketing.php#set-formats" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="set-variant-save"><h3>Добавить формат</h3><div class="tp20-admin-fields"><label>Slug<input name="slug" required maxlength="51" pattern="[a-z0-9][a-z0-9-]{1,50}" placeholder="chef-selection"></label><label>Название<input name="name" required maxlength="120" placeholder="Chef’s Selection"></label><label>Минимум разных блюд<input type="number" name="minimum_distinct_items" min="3" max="8" required value="3"></label><label>Максимум разных блюд<input type="number" name="maximum_distinct_items" min="3" max="8" required value="5"></label><label>Порций всего · максимум 12<input type="number" name="maximum_total_quantity" min="3" max="12" required value="8"></label><label>Порядок показа<input type="number" name="sort_order" min="0" max="10000" value="50"></label><label class="tp20-admin-check"><input type="checkbox" name="active" checked> Доступен в конструкторе</label></div><button class="button" type="submit">Создать формат ↗</button></form>
    <?php if(!$setVariants): ?><p class="tp20-note">Форматов пока нет.</p><?php else: ?><div class="tp20-admin-list"><?php foreach($setVariants as $variant): ?><article class="tp20-admin-row" data-set-variant="<?=e($variant['slug'])?>"><div><span class="kicker"><?=!empty($variant['active'])?'ДОСТУПЕН':'ОТКЛЮЧЁН'?> · <?=e($variant['slug'])?></span><h3><?=e($variant['name'])?></h3><p><?= (int)$variant['minimum_distinct_items']?>–<?= (int)$variant['maximum_distinct_items']?> разных блюд · до <?= (int)$variant['maximum_total_quantity']?> порций · порядок <?= (int)$variant['sort_order']?></p></div><details class="tp20-admin-details"><summary>Изменить формат</summary><form method="post" action="admin-marketing.php#set-formats" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="set-variant-save"><input type="hidden" name="id" value="<?=e($variant['slug'])?>"><div class="tp20-admin-fields"><label>Slug · постоянный<input name="slug" value="<?=e($variant['slug'])?>" readonly></label><label>Название<input name="name" value="<?=e($variant['name'])?>" required maxlength="120"></label><label>Минимум разных блюд<input type="number" name="minimum_distinct_items" min="3" max="8" value="<?= (int)$variant['minimum_distinct_items']?>" required></label><label>Максимум разных блюд<input type="number" name="maximum_distinct_items" min="3" max="8" value="<?= (int)$variant['maximum_distinct_items']?>" required></label><label>Порций всего · максимум 12<input type="number" name="maximum_total_quantity" min="3" max="12" value="<?= (int)$variant['maximum_total_quantity']?>" required></label><label>Порядок показа<input type="number" name="sort_order" min="0" max="10000" value="<?= (int)$variant['sort_order']?>"></label><label class="tp20-admin-check"><input type="checkbox" name="active" <?=!empty($variant['active'])?'checked':''?>> Доступен в конструкторе</label></div><button class="button secondary" type="submit">Сохранить формат ↗</button></form></details><form method="post" action="admin-marketing.php#set-formats"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="set-variant-toggle"><input type="hidden" name="slug" value="<?=e($variant['slug'])?>"><button class="button secondary" type="submit"><?=!empty($variant['active'])?'Отключить':'Активировать'?></button></form></article><?php endforeach ?></div><?php endif ?>
  </section>
  <section class="section tp20-admin-section" id="tiers"><div class="editorial-heading"><div class="kicker">04 · TOKYO PRIVILEGE</div><h2>Уровни клуба</h2><p>Начисления демонстрационные; применяются только по событиям подтверждённого статуса заказа в учебной базе.</p></div>
    <form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="tier-save"><h3>Создать уровень</h3><div class="tp20-admin-fields"><label>Slug<input name="slug" required minlength="2" maxlength="30" pattern="[a-z0-9-]{2,30}" placeholder="silver"></label><label>Название<input name="name" required maxlength="60"></label><label>Порог завершённых заказов, ₸<input type="number" name="minimum_completed_spend" min="0" max="1000000000000" required value="0"></label><label>Множитель начисления · basis points<input type="number" name="earn_multiplier_basis_points" min="0" max="100000" required value="10000"></label><label>Привилегии · по одной на строку<textarea name="benefits" rows="3" maxlength="500"></textarea></label><label>Порядок показа<input type="number" name="sort_order" min="0" max="10000" value="50"></label><label class="tp20-admin-check"><input type="checkbox" name="active" checked> Активен</label></div><button class="button" type="submit">Создать уровень ↗</button></form>
    <div class="tp20-admin-list"><?php foreach($tiers as $tier): $tierBenefits=json_decode((string)$tier['benefits_json'],true); if(!is_array($tierBenefits))$tierBenefits=[]; ?><article class="tp20-admin-row"><div><span class="kicker"><?=number_format((int)$tier['minimum_completed_spend'],0,'.',' ')?> ₸ · <?=number_format((int)$tier['earn_multiplier_basis_points']/100,2)?>×</span><h3><?=e($tier['name'])?></h3><p><?=e(implode(' · ',$tierBenefits))?></p><span><?=!empty($tier['active'])?'Активен':'Отключён'?> · slug <?=e($tier['slug'])?> · порядок <?= (int)$tier['sort_order']?></span></div><details class="tp20-admin-details"><summary>Редактировать уровень</summary><form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="tier-save"><input type="hidden" name="id" value="<?= (int)$tier['id']?>"><label>Slug · сохраняется для ссылок<input value="<?=e($tier['slug'])?>" readonly></label><div class="tp20-admin-fields"><label>Название<input name="name" required maxlength="60" value="<?=e($tier['name'])?>"></label><label>Порог завершённых заказов, ₸<input type="number" name="minimum_completed_spend" min="0" max="1000000000000" required value="<?= (int)$tier['minimum_completed_spend']?>"></label><label>Множитель начисления · basis points<input type="number" name="earn_multiplier_basis_points" min="0" max="100000" required value="<?= (int)$tier['earn_multiplier_basis_points']?>"></label><label>Привилегии · по одной на строку<textarea name="benefits" rows="3" maxlength="500"><?=e(implode("\n",$tierBenefits))?></textarea></label><label>Порядок показа<input type="number" name="sort_order" min="0" max="10000" required value="<?= (int)$tier['sort_order']?>"></label><label class="tp20-admin-check"><input type="checkbox" name="active" <?=!empty($tier['active'])?'checked':''?>> Активен</label></div><button class="button" type="submit">Сохранить изменения ↗</button></form></details></article><?php endforeach ?></div>
  </section>
  <section class="section tp20-admin-section"><div class="editorial-heading"><div class="kicker">05 · ACTUAL DATABASE SNAPSHOT</div><h2>Популярные позиции</h2><p>Показаны только строки, существующие в базе заказов.</p></div><?php if(!$popular): ?><p class="tp20-note">Данных о заказах пока нет.</p><?php else: ?><div class="tp20-admin-list"><?php foreach($popular as $dish): ?><article class="tp20-admin-row"><h3><?=e($dish['item_name'])?></h3><strong><?= (int)$dish['quantity']?> заказано</strong></article><?php endforeach ?></div><?php endif ?></section>

  <section class="section tp20-admin-section" id="secret-rules"><div class="editorial-heading"><div class="kicker">07 · SECRET MENU</div><h2>Правила доступа и остаток подарков</h2><p>Доступ проверяется на сервере по роли, активному уровню или действующему коду. Кодовое правило автоматически связывается с активным промокодом этой кампании; сроки и лимиты кода редактируются в разделе «Промокоды».</p></div>
    <form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="secret-rule-save"><h3>Добавить правило доступа</h3><div class="tp20-admin-fields"><label>Кампания<select name="campaign_id" required><?php foreach($campaigns as $campaign): if($campaign['campaign_type']!=='secret_menu')continue; ?><option value="<?= (int)$campaign['id']?>"><?=e($campaign['title'])?></option><?php endforeach ?></select></label><label>Тип доступа<select name="access_kind"><option value="code">Код</option><option value="tier">Уровень клуба</option><option value="role">Роль</option></select></label><label>Значение<input name="required_value" maxlength="80" required placeholder="SECRET / gold / member"></label><label>С даты<input type="datetime-local" name="starts_at"></label><label>До даты<input type="datetime-local" name="ends_at"></label><label class="tp20-admin-check"><input type="checkbox" name="active" checked> Активно</label></div><button class="button" type="submit">Сохранить правило ↗</button></form>
    <div class="tp20-admin-list"><?php foreach($secretRules as $rule): ?><details class="tp20-admin-details"><summary><?=e($rule['title'])?> · <?=e(strtoupper((string)$rule['access_kind']))?> · <?=!empty($rule['active'])?'АКТИВНО':'ОТКЛЮЧЕНО'?> · <?=e($rule['required_value'])?> · редактировать</summary>
      <form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="secret-rule-save"><input type="hidden" name="id" value="<?=(int)$rule['id']?>"><div class="tp20-admin-fields">
        <label>Кампания<select name="campaign_id" required><?php foreach($campaigns as $campaign): if($campaign['campaign_type']!=='secret_menu')continue; ?><option value="<?=(int)$campaign['id']?>" <?=(int)$rule['campaign_id']===(int)$campaign['id']?'selected':''?>><?=e($campaign['title'])?></option><?php endforeach ?></select></label>
        <label>Тип доступа<select name="access_kind"><option value="code" <?=$rule['access_kind']==='code'?'selected':''?>>Код</option><option value="tier" <?=$rule['access_kind']==='tier'?'selected':''?>>Уровень клуба</option><option value="role" <?=$rule['access_kind']==='role'?'selected':''?>>Роль</option></select></label>
        <label>Значение<input name="required_value" value="<?=e($rule['required_value'])?>" maxlength="80" required></label>
        <label>С даты<input type="datetime-local" name="starts_at" value="<?=e($rule['starts_at']?str_replace(' ','T',substr((string)$rule['starts_at'],0,16)):'')?>"></label>
        <label>До даты<input type="datetime-local" name="ends_at" value="<?=e($rule['ends_at']?str_replace(' ','T',substr((string)$rule['ends_at'],0,16)):'')?>"></label>
        <label class="tp20-admin-check"><input type="checkbox" name="active" <?=!empty($rule['active'])?'checked':''?>> Активно</label>
      </div><button class="button" type="submit">Сохранить правило ↗</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="secret-rule-toggle"><input type="hidden" name="id" value="<?=(int)$rule['id']?>"><button class="button secondary" type="submit"><?=!empty($rule['active'])?'Отключить правило':'Активировать правило'?></button></form>
    </details><?php endforeach ?></div>
    <form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="gift-stock-save"><h3>Настроить остаток подарка от суммы заказа</h3><div class="tp20-admin-fields"><label>Кампания<select name="campaign_id" required><?php foreach($campaigns as $campaign): if($campaign['campaign_type']!=='gift_threshold')continue; ?><option value="<?= (int)$campaign['id']?>"><?=e($campaign['title'])?> · от <?=number_format((int)$campaign['minimum_subtotal'],0,'.',' ')?> ₸</option><?php endforeach ?></select></label><label>Подарочное блюдо<select name="dish_id" required><?php foreach($dishes as $dish): ?><option value="<?= (int)$dish['id']?>">#<?= (int)$dish['id']?> · <?=e($dish['name'])?></option><?php endforeach ?></select></label><label>Доступный остаток<input type="number" name="remaining_quantity" min="0" max="10000" required value="0"></label><label>Максимум на заказ<input type="number" name="max_per_order" min="1" max="10" required value="1"></label><label class="tp20-admin-check"><input type="checkbox" name="active"> Доступно</label></div><button class="button" type="submit">Сохранить остаток ↗</button></form>
    <div class="tp20-admin-list"><?php foreach($giftOptions as $gift): ?><article class="tp20-admin-row"><div><span class="kicker"><?=!empty($gift['active'])?'АКТИВНО':'СКРЫТО'?> · остаток <?= (int)$gift['remaining_quantity']?> · максимум <?= (int)$gift['max_per_order']?></span><h3><?=e($gift['title'])?></h3><p><?=e($gift['name'])?></p></div></article><?php endforeach ?></div>
  </section>
  <section class="section tp20-admin-section" id="loyalty-settings"><div class="editorial-heading"><div class="kicker">08 · TOKYO PRIVILEGE SETTINGS</div><h2>Настройки бонусов и подарков</h2><p>Параметры демонстрационные и настраиваются без жёстко заданных обещаний пользователю.</p></div>
    <form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="loyalty-settings-save"><div class="tp20-admin-fields"><label>Баллов за каждые 100 ₸<input type="number" name="points_per_100" min="0" max="10000" value="<?= (int)$loyaltySettings['points_per_100']?>"></label><label>Тенге за один балл<input type="number" name="currency_per_point" min="1" max="100000" value="<?= (int)$loyaltySettings['currency_per_point']?>"></label><label>Максимум оплаты баллами, %<input type="number" name="maximum_redeem_percent" min="0" max="100" value="<?= (int)$loyaltySettings['maximum_redeem_percent']?>"></label><label>Окно предложения ко дню рождения, дней<input type="number" name="birthday_window_days" min="0" max="60" value="<?= (int)$loyaltySettings['birthday_window_days']?>"></label><label>Баллы к дню рождения<input type="number" name="birthday_reward_points" min="0" value="<?= (int)$loyaltySettings['birthday_reward_points']?>"></label><label>Баллы пригласившему<input type="number" name="referral_inviter_points" min="0" value="<?= (int)$loyaltySettings['referral_inviter_points']?>"></label><label>Баллы приглашённому<input type="number" name="referral_friend_points" min="0" value="<?= (int)$loyaltySettings['referral_friend_points']?>"></label><label>Минимальная сумма заказа для реферала, ₸<input type="number" name="referral_minimum_order" min="0" value="<?= (int)$loyaltySettings['referral_minimum_order']?>"></label><label class="tp20-admin-check"><input type="checkbox" name="enabled" <?=!empty($loyaltySettings['enabled'])?'checked':''?>> Программа активна</label></div><button class="button" type="submit">Сохранить условия клуба ↗</button></form>
    <form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="certificate-settings-save"><label>Номиналы предпросмотра, ₸ · через запятую<input name="denominations" value="<?=e(implode(', ', $certificateDenominations))?>" required></label><button class="button secondary" type="submit">Обновить номиналы ↗</button><p>Сертификаты сохраняются только как preview_only; изменение номинала не означает оплату.</p></form>
    <form method="post" class="tp20-admin-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="loyalty-adjust"><h3>Ручная корректировка журнала</h3><div class="tp20-admin-fields"><label>Аккаунт<select name="user_id" required><?php foreach($users as $user): ?><option value="<?= (int)$user['id']?>">#<?= (int)$user['id']?> · <?=e($user['full_name'])?> · <?=e($user['email'])?></option><?php endforeach ?></select></label><label>Баллы · минус списывает<input type="number" name="points" min="-100000" max="100000" required></label><label>Уникальный event key<input name="event_key" minlength="8" maxlength="160" pattern="[A-Za-z0-9._:-]{8,160}" required placeholder="admin:case:2026-001"></label><label>Примечание<input name="note" maxlength="240" required></label></div><button class="button" type="submit">Записать операцию ↗</button></form>
  </section>
  <section class="section tp20-admin-section" id="loyalty-ledger"><div class="editorial-heading"><div class="kicker">09 · TOKYO PRIVILEGE AUDIT</div><h2>Журнал бонусных операций</h2><p>История начислений и списаний по учебным аккаунтам. Баланс и связанные события рассчитаны по текущим записям SQLite; строки не редактируются и не удаляются из этой панели.</p></div>
    <?php if($ledgerFilterError!==''): ?><p class="form-status error" role="alert"><?=e($ledgerFilterError)?></p><?php endif ?>
    <form method="get" action="admin-marketing.php" class="tp20-admin-form tp20-admin-ledger-filters"><div class="tp20-admin-fields">
      <label>Участник, email, ID операции, заказа, приглашения или текст события<input type="search" name="ledger_search" maxlength="120" value="<?=e($ledgerSearch)?>" placeholder="Имя, email, # ID или текст события"></label>
      <label>Тип операции<select name="ledger_type"><option value="">Все типы</option><?php foreach($ledgerTypeLabels as $typeValue=>$typeLabel): ?><option value="<?=e($typeValue)?>" <?=$ledgerType===$typeValue?'selected':''?>><?=e($typeLabel)?></option><?php endforeach ?></select></label>
      <label>С даты · местное время<input type="date" name="ledger_from" value="<?=e($ledgerFromInput)?>"></label>
      <label>По дату · включительно<input type="date" name="ledger_to" value="<?=e($ledgerToInput)?>"></label>
    </div><div class="tp20-admin-ledger-actions"><button class="button" type="submit">Найти операции ↗</button><a class="button secondary" href="admin-marketing.php#loyalty-ledger">Сбросить фильтры</a></div></form>
    <p class="tp20-note" role="status">Найдено операций: <strong><?=number_format($ledgerTotal,0,'.',' ')?></strong> · Страница <?=$ledgerPage?> из <?=$ledgerPageCount?> · даты указаны по Asia/Qyzylorda.</p>
    <?php if(!$loyaltyLedger): ?><p class="tp20-note tp20-admin-ledger-empty">В журнале пока нет операций, соответствующих фильтрам.</p><?php else: ?>
      <p class="tp20-note tp20-admin-ledger-scroll-hint" id="loyalty-ledger-scroll-hint">На телефоне проведите таблицу по горизонтали, чтобы увидеть текущий баланс, связи и примечания.</p>
      <div class="tp20-admin-ledger-wrap" role="region" aria-label="Таблица журнала бонусных операций" aria-describedby="loyalty-ledger-scroll-hint" tabindex="0"><table class="tp20-admin-ledger-table"><caption>Операции Tokyo Privilege</caption><thead><tr><th scope="col">Дата</th><th scope="col">Участник</th><th scope="col">Событие</th><th scope="col">Баллы</th><th scope="col">Текущий баланс</th><th scope="col">Связь</th><th scope="col">Примечание</th></tr></thead><tbody>
      <?php foreach($loyaltyLedger as $ledgerRow): $ledgerDate=(new DateTimeImmutable((string)$ledgerRow['created_at'],$ledgerUtcTimezone))->setTimezone($ledgerLocalTimezone); $ledgerOrderStatus=(string)($ledgerRow['order_status']??''); ?>
        <tr class="tp20-admin-ledger-row" data-ledger-entry="<?= (int)$ledgerRow['id']?>"><td><time datetime="<?=e((string)$ledgerRow['created_at'])?>"><?=e($ledgerDate->format('d.m.Y H:i'))?></time></td><td><strong><?=e($ledgerRow['full_name'])?></strong><small>#<?= (int)$ledgerRow['user_id']?> · <?=e($ledgerRow['email'])?></small></td><td><?=e($ledgerTypeLabels[$ledgerRow['transaction_type']]??$ledgerRow['transaction_type'])?></td><td class="<?=((int)$ledgerRow['points']>0?'is-positive':'is-negative')?>"><?=((int)$ledgerRow['points']>0?'+':'')?><?=number_format((int)$ledgerRow['points'],0,'.',' ')?></td><td><?=number_format((int)$ledgerRow['current_balance'],0,'.',' ')?></td><td><?php if($ledgerRow['order_id']!==null): ?>Заказ #<?= (int)$ledgerRow['order_id']?><?=$ledgerOrderStatus!==''?' · '.e($ledgerOrderStatus):''?><?php endif ?><?php if($ledgerRow['referral_id']!==null): ?><?php if($ledgerRow['order_id']!==null): ?><br><?php endif ?>Приглашение #<?= (int)$ledgerRow['referral_id']?><?php if(!empty($ledgerRow['referral_code'])): ?> · <?=e($ledgerRow['referral_code'])?><?php endif ?><?php endif ?><?php if($ledgerRow['order_id']===null&&$ledgerRow['referral_id']===null): ?>—<?php endif ?></td><td><?=e($ledgerRow['note'])?><small>event: <?=e($ledgerRow['event_key'])?></small></td></tr>
      <?php endforeach ?>
      </tbody></table></div>
      <?php if($ledgerPageCount>1): ?><nav class="tp20-admin-ledger-pagination" aria-label="Страницы журнала"><?php if($ledgerPage>1): ?><a class="button secondary" href="<?=e($ledgerPageUrl($ledgerPage-1))?>" aria-label="Предыдущая страница">← Назад</a><?php endif ?><span>Страница <?=$ledgerPage?> из <?=$ledgerPageCount?></span><?php if($ledgerPage<$ledgerPageCount): ?><a class="button secondary" href="<?=e($ledgerPageUrl($ledgerPage+1))?>" aria-label="Следующая страница">Дальше →</a><?php endif ?></nav><?php endif ?>
    <?php endif ?>
  </section>
</main>
<script src="admin-marketing-search.js" defer></script><script src="admin-form-guard.js" defer></script>
<?php footer_html(); ?>
