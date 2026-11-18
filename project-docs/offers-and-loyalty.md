# Offers, Tokyo Privilege and static-demo limits

Updated 2026-11-18

## Eligibility consistency follow-up — 2026-11-16

Assigned personal and birthday campaign cards are now returned only while the owner has an unexpired assignment and the campaign has at least one active, in-window promo code with remaining uses. This keeps the Offers page aligned with the account code list and checkout eligibility. The Offers template also reads `campaign_type` from SQLite (with a guarded legacy fallback), avoiding the PHP warning previously embedded in the checked-in static export. The Ideal Evening recommender consistently interprets its input as a per-guest budget, shows the group allowance, and compares the estimated group total against that allowance.

## PHP + SQLite

The protected `/admin-marketing.php` route edits campaign records, promo codes, gift thresholds and seasonal collections. It uses the existing admin session, CSRF token, allow-listed local image paths, bounded input and prepared SQL. Campaign include/exclude targets support active `dish:<id>`, existing `category:<slug>`, and active `set:<slug>` values. Targets are validated against SQLite and saved atomically with the campaign. The public routes are `/offers.php`, `/collection.php?slug=…`, `/set-builder.php`, `/perfect-evening.php` and `/gift-certificates.php`. Each campaign row now has a prefilled in-place editor for its copy, dates, reward, target rules, image and status. Saving preserves the existing weekday mask; active Secret Menu rules must be disabled before changing their campaign type. Campaign, promo-code and collection windows share strict restaurant-timezone date parsing, including calendar-day and end-after-start checks. The base delivery tariff is configurable in the protected site settings; zero means no fee, and the server applies a free-delivery promotion only when a positive tariff exists.

Promotion eligibility, date windows, weekday/time rules in `Asia/Qyzylorda`, target/excluded products, usage limits, gift availability, custom-set composition and final prices are resolved by `lib/commerce.php`. `api.php` returns a quote; order persistence recalculates from SQLite within its transaction. A quote does not reserve a campaign or confirm an order. No payment operation exists.

Secret Menu targeting is applied to the shared PHP catalog and every public entry point, including direct dish routes and the public collection API. A valid code unlock is recorded server-side in the PHP session; subsequent catalog, quote and order requests recalculate access from active rules. The code itself is not a discount, and a browser-supplied ID alone cannot expose or order a locked item. Public offers do not reveal Secret Menu access codes.

The protected Marketing & Loyalty panel supports editing the Secret Menu rule's campaign, access kind, value, active state and local start/end dates. Local datetimes are strictly parsed in `Asia/Qyzylorda`; invalid calendar values and missing rule IDs are rejected. The server enforces each rule's own schedule when granting access. Saving a code rule creates its matching active promo-code record in the same campaign transaction, or rejects a code already owned by a different campaign. Re-enabling a code rule also checks that the corresponding promo code remains active. Existing rules can be disabled without deleting their history.

### Campaign performance in admin — 2026-11-18

`/admin-marketing.php#performance` shows per-campaign saved uses, distinct non-cancelled orders, the discounts attached to those orders, status, demo/live marker and last use in `Asia/Qyzylorda`. It intentionally does not label these educational order records as paid revenue. Campaign rows are included in the existing keyboard-accessible management search. The table has a keyboard-focusable horizontal scroll region for small screens. A regression was added to the existing protected ledger admin test: it renders two uses against one completed and one cancelled order and checks that only the completed order and its discount enter the corresponding totals. The local PHP CLI has no `pdo_sqlite`; the existing hosted PHP/SQLite workflow is the authoritative database check.

Tokyo Privilege levels and benefit settings are database records. Ledger entries are created from verified completed-order status events with unique event keys; duplicate status events cannot add points twice. Referral association is tied to a registered account and a referral reward is triggered only by the qualifying completed order, not by opening a link. Birthday data is optional and the marketing consent is separate. Gift certificate flows are rate-limited previews; they do not create a payable or redeemable certificate.

### Set builder format management — 2026-11-18

The protected Marketing & Loyalty panel now creates, edits, orders, and deactivates custom-set formats. Slugs stay stable for existing links and campaign targeting. Server validation limits a format to 3–8 distinct dishes and at most 12 portions, and the set builder continues to derive prices from active catalogue records. Pages still exposes only the demo catalogue and browser-side estimate. The focused admin-set-variant-regression.php covers authorization, create/edit, invalid bounds, duplicate slugs, CSRF, transaction rollback, activation, and the public PHP builder view.
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

### Tier-restricted offers and birthday assignments — 2026-11-16

`required_tier` is enforced server-side using the active loyalty tier calculated from completed-order spend. Ineligible accounts do not see restricted offer cards or codes; quote and order persistence reject restricted promo codes, and threshold-gift selection skips a campaign when the account is below its required tier. Admin assignment supports `personal` and `birthday` campaigns. A birthday offer can be issued only to an account with a valid birthday, separate consent, and eligibility inside the configured birthday window; the account and checkout recheck the campaign, tier, code and eligibility. GitHub Pages remains a local estimate/demo and does not perform server-tier checks or issue account offers.

## Configurable threshold gift quantities — 2026-11-16

The `max_per_order` configured for a threshold gift now controls the checkout quantity input and server quote. The API returns each eligible gift's current stock and per-order limit; quote and order persistence reject quantities above either bound. The order line records the selected quantity, and the stock decrement subtracts that quantity inside the existing order transaction. GitHub Pages has no authoritative gift stock, so it continues to show no server gift inventory. `php tests/gift-quantity-regression.php` is the short isolated SQLite regression; the full PHP integration suite remains broader regression coverage.

### Pricing attribution and set-price caps — 2026-11-16

A `set_price` campaign's `maximum_discount` applies across the order's targeted custom sets, not independently to each line. The quote allocates that cap across eligible sets and persists the same per-set reductions, keeping order-line merchandise values consistent with the quoted total. Promo redemption history records the campaign discount separately from loyalty-point redemption. The PHP+SQLite regression is `tests/commerce-pricing-regression.php`; Pages remains an estimate-only client demo.
### Existing-member referral flow and account lookup — 2026-11-16

The private admin account lookup performs Cyrillic-aware case-insensitive matching for name/email search. A validated invitation code survives login for an existing member and is prefilled for explicit account attachment; referral rewards still follow the existing completed-order rule. The PHP/SQLite regression is `tests/admin-account-search-referral-regression.php`.
## Promo-code administration follow-up — 2026-11-17

Promo-code rows now expose the persisted redemption counter, total and per-account limits, validity window, and first-order restriction. Each existing code has a prefilled editor for those settings; the code string is read-only there so editing cannot accidentally create a second code while leaving the original active. Disabling remains reversible and preserves redemption history.

## Searchable Tokyo Privilege ledger — 2026-11-18

The protected `/admin-marketing.php#loyalty-ledger` section now shows the current server-side loyalty ledger with member and email details, event type, signed points, current balance, linked order/referral, note, and event key. Search covers hash-prefixed or plain operation/member/order/referral IDs and case-insensitive name, email, note, event key, or referral code; type and inclusive local-calendar-date filters can be combined. Results are ordered newest first and paginated at 25 rows. Date bounds are parsed as real dates in `Asia/Qyzylorda`, then converted to UTC for the existing SQLite timestamps. SQL wildcard characters in search input are treated literally. The page remains behind the existing admin session; it does not provide edit/delete controls for ledger history.

At narrow widths the filter form becomes one column and the wide ledger stays inside its own horizontally scrollable region. A visible mobile scroll hint is connected to the keyboard-focusable table region through `aria-describedby`; the page itself remains within the viewport. Desktop and mobile screenshots plus geometry measurements are in `project-docs/visual-qa/admin-loyalty-ledger-2026-11-18/`.

`php tests/admin-loyalty-ledger-regression.php` creates an isolated temporary SQLite database and verifies the protected page rendering, totals and pagination, member search, linked events, operation-type filtering, signed balance, local date boundaries, literal wildcard search, invalid-date handling, HTML escaping, and the mobile scroll hint. This adds no migration and does not alter the seed database.

## Campaign imagery and composed Ideal Evening — 2026-11-18

The four seeded promotion concepts now use separate original, optimized WebP photographs: Tokyo Exclusive, Golden Hour, Chef's Secret Drop and Secret Menu. Additive migration 13 changes only the original seed image values, leaves administrator-edited image paths intact, and inserts a 30-day `demo_only` Chef's Secret Drop tied to the active scallop nigiri catalog record. The Offers page renders this campaign from SQLite, links to that real item when it is visible to the current visitor, and does not invent a remaining-stock count. The admin campaign image selector accepts these local files through its exact allow-list.

Ideal Evening now uses active, viewable catalog pairings to build a guest-sized meal with a main item, a linked drink, and optional side/dessert lines when the group budget permits. It shows roles, quantities, and the group estimate, and one action adds the full plan atomically through `TokyoCart.addMany`; ordinary cart controls continue to share the same canonical cart state. Choosing Omakase with a catalog set carries the set's matching format, preferred zone, and an editable plan summary into booking. Secret Menu-only items are excluded unless they are actually visible in the current PHP session. GitHub Pages labels its result as a browser estimate and keeps all submitted data local.
