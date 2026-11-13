# Tokyo Premium 2.0 — verification record

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
