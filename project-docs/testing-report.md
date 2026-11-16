# Tokyo Premium 2.0 — verification record

## 2026-11-16 route-specific social previews

PHP routes and the checked-in GitHub Pages export now emit route-specific Open Graph and Twitter preview images. The existing 11-route static visual regression asserts both metadata values for every route and passed in static mode. PHP syntax and JavaScript syntax checks passed. PHP rendering/export could not be run locally because this PHP CLI lacks `pdo_sqlite`; hosted CI and public deployment remain unverified for this patch.

Updated 2026-11-13

## Local verification completed

| Check | Result | Evidence / scope |
|---|---|---|
| Cart store | Passed, 12/12 | Input sanitization, old-format migration, quantity limits, cross-tab state, checkout lock/retry key, custom-set bounds and clear/re-add behavior. |
| PHP + SQLite integration | Passed, 127 assertions locally | Isolated database; six independent PHP processes race to initialize a clean SQLite file, WAL mode is verified, plus order price authority, idempotent and concurrent same-key orders, rollback, booking date/status, admin search/notifications, campaign targets, loyalty/order history, Secret Menu route/API protection plus session unlock, server-validated custom-set pricing/composition, and email outbox queuing, fake-SMTP acceptance/rejection/retry. No external SMTP host is contacted by tests. |
| PHP syntax | Passed | All 25 PHP application and support files linted with PHP 8.4. |
| JavaScript syntax | Passed | Application JS and CJS regression files checked by Node. |
| Core browser regression | Passed | 11 page types, all 20 dish details (18 public; 2 after a server-validated Secret Menu unlock), cart/checkout, tabs, favorites, navigation, menu filters and six viewport widths. |
| Tokyo 2.0 browser regression | Passed in PHP and exported static mode | Offers/collection, Secret Menu unlock → cart, Omakase stage selection, set builder and shared cart, preference wizard, table-preference disclosure, certificate preview, quick view, profile demo labeling, and six viewport widths. Static-only Secret Menu data is explicitly client-side and cannot grant PHP access. |
| Review demo | Passed | Save, browser persistence, clear, XSS payload displayed as text, public aggregate unchanged. |
| Cart visual states | Passed | Empty/populated, desktop/mobile validation, pickup, delivery; three dishes and quantity two in the populated base scenario. |
| Responsive regression | Passed | 11 PHP page types × 14 widths (320–1920 px), plus static Pages header at 929/1024/1025 px; heading clipping checks, cart access, mobile/tablet menu keyboard behavior and booking dates across browser time zones. The 901–1023 px overflow band was reproduced on the public menu and fixed by moving the shared header/navigation breakpoint to 1024 px without changing the page-layout breakpoint. |
| Gallery/profile visual state | Passed | Default gallery and static profile desktop/mobile captures. |
| Booking email follow-up | Passed locally | Optional SMTP adapter and SQLite outbox exercised with a loopback fake SMTP server for accepted mail, 550 rejection, and retry. No external provider is configured or contacted; the public Pages deployment remains static and does not send email. |
| Visual fidelity follow-up | Passed | New `tests/visual-fidelity-regression.cjs` checks seven routes in PHP and static mode; unique home/menu heroes, all four visible home recommendations after scrolling, philosophy hero image, eight non-clipped gallery tiles, five catalog-backed Omakase overview photos, SVG preference floorplan, fictional map disclosure, image loading, mobile title geometry and no console errors. Captured 14 PHP desktop/mobile full-page screenshots. |
| Cinematic home hero | Passed locally in PHP and static export | `test:motion` verifies three manual scenes, image/alt/copy/CTA synchronization, PHP-to-static route mapping, arrows and dots, keyboard control, rapid input queuing, no autoplay, reduced motion, no overflow and title/control bounds at 320, 360, 390, 430, 768, 1024, 1440 and 1920 px. Captures default and Omakase scenes on desktop/mobile. |
| Static export | Passed | Exporter generated 36 pages; locked Secret Menu detail routes are omitted, stale generated detail pages are removed, and the client-only preview payload is generated separately. No PHP/backend files are exported. |
| Diff hygiene | Passed | Feature and SQLite follow-up patches passed staged diff checks; release-documentation changes are checked before commit. |

The Playwright evidence suite writes to `TOKYO_EVIDENCE_DIR` when set; by default it writes into `project-docs/visual-qa/`. This keeps the checks reproducible in restricted workspaces without changing what CI captures. Secret Menu unlock desktop/mobile captures are included alongside the route and checkout states.

## 2026-11-16 focused product-pairing check

For the catalogue-pairing change, only the affected dish route and its PHP/schema syntax were checked. PHP lint, JS syntax, and diff hygiene passed. A Python SQLite check executed the canonical schema and seed, verified 40 pairings, and found no foreign-key violations. The static salmon-nigiri page showed four recommendations at desktop and mobile widths; configured yuzu and tuna pairings were present, add-to-order updated the shared count, and no browser errors or horizontal overflow were observed. The machine's PHP build has no `pdo_sqlite`, so `php_integrity_integration.php` was not run. No broad regression, hosted CI, or public deployment was repeated.

The same focused change adds HTTP regression assertions for admin create/reorder, type edit, self-pair rejection and deletion. Those assertions are included in `php_integrity_integration.php` but remain unexecuted locally because the PHP SQLite PDO driver is unavailable.

The Secret Menu follow-up adds focused assertions for rule creation/editing, automatic promo-code provisioning, the resulting server unlock decision, schedule enforcement (unscheduled, future-start and expired rules), date validation and disabling. They are included in the integration file but were not executed locally because `pdo_sqlite` is unavailable. PHP syntax lint is the local verification for those handlers.

For the static account empty-state improvement, one focused Chromium run checked the menu, booking and favorites empty-state links at desktop and mobile widths. Targets were correct, page width matched the 390 px viewport, and no page errors occurred. The full responsive, account, PHP integration and hosted suites were not rerun.

## Visual inspection performed

Full-page browser captures were inspected for the populated cart on desktop and mobile, the Tokyo set builder, offers, static account/Privilege, and the 320 px booking heading. The single active campaign card was changed from a narrow third-column card into a centered editorial feature. The set in the cart uses a collage of the actual selected dish photos; it does not invent a final dish image.

The repository also contains route screenshots for the existing 11-page visual QA set and interaction captures. A fresh pixel-difference pass against every original design PNG was not completed in this cycle; refer to the older `final-acceptance-report.md` for earlier page-level findings. No claim of pixel-perfect matching is made.

The 929 px navigation breakpoint was also visually checked on the exported menu page. The closed and keyboard-open states are saved as `visual-qa/header-breakpoint-929.png` and `visual-qa/header-breakpoint-929-menu-open.png`; they show the cart retained in the header, booking/account links in the menu, and no horizontal page overflow. This targeted check does not replace the outstanding full comparison against all 11 design references.

The final booking regression additionally checks both initial and selected-table form messages, then changes date, time, guest count and zone in turn. Every change clears the preference; selecting again restores the confirmation copy in both the floorplan and booking form. The PHP and static selected-table captures were refreshed after that check.

Secret Menu visibility is enforced at the PHP query and order-validation layers. Anonymous visitors do not receive those dishes in the shared catalog, menu, details, set builder, recommendation wizard, seasonal collection page, or public offers API. A successful code unlock is recorded in the PHP session and rechecked against active campaign rules on subsequent pages and order quotes. Static GitHub Pages includes only a public browser-preview payload; it is intentionally not an authorization mechanism.

## Final CI and public-site verification (2026-11-13)

The hosted [Pages workflow run 43312293771](https://github.com/altento/tokyo-premium/actions/runs/43312293771), for commit d1bc5543d67ef04c8353d713eca1b0b3f7eea9e9, completed successfully. Both build and deploy jobs passed. The build installed dependencies, ran cart and PHP/SQLite integrity checks, PHP syntax checks, form/admin smoke checks, exported the static demo, ran browser regression and visual QA, and uploaded the screenshot artifact.

After deployment, the public Pages site was opened with a cache-busting URL. Browser fetch checks returned HTTP 200 for 15 routes: home, menu, Omakase, offers, philosophy, gallery, reviews, contacts, booking, Perfect Evening, set builder, account, cart, Autumn in Kyoto collection, and gift certificates. The Offers page and its images loaded without failed same-origin requests; the browser recorded zero console errors and zero warnings. At 320 px and 390 px viewport widths, the Offers heading remained inside the viewport and there was no horizontal overflow.

The local PHP/SQLite integration suite now passes 114 assertions; hosted CI run 43341069860 passed the expanded suite under its multi-worker test configuration. Cart, Tokyo 2.0, review, responsive, and visual-state Playwright suites passed in the hosted build.

The intermediate-width header correction was merged as `9599f3c4996e1eeb43948a1dba80c3bc62f06e29`. Follow-up [Pages workflow run 43320764592](https://github.com/altento/tokyo-premium/actions/runs/43320764592) completed with both build and deploy successful, including the expanded responsive regression. A fresh Playwright check of the deployed menu returned HTTP 200 at 929 px and 1025 px: 929 px had no horizontal overflow, retained the header cart, exposed booking and account links in the keyboard-operable menu, and closed on Escape; 1025 px restored the desktop navigation and booking CTA with no overflow. No failed same-origin requests or console errors were observed.

A fresh pixel-by-pixel comparison of every current page against all 11 source PNG references remains incomplete. Screenshots and older visual gap notes remain in visual-qa/ and final-acceptance-report.md. No pixel-perfect acceptance is claimed.

## Cinematic home follow-up (2026-11-13)

The home hero now has three manually operated scenes: salmon nigiri, the seasonal Omakase table, and chef craft. Arrows, scene dots and left/right keyboard input update the photograph, alternative text, headings, lead copy and both actions together. No automatic rotation, video or audio was added. Pointer parallax runs only on fine-pointer devices; `prefers-reduced-motion` disables it and transition effects. Static links map to the exported `.html` routes.

Local Playwright passed `test:motion` in PHP and the exported static build, including eight viewport widths and reduced-motion contexts. The screenshots and the rest of the current regression pass are indexed in `visual-qa/README.md`. Hosted CI and public Pages verification for this focused motion follow-up remain pending until its PR is pushed and deployed.

### Additional current-source visual check (2026-11-13)

Run with a local PHP + SQLite server and exported static build:

```powershell
$env:TOKYO_BASE_URL='http://127.0.0.1:8085'
$env:TOKYO_STATIC_BASE_URL='http://127.0.0.1:8085/docs'
npm run test:visual-fidelity
```

The run passed seven routes in both PHP and static mode and saved 14 PHP screenshots under `visual-qa/2.0-visual-fidelity/`. It tests image uniqueness/loading, reveal-triggered recommendations, the philosophy background, gallery bounds, Omakase catalog photos, booking SVG and disclosure, contact-map disclosure, responsive title bounds, and JavaScript/console errors. It does not assert pixel-level similarity for all 11 reference designs.

Local `npm ci` and the pinned Playwright 1.65.0 Chromium installation completed successfully in this Windows environment. The responsive regression passed locally against PHP and the freshly exported Pages build after the intermediate-width header fix. The post-merge CI and public deployment check are complete for commit `9599f3c`.

## Tokyo 2.0 visual release and custom-set integrity follow-up — 2026-11-13

- PR [#16](https://github.com/altento/tokyo-premium/pull/16) merged at `4256718a68f8016ff7e9b5dafb0ffa4d33a917c3`. Pull-request workflow [43335988363](https://github.com/altento/tokyo-premium/actions/runs/43335988363) passed, including the seven-route PHP/static visual-fidelity regression. Main deployment [43336522224](https://github.com/altento/tokyo-premium/actions/runs/43336522224) completed successfully.
- Public Chromium check with a commit cache-buster passed on home, menu, Omakase, offers, Perfect Evening, set builder, booking and account. The distinct home hero returned HTTP 200 and loaded; desktop 1440 px and mobile 390 px had no horizontal overflow; the mobile heading stayed in the viewport; there were no console or page errors. Public desktop/mobile full-page home screenshots are saved in `visual-qa/2.0-visual-fidelity/` and were inspected after natural scroll through the page.
- The live `offers.html` route rendered correctly; one early assertion was aimed at an absent optional data attribute, so it was replaced by a route heading check. This was a test-selector mismatch, not a page failure.
- **Local follow-up, not yet hosted:** `tests/php_integrity_integration.php` now adds custom-set threat cases (forged client prices, unavailable dish, inactive/unknown variant, duplicate IDs, too few dishes, and excessive quantity), then checks persisted server-price/component snapshots and no order on invalid checkout. PHP/SQLite integration passed 114 assertions. The added assertions are queued for a subsequent PR and have not yet run in CI.
- Scope remains explicit: the release is a focused visual pass; a fresh pixel-level comparison across all 11 references, focused birthday/referral tests, and the coordinated cinematic scroll-story remain incomplete.

## Follow-up visual and dashboard pass (2026-11-13)

The latest local pass reproduced and fixed three presentation defects against the current PHP routes and static export:

- Menu pagination now shows all six cards on the current page immediately. At 1440 px it forms a 3×2 grid, at 390 px a 2×3 grid, and at 320 px a single column. Favorite controls sit on the photo; “Подробнее” and quick view have separate hit areas. The dedicated menu check loaded all six photos after normal lazy loading and found no hidden rows, overlap, overflow or console errors.
- Dish detail recommendations now prefer active items in the same category. Recommendation cards no longer depend on the scroll reveal observer, so they appear in a full-page capture and on a fast first visit.
- Omakase stages now use active catalog records and their real demo descriptions, photos, portions and prices. Stage 2 switches to the matching tuna nigiri entry and its image. The selected package still transfers to the booking form. The first stage and recommendations no longer depend on scroll-only reveal behavior.
- PHP and static account captures were reviewed. The authenticated PHP dashboard now prioritizes account-owned orders/bookings, separately labeled browser-local favorites, Tokyo Privilege balance, referrals and settings. Query inspection confirmed user scoping for orders, order items, bookings, profile, loyalty, referrals and personal offers. An authenticated mobile capture includes saved demo orders and a local favorite; the anonymous login/register state remains separate.

Local checks against the current source and a freshly generated 36-page static export passed: `test:cart` (12/12), `test:browser` (11 routes, 20 dish details, cart and account flows, including authenticated populated desktop/mobile dashboard captures), `test:tokyo20` (PHP + static; now checks catalog photos and visible recommendations), `test:reviews`, `test:cart-visual` (8 states), `test:responsive` (11 route types × 14 widths plus static header breakpoints), `test:visual-states`, and 102 PHP/SQLite integration assertions. `menu-visual-regression.cjs`, PHP lint for changed routes, JavaScript syntax checks and `git diff --check` passed.

New evidence is indexed in `visual-qa/README.md`: menu 1440/390/320 initial states, dish detail desktop/mobile, Omakase desktop/mobile and stage 2 in PHP/static modes, and authenticated populated account desktop/mobile. The complete pixel-by-pixel comparison of every current page against all 11 original PNGs remains incomplete; these focused fixes do not establish full visual acceptance. This follow-up was merged as `64061e4ff3f4c8fb4029e6ba95e3a5cce58444b8`; hosted Pages workflow run [43329426971](https://github.com/altento/tokyo-premium/actions/runs/43329426971) passed build, browser QA and deploy. A cache-busted Playwright check of the public site returned HTTP 200 for home, menu, Omakase, cart and account; all inspected images decoded, stage 2 displayed “Нигири с тунцом”, the 390 px menu had no horizontal overflow, and no console/page errors were observed.

## Not production-certified

- The optional SMTP delivery adapter and email queue are implemented, but no restaurant SMTP/provider credentials are configured. The admin shows message state and retry controls; external delivery remains unverified.
- No payment provider, live inventory, live table availability service, or production restaurant backend is configured. A booking request is not automatically confirmed.
- GitHub Pages is a browser-local demonstration, not server authentication or durable shared storage.
- User, menu, price, contact and offer seed values are fictional educational data.
- A new full pixel-by-pixel pass across all 11 design references remains outstanding.

## Booking email and migration follow-up — 2026-11-13

- Reproduced an upgrade defect on an existing v6 SQLite file: bootstrap skipped the v7 migration when `api_order_requests` existed. The readiness check now also requires migration 7, `api_booking_requests`, and `booking_email_rate_limits`. An isolated v6 fixture upgrades through bootstrap successfully.
- `php tests/php_integrity_integration.php`: **131 assertions passed**. Coverage includes migration v6→v7, same-key bookings, recipient throttling, SMTP success/failure/retry, and two competing SMTP workers using a gated local fake server. The Windows PHP built-in server serializes HTTP requests; actual multiworker HTTP contention remains for Linux CI.
- `npm run test:browser`: **passed** against local PHP + Pages: 11 routes, all 20 dish details, cart/checkout flows, and six viewport widths. A repeat booking reuses the same idempotency key after a recoverable API response.
- PHP 8.4 lint passed for the changed bootstrap, API, mail adapter and integration fixtures; `git diff --check` passed. SMTP credentials are not configured, so delivery to a real mailbox was not tested.
- Browser screenshots from this run were written to a temporary evidence directory because these backend changes did not alter layout. Existing visual captures remain in `visual-qa/`; this update does not claim a new 11-reference pixel comparison.

## Homepage scroll-story follow-up — 2026-11-13

Added a single requestAnimationFrame-throttled progress line, one-time chapter reveals and restrained active-chapter accents across the homepage story. Reduced-motion users receive all content immediately with transitions disabled. Static output was regenerated from the PHP routes, and the focused `npm run test:motion` run passed in PHP and Pages modes, including progress updates, chapter activation, all reveals, keyboard controls, reduced motion, no autoplay and eight viewport widths. Four updated full-page captures are saved under `visual-qa/2.0-motion/story-*`. The 11-design pixel comparison remains incomplete.

## Referral and birthday integration follow-up — 2026-11-13

The PHP/SQLite integration suite now passes 150 assertions. New coverage verifies registration via an invite link, explicit attachment, retry idempotency, multiple invitees per personal code, self-invite rejection, inviter replacement rejection, completed-order rewards, duplicate completion, cancellation reversal and re-qualification. Birthday checks cover invalid dates, optional consent, eligibility windows, consent withdrawal, configured points and no repeat grant on account reload. `php -l` passed on the changed PHP files, `node --check tokyo20.js` passed, and `git diff --check` was clean. Browser suites were not repeated because this change affects PHP account/referral logic; the prior focused cinematic-motion browser run remains the relevant visual check.

The suite uses a disposable SQLite database. The earlier checks exposed stale read snapshots in the expanded integration fixture; the fixture now uses fresh connections across concurrent HTTP writes. The application flow itself passed after that isolation fix.

## Homepage GSAP integration — 2026-11-16

- Added assertions to `test:motion` that verify ScrollTrigger is active in PHP and static builds and absent when reduced motion is requested.
- With `TOKYO_BASE_URL=http://127.0.0.1:8100` and `TOKYO_STATIC_BASE_URL=http://127.0.0.1:8100/docs`, `npm run test:motion` passed. It covers three hero scenes, keyboard controls, chapter state, reading progress, reveals, eight viewport widths, reduced motion and browser errors.
- `npm run test:visual-fidelity` against the same PHP server and static export passed for seven route families in both modes.
- PHP 8.4 lint passed for `index.php`; `tools/export-pages.php` generated 36 static pages and copied the vendored GSAP files. `git diff --check` passed.
- Full-page and hero screenshots were generated in `visual-qa/2.0-motion/` and `visual-qa/2.0-visual-fidelity/`. Homepage desktop/mobile PHP captures were visually inspected against `01-home.png`; this focused motion update does not close the 11-reference pixel comparison.
- This is local evidence only. No CI run or public Pages deployment was performed for this revision.
# Focused follow-up — 2026-11-16

- Administrative loyalty debit race: `tests/loyalty-adjustment-atomicity.php` passed 6 assertions. Two concurrent 80-point deductions against 100 points produced exactly one accepted ledger entry and a balance of 20; overdraft rejection left the balance unchanged; a separate credit succeeded.
- PHP lint passed for `lib/commerce.php`, `account.php`, and the focused regression script under PHP 8.4 with `pdo_sqlite` enabled.
- Account layout was browser-captured once at desktop 1440 px and mobile 390 px with a synthetic order. Mobile scroll width was 390 px, the page heading remained inside the viewport, and the browser recorded no page errors. Screenshots are indexed in `visual-qa/README.md`.
- The broad browser, responsive and PHP integration suites were not rerun in this focused cycle.

## Personal offers follow-up — 2026-11-16

`tests/personal-offer-regression.php` passed 10 isolated SQLite assertions, including finding an account older than the latest-200 list by email, server-priced personal-code use, duplicate-free reassignment, expiry validation, eligibility expiry, revocation, and rejection of non-personal or code-less campaigns. PHP 8.4 lint passed for the changed account, offers, cart, commerce, admin, and test PHP files; Node syntax checks passed for both source and static `tokyo20.js`. A local HTTP smoke check confirmed the cart route pre-fills a valid promo query at port 8100. Route assertions for account search by email, private-code visibility, account copy/checkout links, and the public offers page are in the broader integration suite but were not run in this shortened cycle. No browser capture, full regression suite, CI, or Pages deployment is claimed.

## Gift quantity follow-up — 2026-11-16

Added the focused `php tests/gift-quantity-regression.php` check for eligible quantity 2, rejection above the configured cap, zero and insufficient stock, persisted order-line quantity, transactional stock decrement, and the legacy default of one. It passed 9 assertions using PHP 8.5.11 with the installed SQLite extensions loaded explicitly. PHP lint for all changed PHP files, JS syntax checks, source/static CSS/JS parity and `git diff --check` passed. The broader PHP, browser and Pages suites were not run for this change.

## Omakase spacing and gift API assertions — 2026-11-16

- Tightened Omakase course and set-card spacing in `tokyo20.css`; `docs/tokyo20.css` is byte-for-byte synchronized. A focused static-browser check confirmed the course tabs still switch, compact desktop spacing is applied, and the 390 px layout has no horizontal overflow. This was not a full-page visual comparison against `04-omakase.png`.
- Extended `tests/php_integrity_integration.php` with API assertions for quoted gift quantity/limits, rejecting quantities above the cap, persisted order quantity, stock decrement, and rejecting a request exceeding remaining stock. `php -l` passed; the integration suite was not run in this shortened cycle.
- No broad PHP, browser, responsive, CI, or Pages deployment checks were run. The already-recorded nine-assertion isolated gift regression remains the latest executed PHP coverage for quantity handling.

## Set-builder draft persistence — 2026-11-16

The set builder now saves only selected dish IDs and quantities in browser-local storage, separately per set format. On reload it validates the draft against the current format's dish list and quantity limits; the displayed estimate is recalculated from the current catalog. An accepted add-to-cart event now returns a result to the builder, which clears the draft and selection only after the shared cart confirms acceptance. Failed adds retain the draft. `npm run test:set-draft` passed 16 focused browser assertions across local PHP and static Pages modes (save, reload, restore, transfer, clear). Node syntax checks and `git diff --check` passed. No broad suite, CI, or public deployment was run.

## Omakase visual review — 2026-11-16

The local PHP Omakase route was captured at 1440 and 390 CSS px, after scrolling to trigger all image reveals, and inspected beside `design-references/04-omakase.png`. The five course photos load and the mobile document remains 390 px wide. The current editorial order and hero framing differ from the mockup; this is a focused page review only, not pixel equivalence. Captures and comparison notes are in `project-docs/visual-qa/`.

## Account saved-address follow-up — 2026-11-16

PHP accounts now support adding, editing, selecting a default, and deleting up to ten saved delivery addresses. Migration 8 adds an account-owned table without changing existing user data; ownership is checked in every read/write operation, and a partial unique index plus transactions keep one default per account. Authenticated checkout pre-fills the default address and lets the guest choose another saved address; switching to pickup clears and disables delivery fields, and delivery restores required validation. The Pages demo offers the same convenience using only its local browser storage. `tests/saved-address-regression.php` passed 12 isolated SQLite assertions, including a v6-to-v8 upgrade. `npm run test:saved-address-browser` passed a focused PHP and Pages browser flow for registration, address CRUD, default switching/fallback, checkout prefill and fulfillment switching, and a 390px viewport with no horizontal overflow. PHP lint, Node syntax, source/static parity, and `git diff --check` passed. The broad integration, browser, CI, and public deployment suites were not rerun in this focused cycle.

## Admin order-status side effects — 2026-11-16

Confirmed that the admin's generic status update bypassed `commerce_set_order_status()`, so completing or cancelling an order could skip its transactional loyalty/referral events. Order status changes now use the shared commerce transition function; non-order records keep their existing status path. `tests/admin-order-status-regression.php` passed 6 focused assertions covering completion, duplicate completion, cancellation/reversal, duplicate cancellation, and invalid status. The personal-offer regression was extended to cover route authorization/CSRF, account isolation, missing owners, and rollback; it passed 16 assertions. These focused PHP checks and syntax validation passed locally. Broad suites, CI, public deployment, and live email delivery were not rerun or verified in this cycle.

## Menu-focused visual follow-up — 2026-11-16

`npm run test:menu-visual` passed four focused Playwright cases against local PHP and the freshly exported Pages build at 1440×1000 and 390×844 CSS px. Both modes returned HTTP 200, loaded the new hero and two story photos, showed the breadcrumb and six visible first-page dishes, linked to the correct set builder route, and had no horizontal overflow or browser console/page errors. The hero measured 432 px on desktop and 370 px on mobile. Full-page captures are indexed in `project-docs/visual-qa/README.md`.

The static exporter generated 36 pages before the browser check. The changed page was visually inspected beside `design-references/02-menu.png`; this does not complete the outstanding 11-page pixel comparison. No broad suite, CI run, GitHub Pages deployment, or public-site check was repeated in this shortened cycle.

## 2026-11-16 dish-detail focused regression

The visual-fidelity browser regression was run only for dish, in PHP and freshly exported static mode, on desktop and mobile. It passed the one-title, loaded-image, editorial-photo and add-to-cart assertions, then saved four full-page screenshots under project-docs/visual-qa/. The static exporter produced 36 pages. PHP syntax for pages.php, JavaScript syntax for the browser test, and git diff --check passed.

This accelerated pass did not rerun the broad browser, responsive or PHP/SQLite suites, hosted CI, or public deployment checks. It does not claim that all twenty product routes or all eleven design references have now been visually reviewed.

## 2026-11-16 Omakase mobile selector

A single focused visual-fidelity run checked the Omakase route in PHP and static mode at desktop and 390 px mobile. It passed with all five stage tabs inside the selector bounds, no tab-strip horizontal scrolling, no page overflow, and the existing five-course/image checks intact. The four full-page screenshots were reviewed. No broad suite, CI, or Pages deployment was run.

## 2026-11-16 Omakase hero follow-up

One focused visual-fidelity run passed for the Omakase route in PHP and exported static mode at desktop and 390 px mobile. It verifies the hero uses its Omakase-only mirrored composition, preserves the five course photos, exposes all five mobile stage tabs without horizontal scrolling, and has no page overflow or browser errors. Four updated full-page captures were visually inspected. No full suite, CI run, or Pages deployment was performed.

## 2026-11-16 accelerated follow-up

- Added per-tier editing in `/admin-marketing.php`; the focused `tests/loyalty-tier-admin-regression.php` passed 8 assertions for authorized update, CSRF, bounded values, stable slug, and rollback. PHP lint and diff hygiene passed.
- Added an optional native cross-document View Transition fade, enabled only when `prefers-reduced-motion: no-preference`; browsers without support keep regular navigation. The focused `npm run test:motion` passed in PHP and exported Pages mode, including the new opt-in and reduced-motion assertions plus the existing hero/story checks at eight widths.
- This accelerated pass used focused tests only. It did not run the full regression matrix, hosted CI, or a new public deployment check.

## 2026-11-16 account and reviews visual follow-up

- The account-only PHP/static browser run captured authenticated desktop and mobile states, checked the PHP hero and heading/page bounds at 390 px, seeded a local static demo order/booking, and reported no JavaScript errors. The PHP fixture had no persisted orders/bookings, so its empty history is genuine for that isolated database.
- The reviews-only PHP/static desktop/mobile browser run verified HTTP routes, four demo review cards, cuisine filtering, sort control, title bounds, 370 px hero height and absence of page errors/overflow. A narrow mobile follow-up verified that all five category filters are visible at 390 px and that Omakase filtering returns the expected card.
- `php -l account.php`, `php -l reviews.php`, and `git diff --check` passed. Broad suites, CI and public Pages deployment were not run for these visual-only changes.

## 2026-11-16 cart layout follow-up

The cart-only static browser regression passed and saved eight states after recommendations were moved below the order/checkout grid. The populated case contains three catalog items with one quantity of two and a 12,700 ₸ subtotal; empty, pickup, delivery and validation states were also refreshed. The PHP template passed syntax lint, and `premium.css` is synchronized with `docs/premium.css`. PHP browser rendering was not verified in this runtime because the active PHP executable reports `could not find driver` for PDO SQLite. No broad suite, hosted CI or Pages deployment was run.

## 2026-11-16 philosophy hero follow-up

One focused static Playwright route check passed at 320, 390, 768 and 1440 px: the title remains in bounds, the decorative mark avoids the title/actions, section links resolve, and the page has no horizontal overflow. HTTP 200, no console errors, no failed resources; the desktop capture decoded all four content images. `php -l about.php`, CSS hash comparison and `git diff --check` passed. PHP browser rendering, broad suites, hosted CI and public Pages were not checked.

## 2026-11-16 booking hero copy follow-up

One focused static route check passed at 320, 390 and 1440 px. It verified the title, subtitle, description and CTA remain inside the viewport, the booking anchor is present, both images load, and there are no console errors, failed resources or horizontal overflow. `php -l pages.php` and `git diff --check` passed. PHP browser rendering, broad suites, hosted CI and public Pages were not checked.

## 2026-11-16 space gallery hero follow-up

One focused static route check passed at 1440 px and 320 px. It verified the title fits its copy area, the desktop title stays single-line, the gallery anchor exists, and the page has no horizontal overflow, console/page errors or failed requests. Both full-page captures were visually inspected against `design-references/06-gallery.png`. `php -l pages.php`, equality of `premium.css` and `docs/premium.css`, and `git diff --check` passed. PHP browser rendering, the broad suite, hosted CI and public Pages were not checked.

## 2026-11-16 space-zone content follow-up

One static route check passed at 1440 px and 390 px. It verified the four reference-aligned zone names, four distinct descriptions, HTTP 200, no browser errors, and no horizontal overflow. Full-page screenshots were visually inspected. This was a content-only follow-up; no broad suite, PHP browser render, CI run, or deployment was repeated.

## 2026-11-16 Omakase style delivery fix

One static Omakase route check passed at 1440 px and 390 px. It verified that `luxury-refinements.css` returns HTTP 200, the hero image receives its horizontal mirror transform, all five tabs fit at mobile width, and the document has no browser errors or horizontal overflow. `php -l bootstrap.php` passed. No broad suite, full Pages re-export, PHP browser render, hosted CI or deployment was run.

## 2026-11-16 Omakase compact hero

`omakase-compact-hero-2026-11-16/` contains desktop and 320 px full-page captures, focused hero captures, and a comparison against `design-references/04-omakase.png`. The desktop hero is 460 px high and the 320 px hero is 449 px; title/copy bounds fit, five course tabs remain visible, the mirrored chef image is active, and a focused static-browser check reported HTTP 200 with no errors, failed requests, or horizontal overflow. The complete lower page remains an adaptation of the concept. PHP browser rendering, a full export, broad suite, hosted CI, and public deployment were not run.

## 2026-11-16 Omakase current selector check

`npm run test:visual-fidelity` passed with `TOKYO_VISUAL_ROUTES=omakase` and `TOKYO_VISUAL_MODE=static`. It validated the exported static page at 1440 px and 390 px, all five distinct course previews, stage-panel selection, ArrowRight roving focus, centered fifth mobile preview, no horizontal overflow, and no console/page errors. Fresh full-page captures are in `visual-qa/omakase-current-2026-11-16/`. The source and exported `tokyo20.css` files match. PHP browser mode is unverified locally because `php -S` returned PHP source as `application/octet-stream`; no full suite, hosted CI, or deployment was run for this focused pass.

## 2026-11-16 static account refinement delivery

The custom Pages account template had omitted `luxury-refinements.css`; the exporter now includes it and the Pages workflow checks the generated account HTML for the link. The checked-in static route mirrors the template fix. One focused local browser check passed at 1440 px and 390 px: stylesheet loaded, overview uses two columns on desktop and one on mobile, no horizontal overflow, console errors, or failed requests. Captures and a reference comparison are in `visual-qa/account-refinement-static-2026-11-16/`. This is a focused account styling fix; full profile fidelity, PHP rendering, hosted CI, and public deployment were not verified.

## 2026-11-16 menu spacing and visual comparison

`menu-visual-follow-up-2026-11-16/` contains eager-loaded 1440 px and 390 px captures compared against `02-menu.png`. The first full-page capture showed blank lower-card images because the manual screenshot did not scroll to trigger native lazy loading; the replacement capture explicitly decoded all 22 images before taking the screenshot. No asset was missing. Menu-section bottom padding is now limited to 58 px desktop / 32 px mobile. The focused static route had HTTP 200, no overflow, browser errors, or HTTP failures. PHP rendering, full interaction regression, CI, and Pages deployment were not checked.

Follow-up browser check: the category rail was captured at 1440 px and 390 px, has no zero-result filter, and displays the set-builder shortcut within the initial viewport at both widths. The 390 px flow followed the shortcut to `set-builder.html` successfully with no page or console errors. The strip itself remains horizontally scrollable by design. No broad suite or PHP browser render was repeated.

## Home booking progressive step — 2026-11-16

The homepage now starts with date, time, guest count and preferred zone; contact fields appear after the visitor submits those choices. The first step never saves or sends a booking. On the second step, the existing PHP/API or browser-local demo handler receives the same required contact fields; after a successful demo save, the form resets to the compact first step. The no-JavaScript fallback keeps all fields visible. A single focused Playwright check passed in the static demo at 1440 px and 390 px, including empty-contact validation and successful local demo submission. Four section screenshots are in `visual-qa/home-booking-progressive-2026-11-16/`. PHP route rendering was not tested locally because this PHP runtime lacks `pdo_sqlite`; no broad suite or public deployment was run.

The same route-focused suite now includes the reference-aligned grid/list switch. It checks switching both ways and confirms searching the unique dish name “Рамен с говядиной” in list mode leaves one result while hiding nonmatches. Static-only run: two viewports (1440/390); PHP browser mode was not run in this pass.

Ingredient quick-filters now combine with category and text search. The focused menu test verifies that “Роллы” + “Лосось” + “Филадельфия” yields one dish and that changing category to “Суши и сашими” yields none while the other filters remain active. Filters with no matching catalog item remain hidden; the seeded menu currently has no scallop dish. This supplements the same two-width static-only run; no full suite was repeated.

## Campaign editor follow-up — 2026-11-16

Added an in-place editor for existing marketing campaigns and two regression assertions for prefilled fields, update persistence, editor retention after POST and weekday-mask preservation. `php -l admin-marketing.php`, `php -l tests/php_integrity_integration.php` and `git diff --check` passed. The campaign, promo-code and collection form handlers now use the same strict `Asia/Qyzylorda` datetime parser, reject impossible local dates and reject reversed collection windows. A regression assertion covers an impossible campaign date. The SQLite HTTP regression was not run locally because this PHP CLI has no `pdo_sqlite`; no full suite was repeated.

## Delivery tariff configuration — 2026-11-16

The protected site settings now validate and persist a delivery tariff from 0 to 100,000 ₸, and the campaign creation form exposes the existing free-delivery reward. The focused integration assertions cover saving a positive rate, rejecting a negative rate without overwriting it, and applying the configured amount to a server-quoted free-delivery promotion. PHP lint and `git diff --check` passed; the SQLite integration suite was not run locally because `pdo_sqlite` is unavailable.

## Browser-only account sample — 2026-11-16

Added an explicit “Заполнить учебный пример” action to the static profile. `npm run test:account-demo` passed: it checks three catalog-backed order snapshots, a locally labeled booking request, favorites, idempotent repeat clicks, updated metrics, mobile overflow, and the existing clear-data control. Full-page desktop/mobile captures were visually inspected at `visual-qa/11-account-seeded-{desktop,mobile}.jpg`. JavaScript syntax, exporter PHP syntax and `git diff --check` passed. The local PHP CLI lacks `pdo_sqlite`, so this pass did not rerun the exporter or PHP server-backed account path; hosted CI must verify the generated export.

## Eleven-route static visual capture — 2026-11-16

Extended `tests/visual-fidelity-regression.cjs` to cover reviews, cart and account, support `TOKYO_VISUAL_MODE=static`, and include animated story targets in its post-scroll visibility assertion. Three focused static-only route batches collectively passed all 11 route families at 1440 px desktop and 390 px mobile. Full-page captures are in `project-docs/visual-qa/2.0-visual-fidelity-2026-11-16/`; the eight populated/empty/validation/pickup/delivery cart captures are included there as well. The existing cart visual regression asserts three distinct catalog dishes, quantity ≥2, decoded photos and a displayed total matching the shared store. Home and populated-cart screenshots were reviewed against references 01 and 10; visible differences remain and are recorded in the visual QA index. PHP browser rendering was not run because this local PHP CLI lacks `pdo_sqlite`; there was no public deployment in this pass.

## 2026-11-16 menu and Omakase visual follow-up

`npm run test:visual-fidelity` passed in static mode with `TOKYO_VISUAL_ROUTES=menu,omakase` and `TOKYO_CAPTURE_STATIC=1`. The focused run validates the food-led menu hero, five distinct loaded catalog photos in the Omakase stage selector, selected stage structure, mobile tab bounds, title geometry, image loading, no console/page errors, and no horizontal overflow. Captures in `visual-qa/visual-fidelity-followup-2026-11-16/` were inspected at 1440 px and 390 px against `02-menu.png` and `04-omakase.png`. The Omakase test also now asserts the desktop screenshot viewport before capture; this fixes a prior false desktop label caused by resizing before the desktop screenshot. PHP browser mode, full suites, hosted CI, and deployment were not run in this focused pass.

## 2026-11-16 philosophy reference refinement

Compared the current static route with `design-references/05-philosophy.png`. The hero now uses the existing sakura entrance photograph; the story row follows copy → room photograph → fictional timeline; chef and ingredient sections use the dark editorial palette, while the warm quote strip stays light. `premium.css` and the Pages copy are synchronized. `npm run test:visual-fidelity` passed for the static philosophy route at 1440 px and 390 px: the hero asset loads, the heading remains within bounds, and there are no JavaScript/page errors or horizontal overflow. Full-page captures in `visual-qa/philosophy-refinement-2026-11-16/` were visually inspected beside the approved reference. This remains a focused single-page comparison; PHP browser mode, full suites, all-11 pixel parity, hosted CI and deployment were not checked in this pass.

## 2026-11-16 account hero refinement

Focused static browser regression: `npm run test:account-demo` passed at 1440 px and 390 px. It checked the stable account title/subtitle, loaded entrance photo, seeded local order/booking/favorites, duplicate prevention, clear-data behavior, mobile title bounds, and zero browser exceptions/overflow. Screenshots: `visual-qa/account-hero-refinement-2026-11-16/`. PHP browser rendering, broad suite, hosted CI, and public deployment were not run.

## 2026-11-16 final focused visual and integrity pass

The cart hero rule had drifted in both PHP and Pages stylesheets, leaving the desktop banner at 430 px. Both copies now use the compact 250 px target, with the mobile spacing kept responsive. The route-focused visual-fidelity regression now asserts that the cart hero stays at or below 270 px in both modes. `TOKYO_VISUAL_MODE=both TOKYO_VISUAL_ROUTES=cart` passed against the local PHP server and current static export.

`npm run test:cart-visual` passed and wrote all eight empty/populated/validation/pickup/delivery captures to `visual-qa/cart-state-final-2026-11-16/`. The populated state contains three distinct catalog dishes, a quantity of two, loaded photos and an exact shared-store total. Those desktop/mobile captures were reviewed beside `design-references/10-cart.png`. `npm run test:account-demo` also passed and produced freshly seeded desktop/mobile account captures in `visual-qa/account-state-final-2026-11-16/`; local-only labels, item snapshots, repeat-seed behavior and clear-data flow passed.

`tests/php_integrity_integration.php` passed 193 assertions against an isolated SQLite database. The packaged SQLite DLLs were enabled through a temporary local `php.ini`; no project configuration or secret was changed. Fresh static desktop/mobile captures cover all 11 route families in `visual-qa/reference-pass-current-2026-11-16/`. The human visual review and remaining differences are recorded in `visual-qa/reference-comparison-current-2026-11-16.md`; it is qualitative, not pixel-difference acceptance.

This focused pass did not run the full browser suite, complete 11-route PHP visual comparison, hosted CI, deployment, or a cache-busted public Pages check. These local checks do not establish the current public site state.
## Focused account history alignment — 2026-11-16

- `npm run test:account-demo` passed against a freshly served static export.
- Added a desktop geometry assertion for the top alignment of the orders and bookings headings after seeding; existing assertions also covered empty-by-default state, itemized demo orders, local labels, persistence/idempotency, clear-data, mobile overflow and browser errors.
- Fresh captures: `project-docs/visual-qa/account-history-alignment-2026-11-16/11-account-seeded-{desktop,mobile}.jpg`. PHP account behavior was not changed or re-tested in this focused CSS pass.

## Threshold gift schedule and static pricing disclosure — 2026-11-16

- `commerce_quote()` now skips threshold-gift campaigns outside their start/end dates, weekday mask, or time window, then selects the next scheduled campaign.
- Added focused regression cases for future, expired, disabled-weekday and out-of-window campaigns in `tests/gift-quantity-regression.php`.
- PHP syntax checks passed. The local CLI lacks `pdo_sqlite`; hosted run [#147](https://github.com/altento/tokyo-premium/actions/runs/43914869461) passed PHP/SQLite integrity and the threshold-gift schedule regression.
- GitHub Pages set-builder, offer and cart copy now identifies amounts and promo results as local estimates without server validation. Dynamic set-builder and evening messages use the same disclosure.
- `npm run test:offers-evening` passed in the static browser, including the set-builder interaction and pricing disclosure.
- No merge or publication was performed in this pass.

## Account demo navigation badge spacing — 2026-11-16

- The static account navigation now lays out each tab label and its order/favorite/address count as separate flex items with a compact gold badge.
- `tests/account-demo-regression.cjs` checks that all three badges remain inside their buttons and have visible separation from their labels at desktop and 390 px mobile widths.
- `npm run test:account-demo` passed in the static Pages export. Fresh full-page captures are in `project-docs/visual-qa/account-tab-count-refinement-2026-11-16/` and were visually inspected at both widths.
- This focused CSS change does not modify or verify PHP account storage/authentication. The account dashboard still has broader structural differences from `design-references/11-account.png`.

## Tokyo Premium 2.0 targeted regressions — 2026-11-16

- `npm run test:offers-evening` passed in the static browser after adding restart-state gating and occasion-sensitive recommendation ranking. Updated captures: `project-docs/visual-qa/ideal-evening-budget-desktop-2026-11-16.png` and `ideal-evening-budget-mobile-2026-11-16.png`; both were visually inspected.
- `npm run test:tokyo20` passed in PHP and static modes with PHP 8.4 + SQLite, including checkout-created browser-local orders and bookings being marked as educational demo records and shown with local badges in account history.
- Campaign tier checks now cover promo quote/order, public offer visibility and threshold gifts. Birthday assignment checks cover consent, configured eligibility window, promotion code, account display and the admin path in `tests/gift-quantity-regression.php` and `tests/personal-offer-regression.php`.
- The project-wide CLI lacks `pdo_sqlite`, so the two focused SQLite regressions were not run locally. The workflow runs both `test:gift-quantity` and `test:personal-offer` in its PHP+SQLite job.
- Admin notification links now clear the session search before jumping to the counted section. The PHP integration regression covers the positive count, empty filtered list and reset destination; `php -l` passed locally. That database-backed integration runs in the same PHP+SQLite job.

## Gallery lightbox caption repair — 2026-11-16

The gallery lightbox now has an accessible live caption. It uses the visible tile label or room title and updates when navigating images. `npm run test:gallery-caption` passed against the static Pages export; it checks the selected, next and previous captions plus Escape at desktop and mobile widths. The desktop and mobile screenshots in `project-docs/visual-qa/gallery-caption-followup-2026-11-16/` were visually inspected. Browser CI is configured to check PHP and Pages modes. No public deployment was performed.
## Commerce pricing integrity follow-up — 2026-11-16

`commerce_quote()` now allocates a campaign-wide `maximum_discount` across all targeted custom sets before the order lines are stored. Promo redemption rows now record only the promotion portion when loyalty points are also redeemed. `tests/commerce-pricing-regression.php` covers two capped set lines, authoritative total reconciliation, and stackable promo-plus-points attribution. PHP lint passed; the focused SQLite test skipped locally because this CLI lacks `pdo_sqlite`. The workflow now runs it in the PHP+SQLite job. No broad local suite was repeated.
## Admin Cyrillic search and referral login — 2026-11-16

The admin account finder now compares Russian and Kazakh Cyrillic names without case sensitivity using a local SQLite scalar function; referral codes from validated invite links now survive an existing member's login and remain available for explicit attachment. The focused HTTP regression passed with 8 assertions under PHP 8.4 + SQLite, and PHP lint passed. The test is wired into the PHP+SQLite workflow. No broad suite was run.

## Focused visual and seeded-account follow-up — 2026-11-16

- The detail-page related-product grid selector now matches the page's actual `.editorial-page` class. A focused browser run checked the desktop, tablet and mobile grid geometry at 1440, 900 and 390 px; screenshots were inspected.
- Rechecked reviews against the current export after identifying that an earlier port served stale markup. Three desktop columns and one mobile column were confirmed; `npm run test:reviews` passed with seeded-card disclosure, photo interlude, local review XSS/persistence, and public score stability checks.
- `npm run test:cart-visual` passed for all eight cart states. The populated desktop/mobile captures show three distinct dishes, a repeated quantity, working recommendations and matching totals; both were visually inspected beside `design-references/10-cart.png`.
- `npm run test:account-demo` passed after moving the browser-local privilege summary nearer the profile overview. It now asserts that demo points are recalculated after seeding and return to zero after local data is cleared. Updated full-page desktop/mobile captures are in `project-docs/visual-qa/11-account-seeded-{desktop,mobile}.jpg`.
- PHP syntax and whitespace checks passed. This accelerated pass did not run the broad browser suite, PHP browser mode or public-site check; the draft branch has not been published to GitHub Pages.
