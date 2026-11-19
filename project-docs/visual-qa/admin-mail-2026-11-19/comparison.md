# Admin booking and email integrity — 2026-11-19

Baseline `a2bc130`; restoration branch `backup/admin-mail-20261119`.

## Confirmed defects

[Reproduction](reproduction.json): a confirmed booking was cancelled while its confirmation remained unsent; retry still attempted SMTP and incremented attempts. The reproduction deliberately used loopback port 1, not a real recipient/provider connection. A second code-confirmed mismatch was the reviews notification filter: SQL selected pending reviews while the dropdown attempted to select nonexistent status `new`, appearing as all statuses.

## Changes

- Migration v17 expands outbox statuses with `cancelled`, retaining all row data/IDs, explicit indexes/triggers, foreign keys and autoincrement sequence. Standard legacy CHECK constraints are rebuilt transactionally; unknown definitions fail rather than silently discarding data.
- Admin status changes cancel obsolete pending/failed confirmations within the booking transaction. Delivery rechecks booking state before SMTP claim; deleted bookings and expired send leases are covered. Manual retry cannot reactivate cancellation. Explicit reconfirmation can restore the same unsent unique event with current content. Sent records remain historical.
- Already active SMTP transmission cannot be recalled. Fresh leases stay `sending`, including when configuration has changed; this is not presented as successful recall. The worker/admin distinguish cancelled email from a failed delivery.
- Pending-review dropdown now reflects the actual pending query. Existing case-insensitive Cyrillic search is preserved and verified, not rewritten.

## Actual verification and inspected images

- `test:booking-mail-cancellation`: 21 assertions on legacy migration, row/body/ID/index/sequence preservation, foreign-key validity, repeat migration, retry, reconfirmation, stale/deleted bookings and active/expired leases. No SMTP connection made in this focused test.
- `tests/php_integrity_integration.php`: 229 assertions passed, with local fake SMTP success/failure/retry and lease concurrency. A temporary PHPRC was used so Windows child PHP processes load PDO SQLite; the initial environment-limited attempt was not accepted as a pass.
- `test:admin-booking-browser`: a private temporary DB/server and random temporary credentials; real admin/guest logins, pending-review query/selector, Cyrillic search, admin confirmation, guest badge, cancellation, outbox state and two durable status notifications passed. SMTP variables are removed from the test server environment. Fixture DB/credentials are deleted afterward.
- Inspected [reviews filter](reviews-filter.jpg), [confirmed admin](booking-confirmed-admin.jpg), [confirmed guest](booking-confirmed-guest.jpg), and [cancelled admin/mobile](booking-cancelled-admin-mobile.jpg). These are focused section captures, not a claim of a complete new admin visual-parity audit.
- PHP-mode `test:tokyo20` passed 21 detail routes and cart/interactive flows. Static export produced 37 pages; PHP/JS syntax and diff hygiene passed.

The admin is PHP-only. Public Pages contains the generated UI demo, not admin/PHP/SQLite files. These checks do not activate production email or prove deployment to a public PHP host.

## Publication

Implementation `9fb34fc` was pushed to main. [Actions 44489862510](https://github.com/altento/tokyo-premium/actions/runs/44489862510) passed build and Pages deployment, including the new migration/cancellation test in the fast profile. Optional full browser CI was skipped; authenticated PHP browser checks ran locally.

Fresh public Chromium loaded home/account with HTTP 200 and no page errors. Public `admin.php` and `schema.sql` returned 404 as expected for the static artifact. [Public verification](public-verification.json). No public PHP admin deployment is claimed.
