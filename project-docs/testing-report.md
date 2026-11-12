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
| Responsive regression | Passed | 11 PHP page types × 14 widths (320–1920 px), plus static Pages header at 929/1024/1025 px; heading clipping checks, cart access, mobile/tablet menu keyboard behavior and booking dates across browser time zones. The 901–1023 px overflow band was reproduced on the public menu and fixed by moving the shared header/navigation breakpoint to 1024 px without changing the page-layout breakpoint. |
| Gallery/profile visual state | Passed | Default gallery and static profile desktop/mobile captures. |
| Static export | Passed | Exporter generated 36 pages; locked Secret Menu detail routes are omitted, stale generated detail pages are removed, and the client-only preview payload is generated separately. No PHP/backend files are exported. |
| Diff hygiene | Passed | Feature and SQLite follow-up patches passed staged diff checks; release-documentation changes are checked before commit. |

The Playwright evidence suite writes to `TOKYO_EVIDENCE_DIR` when set; by default it writes into `project-docs/visual-qa/`. This keeps the checks reproducible in restricted workspaces without changing what CI captures. Secret Menu unlock desktop/mobile captures are included alongside the route and checkout states.

## Visual inspection performed

Full-page browser captures were inspected for the populated cart on desktop and mobile, the Tokyo set builder, offers, static account/Privilege, and the 320 px booking heading. The single active campaign card was changed from a narrow third-column card into a centered editorial feature. The set in the cart uses a collage of the actual selected dish photos; it does not invent a final dish image.

The repository also contains route screenshots for the existing 11-page visual QA set and interaction captures. A fresh pixel-difference pass against every original design PNG was not completed in this cycle; refer to the older `final-acceptance-report.md` for earlier page-level findings. No claim of pixel-perfect matching is made.

The 929 px navigation breakpoint was also visually checked on the exported menu page. The closed and keyboard-open states are saved as `visual-qa/header-breakpoint-929.png` and `visual-qa/header-breakpoint-929-menu-open.png`; they show the cart retained in the header, booking/account links in the menu, and no horizontal page overflow. This targeted check does not replace the outstanding full comparison against all 11 design references.

The final booking regression additionally checks both initial and selected-table form messages, then changes date, time, guest count and zone in turn. Every change clears the preference; selecting again restores the confirmation copy in both the floorplan and booking form. The PHP and static selected-table captures were refreshed after that check.

Secret Menu visibility is enforced at the PHP query and order-validation layers. Anonymous visitors do not receive those dishes in the shared catalog, menu, details, set builder, recommendation wizard, seasonal collection page, or public offers API. A successful code unlock is recorded in the PHP session and rechecked against active campaign rules on subsequent pages and order quotes. Static GitHub Pages includes only a public browser-preview payload; it is intentionally not an authorization mechanism.

## Final CI and public-site verification (2026-11-13)

The hosted [Pages workflow run 43312293771](https://github.com/altento/tokyo-premium/actions/runs/43312293771), for commit d1bc5543d67ef04c8353d713eca1b0b3f7eea9e9, completed successfully. Both build and deploy jobs passed. The build installed dependencies, ran cart and PHP/SQLite integrity checks, PHP syntax checks, form/admin smoke checks, exported the static demo, ran browser regression and visual QA, and uploaded the screenshot artifact.

After deployment, the public Pages site was opened with a cache-busting URL. Browser fetch checks returned HTTP 200 for 15 routes: home, menu, Omakase, offers, philosophy, gallery, reviews, contacts, booking, Perfect Evening, set builder, account, cart, Autumn in Kyoto collection, and gift certificates. The Offers page and its images loaded without failed same-origin requests; the browser recorded zero console errors and zero warnings. At 320 px and 390 px viewport widths, the Offers heading remained inside the viewport and there was no horizontal overflow.

The local PHP/SQLite integration suite passed 102 assertions in three consecutive runs. Hosted CI also passed the integration suite under its multi-worker test configuration. Cart, Tokyo 2.0, review, responsive, and visual-state Playwright suites passed in the hosted build.

A fresh pixel-by-pixel comparison of every current page against all 11 source PNG references remains incomplete. Screenshots and older visual gap notes remain in visual-qa/ and final-acceptance-report.md. No pixel-perfect acceptance is claimed.

Local `npm ci` and the pinned Playwright 1.65.0 Chromium installation completed successfully in this Windows environment. The responsive regression passed locally against PHP and the freshly exported Pages build after the intermediate-width header fix. Hosted CI previously completed the full suite; the current code change still awaits its post-merge workflow and deployment check.

## Not production-certified

- No restaurant SMTP/provider credentials or email delivery adapter is configured; the admin notification center is in-app only.
- No payment provider, live inventory, live table availability service, or production restaurant backend is configured. A booking request is not automatically confirmed.
- GitHub Pages is a browser-local demonstration, not server authentication or durable shared storage.
- User, menu, price, contact and offer seed values are fictional educational data.
- A new full pixel-by-pixel pass across all 11 design references remains outstanding.
