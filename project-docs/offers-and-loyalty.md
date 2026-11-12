# Offers, Tokyo Privilege and static-demo limits

Updated 2026-11-13

## PHP + SQLite

The protected `/admin-marketing.php` route edits campaign records, promo codes, gift thresholds and seasonal collections. It uses the existing admin session, CSRF token, allow-listed local image paths, bounded input and prepared SQL. Campaign include/exclude targets support active `dish:<id>`, existing `category:<slug>`, and active `set:<slug>` values. Targets are validated against SQLite and saved atomically with the campaign. The public routes are `/offers.php`, `/collection.php?slug=…`, `/set-builder.php`, `/perfect-evening.php` and `/gift-certificates.php`.

Promotion eligibility, date windows, weekday/time rules in `Asia/Qyzylorda`, target/excluded products, usage limits, gift availability, custom-set composition and final prices are resolved by `lib/commerce.php`. `api.php` returns a quote; order persistence recalculates from SQLite within its transaction. A quote does not reserve a campaign or confirm an order. No payment operation exists.

Secret Menu targeting is applied to the shared PHP catalog and every public entry point, including direct dish routes and the public collection API. A valid code unlock is recorded server-side in the PHP session; subsequent catalog, quote and order requests recalculate access from active rules. The code itself is not a discount, and a browser-supplied ID alone cannot expose or order a locked item. Public offers do not reveal Secret Menu access codes.

Tokyo Privilege levels and benefit settings are database records. Ledger entries are created from verified completed-order status events with unique event keys; duplicate status events cannot add points twice. Referral association is tied to a registered account and a referral reward is triggered only by the qualifying completed order, not by opening a link. Birthday data is optional and the marketing consent is separate. Gift certificate flows are rate-limited previews; they do not create a payable or redeemable certificate.

## GitHub Pages

The export presents local versions of the offers, set builder, recommendation wizard, seasonal collection, certificate preview, promo quote and loyalty summary. Any stored values stay in this browser's `localStorage`. The local price quote is marked as an estimate; `tokyo-secret-demo.js` carries an intentionally public client-only Secret Menu preview payload. This is presentation only and does not provide PHP authorization. Referral codes do not register users or earn a reward. The certificate UI explicitly says it is not payment or stored value. Static actions do not reach PHP, email or restaurant staff.

## Seed examples

`DEMO10`, `TOKYOHOUR`, and `SECRET` are fictitious defaults for testing. The sample Golden Hour rule is weekdays 14:00–17:00 in the restaurant timezone. Tier thresholds and reward values are also examples that must be edited before any real service. They are not public offers or contractual terms.
