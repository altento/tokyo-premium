PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS categories (slug TEXT PRIMARY KEY, name TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0);
CREATE TABLE IF NOT EXISTS dishes (id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT NOT NULL REFERENCES categories(slug), name TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', weight TEXT NOT NULL DEFAULT '', price INTEGER NOT NULL DEFAULT 0 CHECK(price >= 0), image_url TEXT NOT NULL DEFAULT '', featured INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, full_name TEXT NOT NULL, password_hash TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, customer_name TEXT NOT NULL, phone TEXT NOT NULL, address TEXT NOT NULL DEFAULT '', fulfillment TEXT NOT NULL DEFAULT 'delivery' CHECK(fulfillment IN ('delivery','pickup')), comment TEXT NOT NULL DEFAULT '', total INTEGER NOT NULL CHECK(total >= 0), status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','confirmed','preparing','ready','completed','cancelled')), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE, dish_id INTEGER REFERENCES dishes(id) ON DELETE SET NULL, item_name TEXT NOT NULL, price INTEGER NOT NULL, quantity INTEGER NOT NULL CHECK(quantity > 0));
CREATE TABLE IF NOT EXISTS bookings (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, guest_name TEXT NOT NULL, guest_email TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL, guest_count INTEGER NOT NULL CHECK(guest_count BETWEEN 1 AND 12), booking_date TEXT NOT NULL, booking_time TEXT NOT NULL, zone TEXT NOT NULL DEFAULT '', preferred_table TEXT NOT NULL DEFAULT '', format TEXT NOT NULL DEFAULT '', message TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','confirmed','completed','cancelled')), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS email_outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, event_key TEXT NOT NULL UNIQUE, booking_id INTEGER REFERENCES bookings(id) ON DELETE SET NULL, recipient TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','sending','sent','failed')), attempts INTEGER NOT NULL DEFAULT 0 CHECK(attempts >= 0), locked_at TEXT, sent_at TEXT, last_error TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_email_outbox_status_id ON email_outbox(status,id);
CREATE INDEX IF NOT EXISTS idx_email_outbox_booking_id ON email_outbox(booking_id,id);
CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, guest_name TEXT NOT NULL, rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5), body TEXT NOT NULL, theme TEXT NOT NULL DEFAULT 'Кухня', status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','published','hidden')), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS app_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS contact_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, guest_name TEXT NOT NULL, email TEXT NOT NULL, subject TEXT NOT NULL, message TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','read','archived')), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS api_idempotency (token TEXT PRIMARY KEY, response_json TEXT NOT NULL, response_status INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
-- Order key reservation, order, items and replay response are written in one transaction.
CREATE TABLE IF NOT EXISTS api_order_requests (
 token TEXT PRIMARY KEY,
 order_id INTEGER UNIQUE REFERENCES orders(id) ON DELETE SET NULL,
 response_json TEXT,
 response_status INTEGER,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK ((response_json IS NULL AND response_status IS NULL) OR (response_json IS NOT NULL AND response_status IS NOT NULL))
);
-- Booking key reservation and response are committed with the booking and mail outbox rows.
CREATE TABLE IF NOT EXISTS api_booking_requests (
 token TEXT PRIMARY KEY,
 booking_id INTEGER UNIQUE REFERENCES bookings(id) ON DELETE SET NULL,
 response_json TEXT,
 response_status INTEGER,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK ((response_json IS NULL AND response_status IS NULL) OR (response_json IS NOT NULL AND response_status IS NOT NULL))
);
-- Store one-way hashes only; rate-limit windows are evaluated in SQLite UTC.
CREATE TABLE IF NOT EXISTS booking_email_rate_limits (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 bucket_hash TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_booking_email_rate_bucket_time ON booking_email_rate_limits(bucket_hash,created_at);

-- Tokyo Premium 2.0 commerce, offers and loyalty. All changes are additive so
-- existing educational databases retain their orders and account records.
CREATE TABLE IF NOT EXISTS promotion_campaigns (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 slug TEXT NOT NULL UNIQUE,
 campaign_type TEXT NOT NULL CHECK(campaign_type IN ('exclusive','golden_hour','chef_drop','secret_menu','birthday','personal','gift_threshold')),
 title TEXT NOT NULL,
 description TEXT NOT NULL DEFAULT '',
 image_url TEXT NOT NULL DEFAULT '',
 reward_type TEXT NOT NULL CHECK(reward_type IN ('percent','fixed','gift_dish','set_price','free_delivery','bonus_points','access','none')),
 reward_value INTEGER NOT NULL DEFAULT 0 CHECK(reward_value >= 0),
 gift_dish_id INTEGER REFERENCES dishes(id) ON DELETE SET NULL,
 minimum_subtotal INTEGER NOT NULL DEFAULT 0 CHECK(minimum_subtotal >= 0),
 maximum_discount INTEGER NOT NULL DEFAULT 0 CHECK(maximum_discount >= 0),
 starts_at TEXT,
 ends_at TEXT,
 weekday_mask INTEGER NOT NULL DEFAULT 127 CHECK(weekday_mask BETWEEN 0 AND 127),
 starts_time TEXT,
 ends_time TEXT,
 timezone TEXT NOT NULL DEFAULT 'Asia/Qyzylorda',
 required_tier TEXT,
 active INTEGER NOT NULL DEFAULT 0 CHECK(active IN (0,1)),
 stackable INTEGER NOT NULL DEFAULT 0 CHECK(stackable IN (0,1)),
 demo_only INTEGER NOT NULL DEFAULT 1 CHECK(demo_only IN (0,1)),
 sort_order INTEGER NOT NULL DEFAULT 0,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at),
 CHECK((starts_time IS NULL AND ends_time IS NULL) OR (starts_time IS NOT NULL AND ends_time IS NOT NULL))
);
CREATE TABLE IF NOT EXISTS promotion_targets (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 campaign_id INTEGER NOT NULL REFERENCES promotion_campaigns(id) ON DELETE CASCADE,
 target_kind TEXT NOT NULL CHECK(target_kind IN ('dish','category','set')),
 target_key TEXT NOT NULL,
 excluded INTEGER NOT NULL DEFAULT 0 CHECK(excluded IN (0,1)),
 UNIQUE(campaign_id,target_kind,target_key,excluded)
);
CREATE TABLE IF NOT EXISTS campaign_gift_options (
 campaign_id INTEGER NOT NULL REFERENCES promotion_campaigns(id) ON DELETE CASCADE,
 dish_id INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
 remaining_quantity INTEGER NOT NULL DEFAULT 0 CHECK(remaining_quantity >= 0),
 max_per_order INTEGER NOT NULL DEFAULT 1 CHECK(max_per_order BETWEEN 1 AND 10),
 active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
 PRIMARY KEY(campaign_id,dish_id)
);
CREATE TABLE IF NOT EXISTS promo_codes (
 code TEXT PRIMARY KEY COLLATE NOCASE,
 campaign_id INTEGER NOT NULL REFERENCES promotion_campaigns(id) ON DELETE CASCADE,
 active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
 starts_at TEXT,
 ends_at TEXT,
 maximum_uses INTEGER CHECK(maximum_uses IS NULL OR maximum_uses >= 0),
 maximum_uses_per_user INTEGER CHECK(maximum_uses_per_user IS NULL OR maximum_uses_per_user >= 0),
 uses_count INTEGER NOT NULL DEFAULT 0 CHECK(uses_count >= 0),
 first_order_only INTEGER NOT NULL DEFAULT 0 CHECK(first_order_only IN (0,1)),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
);
CREATE TABLE IF NOT EXISTS promo_redemptions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 promo_code TEXT NOT NULL REFERENCES promo_codes(code),
 campaign_id INTEGER NOT NULL REFERENCES promotion_campaigns(id),
 user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
 actor_key TEXT NOT NULL,
 order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
 discount_amount INTEGER NOT NULL DEFAULT 0 CHECK(discount_amount >= 0),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(promo_code,order_id),
 UNIQUE(promo_code,actor_key,order_id)
);
CREATE INDEX IF NOT EXISTS idx_promo_redemptions_actor ON promo_redemptions(promo_code,actor_key);
CREATE TABLE IF NOT EXISTS order_adjustments (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
 campaign_id INTEGER REFERENCES promotion_campaigns(id) ON DELETE SET NULL,
 adjustment_type TEXT NOT NULL CHECK(adjustment_type IN ('discount','gift','bonus_points','free_delivery','set_price','loyalty_redeem')),
 code TEXT NOT NULL DEFAULT '',
 label TEXT NOT NULL,
 amount INTEGER NOT NULL DEFAULT 0 CHECK(amount >= 0),
 gift_dish_id INTEGER REFERENCES dishes(id) ON DELETE SET NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(order_id,adjustment_type,code)
);
CREATE TABLE IF NOT EXISTS seasonal_collections (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 slug TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL,
 description TEXT NOT NULL DEFAULT '',
 image_url TEXT NOT NULL DEFAULT '',
 theme_key TEXT NOT NULL DEFAULT 'sakura',
 starts_at TEXT,
 ends_at TEXT,
 active INTEGER NOT NULL DEFAULT 0 CHECK(active IN (0,1)),
 demo_only INTEGER NOT NULL DEFAULT 1 CHECK(demo_only IN (0,1)),
 sort_order INTEGER NOT NULL DEFAULT 0,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
);
CREATE TABLE IF NOT EXISTS seasonal_collection_items (
 collection_id INTEGER NOT NULL REFERENCES seasonal_collections(id) ON DELETE CASCADE,
 dish_id INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
 sort_order INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(collection_id,dish_id)
);
CREATE TABLE IF NOT EXISTS custom_set_variants (
 slug TEXT PRIMARY KEY,
 name TEXT NOT NULL,
 minimum_distinct_items INTEGER NOT NULL DEFAULT 3 CHECK(minimum_distinct_items BETWEEN 2 AND 8),
 maximum_distinct_items INTEGER NOT NULL DEFAULT 8 CHECK(maximum_distinct_items BETWEEN 2 AND 12),
 maximum_total_quantity INTEGER NOT NULL DEFAULT 12 CHECK(maximum_total_quantity BETWEEN 2 AND 40),
 active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
 demo_only INTEGER NOT NULL DEFAULT 1 CHECK(demo_only IN (0,1)),
 sort_order INTEGER NOT NULL DEFAULT 0,
 CHECK(maximum_distinct_items >= minimum_distinct_items)
);
CREATE TABLE IF NOT EXISTS ingredients (
 slug TEXT PRIMARY KEY,
 name TEXT NOT NULL UNIQUE,
 active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1))
);
CREATE TABLE IF NOT EXISTS dish_ingredients (
 dish_id INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
 ingredient_slug TEXT NOT NULL REFERENCES ingredients(slug) ON UPDATE CASCADE ON DELETE RESTRICT,
 is_optional INTEGER NOT NULL DEFAULT 0 CHECK(is_optional IN (0,1)),
 sort_order INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(dish_id,ingredient_slug)
);
CREATE TABLE IF NOT EXISTS dish_tags (
 dish_id INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
 tag TEXT NOT NULL,
 PRIMARY KEY(dish_id,tag)
);
CREATE TABLE IF NOT EXISTS dish_pairings (
 dish_id INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
 paired_dish_id INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
 pairing_type TEXT NOT NULL CHECK(pairing_type IN ('drink','sauce','dish','dessert')),
 sort_order INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(dish_id,paired_dish_id,pairing_type),
 CHECK(dish_id<>paired_dish_id)
);
CREATE TABLE IF NOT EXISTS secret_menu_rules (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 campaign_id INTEGER NOT NULL REFERENCES promotion_campaigns(id) ON DELETE CASCADE,
 access_kind TEXT NOT NULL CHECK(access_kind IN ('role','tier','code')),
 required_value TEXT NOT NULL,
 starts_at TEXT,
 ends_at TEXT,
 active INTEGER NOT NULL DEFAULT 0 CHECK(active IN (0,1)),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK(ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
);
CREATE TABLE IF NOT EXISTS personalized_offers (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 campaign_id INTEGER NOT NULL REFERENCES promotion_campaigns(id) ON DELETE CASCADE,
 assigned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at TEXT,
 used_order_id INTEGER UNIQUE REFERENCES orders(id) ON DELETE SET NULL,
 UNIQUE(user_id,campaign_id)
);
CREATE TABLE IF NOT EXISTS user_profiles (
 user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
 birth_date TEXT,
 birthday_offer_consent INTEGER NOT NULL DEFAULT 0 CHECK(birthday_offer_consent IN (0,1)),
 birthday_consent_at TEXT,
 referral_code TEXT UNIQUE COLLATE NOCASE,
 preferences_json TEXT NOT NULL DEFAULT '{}',
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK((birthday_offer_consent=0 AND birthday_consent_at IS NULL) OR birthday_offer_consent=1)
);
CREATE TABLE IF NOT EXISTS loyalty_settings (
 id INTEGER PRIMARY KEY CHECK(id=1),
 enabled INTEGER NOT NULL DEFAULT 1 CHECK(enabled IN (0,1)),
 points_per_100 INTEGER NOT NULL DEFAULT 5 CHECK(points_per_100 BETWEEN 0 AND 10000),
 currency_per_point INTEGER NOT NULL DEFAULT 1 CHECK(currency_per_point BETWEEN 1 AND 100000),
 maximum_redeem_percent INTEGER NOT NULL DEFAULT 50 CHECK(maximum_redeem_percent BETWEEN 0 AND 100),
 birthday_window_days INTEGER NOT NULL DEFAULT 7 CHECK(birthday_window_days BETWEEN 0 AND 60),
 birthday_reward_points INTEGER NOT NULL DEFAULT 0 CHECK(birthday_reward_points >= 0),
 referral_inviter_points INTEGER NOT NULL DEFAULT 0 CHECK(referral_inviter_points >= 0),
 referral_friend_points INTEGER NOT NULL DEFAULT 0 CHECK(referral_friend_points >= 0),
 referral_minimum_order INTEGER NOT NULL DEFAULT 0 CHECK(referral_minimum_order >= 0),
 demo_only INTEGER NOT NULL DEFAULT 1 CHECK(demo_only IN (0,1)),
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS loyalty_tiers (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 slug TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL,
 minimum_completed_spend INTEGER NOT NULL DEFAULT 0 CHECK(minimum_completed_spend >= 0),
 earn_multiplier_basis_points INTEGER NOT NULL DEFAULT 10000 CHECK(earn_multiplier_basis_points BETWEEN 0 AND 100000),
 benefits_json TEXT NOT NULL DEFAULT '[]',
 active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
 sort_order INTEGER NOT NULL DEFAULT 0,
 demo_only INTEGER NOT NULL DEFAULT 1 CHECK(demo_only IN (0,1))
);
CREATE TABLE IF NOT EXISTS loyalty_ledger (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
 referral_id INTEGER,
 event_key TEXT NOT NULL,
 transaction_type TEXT NOT NULL CHECK(transaction_type IN ('earn','redeem','reversal','referral','birthday','admin_adjustment')),
 points INTEGER NOT NULL CHECK(points <> 0),
 reversal_of_event TEXT,
 admin_id INTEGER REFERENCES admins(id) ON DELETE SET NULL,
 note TEXT NOT NULL DEFAULT '',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(user_id,event_key),
 UNIQUE(user_id,reversal_of_event)
);
CREATE INDEX IF NOT EXISTS idx_loyalty_ledger_user ON loyalty_ledger(user_id,created_at DESC);
CREATE TABLE IF NOT EXISTS referrals (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 code TEXT NOT NULL UNIQUE COLLATE NOCASE,
 inviter_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 referred_user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE SET NULL,
 qualifying_order_id INTEGER UNIQUE REFERENCES orders(id) ON DELETE SET NULL,
 status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','qualified','rewarded','cancelled')),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 qualified_at TEXT,
 CHECK(referred_user_id IS NULL OR referred_user_id <> inviter_user_id)
);
CREATE TABLE IF NOT EXISTS referral_rewards (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 referral_id INTEGER NOT NULL REFERENCES referrals(id) ON DELETE CASCADE,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 qualifying_order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
 event_key TEXT NOT NULL UNIQUE,
 points INTEGER NOT NULL CHECK(points > 0),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS order_status_events (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
 previous_status TEXT NOT NULL,
 new_status TEXT NOT NULL,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS gift_certificate_previews (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
 actor_key TEXT NOT NULL DEFAULT '',
 recipient_name TEXT NOT NULL,
 recipient_email TEXT NOT NULL DEFAULT '',
 amount INTEGER NOT NULL CHECK(amount > 0),
 theme_key TEXT NOT NULL DEFAULT 'sakura',
 greeting TEXT NOT NULL DEFAULT '',
 status TEXT NOT NULL DEFAULT 'preview_only' CHECK(status='preview_only'),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_gift_preview_actor_created ON gift_certificate_previews(actor_key,created_at DESC);
CREATE TABLE IF NOT EXISTS order_custom_set_components (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 order_item_id INTEGER NOT NULL REFERENCES order_items(id) ON DELETE CASCADE,
 dish_id INTEGER REFERENCES dishes(id) ON DELETE SET NULL,
 item_name TEXT NOT NULL,
 price INTEGER NOT NULL CHECK(price >= 0),
 quantity INTEGER NOT NULL CHECK(quantity > 0)
);
INSERT OR IGNORE INTO loyalty_settings(id) VALUES(1);
