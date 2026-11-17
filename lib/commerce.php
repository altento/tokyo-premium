<?php
declare(strict_types=1);

/**
 * Additive schema upgrade for the commerce modules. schema.sql creates the
 * normalized tables; this migration only adds columns to already existing
 * tables and seeds editable educational defaults.
 */
function commerce_migrate(PDO $pdo): void
{
    $seen = $pdo->query('SELECT 1 FROM app_migrations WHERE version=3')->fetchColumn();
    if (!$seen) {
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $columns = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
        $additions = [
            'subtotal' => 'INTEGER NOT NULL DEFAULT 0 CHECK(subtotal >= 0)',
            'discount_total' => 'INTEGER NOT NULL DEFAULT 0 CHECK(discount_total >= 0)',
            'delivery_fee' => 'INTEGER NOT NULL DEFAULT 0 CHECK(delivery_fee >= 0)',
            'promo_code' => "TEXT NOT NULL DEFAULT ''",
        ];
        foreach ($additions as $column => $definition) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        $pdo->exec('UPDATE orders SET subtotal=total WHERE subtotal=0 AND discount_total=0');
        $pdo->exec("INSERT OR IGNORE INTO settings(key,value) VALUES('delivery_fee','0'),('commerce_mode','educational_demo')");
        $pdo->exec("INSERT OR IGNORE INTO custom_set_variants(slug,name,minimum_distinct_items,maximum_distinct_items,maximum_total_quantity,active,demo_only,sort_order) VALUES
            ('signature','Tokyo Signature',3,5,8,1,1,10),
            ('seasonal','Seasonal Selection',4,6,10,1,1,20),
            ('omakase','Omakase Selection',5,8,12,1,1,30)");
        $pdo->exec("INSERT OR IGNORE INTO loyalty_tiers(slug,name,minimum_completed_spend,earn_multiplier_basis_points,benefits_json,active,sort_order,demo_only) VALUES
            ('silver','Silver',0,10000,'[\"Базовые привилегии\"]',1,10,1),
            ('gold','Gold',150000,12500,'[\"Приоритетные предложения\"]',1,20,1),
            ('platinum','Platinum',400000,15000,'[\"Персональные предложения\"]',1,30,1)");
        $campaign=$pdo->prepare("INSERT OR IGNORE INTO promotion_campaigns(slug,campaign_type,title,description,image_url,reward_type,reward_value,minimum_subtotal,maximum_discount,active,demo_only,sort_order) VALUES(?,?,?,?,?,?,?,?,?,1,1,?)");
        $campaign->execute(['demo-privilege','exclusive','Tokyo Privilege · учебное предложение','Демонстрационный код для проверки корзины и серверного пересчёта. Условия вымышлены и доступны для изменения администратором.','assets/generated/home-hero-nigiri-v2.webp','percent',10,0,5000,10]);
        $demoCampaignId=(int)$pdo->query("SELECT id FROM promotion_campaigns WHERE slug='demo-privilege'")->fetchColumn();
        $pdo->prepare('INSERT OR IGNORE INTO promo_codes(code,campaign_id,active,maximum_uses,maximum_uses_per_user,first_order_only) VALUES(?,?,1,500,1,0)')->execute(['DEMO10',$demoCampaignId]);
        $campaign->execute(['golden-hour','golden_hour','Golden Hour · сет дня','Условное предложение в будни с 14:00 до 17:00. Учебное правило настраивается администратором.','assets/generated/room-garden-terrace.webp','percent',15,0,4000,20]);
        $hourCampaignId=(int)$pdo->query("SELECT id FROM promotion_campaigns WHERE slug='golden-hour'")->fetchColumn();
        $pdo->prepare("UPDATE promotion_campaigns SET weekday_mask=31,starts_time='14:00',ends_time='17:00' WHERE id=?")->execute([$hourCampaignId]);
        $pdo->prepare('INSERT OR IGNORE INTO promo_codes(code,campaign_id,active,maximum_uses,maximum_uses_per_user,first_order_only) VALUES(?,?,1,200,1,0)')->execute(['TOKYOHOUR',$hourCampaignId]);
        $campaign->execute(['secret-menu-demo','secret_menu','Secret Menu · учебный доступ','Скрытый учебный набор. Доступ проверяется по промокоду или уровню участника.','assets/food/17-omakase-set.webp','access',0,0,0,90]);
        $secretId=(int)$pdo->query("SELECT id FROM promotion_campaigns WHERE slug='secret-menu-demo'")->fetchColumn();
        $pdo->prepare('INSERT OR IGNORE INTO secret_menu_rules(campaign_id,access_kind,required_value,active) VALUES(?,?,?,1)')->execute([$secretId,'code','SECRET']);
        $pdo->prepare('INSERT OR IGNORE INTO secret_menu_rules(campaign_id,access_kind,required_value,active) VALUES(?,?,?,1)')->execute([$secretId,'tier','gold']);
        $pdo->prepare('INSERT OR IGNORE INTO promo_codes(code,campaign_id,active,maximum_uses,maximum_uses_per_user,first_order_only) VALUES(?,?,1,500,1,0)')->execute(['SECRET',$secretId]);
        $pdo->prepare("INSERT OR IGNORE INTO promotion_targets(campaign_id,target_kind,target_key,excluded) VALUES(?,'dish',?,0)")->execute([$secretId,'17']);
        $pdo->prepare("INSERT OR IGNORE INTO promotion_targets(campaign_id,target_kind,target_key,excluded) VALUES(?,'dish',?,0)")->execute([$secretId,'18']);
        $pdo->prepare("INSERT OR IGNORE INTO seasonal_collections(slug,name,description,image_url,theme_key,active,demo_only,sort_order) VALUES('autumn-in-kyoto','Autumn in Kyoto','Сезонная учебная коллекция блюд и вкусов. Состав и период редактируются администратором.','assets/generated/tokyo-seasonal-omakase-v2.webp','autumn',1,1,10)")->execute();
        $pdo->prepare("UPDATE seasonal_collections SET image_url='assets/generated/tokyo-seasonal-omakase-v2.webp' WHERE slug='autumn-in-kyoto' AND demo_only=1 AND image_url='assets/generated/room-garden-terrace.webp'")->execute();
        $collectionId=(int)$pdo->query("SELECT id FROM seasonal_collections WHERE slug='autumn-in-kyoto'")->fetchColumn();
        $collectionItem=$pdo->prepare('INSERT OR IGNORE INTO seasonal_collection_items(collection_id,dish_id,sort_order) VALUES(?,?,?)');
        foreach ([6,12,17,19,20] as $position=>$dishId) $collectionItem->execute([$collectionId,$dishId,($position+1)*10]);
        $pdo->exec("INSERT OR IGNORE INTO app_migrations(version) VALUES(3)");
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    }
    // v4 adds certificate-preview rate limiting and editable denomination settings.
    // Keep this separate so installations that already recorded v3 are upgraded too.
    if (!$pdo->query('SELECT 1 FROM app_migrations WHERE version=4')->fetchColumn()) {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $columns = $pdo->query('PRAGMA table_info(gift_certificate_previews)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('actor_key', $columns, true)) {
                $pdo->exec("ALTER TABLE gift_certificate_previews ADD COLUMN actor_key TEXT NOT NULL DEFAULT ''");
            }
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_gift_preview_actor_created ON gift_certificate_previews(actor_key,created_at DESC)');
            $pdo->exec("INSERT OR IGNORE INTO settings(key,value) VALUES('certificate_denominations','[10000,20000,30000,50000]')");
            $pdo->exec('INSERT OR IGNORE INTO app_migrations(version) VALUES(4)');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
    if (!$pdo->query('SELECT 1 FROM app_migrations WHERE version=5')->fetchColumn()) {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $pdo->exec("UPDATE seasonal_collections SET image_url='assets/generated/tokyo-seasonal-omakase-v2.webp' WHERE slug='autumn-in-kyoto' AND demo_only=1 AND image_url='assets/generated/room-garden-terrace.webp'");
            $pdo->exec('INSERT OR IGNORE INTO app_migrations(version) VALUES(5)');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    if (!$pdo->query('SELECT 1 FROM app_migrations WHERE version=6')->fetchColumn()) {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $bookingColumns = $pdo->query('PRAGMA table_info(bookings)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('guest_email', $bookingColumns, true)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN guest_email TEXT NOT NULL DEFAULT ''");
            }
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_outbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_key TEXT NOT NULL UNIQUE,
                booking_id INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
                recipient TEXT NOT NULL,
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','sending','sent','failed')),
                attempts INTEGER NOT NULL DEFAULT 0 CHECK(attempts >= 0),
                locked_at TEXT,
                sent_at TEXT,
                last_error TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_email_outbox_status_id ON email_outbox(status,id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_email_outbox_booking_id ON email_outbox(booking_id,id)');
            $pdo->exec('INSERT OR IGNORE INTO app_migrations(version) VALUES(6)');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    if (!$pdo->query('SELECT 1 FROM app_migrations WHERE version=7')->fetchColumn()) {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS api_booking_requests (
                token TEXT PRIMARY KEY,
                booking_id INTEGER UNIQUE REFERENCES bookings(id) ON DELETE SET NULL,
                response_json TEXT,
                response_status INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK ((response_json IS NULL AND response_status IS NULL) OR (response_json IS NOT NULL AND response_status IS NOT NULL))
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS booking_email_rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bucket_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_booking_email_rate_bucket_time ON booking_email_rate_limits(bucket_hash,created_at)');
            $pdo->exec('INSERT OR IGNORE INTO app_migrations(version) VALUES(7)');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    $addressTableExists = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='user_addresses'")->fetchColumn();
    if (!$pdo->query('SELECT 1 FROM app_migrations WHERE version=8')->fetchColumn() || !$addressTableExists) {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_addresses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                label TEXT NOT NULL CHECK(length(label) BETWEEN 2 AND 60),
                address TEXT NOT NULL CHECK(length(address) BETWEEN 5 AND 240),
                is_default INTEGER NOT NULL DEFAULT 0 CHECK(is_default IN (0,1)),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_addresses_owner ON user_addresses(user_id,id DESC)');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_user_addresses_one_default ON user_addresses(user_id) WHERE is_default=1');
            $pdo->exec('INSERT OR IGNORE INTO app_migrations(version) VALUES(8)');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    // v9 seeds editable educational pairings for the existing catalogue.
    // INSERT OR IGNORE preserves any pairings an administrator has already set.
    if (!$pdo->query('SELECT 1 FROM app_migrations WHERE version=9')->fetchColumn()) {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS dish_pairings (
                dish_id INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
                paired_dish_id INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
                pairing_type TEXT NOT NULL CHECK(pairing_type IN ('drink','sauce','dish','dessert')),
                sort_order INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY(dish_id,paired_dish_id,pairing_type),
                CHECK(dish_id<>paired_dish_id)
            )");
            $pairings = [
                [1,4,'drink',10],[1,19,'dessert',20],
                [2,20,'drink',10],[2,8,'dessert',20],
                [3,20,'drink',10],[3,6,'dish',20],
                [4,8,'dessert',10],[4,19,'dessert',20],
                [5,20,'drink',10],[5,19,'dessert',20],
                [6,20,'drink',10],[6,3,'dish',20],
                [7,4,'drink',10],[7,8,'dessert',20],
                [8,4,'drink',10],[8,19,'dessert',20],
                [9,4,'drink',10],[9,14,'dish',20],
                [10,20,'drink',10],[10,3,'dish',20],
                [11,20,'drink',10],[11,8,'dessert',20],
                [12,20,'drink',10],[12,15,'dish',20],
                [13,20,'drink',10],[13,14,'dish',20],
                [14,4,'drink',10],[14,6,'dish',20],
                [15,20,'drink',10],[15,14,'dish',20],
                [16,4,'drink',10],[16,19,'dessert',20],
                [17,20,'drink',10],[17,19,'dessert',20],
                [18,20,'drink',10],[18,19,'dessert',20],
                [19,4,'drink',10],[19,8,'dessert',20],
                [20,12,'dish',10],[20,15,'dish',20],
            ];
            $insertPairing = $pdo->prepare('INSERT OR IGNORE INTO dish_pairings(dish_id,paired_dish_id,pairing_type,sort_order) VALUES(?,?,?,?)');
            foreach ($pairings as $pairing) $insertPairing->execute($pairing);
            $pdo->exec('INSERT OR IGNORE INTO app_migrations(version) VALUES(9)');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    // v10 adds one photographed scallop nigiri to make the existing scallop
    // ingredient filter useful. Keep it additive: never replace an edited dish.
    if (!$pdo->query('SELECT 1 FROM app_migrations WHERE version=10')->fetchColumn()) {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $pdo->exec("INSERT OR IGNORE INTO ingredients(slug,name,active) VALUES ('scallop','Морской гребешок',1)");
            $ingredientQuery = $pdo->query("SELECT slug FROM ingredients WHERE slug='scallop' OR name='Морской гребешок' ORDER BY CASE WHEN slug='scallop' THEN 0 ELSE 1 END LIMIT 1");
            $ingredientSlug = (string)$ingredientQuery->fetchColumn();
            if ($ingredientSlug === '') throw new RuntimeException('Scallop ingredient could not be resolved.');
            $dishName = 'Нигири с морским гребешком';
            $findDish = $pdo->prepare('SELECT id FROM dishes WHERE name=? ORDER BY id LIMIT 1');
            $findDish->execute([$dishName]);
            $dishId = $findDish->fetchColumn();
            if ($dishId === false) {
                $insertDish = $pdo->prepare('INSERT INTO dishes(category,name,description,weight,price,image_url,featured,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');
                $insertDish->execute(['sushi',$dishName,'Сладкий морской гребешок, слегка обожжённый снаружи, на рисе для суши; цитрусовый акцент юдзу.','40 г',2200,'assets/food/21-nigiri-scallop.webp',0,1,4]);
                $dishId = (int)$pdo->lastInsertId();
            }
            $dishId = (int)$dishId;
            $linkIngredient = $pdo->prepare('INSERT OR IGNORE INTO dish_ingredients(dish_id,ingredient_slug,is_optional,sort_order) VALUES(?,?,0,10)');
            $linkIngredient->execute([$dishId,$ingredientSlug]);
            $linkPairing = $pdo->prepare("INSERT OR IGNORE INTO dish_pairings(dish_id,paired_dish_id,pairing_type,sort_order) VALUES(?,20,'drink',10)");
            $linkPairing->execute([$dishId]);
            $pdo->exec('INSERT OR IGNORE INTO app_migrations(version) VALUES(10)');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    // v11 adds source-backed ingredient notes for the educational dish cards.
    // Seed once and only insert missing links so later admin edits are preserved.
    if (!$pdo->query('SELECT 1 FROM app_migrations WHERE version=11')->fetchColumn()) {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $composition = [
                'Филадельфия' => ['Лосось','Сливочный сыр','Огурец','Рис для суши','Нори'],
                'Калифорния' => ['Снежный краб','Авокадо','Огурец','Икра тобико','Рис для суши'],
                'Зелёный дракон' => ['Копчёный угорь','Авокадо','Сливочный сыр','Соус унаги','Кунжут'],
                'Запечённый ролл «Вулкан»' => ['Лосось','Огурец','Сливочный сыр','Соус спайси'],
                'Нигири с лососем' => ['Лосось','Рис для суши'],
                'Нигири с тунцом' => ['Тунец','Рис для суши'],
                'Гункан с икрой' => ['Икра лосося','Рис для суши','Нори'],
                'Рамен с говядиной' => ['Говядина','Говяжий бульон','Лапша','Яйцо адзитама'],
                'Удон с курицей терияки' => ['Лапша удон','Курица','Овощи','Соус терияки'],
                'Моти' => ['Рисовое тесто','Ягодная начинка'],
                'Матча латте' => ['Матча','Кокосовое молоко'],
                'Тартар из тунца с юдзу' => ['Тунец ахи','Юдзу','Соус понзу','Зелёное яблоко','Хрустящий рис'],
                'Гёдза с уткой' => ['Утка','Имбирь','Кунжутное масло','Соус понзу','Тесто'],
                'Мисо-суп с вакаме' => ['Бульон даси','Паста сира-мисо','Тофу','Вакаме','Зелёный лук'],
                'Эби темпура' => ['Тигровые креветки','Тесто темпура','Сезонные овощи','Соус тэнцую','Дайкон'],
                'Донбури с угрём унаги' => ['Рис косихикари','Угорь','Глазурь кабаяки','Тамаго','Маринованный дайкон'],
                'Матча-тирамису' => ['Крем маскарпоне','Савоярди','Ходзича','Матча'],
                'Юдзу-лимонад' => ['Юдзу','Содовая','Свежий имбирь'],
                'Нигири с морским гребешком' => ['Морской гребешок','Рис для суши','Юдзу'],
            ];
            $insertIngredient = $pdo->prepare('INSERT OR IGNORE INTO ingredients(slug,name,active) VALUES(?,?,1)');
            $findIngredient = $pdo->prepare('SELECT slug FROM ingredients WHERE name=? ORDER BY slug LIMIT 1');
            $findDish = $pdo->prepare('SELECT id FROM dishes WHERE name=? ORDER BY id LIMIT 1');
            $insertLink = $pdo->prepare('INSERT OR IGNORE INTO dish_ingredients(dish_id,ingredient_slug,is_optional,sort_order) VALUES(?,?,0,?)');
            foreach ($composition as $dishName => $ingredientNames) {
                $findDish->execute([$dishName]);
                $dishId = $findDish->fetchColumn();
                if ($dishId === false) continue;
                foreach ($ingredientNames as $position => $ingredientName) {
                    $slug = 'dish-note-' . substr(hash('sha256', $ingredientName), 0, 20);
                    $insertIngredient->execute([$slug,$ingredientName]);
                    $findIngredient->execute([$ingredientName]);
                    $resolvedSlug = (string)$findIngredient->fetchColumn();
                    if ($resolvedSlug === '') throw new RuntimeException('Could not resolve seeded dish ingredient.');
                    $insertLink->execute([(int)$dishId,$resolvedSlug,($position + 1) * 10]);
                }
            }
            $pdo->exec('INSERT OR IGNORE INTO app_migrations(version) VALUES(11)');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

function commerce_actor_key(?int $userId, string $sessionId): string
{
    return hash('sha256', $userId ? 'user:' . $userId : 'session:' . $sessionId);
}

function commerce_timezone(string $value = 'Asia/Qyzylorda'): DateTimeZone
{
    try { return new DateTimeZone($value ?: 'Asia/Qyzylorda'); }
    catch (Throwable) { return new DateTimeZone('Asia/Qyzylorda'); }
}

function commerce_campaign_is_active(array $campaign, ?DateTimeImmutable $now = null): bool
{
    if ((int)($campaign['active'] ?? 0) !== 1) return false;
    $zone = commerce_timezone((string)($campaign['timezone'] ?? 'Asia/Qyzylorda'));
    $now = ($now ?? new DateTimeImmutable('now', $zone))->setTimezone($zone);
    foreach (['starts_at' => 1, 'ends_at' => -1] as $key => $direction) {
        $raw = trim((string)($campaign[$key] ?? ''));
        if ($raw === '') continue;
        try { $date = new DateTimeImmutable($raw, $zone); }
        catch (Throwable) { return false; }
        if (($direction === 1 && $now < $date) || ($direction === -1 && $now > $date)) return false;
    }
    $mask = (int)($campaign['weekday_mask'] ?? 127);
    $dayBit = 1 << ((int)$now->format('N') - 1);
    if (($mask & $dayBit) === 0) return false;
    $start = (string)($campaign['starts_time'] ?? '');
    $end = (string)($campaign['ends_time'] ?? '');
    if ($start !== '' && $end !== '') {
        $time = $now->format('H:i');
        if ($start <= $end ? ($time < $start || $time > $end) : ($time < $start && $time > $end)) return false;
    }
    return true;
}

function commerce_date_window_is_active(?string $startsAt, ?string $endsAt, string $timezone = 'Asia/Qyzylorda'): bool
{
    $zone=commerce_timezone($timezone); $now=new DateTimeImmutable('now',$zone);
    foreach ([[$startsAt,1],[$endsAt,-1]] as [$raw,$direction]) {
        if (!$raw) continue;
        try { $date=new DateTimeImmutable($raw,$zone); } catch (Throwable) { return false; }
        if (($direction===1 && $now<$date) || ($direction===-1 && $now>$date)) return false;
    }
    return true;
}

function commerce_campaign_tier_is_eligible(PDO $pdo, array $campaign, ?int $userId): bool
{
    $requiredSlug = trim((string)($campaign['required_tier'] ?? ''));
    if ($requiredSlug === '') return true;
    if (!$userId || $userId < 1) return false;

    $requiredQuery = $pdo->prepare('SELECT minimum_completed_spend,sort_order FROM loyalty_tiers WHERE slug=? AND active=1');
    $requiredQuery->execute([$requiredSlug]);
    $requiredTier = $requiredQuery->fetch();
    $currentTier = commerce_user_tier($pdo, $userId);
    if (!$requiredTier || !$currentTier) return false;

    $currentSpend = (int)$currentTier['minimum_completed_spend'];
    $requiredSpend = (int)$requiredTier['minimum_completed_spend'];
    return $currentSpend > $requiredSpend
        || ($currentSpend === $requiredSpend && (int)$currentTier['sort_order'] >= (int)$requiredTier['sort_order']);
}

function commerce_public_offers(PDO $pdo, ?int $userId = null): array
{
    $rows = $pdo->query("SELECT c.* FROM promotion_campaigns c
        WHERE c.active=1 AND c.campaign_type<>'secret_menu'
        ORDER BY c.sort_order,c.id DESC")->fetchAll();
    $offers = [];
    foreach ($rows as $row) {
        if (in_array($row['campaign_type'], ['personal','birthday'], true)) {
            if (!$userId) continue;
            $q = $pdo->prepare('SELECT 1 FROM personalized_offers WHERE user_id=? AND campaign_id=? AND used_order_id IS NULL AND (expires_at IS NULL OR expires_at>=CURRENT_TIMESTAMP)');
            $q->execute([$userId, $row['id']]);
            if (!$q->fetchColumn()) continue;
            if ($row['campaign_type']==='birthday' && !commerce_birthday_eligible($pdo,$userId)) continue;
            if (!commerce_campaign_has_usable_promo_code($pdo, (int)$row['id'], (string)$row['timezone'])) continue;
        }
        if (!commerce_campaign_tier_is_eligible($pdo, $row, $userId)) continue;
        if (!commerce_campaign_is_active($row)) continue;
        $row['demo_only'] = (int)$row['demo_only'] === 1;
        $offers[] = $row;
    }
    return $offers;
}

function commerce_campaign_has_usable_promo_code(PDO $pdo, int $campaignId, string $timezone): bool
{
    if ($campaignId < 1) return false;
    $query = $pdo->prepare('SELECT starts_at,ends_at,uses_count,maximum_uses FROM promo_codes WHERE campaign_id=? AND active=1');
    $query->execute([$campaignId]);
    foreach ($query->fetchAll() as $code) {
        $hasUses = $code['maximum_uses'] === null || (int)$code['uses_count'] < (int)$code['maximum_uses'];
        if ($hasUses && commerce_date_window_is_active($code['starts_at'] ?: null, $code['ends_at'] ?: null, $timezone)) return true;
    }
    return false;
}

function commerce_public_collections(PDO $pdo): array
{
    $rows = $pdo->query("SELECT c.*,COUNT(ci.dish_id) AS dish_count FROM seasonal_collections c
        LEFT JOIN seasonal_collection_items ci ON ci.collection_id=c.id
        WHERE c.active=1 GROUP BY c.id ORDER BY c.sort_order,c.id DESC")->fetchAll();
    return array_values(array_filter($rows, static function (array $row): bool {
        return commerce_campaign_is_active([
            'active' => $row['active'], 'starts_at' => $row['starts_at'], 'ends_at' => $row['ends_at'],
            'timezone' => 'Asia/Qyzylorda', 'weekday_mask' => 127,
        ]);
    }));
}

function commerce_birthday_occurrence(PDO $pdo, int $userId): ?DateTimeImmutable
{
    if ($userId<1) return null;
    $q=$pdo->prepare('SELECT birth_date,birthday_offer_consent FROM user_profiles WHERE user_id=?');
    $q->execute([$userId]); $profile=$q->fetch();
    if (!$profile || (int)$profile['birthday_offer_consent']!==1 || empty($profile['birth_date'])) return null;
    $settings=$pdo->query('SELECT birthday_window_days FROM loyalty_settings WHERE id=1')->fetch() ?: [];
    $window=max(0,min(60,(int)($settings['birthday_window_days']??0)));
    try {
        $birth=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$profile['birth_date'],commerce_timezone());
        if (!$birth || $birth->format('Y-m-d')!==$profile['birth_date']) return null;
        $today=new DateTimeImmutable('today',commerce_timezone());
        $month=(int)$birth->format('m'); $day=(int)$birth->format('d');
        $year=(int)$today->format('Y');
        $candidate=$today->setDate($year,$month,min($day,(int)$today->setDate($year,$month,1)->format('t')));
        if ($candidate<$today) {
            $year++;
            $candidate=$candidate->setDate($year,$month,min($day,(int)$candidate->setDate($year,$month,1)->format('t')));
        }
        return (int)$today->diff($candidate)->format('%a') <= $window ? $candidate : null;
    } catch (Throwable) { return null; }
}

function commerce_birthday_eligible(PDO $pdo, int $userId): bool
{
    return commerce_birthday_occurrence($pdo,$userId)!==null;
}

function commerce_award_birthday_points(PDO $pdo, int $userId): int
{
    if ($userId<1) return 0;
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $settings=$pdo->query('SELECT enabled,birthday_reward_points FROM loyalty_settings WHERE id=1')->fetch() ?: [];
        $points=(int)($settings['birthday_reward_points']??0);
        $occurrence=(!empty($settings['enabled']) && $points>0) ? commerce_birthday_occurrence($pdo,$userId) : null;
        if (!$occurrence) { $pdo->commit(); return 0; }
        $eventKey='birthday:'.$userId.':'.$occurrence->format('Y');
        $insert=$pdo->prepare("INSERT OR IGNORE INTO loyalty_ledger(user_id,event_key,transaction_type,points,note) VALUES(?,?,'birthday',?,'Поздравительные бонусы Tokyo Privilege')");
        $insert->execute([$userId,$eventKey,$points]);
        $awarded=$insert->rowCount()===1 ? $points : 0;
        $pdo->commit();
        return $awarded;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_referral_code_for_user(PDO $pdo, int $userId): string
{
    if ($userId<1) throw new DomainException('Войдите в аккаунт, чтобы получить приглашение.');
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $find=$pdo->prepare('SELECT referral_code FROM user_profiles WHERE user_id=?');
        $find->execute([$userId]);
        $existing=$find->fetchColumn();
        if (is_string($existing) && preg_match('/^TP[A-F0-9]{10}$/',$existing)) {
            $pdo->commit();
            return $existing;
        }

        // Keep codes issued before the profile column was used valid and stable.
        $legacy=$pdo->prepare('SELECT code FROM referrals WHERE inviter_user_id=? ORDER BY id LIMIT 1');
        $legacy->execute([$userId]);
        $legacyCode=$legacy->fetchColumn();
        $save=$pdo->prepare("INSERT INTO user_profiles(user_id,referral_code) VALUES(?,?) ON CONFLICT(user_id) DO UPDATE SET referral_code=COALESCE(NULLIF(user_profiles.referral_code,''),excluded.referral_code)");
        if (is_string($legacyCode) && preg_match('/^TP[A-F0-9]{10}$/',$legacyCode)) {
            try {
                $save->execute([$userId,$legacyCode]);
                $find->execute([$userId]);
                $saved=$find->fetchColumn();
                if (is_string($saved) && preg_match('/^TP[A-F0-9]{10}$/',$saved)) {
                    $pdo->commit();
                    return $saved;
                }
            } catch (PDOException $error) {
                if ((string)$error->getCode()!=='23000' && !str_contains(strtolower($error->getMessage()),'unique')) throw $error;
            }
        }

        for ($attempt=0;$attempt<5;$attempt++) {
            $code='TP'.strtoupper(bin2hex(random_bytes(5)));
            try {
                $save->execute([$userId,$code]);
                $find->execute([$userId]);
                $saved=$find->fetchColumn();
                if (is_string($saved) && preg_match('/^TP[A-F0-9]{10}$/',$saved)) {
                    $pdo->commit();
                    return $saved;
                }
            } catch (PDOException $error) {
                if ((string)$error->getCode()!=='23000' && !str_contains(strtolower($error->getMessage()),'unique')) throw $error;
            }
        }
        throw new RuntimeException('Не удалось создать код приглашения.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_attach_referral(PDO $pdo, int $referredUserId, string $code): bool
{
    $code=strtoupper(trim($code));
    if ($referredUserId<1 || !preg_match('/^TP[A-F0-9]{10}$/',$code)) throw new DomainException('Код приглашения имеет неверный формат.');
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $q=$pdo->prepare('SELECT user_id FROM user_profiles WHERE referral_code=? COLLATE NOCASE');
        $q->execute([$code]); $inviterUserId=(int)$q->fetchColumn();
        if ($inviterUserId<1) {
            // Accept a code already issued by the previous one-invite implementation.
            $q=$pdo->prepare('SELECT inviter_user_id FROM referrals WHERE code=? COLLATE NOCASE ORDER BY id LIMIT 1');
            $q->execute([$code]); $inviterUserId=(int)$q->fetchColumn();
        }
        if ($inviterUserId<1) throw new DomainException('Код приглашения не найден.');
        if ($inviterUserId===$referredUserId) throw new DomainException('Нельзя пригласить самого себя.');
        $q=$pdo->prepare('SELECT inviter_user_id FROM referrals WHERE referred_user_id=?');
        $q->execute([$referredUserId]); $existingInviter=$q->fetchColumn();
        if ($existingInviter!==false) {
            if ((int)$existingInviter===$inviterUserId) {
                $pdo->commit();
                return true;
            }
            throw new DomainException('Для аккаунта уже задан код приглашения.');
        }
        $q=$pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?');
        $q->execute([$referredUserId]);
        if ((int)$q->fetchColumn()>0) throw new DomainException('Код приглашения можно указать до первого заказа.');
        $insert=$pdo->prepare("INSERT INTO referrals(code,inviter_user_id,referred_user_id,status) VALUES(?,?,?,'pending')");
        for ($attempt=0;$attempt<5;$attempt++) {
            $trackingCode='TP'.strtoupper(bin2hex(random_bytes(5)));
            try {
                $insert->execute([$trackingCode,$inviterUserId,$referredUserId]);
                $pdo->commit();
                return true;
            } catch (PDOException $error) {
                if ((string)$error->getCode()!=='23000' && !str_contains(strtolower($error->getMessage()),'unique')) throw $error;
            }
        }
        throw new RuntimeException('Не удалось сохранить приглашение.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_secret_menu_restricted_dish_ids(PDO $pdo): array
{
    $campaigns = $pdo->query("SELECT id FROM promotion_campaigns WHERE campaign_type='secret_menu'")->fetchAll(PDO::FETCH_COLUMN);
    if (!$campaigns) return [];
    $dishes = $pdo->query('SELECT id,category FROM dishes WHERE active=1')->fetchAll();
    $restricted = [];
    foreach ($campaigns as $campaignId) {
        $targets = commerce_campaign_targets($pdo, (int)$campaignId);
        // A Secret Menu campaign must have an explicit inclusion scope. This avoids
        // accidentally hiding (or unlocking) the entire catalog when its targets are blank.
        if (!$targets || !array_filter($targets, static fn(array $target): bool => (int)$target['excluded'] === 0)) continue;
        foreach ($dishes as $dish) {
            if (commerce_item_is_targeted(['dish'=>['id'=>(int)$dish['id'],'category'=>(string)$dish['category']]], $targets)) {
                $restricted[(int)$dish['id']] = true;
            }
        }
    }
    return array_keys($restricted);
}

function commerce_filter_secret_menu_dishes(PDO $pdo, array $dishes, ?int $userId = null, string $secretCode = '', bool $isAdmin = false): array
{
    $restricted = array_fill_keys(commerce_secret_menu_restricted_dish_ids($pdo), true);
    if (!$restricted) return $dishes;
    $allowed = [];
    foreach (commerce_secret_menu_dishes($pdo, $userId, $secretCode, $isAdmin) as $dish) $allowed[(int)$dish['id']] = true;
    return array_values(array_filter($dishes, static function (array $dish) use ($restricted, $allowed): bool {
        $id = (int)($dish['id'] ?? 0);
        return !isset($restricted[$id]) || isset($allowed[$id]);
    }));
}

function commerce_catalog_items(PDO $pdo, array $items, ?int $userId = null, string $secretCode = '', bool $isAdmin = false): array
{
    if (!array_is_list($items) || count($items) > 30) {
        throw new DomainException('Некорректный состав корзины.');
    }
    $quantities = [];
    foreach ($items as $item) {
        if (!is_array($item) || array_is_list($item)) throw new DomainException('Некорректный состав корзины.');
        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1 || !$qty || $qty < 1 || $qty > 20) throw new DomainException('Некорректное количество блюда.');
        $quantities[$id] = ($quantities[$id] ?? 0) + $qty;
        if ($quantities[$id] > 20) throw new DomainException('Количество одной позиции не может превышать 20.');
    }
    $result = [];
    $restrictedSecretIds = array_fill_keys(commerce_secret_menu_restricted_dish_ids($pdo), true);
    $authorizedSecretIds = [];
    if ($restrictedSecretIds) {
        foreach (commerce_secret_menu_dishes($pdo, $userId, $secretCode, $isAdmin) as $secretDish) $authorizedSecretIds[(int)$secretDish['id']] = true;
    }
    $query = $pdo->prepare('SELECT d.id,d.name,d.category,d.price,d.image_url FROM dishes d WHERE d.id=? AND d.active=1');
    foreach ($quantities as $id => $qty) {
        $query->execute([$id]);
        $dish = $query->fetch();
        if (!$dish) throw new DomainException('Одно из блюд больше недоступно. Обновите меню.');
        $dish['id'] = (int)$dish['id'];
        if (isset($restrictedSecretIds[$dish['id']]) && !isset($authorizedSecretIds[$dish['id']])) throw new DomainException('Позиция Secret Menu требует действующий код или привилегированный аккаунт.');
        $dish['price'] = (int)$dish['price'];
        $result[] = ['dish' => $dish, 'qty' => $qty, 'line_total' => $dish['price'] * $qty];
    }
    return $result;
}

function commerce_validate_custom_set(PDO $pdo, array $set, ?int $userId = null, string $secretCode = '', bool $isAdmin = false): array
{
    $variant = strtolower(trim((string)($set['variant'] ?? 'signature')));
    $q = $pdo->prepare('SELECT * FROM custom_set_variants WHERE slug=? AND active=1');
    $q->execute([$variant]);
    $rule = $q->fetch();
    if (!$rule) throw new DomainException('Выберите доступный формат сета.');
    $rawItems=$set['items']??[];
    if (!is_array($rawItems) || !array_is_list($rawItems) || count($rawItems)<3 || count($rawItems)>8) throw new DomainException('Добавьте от 3 до 8 разных блюд в сет.');
    $ids=[];
    foreach ($rawItems as $raw) {
        $id=is_array($raw)?filter_var($raw['id']??null,FILTER_VALIDATE_INT):false;
        $qty=is_array($raw)?filter_var($raw['qty']??null,FILTER_VALIDATE_INT):false;
        if (!$id || $id<1 || !$qty || $qty<1 || $qty>4 || isset($ids[$id])) throw new DomainException('В сете должны быть разные блюда, от 1 до 4 порций каждого.');
        $ids[$id]=true;
    }
    $items = commerce_catalog_items($pdo, $rawItems, $userId, $secretCode, $isAdmin);
    $distinct = count($items);
    $totalQty = array_sum(array_column($items, 'qty'));
    if ($distinct < max(3,(int)$rule['minimum_distinct_items']) || $distinct > min(8,(int)$rule['maximum_distinct_items']) || $totalQty > min(12,(int)$rule['maximum_total_quantity'])) {
        throw new DomainException('Состав сета не соответствует выбранному формату.');
    }
    return [
        'variant' => $variant,
        'name' => (string)$rule['name'],
        'items' => $items,
        'subtotal' => array_sum(array_column($items, 'line_total')),
        'total_quantity' => $totalQty,
    ];
}

function commerce_campaign_targets(PDO $pdo, int $campaignId): array
{
    $q = $pdo->prepare('SELECT target_kind,target_key,excluded FROM promotion_targets WHERE campaign_id=?');
    $q->execute([$campaignId]);
    return $q->fetchAll();
}

function commerce_item_is_targeted(array $item, array $targets, bool $isSet = false): bool
{
    if (!$targets) return true;
    $included = array_filter($targets, static fn(array $row): bool => (int)$row['excluded'] === 0);
    $matched = false;
    foreach ($targets as $target) {
        $kind = (string)$target['target_kind'];
        $key = (string)$target['target_key'];
        $hit = $isSet
            ? ($kind === 'set' && $key === (string)$item['variant'])
            : (($kind === 'dish' && (string)$item['dish']['id'] === $key) || ($kind === 'category' && (string)$item['dish']['category'] === $key));
        if (!$hit) continue;
        if ((int)$target['excluded'] === 1) return false;
        $matched = true;
    }
    return !$included || $matched;
}

function commerce_get_loyalty_balance(PDO $pdo, int $userId): int
{
    $q = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM loyalty_ledger WHERE user_id=?');
    $q->execute([$userId]);
    return max(0, (int)$q->fetchColumn());
}

function commerce_quote(PDO $pdo, array $items, array $customSets = [], string $code = '', string $fulfillment = 'delivery', ?int $userId = null, string $actorKey = '', ?int $giftDishId = null, int $pointsToRedeem = 0, string $secretCode = '', bool $isAdmin = false, int $giftQuantity = 1): array
{
    try {
        $giftItems=[];
        if ($giftQuantity < 1 || $giftQuantity > 10) throw new DomainException('Количество подарка должно быть от 1 до 10.');
        if (!in_array($fulfillment, ['delivery','pickup'], true)) throw new DomainException('Выберите способ получения заказа.');
        if (!array_is_list($customSets) || count($customSets) > 3) throw new DomainException('Некорректное количество сетов.');
        $catalog = commerce_catalog_items($pdo, $items, $userId, $secretCode, $isAdmin);
        $sets = [];
        foreach ($customSets as $set) {
            if (!is_array($set) || array_is_list($set)) throw new DomainException('Некорректный сет.');
            $sets[] = commerce_validate_custom_set($pdo, $set, $userId, $secretCode, $isAdmin);
        }
        if (!$catalog && !$sets) throw new DomainException('Добавьте блюда или соберите сет.');
        $subtotal = array_sum(array_column($catalog, 'line_total')) + array_sum(array_column($sets, 'subtotal'));
        if ($subtotal < 1 || $subtotal > 500000) throw new DomainException('Проверьте сумму заказа.');
        $deliveryFee = $fulfillment === 'delivery' ? max(0, (int)setting('delivery_fee','0')) : 0;
        $discount = 0;
        $promotionDiscount = 0;
        $adjustments = [];
        $appliedCampaign = null;
        $promoStackable = true;
        $promotionCode = strtoupper(trim($code));
        if ($promotionCode !== '') {
            $q = $pdo->prepare('SELECT pc.code,pc.campaign_id,pc.active AS code_active,pc.starts_at AS code_starts_at,pc.ends_at AS code_ends_at,pc.maximum_uses,pc.maximum_uses_per_user,pc.uses_count,pc.first_order_only,c.* FROM promo_codes pc JOIN promotion_campaigns c ON c.id=pc.campaign_id WHERE pc.code=?');
            $q->execute([$promotionCode]);
            $promo = $q->fetch();
            if (!$promo || (int)$promo['code_active'] !== 1 || !commerce_campaign_is_active($promo) || !commerce_date_window_is_active($promo['code_starts_at']??null,$promo['code_ends_at']??null,(string)$promo['timezone'])) throw new DomainException('Промокод не найден, отключён или срок его действия завершён.');
            if (!commerce_campaign_tier_is_eligible($pdo, $promo, $userId)) throw new DomainException('Это предложение доступно только участникам требуемого уровня.');
            $promoStackable = (int)$promo['stackable'] === 1;
            if ((int)$promo['uses_count'] >= (int)($promo['maximum_uses'] ?? PHP_INT_MAX)) throw new DomainException('Лимит применений промокода исчерпан.');
            if ((int)($promo['minimum_subtotal'] ?? 0) > $subtotal) throw new DomainException('Сумма заказа меньше минимальной для этого предложения.');
            $actor = $actorKey !== '' ? $actorKey : commerce_actor_key($userId, session_id());
            if ($promo['maximum_uses_per_user'] !== null) {
                $q = $pdo->prepare('SELECT COUNT(*) FROM promo_redemptions WHERE promo_code=? AND actor_key=?');
                $q->execute([$promotionCode, $actor]);
                if ((int)$q->fetchColumn() >= (int)$promo['maximum_uses_per_user']) throw new DomainException('Вы уже использовали этот промокод максимально допустимое число раз.');
            }
            if ((int)$promo['first_order_only'] === 1) {
                if (!$userId) throw new DomainException('Для этого промокода войдите в аккаунт.');
                $q = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?');
                $q->execute([$userId]);
                if ((int)$q->fetchColumn() > 0) throw new DomainException('Промокод доступен только для первого заказа аккаунта.');
            }
            if (in_array((string)$promo['campaign_type'],['personal','birthday'],true)) {
                if (!$userId) throw new DomainException('Персональное предложение доступно только после входа в аккаунт.');
                $assigned=$pdo->prepare('SELECT 1 FROM personalized_offers WHERE user_id=? AND campaign_id=? AND used_order_id IS NULL AND (expires_at IS NULL OR expires_at>=CURRENT_TIMESTAMP)');
                $assigned->execute([$userId,$promo['id']]);
                if (!$assigned->fetchColumn()) throw new DomainException('Это персональное предложение вам недоступно.');
                if ($promo['campaign_type']==='birthday' && !commerce_birthday_eligible($pdo,$userId)) throw new DomainException('Проверьте дату рождения и согласие на персональные предложения в профиле.');
            }
            $targets = commerce_campaign_targets($pdo, (int)$promo['campaign_id']);
            $eligible = 0;
            foreach ($catalog as $line) if (commerce_item_is_targeted($line, $targets)) $eligible += $line['line_total'];
            foreach ($sets as $set) if (commerce_item_is_targeted($set, $targets, true)) $eligible += $set['subtotal'];
            if ($targets && $eligible < 1) throw new DomainException('Это предложение не подходит к выбранным блюдам.');
            $type = (string)$promo['reward_type'];
            $value = (int)$promo['reward_value'];
            $amount = 0;
            if ($type === 'percent') $amount = (int)floor($eligible * min(100, $value) / 100);
            elseif ($type === 'fixed') $amount = min($eligible, $value);
            elseif ($type === 'set_price') {
                $desiredSetDiscounts = [];
                $desiredTotal = 0;
                foreach ($sets as $index => $set) {
                    if (!commerce_item_is_targeted($set, $targets, true)) continue;
                    $desiredSetDiscounts[$index] = max(0, (int)$set['subtotal'] - $value);
                    $desiredTotal += $desiredSetDiscounts[$index];
                }
                $maximumDiscount = (int)$promo['maximum_discount'];
                if ($maximumDiscount > 0 && $desiredTotal > $maximumDiscount) {
                    $remaining = $maximumDiscount;
                    foreach ($desiredSetDiscounts as $index => $desired) {
                        $allocated = intdiv($desired * $maximumDiscount, $desiredTotal);
                        $desiredSetDiscounts[$index] = $allocated;
                        $remaining -= $allocated;
                    }
                    // Distribute rounding remainder in set order so the saved
                    // line prices carry exactly the same total discount.
                    foreach ($desiredSetDiscounts as $index => $allocated) {
                        if ($remaining < 1) break;
                        $desired = max(0, (int)$sets[$index]['subtotal'] - $value);
                        if ($allocated >= $desired) continue;
                        $desiredSetDiscounts[$index]++;
                        $remaining--;
                    }
                }
                foreach ($desiredSetDiscounts as $index => $reduction) {
                    $sets[$index]['discount'] = $reduction;
                    $amount += $reduction;
                }
            } elseif ($type === 'free_delivery') {
                if ($deliveryFee < 1) throw new DomainException('Бесплатная доставка недоступна: стоимость доставки не настроена.');
                $amount = $deliveryFee;
            } elseif ($type === 'gift_dish') {
                $giftId = (int)($promo['gift_dish_id'] ?? 0);
                if ($giftId < 1) throw new DomainException('Для подарочного предложения не настроено блюдо.');
                $stock=$pdo->prepare('SELECT remaining_quantity FROM campaign_gift_options WHERE campaign_id=? AND dish_id=? AND active=1');
                $stock->execute([$promo['id'],$giftId]);
                if ((int)$stock->fetchColumn()<1) throw new DomainException('Подарок закончился или для него не задан остаток.');
                $gift = commerce_find_active_dish($pdo, $giftId);
                $giftItems[] = ['dish' => $gift, 'qty' => 1, 'line_total' => 0, 'campaign_id' => (int)$promo['campaign_id'], 'gift_key' => 'promo:' . $promotionCode];
            } elseif ($type === 'bonus_points') {
                if (!$userId) throw new DomainException('Для получения бонусов по этому предложению войдите в аккаунт.');
                $adjustments[] = ['type'=>'bonus_points','code'=>$promotionCode,'label'=>'Бонусы за заказ','amount'=>$value,'campaign_id'=>(int)$promo['campaign_id']];
            } elseif (!in_array($type, ['access','none'], true)) {
                throw new DomainException('Тип предложения не поддерживается.');
            }
            if ((int)$promo['maximum_discount'] > 0 && $amount > 0) $amount = min($amount, (int)$promo['maximum_discount']);
            if ($amount > 0) {
                $discount += $amount;
                $promotionDiscount += $amount;
                $adjustments[] = ['type'=>$type==='free_delivery'?'free_delivery':($type==='set_price'?'set_price':'discount'),'code'=>$promotionCode,'label'=>(string)$promo['title'],'amount'=>$amount,'campaign_id'=>(int)$promo['campaign_id']];
            }
            $appliedCampaign = ['id'=>(int)$promo['campaign_id'],'code'=>$promotionCode,'reward_type'=>$type,'title'=>(string)$promo['title'],'bonus_points'=>$type==='bonus_points'?$value:0];
        }

        if ($pointsToRedeem > 0 && $promotionCode !== '' && !$promoStackable) throw new DomainException('Это предложение нельзя сочетать со списанием бонусов. Уберите промокод или измените количество баллов.');

        $giftItems = $giftItems ?? [];
        $giftOptions = [];
        $q = $pdo->prepare("SELECT c.*,g.dish_id,g.remaining_quantity,g.max_per_order,d.name,d.price,d.category,d.image_url
            FROM promotion_campaigns c JOIN campaign_gift_options g ON g.campaign_id=c.id
            JOIN dishes d ON d.id=g.dish_id AND d.active=1
            WHERE c.campaign_type='gift_threshold' AND c.active=1 AND g.active=1
              AND g.remaining_quantity>0 AND c.minimum_subtotal<=?
            ORDER BY c.minimum_subtotal DESC,c.sort_order,c.id");
        $q->execute([$subtotal]);
        $threshold = null;
        foreach ($q->fetchAll() as $candidate) {
            if (commerce_campaign_is_active($candidate) && commerce_campaign_tier_is_eligible($pdo, $candidate, $userId)) {
                $threshold = $candidate;
                break;
            }
        }
        if ($threshold) {
            $thresholdStackable = (int)$threshold['stackable'] === 1;
            $canCombineThresholdGift = ($promotionCode === '' || ($promoStackable && $thresholdStackable)) && ($pointsToRedeem === 0 || $thresholdStackable);
            if (!$canCombineThresholdGift) {
                if ($giftDishId) throw new DomainException('Подарок от суммы нельзя сочетать с выбранной акцией или списанием бонусов.');
                $threshold = null;
            }
        }
        if ($threshold) {
            $q = $pdo->prepare('SELECT g.dish_id,g.remaining_quantity,g.max_per_order,d.name,d.price,d.category,d.image_url FROM campaign_gift_options g JOIN dishes d ON d.id=g.dish_id AND d.active=1 WHERE g.campaign_id=? AND g.active=1 AND g.remaining_quantity>0 ORDER BY d.sort_order,d.id');
            $q->execute([$threshold['id']]);
            $giftOptions = $q->fetchAll();
            $selected = $giftDishId;
            if (!$selected && count($giftOptions) === 1) $selected = (int)$giftOptions[0]['dish_id'];
            if ($selected) {
                $option = null;
                foreach ($giftOptions as $candidate) if ((int)$candidate['dish_id'] === $selected) $option = $candidate;
                if (!$option || (int)$option['remaining_quantity'] < 1) throw new DomainException('Выбранный подарок закончился.');
                $giftLimit = min(10, max(1, (int)$option['max_per_order']), (int)$option['remaining_quantity']);
                if ($giftQuantity < 1 || $giftQuantity > $giftLimit) throw new DomainException('Количество подарка превышает доступный остаток или лимит на заказ.');
                $giftItems[] = ['dish'=>['id'=>(int)$option['dish_id'],'name'=>$option['name'],'price'=>(int)$option['price'],'category'=>$option['category'],'image_url'=>$option['image_url']],'qty'=>$giftQuantity,'line_total'=>0,'campaign_id'=>(int)$threshold['id'],'gift_key'=>'threshold:'.(int)$threshold['id']];
                $adjustments[] = ['type'=>'gift','code'=>'gift:'.(int)$threshold['id'],'label'=>'Подарок от суммы заказа','amount'=>0,'campaign_id'=>(int)$threshold['id'],'gift_dish_id'=>(int)$option['dish_id']];
            }
        }
        if ($giftDishId && !$threshold && !$giftItems) throw new DomainException('Подарок пока недоступен.');
        if (!$threshold && $giftQuantity !== 1) throw new DomainException('Количество подарка можно изменить только для доступного подарка от суммы заказа.');

        $pointsDiscount = 0;
        if ($pointsToRedeem < 0) throw new DomainException('Количество бонусов не может быть отрицательным.');
        if ($pointsToRedeem > 0) {
            if (!$userId) throw new DomainException('Для списания бонусов войдите в аккаунт.');
            $settings = $pdo->query('SELECT * FROM loyalty_settings WHERE id=1')->fetch() ?: [];
            if ((int)($settings['enabled'] ?? 0) !== 1) throw new DomainException('Программа лояльности временно отключена.');
            $balance = commerce_get_loyalty_balance($pdo, $userId);
            $unit = max(1, (int)$settings['currency_per_point']);
            $maxValue = (int)floor($subtotal * min(100, (int)$settings['maximum_redeem_percent']) / 100);
            $maxPoints = min($balance, (int)floor($maxValue / $unit));
            if ($pointsToRedeem > $maxPoints) throw new DomainException('Недостаточно доступных бонусов или превышен лимит списания.');
            $pointsDiscount = $pointsToRedeem * $unit;
            $discount += $pointsDiscount;
            $adjustments[] = ['type'=>'loyalty_redeem','code'=>'loyalty','label'=>'Оплата бонусами','amount'=>$pointsDiscount,'points'=>$pointsToRedeem];
        }
        $discount = min($subtotal + $deliveryFee, $discount);
        $total = max(0, $subtotal + $deliveryFee - $discount);
        if ($total > 500000) throw new DomainException('Сумма заказа превышает допустимый предел.');
        $requiresGiftChoice = $threshold && count($giftOptions) > 1 && !$giftDishId;
        return [
            'ok'=>true,'subtotal'=>$subtotal,'discount_total'=>$discount,'promotion_discount'=>$promotionDiscount,'delivery_fee'=>$deliveryFee,'total'=>$total,
            'items'=>$catalog,'custom_sets'=>$sets,'gift_items'=>$giftItems,'gift_options'=>$giftOptions,
            'requires_gift_choice'=>(bool)$requiresGiftChoice,'promo_code'=>$promotionCode,
            'points_to_redeem'=>$pointsToRedeem,'points_discount'=>$pointsDiscount,
            'adjustments'=>$adjustments,'promotion'=>$appliedCampaign,
        ];
    } catch (DomainException $error) {
        return ['ok'=>false,'message'=>$error->getMessage(),'errors'=>[$error->getMessage()]];
    }
}

function commerce_find_active_dish(PDO $pdo, int $id): array
{
    $q=$pdo->prepare('SELECT id,name,price,category,image_url FROM dishes WHERE id=? AND active=1');
    $q->execute([$id]);
    $dish=$q->fetch();
    if (!$dish) throw new DomainException('Подарочное блюдо недоступно.');
    $dish['id']=(int)$dish['id']; $dish['price']=(int)$dish['price'];
    return $dish;
}

function commerce_persist_order(PDO $pdo, array $input, ?int $userId, string $actorKey): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Создание заказа требует активной транзакции.');
    $quote=commerce_quote($pdo,$input['items']??[], $input['custom_sets']??[], (string)($input['promo_code']??''), (string)($input['fulfillment']??'delivery'), $userId, $actorKey, isset($input['gift_dish_id'])?(int)$input['gift_dish_id']:null, (int)($input['points_to_redeem']??0), (string)($input['secret_code']??''), !empty($input['is_admin']), (int)($input['gift_quantity']??1));
    if (!$quote['ok']) throw new DomainException((string)$quote['message']);
    if ($quote['requires_gift_choice']) throw new DomainException('Выберите доступный подарок.');

    $name=trim((string)($input['name']??''));
    $phone=trim((string)($input['phone']??''));
    $fulfillment=(string)($input['fulfillment']??'delivery');
    $address=trim((string)($input['address']??''));
    if (!preg_match('/^[\p{L} .\'-]{2,80}$/u',$name)) throw new DomainException('Укажите имя от 2 до 80 символов.');
    if (!preg_match('/^[+0-9() \\-]{7,30}$/',$phone)) throw new DomainException('Проверьте номер телефона.');
    if ($fulfillment==='delivery' && (strlen($address)<5 || strlen($address)>180)) throw new DomainException('Укажите адрес доставки.');
    if ($fulfillment==='pickup') $address='';

    $q=$pdo->prepare('INSERT INTO orders(user_id,customer_name,phone,address,fulfillment,comment,total,subtotal,discount_total,delivery_fee,promo_code) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute([$userId,$name,$phone,$address,$fulfillment,substr(trim((string)($input['comment']??'')),0,300),$quote['total'],$quote['subtotal'],$quote['discount_total'],$quote['delivery_fee'],$quote['promo_code']]);
    $orderId=(int)$pdo->lastInsertId();
    $insert=$pdo->prepare('INSERT INTO order_items(order_id,dish_id,item_name,price,quantity) VALUES(?,?,?,?,?)');
    foreach ($quote['items'] as $line) $insert->execute([$orderId,$line['dish']['id'],$line['dish']['name'],$line['dish']['price'],$line['qty']]);
    foreach ($quote['custom_sets'] as $set) {
        $setDiscount=(int)($set['discount']??0);
        $setPrice=max(0,(int)$set['subtotal']-$setDiscount);
        $insert->execute([$orderId,null,$set['name'].' · сет на заказ',$setPrice,1]);
        $setItemId=(int)$pdo->lastInsertId();
        $component=$pdo->prepare('INSERT INTO order_custom_set_components(order_item_id,dish_id,item_name,price,quantity) VALUES(?,?,?,?,?)');
        foreach ($set['items'] as $line) $component->execute([$setItemId,$line['dish']['id'],$line['dish']['name'],$line['dish']['price'],$line['qty']]);
    }
        foreach ($quote['gift_items'] as $gift) {
        $insert->execute([$orderId,$gift['dish']['id'],'Подарок · '.$gift['dish']['name'],0,$gift['qty']]);
        if (isset($gift['campaign_id'])) {
            $giftQuantity=(int)$gift['qty'];
            $update=$pdo->prepare('UPDATE campaign_gift_options SET remaining_quantity=remaining_quantity-? WHERE campaign_id=? AND dish_id=? AND active=1 AND remaining_quantity>=?');
            $update->execute([$giftQuantity,$gift['campaign_id'],$gift['dish']['id'],$giftQuantity]);
            if ($update->rowCount()!==1) throw new DomainException('Подарок закончился, обновите корзину.');
        }
    }
    $adjustment=$pdo->prepare('INSERT INTO order_adjustments(order_id,campaign_id,adjustment_type,code,label,amount,gift_dish_id) VALUES(?,?,?,?,?,?,?)');
    foreach ($quote['adjustments'] as $row) {
        $adjustment->execute([$orderId,$row['campaign_id']??null,$row['type'],$row['code'],$row['label'],$row['amount'],$row['gift_dish_id']??null]);
    }
    if ($quote['promo_code']!=='') {
        $q=$pdo->prepare('SELECT pc.*,c.id AS resolved_campaign_id FROM promo_codes pc JOIN promotion_campaigns c ON c.id=pc.campaign_id WHERE pc.code=? AND pc.active=1 AND c.active=1');
        $q->execute([$quote['promo_code']]); $promo=$q->fetch();
        if (!$promo) throw new DomainException('Промокод больше не действует.');
        if ($promo['maximum_uses']!==null) {
            $update=$pdo->prepare('UPDATE promo_codes SET uses_count=uses_count+1 WHERE code=? AND active=1 AND uses_count<maximum_uses');
        } else {
            $update=$pdo->prepare('UPDATE promo_codes SET uses_count=uses_count+1 WHERE code=? AND active=1');
        }
        $update->execute([$quote['promo_code']]);
        if ($update->rowCount()!==1) throw new DomainException('Лимит применений промокода исчерпан.');
        $q=$pdo->prepare('INSERT INTO promo_redemptions(promo_code,campaign_id,user_id,actor_key,order_id,discount_amount) VALUES(?,?,?,?,?,?)');
        $q->execute([$quote['promo_code'],$promo['resolved_campaign_id'],$userId,$actorKey,$orderId,(int)($quote['promotion_discount']??0)]);
    }
    if ($quote['points_to_redeem']>0 && $userId) {
        $q=$pdo->prepare("INSERT INTO loyalty_ledger(user_id,order_id,event_key,transaction_type,points,note) VALUES(?,?,?,'redeem',?,?)");
        $q->execute([$userId,$orderId,'order:'.$orderId.':redeem:created',-$quote['points_to_redeem'],'Списание при оформлении заказа']);
    }
    return [
        'ok'=>true,'message'=>'Учебный заказ №'.$orderId.' сохранён в базе данных. Оплата не списывается, в ресторан он не передаётся.',
        'id'=>$orderId,'subtotal'=>$quote['subtotal'],'discount_total'=>$quote['discount_total'],
        'delivery_fee'=>$quote['delivery_fee'],'total'=>$quote['total'],'promo_code'=>$quote['promo_code'],
        'gift_items'=>array_map(static fn(array $gift): array=>['name'=>$gift['dish']['name'],'quantity'=>$gift['qty']],$quote['gift_items']),
        'demo_only'=>true,
    ];
}

function commerce_can_access_secret(PDO $pdo, ?int $userId, string $code = '', bool $isAdmin = false): bool
{
    $rules=$pdo->query("SELECT r.*,c.active AS campaign_active,c.starts_at AS campaign_start,c.ends_at AS campaign_end,c.timezone,c.weekday_mask,c.starts_time,c.ends_time
        FROM secret_menu_rules r JOIN promotion_campaigns c ON c.id=r.campaign_id WHERE r.active=1 AND c.campaign_type='secret_menu'")->fetchAll();
    foreach ($rules as $rule) {
        if (commerce_secret_rule_matches($pdo,$rule,$userId,$code,$isAdmin)) return true;
    }
    return false;
}

function commerce_secret_rule_matches(PDO $pdo, array $rule, ?int $userId, string $code, bool $isAdmin): bool
{
    if (!commerce_campaign_is_active(['active'=>$rule['campaign_active'],'starts_at'=>$rule['campaign_start'],'ends_at'=>$rule['campaign_end'],'timezone'=>$rule['timezone'],'weekday_mask'=>$rule['weekday_mask'],'starts_time'=>$rule['starts_time'],'ends_time'=>$rule['ends_time']])) return false;
    if (!commerce_date_window_is_active($rule['starts_at'] ?? null, $rule['ends_at'] ?? null, (string)($rule['timezone'] ?? 'Asia/Qyzylorda'))) return false;
    if ($rule['access_kind']==='role' && (($rule['required_value']==='admin' && $isAdmin) || ($rule['required_value']==='member' && $userId))) return true;
    if ($rule['access_kind']==='tier' && $userId) {
        $tier=commerce_user_tier($pdo,$userId);
        return $tier && $tier['slug']===(string)$rule['required_value'];
    }
    if ($rule['access_kind']==='code' && $code!=='' && hash_equals(strtoupper((string)$rule['required_value']),strtoupper($code))) {
        $q=$pdo->prepare('SELECT pc.active AS code_active,pc.starts_at AS code_start,pc.ends_at AS code_end,c.active AS campaign_active,c.starts_at AS campaign_start,c.ends_at AS campaign_end,c.timezone,c.weekday_mask,c.starts_time,c.ends_time FROM promo_codes pc JOIN promotion_campaigns c ON c.id=pc.campaign_id WHERE pc.code=? COLLATE NOCASE AND pc.campaign_id=?');
        $q->execute([$code,$rule['campaign_id']]); $grant=$q->fetch();
        return $grant && (int)$grant['code_active']===1
            && commerce_date_window_is_active($grant['code_start'],$grant['code_end'],$grant['timezone'])
            && commerce_campaign_is_active(['active'=>$grant['campaign_active'],'starts_at'=>$grant['campaign_start'],'ends_at'=>$grant['campaign_end'],'timezone'=>$grant['timezone'],'weekday_mask'=>$grant['weekday_mask'],'starts_time'=>$grant['starts_time'],'ends_time'=>$grant['ends_time']]);
    }
    return false;
}

function commerce_secret_menu_dishes(PDO $pdo, ?int $userId, string $code = '', bool $isAdmin = false): array
{
    $rules=$pdo->query("SELECT r.*,c.active AS campaign_active,c.starts_at AS campaign_start,c.ends_at AS campaign_end,c.timezone,c.weekday_mask,c.starts_time,c.ends_time
        FROM secret_menu_rules r JOIN promotion_campaigns c ON c.id=r.campaign_id WHERE r.active=1 AND c.campaign_type='secret_menu'")->fetchAll();
    $campaignIds=[];
    foreach ($rules as $rule) if (commerce_secret_rule_matches($pdo,$rule,$userId,$code,$isAdmin)) $campaignIds[(int)$rule['campaign_id']]=true;
    if (!$campaignIds) return [];
    $all=$pdo->query('SELECT d.*,c.name AS category_name FROM dishes d JOIN categories c ON c.slug=d.category WHERE d.active=1 ORDER BY d.sort_order,d.id')->fetchAll();
    $allowed=[];
    foreach (array_keys($campaignIds) as $campaignId) {
        $targets=commerce_campaign_targets($pdo,(int)$campaignId);
        if (!$targets || !array_filter($targets, static fn(array $target): bool => (int)$target['excluded'] === 0)) continue;
        foreach ($all as $dish) {
            $item=['dish'=>['id'=>(int)$dish['id'],'category'=>(string)$dish['category']]];
            if (commerce_item_is_targeted($item,$targets)) $allowed[(int)$dish['id']]=$dish;
        }
    }
    return array_values($allowed);
}

function commerce_user_tier(PDO $pdo, int $userId): ?array
{
    $q=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=? AND status='completed'");
    $q->execute([$userId]); $spend=(int)$q->fetchColumn();
    $q=$pdo->prepare('SELECT * FROM loyalty_tiers WHERE active=1 AND minimum_completed_spend<=? ORDER BY minimum_completed_spend DESC,sort_order DESC LIMIT 1');
    $q->execute([$spend]); $tier=$q->fetch();
    return $tier ?: null;
}

function commerce_apply_referral_for_completed_order(PDO $pdo, int $orderId, int $statusEventId, array $order): void
{
    if (empty($order['user_id'])) return;
    $settings=$pdo->query('SELECT * FROM loyalty_settings WHERE id=1')->fetch() ?: [];
    $minimum=max(0,(int)($settings['referral_minimum_order']??0));
    if ((int)$order['total']<$minimum) return;
    $q=$pdo->prepare("SELECT r.* FROM referrals r WHERE r.referred_user_id=? AND r.status IN ('pending','cancelled') AND r.qualifying_order_id IS NULL");
    $q->execute([$order['user_id']]); $referral=$q->fetch();
    if (!$referral || (int)$referral['inviter_user_id']===(int)$order['user_id']) return;
    $q=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND status='completed' AND id<>?");
    $q->execute([$order['user_id'],$orderId]);
    if ((int)$q->fetchColumn()>0) return;
    $points=[
        [(int)$referral['inviter_user_id'],(int)($settings['referral_inviter_points']??0)],
        [(int)$order['user_id'],(int)($settings['referral_friend_points']??0)],
    ];
    $status=array_sum(array_column($points,1))>0?'rewarded':'qualified';
    $pdo->prepare('UPDATE referrals SET status=?,qualifying_order_id=?,qualified_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$orderId,$referral['id']]);
    foreach ($points as [$userId,$count]) {
        if ($count<1) continue;
        $event='referral:'.$referral['id'].':status-event:'.$statusEventId.':user:'.$userId;
        $pdo->prepare('INSERT OR IGNORE INTO referral_rewards(referral_id,user_id,qualifying_order_id,event_key,points) VALUES(?,?,?,?,?)')->execute([$referral['id'],$userId,$orderId,$event,$count]);
        $pdo->prepare("INSERT OR IGNORE INTO loyalty_ledger(user_id,order_id,referral_id,event_key,transaction_type,points,note) VALUES(?,?,?,?,'referral',?,'Награда по реферальной программе')")->execute([$userId,$orderId,$referral['id'],$event,$count]);
    }
}

function commerce_set_order_status(PDO $pdo, int $orderId, string $newStatus): bool
{
    $allowed=['new','confirmed','preparing','ready','completed','cancelled'];
    if (!in_array($newStatus,$allowed,true)) throw new DomainException('Недопустимый статус заказа.');
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $q=$pdo->prepare('SELECT * FROM orders WHERE id=?'); $q->execute([$orderId]); $order=$q->fetch();
        if (!$order) throw new DomainException('Заказ не найден.');
        $previous=(string)$order['status'];
        if ($previous===$newStatus) { $pdo->commit(); return false; }
        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$newStatus,$orderId]);
        $pdo->prepare('INSERT INTO order_status_events(order_id,previous_status,new_status) VALUES(?,?,?)')->execute([$orderId,$previous,$newStatus]);
        $eventId=(int)$pdo->lastInsertId();
        if ($newStatus==='completed') {
            if (!empty($order['user_id'])) {
                $userId=(int)$order['user_id'];
                $settings=$pdo->query('SELECT * FROM loyalty_settings WHERE id=1')->fetch() ?: [];
                if ((int)($settings['enabled']??0)===1) {
                    if ($previous==='cancelled') {
                        $redeemAmount=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM order_adjustments WHERE order_id=? AND adjustment_type='loyalty_redeem'");
                        $redeemAmount->execute([$orderId]); $currency=(int)$redeemAmount->fetchColumn();
                        if ($currency>0) {
                            $unit=max(1,(int)$settings['currency_per_point']); $points=(int)ceil($currency/$unit);
                            if (commerce_get_loyalty_balance($pdo,$userId)<$points) throw new DomainException('Недостаточно бонусов для повторного подтверждения заказа.');
                            $pdo->prepare("INSERT INTO loyalty_ledger(user_id,order_id,event_key,transaction_type,points,note) VALUES(?,?,?,'redeem',?,'Повторное списание бонусов после восстановления заказа')")->execute([$userId,$orderId,'order:'.$orderId.':status-event:'.$eventId.':redeem',-$points]);
                        }
                    }
                    $q=$pdo->prepare("SELECT COALESCE(SUM(total-discount_total),0) FROM orders WHERE user_id=? AND status='completed'");
                    $q->execute([$userId]); $spend=max(0,(int)$q->fetchColumn());
                    $tier=commerce_user_tier($pdo,$userId);
                    $multiplier=max(0,(int)($tier['earn_multiplier_basis_points']??10000));
                    $unreversedEarn=$pdo->prepare("SELECT 1 FROM loyalty_ledger earned WHERE earned.order_id=? AND earned.transaction_type='earn' AND NOT EXISTS (SELECT 1 FROM loyalty_ledger reversed WHERE reversed.reversal_of_event=earned.event_key) LIMIT 1");
                    $unreversedEarn->execute([$orderId]);
                    $alreadyEarned=(bool)$unreversedEarn->fetchColumn();
                    $base=max(0,(int)$order['subtotal']-(int)$order['discount_total']);
                    $points=(int)floor(($base*(int)($settings['points_per_100']??0)*$multiplier)/(100*10000));
                    if (!$alreadyEarned && $points>0) {
                        $event='order:'.$orderId.':status-event:'.$eventId.':earn';
                        $pdo->prepare("INSERT OR IGNORE INTO loyalty_ledger(user_id,order_id,event_key,transaction_type,points,note) VALUES(?,?,?,'earn',?,'Бонусы за выполненный заказ')")->execute([$userId,$orderId,$event,$points]);
                    }
                    if (!$alreadyEarned) {
                        $promos=$pdo->prepare("SELECT id,campaign_id,label,amount FROM order_adjustments WHERE order_id=? AND adjustment_type='bonus_points' AND amount>0 ORDER BY id");
                        $promos->execute([$orderId]);
                        foreach ($promos->fetchAll() as $promoReward) {
                            $event='order:'.$orderId.':status-event:'.$eventId.':promo-adjustment:'.$promoReward['id'];
                            $pdo->prepare("INSERT OR IGNORE INTO loyalty_ledger(user_id,order_id,event_key,transaction_type,points,note) VALUES(?,?,?,'earn',?,?)")
                                ->execute([$userId,$orderId,$event,(int)$promoReward['amount'],'Бонусы по предложению: '.substr((string)$promoReward['label'],0,180)]);
                        }
                    }
                    commerce_apply_referral_for_completed_order($pdo,$orderId,$eventId,$order);
                }
            }
        } elseif ($newStatus==='cancelled') {
            $ledger=$pdo->prepare('SELECT * FROM loyalty_ledger WHERE order_id=? AND points<>0');
            $ledger->execute([$orderId]);
            foreach ($ledger->fetchAll() as $entry) {
                if (!empty($entry['reversal_of_event'])) continue;
                $originalPoints=(int)$entry['points'];
                if ($originalPoints<0 && $entry['transaction_type']==='redeem') {
                    $delta=-$originalPoints;
                } elseif ($originalPoints>0 && in_array($entry['transaction_type'],['earn','referral','birthday'],true)) {
                    $delta=-min($originalPoints,commerce_get_loyalty_balance($pdo,(int)$entry['user_id']));
                } else continue;
                if ($delta===0) continue;
                $event='order:'.$orderId.':status-event:'.$eventId.':reverse:'.hash('sha256',(string)$entry['event_key']);
                $pdo->prepare("INSERT OR IGNORE INTO loyalty_ledger(user_id,order_id,referral_id,event_key,transaction_type,points,reversal_of_event,note) VALUES(?,?,?,?,'reversal',?,?,?)")
                    ->execute([$entry['user_id'],$orderId,$entry['referral_id'],$event,$delta,$entry['event_key'],'Корректировка бонусов после отмены заказа']);
            }
            $pdo->prepare("UPDATE referrals SET status='cancelled',qualifying_order_id=NULL,qualified_at=NULL WHERE qualifying_order_id=?")->execute([$orderId]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_user_addresses(PDO $pdo, int $userId): array
{
    $query = $pdo->prepare('SELECT id,label,address,is_default,created_at,updated_at FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');
    $query->execute([$userId]);
    return $query->fetchAll();
}

function commerce_save_user_address(PDO $pdo, int $userId, string $label, string $address, ?int $addressId = null): int
{
    $label = trim($label);
    $address = trim($address);
    $labelLength = preg_match_all('/./us', $label, $matches);
    $addressLength = preg_match_all('/./us', $address, $matches);
    if ($userId < 1 || $labelLength === false || $labelLength < 2 || $labelLength > 60 || strlen($label) > 240 || $addressLength === false || $addressLength < 5 || $addressLength > 240 || strlen($address) > 1000) {
        throw new DomainException('Укажите название адреса и адрес длиной от 5 до 240 символов.');
    }
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        if ($addressId !== null) {
            $exists = $pdo->prepare('SELECT 1 FROM user_addresses WHERE id=? AND user_id=?');
            $exists->execute([$addressId, $userId]);
            if (!$exists->fetchColumn()) throw new DomainException('Сохранённый адрес не найден.');
            $save = $pdo->prepare('UPDATE user_addresses SET label=?,address=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?');
            $save->execute([$label, $address, $addressId, $userId]);
            $id = $addressId;
        } else {
            $count = $pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id=?');
            $count->execute([$userId]);
            if ((int)$count->fetchColumn() >= 10) throw new DomainException('Можно сохранить не больше 10 адресов.');
            $save = $pdo->prepare('INSERT INTO user_addresses(user_id,label,address,is_default) VALUES(?,?,?,CASE WHEN EXISTS(SELECT 1 FROM user_addresses WHERE user_id=?) THEN 0 ELSE 1 END)');
            $save->execute([$userId, $label, $address, $userId]);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_set_user_address_default(PDO $pdo, int $userId, int $addressId): void
{
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $exists = $pdo->prepare('SELECT 1 FROM user_addresses WHERE id=? AND user_id=?');
        $exists->execute([$addressId, $userId]);
        if (!$exists->fetchColumn()) throw new DomainException('Сохранённый адрес не найден.');
        $pdo->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([$userId]);
        $pdo->prepare('UPDATE user_addresses SET is_default=1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?')->execute([$addressId, $userId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_delete_user_address(PDO $pdo, int $userId, int $addressId): void
{
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $query = $pdo->prepare('SELECT is_default FROM user_addresses WHERE id=? AND user_id=?');
        $query->execute([$addressId, $userId]);
        $wasDefault = $query->fetchColumn();
        if ($wasDefault === false) throw new DomainException('Сохранённый адрес не найден.');
        $pdo->prepare('DELETE FROM user_addresses WHERE id=? AND user_id=?')->execute([$addressId, $userId]);
        if ((int)$wasDefault === 1) {
            $next = $pdo->prepare('SELECT id FROM user_addresses WHERE user_id=? ORDER BY id DESC LIMIT 1');
            $next->execute([$userId]);
            $nextId = $next->fetchColumn();
            if ($nextId !== false) $pdo->prepare('UPDATE user_addresses SET is_default=1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?')->execute([(int)$nextId, $userId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_adjust_loyalty(PDO $pdo, int $userId, int $points, string $eventKey, string $note, ?int $adminId = null): void
{
    if ($points===0 || $userId<1 || !$adminId || $adminId<1 || !preg_match('/^[A-Za-z0-9._:-]{8,160}$/',$eventKey)) throw new DomainException('Некорректная операция с бонусами.');
    if ($pdo->inTransaction()) throw new LogicException('Административная корректировка бонусов должна владеть транзакцией.');
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        // Serialize the balance check with the ledger write so concurrent
        // deductions cannot both spend the same points.
        if ($points<0 && commerce_get_loyalty_balance($pdo,$userId)<abs($points)) throw new DomainException('Нельзя списать больше доступного баланса.');
        $q=$pdo->prepare("INSERT INTO loyalty_ledger(user_id,event_key,transaction_type,points,admin_id,note) VALUES(?,?,'admin_adjustment',?,?,?)");
        $q->execute([$userId,$eventKey,$points,$adminId,substr($note,0,240)]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_assign_personal_offer(PDO $pdo, int $userId, int $campaignId, ?string $expiresAt = null): int
{
    if ($userId < 1 || $campaignId < 1) throw new DomainException('Выберите аккаунт и персональную кампанию.');
    if ($pdo->inTransaction()) throw new LogicException('Назначение предложения должно владеть транзакцией.');
    // `personalized_offers.expires_at` is compared with SQLite CURRENT_TIMESTAMP,
    // so keep the persisted value in UTC even though the admin enters local time.
    $timezone = new DateTimeZone('UTC');
    if ($expiresAt !== null) {
        $expiry = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expiresAt, $timezone);
        $parseErrors = DateTimeImmutable::getLastErrors();
        if (!$expiry || ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) || $expiry->format('Y-m-d H:i:s') !== $expiresAt || $expiry <= new DateTimeImmutable('now', $timezone)) {
            throw new DomainException('Срок предложения должен быть корректной будущей датой.');
        }
    }

    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $user = $pdo->prepare('SELECT 1 FROM users WHERE id=?');
        $user->execute([$userId]);
        if (!$user->fetchColumn()) throw new DomainException('Аккаунт не найден.');
        $campaign = $pdo->prepare("SELECT * FROM promotion_campaigns WHERE id=? AND campaign_type IN ('personal','birthday') AND active=1");
        $campaign->execute([$campaignId]);
        $campaignRow=$campaign->fetch();
        if (!$campaignRow) throw new DomainException('Выберите активную персональную или birthday-кампанию.');
        if ($campaignRow['campaign_type']==='birthday' && !commerce_birthday_eligible($pdo,$userId)) {
            throw new DomainException('Для назначения birthday-предложения нужны дата рождения, отдельное согласие и актуальное окно дня рождения.');
        }
        if (!commerce_campaign_tier_is_eligible($pdo,$campaignRow,$userId)) throw new DomainException('Аккаунт не соответствует требуемому уровню кампании.');
        $codes=$pdo->prepare('SELECT starts_at,ends_at,uses_count,maximum_uses FROM promo_codes WHERE campaign_id=? AND active=1');
        $codes->execute([$campaignId]); $hasRedeemableCode=false;
        foreach($codes->fetchAll() as $code){
            if(($code['maximum_uses']===null||(int)$code['uses_count']<(int)$code['maximum_uses'])&&commerce_date_window_is_active($code['starts_at']?:null,$code['ends_at']?:null,(string)$campaignRow['timezone'])){$hasRedeemableCode=true;break;}
        }
        if(!$hasRedeemableCode)throw new DomainException('Добавьте к кампании действующий промокод с доступным лимитом, затем назначайте её аккаунту.');

        $existing = $pdo->prepare('SELECT id,used_order_id FROM personalized_offers WHERE user_id=? AND campaign_id=?');
        $existing->execute([$userId, $campaignId]);
        $row = $existing->fetch();
        if ($row) {
            if ($row['used_order_id'] !== null) throw new DomainException('Это предложение уже использовано; повторно назначить его нельзя.');
            $offerId = (int)$row['id'];
            $pdo->prepare('UPDATE personalized_offers SET expires_at=? WHERE id=?')->execute([$expiresAt, $offerId]);
        } else {
            $pdo->prepare('INSERT INTO personalized_offers(user_id,campaign_id,expires_at) VALUES(?,?,?)')->execute([$userId, $campaignId, $expiresAt]);
            $offerId = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
        return $offerId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_revoke_personal_offer(PDO $pdo, int $offerId): void
{
    if ($offerId < 1) throw new DomainException('Персональное предложение не найдено.');
    if ($pdo->inTransaction()) throw new LogicException('Отзыв предложения должен владеть транзакцией.');
    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $delete = $pdo->prepare('DELETE FROM personalized_offers WHERE id=? AND used_order_id IS NULL');
        $delete->execute([$offerId]);
        if ($delete->rowCount() !== 1) throw new DomainException('Предложение уже использовано или не найдено.');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function commerce_certificate_denominations(PDO $pdo): array
{
    $q=$pdo->prepare('SELECT value FROM settings WHERE key=?');
    $q->execute(['certificate_denominations']);
    $configured=$q->fetchColumn();
    $decoded=json_decode($configured===false?'[10000,20000,30000,50000]':(string)$configured,true);
    if (!is_array($decoded) || !array_is_list($decoded)) return [];
    $values=[];
    foreach ($decoded as $amount) {
        $value=filter_var($amount,FILTER_VALIDATE_INT);
        if ($value!==false && $value>0 && $value<=500000) $values[$value]=$value;
    }
    sort($values,SORT_NUMERIC);
    return array_values($values);
}

function commerce_create_certificate_preview(PDO $pdo, ?int $userId, string $actorKey, string $recipientName, string $recipientEmail, int $amount, string $theme, string $greeting): array
{
    $recipientName=trim($recipientName);
    if (!preg_match('/^[\p{L}\p{M} .’\'-]{2,80}$/u',$recipientName)) throw new DomainException('Укажите имя получателя от 2 до 80 символов.');
    $recipientEmail=trim($recipientEmail);
    if ($recipientEmail!=='' && (!filter_var($recipientEmail,FILTER_VALIDATE_EMAIL) || strlen($recipientEmail)>160)) throw new DomainException('Проверьте email получателя.');
    if (!in_array($amount,commerce_certificate_denominations($pdo),true)) throw new DomainException('Выберите один из доступных демонстрационных номиналов.');
    if (!in_array($theme,['sakura','enso','gold'],true)) throw new DomainException('Выберите доступное оформление сертификата.');
    if (!preg_match('//u',$greeting)) throw new DomainException('Текст поздравления имеет неверную кодировку.');
    $greeting=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/','',$greeting) ?? '');
    if (preg_match('/^.{241,}$/us',$greeting)) throw new DomainException('Поздравление должно быть не длиннее 240 символов.');
    $actorKey=substr($actorKey,0,128);
    if ($actorKey==='') throw new DomainException('Не удалось проверить сессию предварительного просмотра.');
    $ownsTransaction=!$pdo->inTransaction();
    if ($ownsTransaction) $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
    try {
        $q=$pdo->prepare("SELECT COUNT(*) FROM gift_certificate_previews WHERE actor_key=? AND created_at>=datetime('now','-1 hour')");
        $q->execute([$actorKey]);
        if ((int)$q->fetchColumn()>=5) throw new DomainException('За последний час создано слишком много предварительных макетов. Попробуйте позже.');
        $q=$pdo->prepare("INSERT INTO gift_certificate_previews(user_id,actor_key,recipient_name,recipient_email,amount,theme_key,greeting,status) VALUES(?,?,?,?,?,?,?,'preview_only')");
        $q->execute([$userId,$actorKey,$recipientName,$recipientEmail,$amount,$theme,$greeting]);
        $preview=['id'=>(int)$pdo->lastInsertId(),'recipient_name'=>$recipientName,'recipient_email'=>$recipientEmail,'amount'=>$amount,'theme'=>$theme,'greeting'=>$greeting,'status'=>'preview_only'];
        if ($ownsTransaction) $pdo->commit();
        return $preview;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
