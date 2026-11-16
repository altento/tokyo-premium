# Offers, Tokyo Privilege and static-demo limits

Updated 2026-11-13

## PHP + SQLite

The protected `/admin-marketing.php` route edits campaign records, promo codes, gift thresholds and seasonal collections. It uses the existing admin session, CSRF token, allow-listed local image paths, bounded input and prepared SQL. Campaign include/exclude targets support active `dish:<id>`, existing `category:<slug>`, and active `set:<slug>` values. Targets are validated against SQLite and saved atomically with the campaign. The public routes are `/offers.php`, `/collection.php?slug=…`, `/set-builder.php`, `/perfect-evening.php` and `/gift-certificates.php`. Each campaign row now has a prefilled in-place editor for its copy, dates, reward, target rules, image and status. Saving preserves the existing weekday mask; active Secret Menu rules must be disabled before changing their campaign type.

Promotion eligibility, date windows, weekday/time rules in `Asia/Qyzylorda`, target/excluded products, usage limits, gift availability, custom-set composition and final prices are resolved by `lib/commerce.php`. `api.php` returns a quote; order persistence recalculates from SQLite within its transaction. A quote does not reserve a campaign or confirm an order. No payment operation exists.

Secret Menu targeting is applied to the shared PHP catalog and every public entry point, including direct dish routes and the public collection API. A valid code unlock is recorded server-side in the PHP session; subsequent catalog, quote and order requests recalculate access from active rules. The code itself is not a discount, and a browser-supplied ID alone cannot expose or order a locked item. Public offers do not reveal Secret Menu access codes.

The protected Marketing & Loyalty panel supports editing the Secret Menu rule's campaign, access kind, value, active state and local start/end dates. Local datetimes are strictly parsed in `Asia/Qyzylorda`; invalid calendar values and missing rule IDs are rejected. The server enforces each rule's own schedule when granting access. Saving a code rule creates its matching active promo-code record in the same campaign transaction, or rejects a code already owned by a different campaign. Re-enabling a code rule also checks that the corresponding promo code remains active. Existing rules can be disabled without deleting their history.

Tokyo Privilege levels and benefit settings are database records. Ledger entries are created from verified completed-order status events with unique event keys; duplicate status events cannot add points twice. Referral association is tied to a registered account and a referral reward is triggered only by the qualifying completed order, not by opening a link. Birthday data is optional and the marketing consent is separate. Gift certificate flows are rate-limited previews; they do not create a payable or redeemable certificate.

## GitHub Pages

The export presents local versions of the offers, set builder, recommendation wizard, seasonal collection, certificate preview, promo quote and loyalty summary. Any stored values stay in this browser's `localStorage`. The local price quote is marked as an estimate; `tokyo-secret-demo.js` carries an intentionally public client-only Secret Menu preview payload. This is presentation only and does not provide PHP authorization. Referral codes do not register users or earn a reward. The certificate UI explicitly says it is not payment or stored value. Static actions do not reach PHP, email or restaurant staff.

## Seed examples

`DEMO10`, `TOKYOHOUR`, and `SECRET` are fictitious defaults for testing. The sample Golden Hour rule is weekdays 14:00–17:00 in the restaurant timezone. Tier thresholds and reward values are also examples that must be edited before any real service. They are not public offers or contractual terms.

## Referral and birthday follow-up — 2026-11-13

Referral codes are now stable per inviter and stored on the existing profile record. The account offers both a shareable same-origin invitation link and a copy action. Registration preserves the validated code; the invitee explicitly attaches it in the account before ordering. One inviter can refer multiple accounts. Repeated attachment by the same invitee is idempotent; self-invites and changing an already attached inviter are rejected. Existing legacy referral codes remain resolvable. Referral points are awarded only for a qualifying completed order and retain the existing reversal/idempotency ledger rules.

Birthday remains optional. The profile date is validated and a separate, withdrawable consent gates birthday campaigns. When points are configured, the loyalty program is enabled, and an eligible user opens the account during the configured birthday window, the server awards the birthday points once for that calendar-year occurrence. A unique ledger event key and SQLite write transaction prevent duplicate grants. Withdrawing consent stops future birthday offers; already issued educational points are retained in the account ledger. This is PHP + SQLite functionality; the static export remains a browser-only demonstration and does not register referrals or issue rewards.

Administrative point adjustments now serialize the balance check and ledger insert in one SQLite `BEGIN IMMEDIATE` transaction. The helper owns that transaction and rejects nested use so a caller cannot silently perform the balance check under a deferred transaction. `tests/loyalty-adjustment-atomicity.php` runs two concurrent 80-point debits against a 100-point balance and checks overdraft rollback plus an independent valid adjustment.

## Personal campaign assignment — 2026-11-16

`/admin-marketing.php` can search registered accounts by ID, name or email, assign an active `personal` campaign, set or update its optional expiry, and revoke an unused assignment. Assignment requires an active code with remaining uses and a current code window. The operation uses the existing unique `(user_id, campaign_id)` record and an owned SQLite `BEGIN IMMEDIATE` transaction; a used offer cannot be reissued or revoked. The admin enters expiry in `Asia/Qyzylorda`; it is stored in UTC because SQLite's `CURRENT_TIMESTAMP` entitlement checks use UTC, and is displayed back in local time. The account shows the eligible code with copy and checkout actions; private/birthday campaign codes are excluded from the public code list and included only for the assigned account. The server still recalculates code eligibility and price. This is PHP + SQLite functionality; the static Pages demo has no server identity or assignment endpoint.

## Configurable threshold gift quantities — 2026-11-16

The `max_per_order` configured for a threshold gift now controls the checkout quantity input and server quote. The API returns each eligible gift's current stock and per-order limit; quote and order persistence reject quantities above either bound. The order line records the selected quantity, and the stock decrement subtracts that quantity inside the existing order transaction. GitHub Pages has no authoritative gift stock, so it continues to show no server gift inventory. `php tests/gift-quantity-regression.php` is the short isolated SQLite regression; the full PHP integration suite remains broader regression coverage.
