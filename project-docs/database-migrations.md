# SQLite schema and migration notes

## v17 · Obsolete booking confirmations

Adds `cancelled` to the email outbox status constraint. SQLite requires a transactional table rebuild for the legacy CHECK: all existing columns/rows/IDs are copied, explicit indexes/triggers are recreated and the autoincrement high-water mark is preserved. Existing confirmed/sent/history data is retained. Obsolete pending/failed confirmation events become cancelled; unrelated receipt events remain untouched. Fresh databases start with the expanded constraint. Bootstrap readiness now requires v17. The migration regression covers old-schema upgrade, repeat application, row/body/ID/sequence/index preservation and foreign-key checks.

Updated 2026-11-19

The app runs `schema.sql` with `CREATE TABLE/INDEX IF NOT EXISTS`, then records explicit upgrades in `app_migrations`. Existing SQLite files are upgraded additively; migrations avoid deleting menu, user, order or booking data.

| Version | Change |
|---|---|
| 1 | Base application tables and fictional educational seed data (`seed.sql`). |
| 2 | Repoint seeded dish photos from old remote URLs to local WebP assets when the existing value is still an HTTPS URL. |
| 3 | Add order subtotals/discount fields and commerce defaults; seed demo set variants, loyalty tiers, campaigns, promo codes, Secret Menu access rules, and the Autumn collection. Transaction is `BEGIN IMMEDIATE`. |
| 4 | Add certificate-preview actor key, rate-limit index and editable example denominations. |
| 5 | Update only the untouched sample Autumn collection image to its generated seasonal asset; preserves any administrator-selected image. |
| 6 | Add optional booking guest email and a unique-event SMTP outbox. Existing bookings are preserved and receive an empty default email; delivery remains disabled until the PHP service is configured. |
| 7 | Add transactional booking idempotency reservations and hashed email/IP rate-limit buckets. Existing bookings and outbox rows are preserved. |
| 8 | Add account-owned delivery addresses, a single-default partial unique index, and owner-scoped address operations. Existing accounts are unchanged and begin with no saved addresses. |
| 9 | Seed editable drink, dish and dessert pairings for the educational catalogue. Inserts are transactional and idempotent; existing relationships are preserved. Product detail pages resolve active, viewable pairings from SQLite and fill remaining slots from the catalogue. Admins can create, edit, reorder and remove pairings in the menu management panel. |
| 10 | Add the photographed scallop nigiri catalog item and its normalized scallop ingredient/pairing links when absent; preserve an existing dish record and photo. |
| 11 | Add catalog-derived ingredient notes to dish details using the existing normalized ingredient tables. The additive transaction uses `INSERT OR IGNORE`, preserves administrator edits on future boots, and does not provide or imply allergen verification. Set pages without a component list retain an explicit note instead of invented ingredients. |
| 12 | Add account-owned persisted notifications with unique event keys, owner-scoped read helpers, and booking status event IDs. Order and booking status notifications are committed in the same transaction as the underlying status event; unchanged status requests do not emit notifications. Existing accounts keep an empty inbox. |
| 13 | Assign dedicated generated WebP imagery to unchanged seeded offers without overwriting administrator edits, and add a 30-day demo Chef's Secret Drop linked to the active scallop nigiri item. |
| 14 | Save the selected custom-set format slug on each order line and backfill older set lines by their saved format name. Existing order snapshots and component rows remain intact, allowing account repeat-order to rebuild only currently available items at current catalogue prices. |
| 15 | Add atomic administrator login cooldown buckets. SQLite stores keyed hashes only, with a 15-minute IP window and a higher account threshold; bootstrap readiness now requires v15 and the attempt table, so older v13/v14 databases continue through later migrations. |
| 16 | Add optional order payment status, per-attempt FreedomPay records and a hashed payment capability. Existing orders remain unpaid; card data and credentials are not stored. |
| 17 | Mark obsolete queued booking-confirmation email events as cancelled while preserving all outbox rows and IDs. |
| 18 | Add nullable, session-keyed request fingerprints to order and booking idempotency rows. Existing rows are retained; an old replay with no fingerprint returns a conflict instead of claiming changed data was submitted. |
| 19 | Add 29 fictional public menu items when absent, plus normalized ingredient, tag and pairing links. Existing administrator-edited dish rows are preserved; additions are transactional and idempotent. |
| 20 | Add two additional public items to round the menu out to 50 visible dishes on databases that already contain two hidden Secret Menu entries; preserve existing products and related catalog links. |

For migration v18, the fingerprint is an HMAC of canonical request fields excluding the CSRF token, with the current PHP session ID as the key. Exact retries replay the stored response; reusing a key with changed contact or order data returns HTTP 409. Raw personal data is not added to the idempotency tables. New reservations store the fingerprint in the same `BEGIN IMMEDIATE` transaction as the order/booking and replay result.

Migrations v19–20 extend the fictional catalogue to 50 publicly visible items on the current database. The additional copycat image paths point to existing local WebP assets so every card loads offline; 29 new products still need their own dedicated photographs before the catalogue can be described as having unique dish photography.

The account notification helpers are `commerce_account_notifications($pdo, $userId, $limit)` and `commerce_mark_account_notification_read($pdo, $userId, $notificationId)`. The mark-read helper only updates rows owned by the supplied user and is safe to retry. The authenticated `account.php` page exposes the `notification-read` POST action and checks CSRF before invoking the helper.

`schema.sql` declares the normalized entities for campaigns and targets, gift options, promo-code redemptions, collection membership, set variants, Secret Menu rules, personalized offers, loyalty settings/tiers/ledger, order set components, referral relationships/rewards, certificate previews and status-event idempotency. Foreign keys, unique event constraints, indexes and `CHECK` constraints are used where the domain supports them.

To verify without changing a user's database, use the isolated integration test:

```sh
php tests/php_integrity_integration.php
```

It creates a temporary SQLite database, exercises migrations and business rules, and removes the database after the run. Deployment should back up the SQLite file before upgrading any persistent production copy. This repository is a fictional educational project, not a configured production data service.

### Cross-version SQLite transactions

Application code that needs `BEGIN IMMEDIATE` uses `sqlite_begin_immediate()`, `sqlite_commit()` and `sqlite_rollback()` from `lib/commerce.php`. PDO_SQLite versions differ in how SQL-started transactions are exposed through `PDO::inTransaction()` and `PDO::commit()`. A per-connection `WeakMap` tracks those explicit transactions while preserving native PDO transactions, so migrations, checkout, payment callbacks, admin mutations and mail-outbox claims share the same transaction semantics.
