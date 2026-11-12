# Tokyo Premium 2.0 — verification record

Updated 2026-11-13

## Local verification completed

| Check | Result | Evidence / scope |
|---|---|---|
| Cart store | Passed, 12/12 | Input sanitization, old-format migration, quantity limits, cross-tab state, checkout lock/retry key, custom-set bounds and clear/re-add behavior. |
| PHP + SQLite integration | Passed, 102 assertions | Isolated database; six independent PHP processes race to initialize a clean SQLite file, WAL mode is verified, plus order price authority, idempotent and concurrent same-key orders, rollback, booking date/status, admin search/notifications, campaign targets, loyalty/order history, and Secret Menu route/API protection plus session unlock. The suite passed three consecutive runs after the CI-discovered locking fixes. |
| PHP syntax | Passed | All 25 PHP application and support files linted with PHP 8.4. |
| JavaScript syntax | Passed | Application JS and CJS regression files checked by Node. |
| Core browser regression | Passed | 11 page types, all 20 dish details (18 public; 2 after a server-validated Secret Menu unlock), cart/checkout, tabs, favorites, navigation, menu filters and six viewport widths. |
| Tokyo 2.0 browser regression | Passed in PHP and exported static mode | Offers/collection, Secret Menu unlock → cart, Omakase stage selection, set builder and shared cart, preference wizard, table-preference disclosure, certificate preview, quick view, profile demo labeling, and six viewport widths. Static-only Secret Menu data is explicitly client-side and cannot grant PHP access. |
| Review demo | Passed | Save, browser persistence, clear, XSS payload displayed as text, public aggregate unchanged. |
| Cart visual states | Passed | Empty/populated, desktop/mobile validation, pickup, delivery; three dishes and quantity two in the populated base scenario. |
| Responsive regression | Passed | 11 page types × 9 widths (320–1920 px), heading clipping checks, cart access, mobile menu keyboard/focus behavior and booking dates across browser time zones. |
| Gallery/profile visual state | Passed | Default gallery and static profile desktop/mobile captures. |
| Static export | Passed | Exporter generated 36 pages; locked Secret Menu detail routes are omitted, stale generated detail pages are removed, and the client-only preview payload is generated separately. No PHP/backend files are exported. |
| Diff hygiene | Pending | The complete 133-file feature commit and subsequent CI-fix patches passed staged `git diff --cached --check`; the WAL follow-up will be checked before its PR is opened. |

The Playwright evidence suite writes to `TOKYO_EVIDENCE_DIR` when set; by default it writes into `project-docs/visual-qa/`. This keeps the checks reproducible in restricted workspaces without changing what CI captures. Secret Menu unlock desktop/mobile captures are included alongside the route and checkout states.

## Visual inspection performed

Full-page browser captures were inspected for the populated cart on desktop and mobile, the Tokyo set builder, offers, static account/Privilege, and the 320 px booking heading. The single active campaign card was changed from a narrow third-column card into a centered editorial feature. The set in the cart uses a collage of the actual selected dish photos; it does not invent a final dish image.

The repository also contains route screenshots for the existing 11-page visual QA set and interaction captures. A fresh pixel-difference pass against every original design PNG was not completed in this cycle; refer to the older `final-acceptance-report.md` for earlier page-level findings. No claim of pixel-perfect matching is made.

The final booking regression additionally checks both initial and selected-table form messages, then changes date, time, guest count and zone in turn. Every change clears the preference; selecting again restores the confirmation copy in both the floorplan and booking form. The PHP and static selected-table captures were refreshed after that check.

Secret Menu visibility is enforced at the PHP query and order-validation layers. Anonymous visitors do not receive those dishes in the shared catalog, menu, details, set builder, recommendation wizard, seasonal collection page, or public offers API. A successful code unlock is recorded in the PHP session and rechecked against active campaign rules on subsequent pages and order quotes. Static GitHub Pages includes only a public browser-preview payload; it is intentionally not an authorization mechanism.

## CI follow-up

The first GitHub Pages workflow run installed dependencies and passed cart tests, then found that multiple PHP workers could repeat schema initialization, producing `database is locked` before order handling. A per-database initialization lock and migration fast path fixed that race. The next workflow reached the gift-order commit and exposed a second lock: a reader could block commit under SQLite's default rollback journal. `db()` now switches the database to WAL while initialization is serialized. The regression suite starts six independent PHP processes against a clean database and asserts WAL mode; locally it passed three consecutive runs (102 assertions each). The full browser suite passed before the WAL-only change; the current hosted run must still verify it before Pages is considered deployed.

Local `npm ci` did not finish in this Windows environment; browser suites used the already cached, pinned Playwright 1.65.0 package. The hosted workflow did complete `npm ci` successfully before reaching the database failure.

## Not production-certified

- No restaurant SMTP/provider credentials or email delivery adapter is configured; the admin notification center is in-app only.
- There is no payment provider, live inventory, table availability service, or production restaurant backend. A booking request is not automatically confirmed.
- GitHub Pages is a browser-local demonstration, not server authentication or durable shared storage.
- User, menu, price, contact and offer seed values are fictional educational data.
- The Pages deployment remains unverified until the current workflow completes successfully and the public URL is checked.
