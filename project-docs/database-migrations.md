# SQLite schema and migration notes

Updated 2026-11-12

The app runs `schema.sql` with `CREATE TABLE/INDEX IF NOT EXISTS`, then records explicit upgrades in `app_migrations`. Existing SQLite files are upgraded additively; migrations avoid deleting menu, user, order or booking data.

| Version | Change |
|---|---|
| 1 | Base application tables and fictional educational seed data (`seed.sql`). |
| 2 | Repoint seeded dish photos from old remote URLs to local WebP assets when the existing value is still an HTTPS URL. |
| 3 | Add order subtotals/discount fields and commerce defaults; seed demo set variants, loyalty tiers, campaigns, promo codes, Secret Menu access rules, and the Autumn collection. Transaction is `BEGIN IMMEDIATE`. |
| 4 | Add certificate-preview actor key, rate-limit index and editable example denominations. |
| 5 | Update only the untouched sample Autumn collection image to its generated seasonal asset; preserves any administrator-selected image. |

`schema.sql` declares the normalized entities for campaigns and targets, gift options, promo-code redemptions, collection membership, set variants, Secret Menu rules, personalized offers, loyalty settings/tiers/ledger, order set components, referral relationships/rewards, certificate previews and status-event idempotency. Foreign keys, unique event constraints, indexes and `CHECK` constraints are used where the domain supports them.

To verify without changing a user's database, use the isolated integration test:

```sh
php tests/php_integrity_integration.php
```

It creates a temporary SQLite database, exercises migrations and business rules, and removes the database after the run. Deployment should back up the SQLite file before upgrading any persistent production copy. This repository is a fictional educational project, not a configured production data service.
