# Tokyo Premium 2.0 implementation record

Updated 2026-11-23

## 2026-11-23 — booking page rhythm

Reduced the desktop booking hero and its three explanatory cards so the reservation form starts sooner and the first screen is closer to approved reference 07. Browser inspection found a shared 38 px hero button margin and a timezone hint that made only the time field taller than its neighbor. The hero margin is now 18 px and the booking time hint spans the form row. On live Pages, hero height fell from 332 to 280 px at 1024 px and from 365 to 312 px at 1440 px; the 390 px mobile layout stayed at 390 px. At 1024 px the booking form and floorplan now each use the full 881 px row; the wide 1440 px layout remains two columns. The public page returned HTTP 200 at 390, 1024 and 1440 px; all three had no horizontal overflow, the heading remained visible, the floorplan loaded and there were no JavaScript page errors. Screenshots and visual notes are in `project-docs/visual-qa/booking-vertical-rhythm-2026-11-23/`. No full suite was run for this scoped change.

## Current release checkpoint

The latest verified public release is `3d08759` (GitHub Actions run [45134262257](https://github.com/altento/tokyo-premium/actions/runs/45134262257); build and deploy succeeded). Cache-busted public home/cart routes and their changed assets returned HTTP 200. The focused home visual-fidelity regression passed against the public Pages site at desktop and mobile widths; the curated catalog IDs and space-to-booking flow matched the intended structure. The 37-page static export was also checked for Omakase/set-builder parity, booking floorplan status, and checkout quote consistency. Full browser/visual QA remains opt-in and known visual gaps remain open. The PHP admin was checked locally against an isolated SQLite database with synthetic bookings; production data and live email delivery were not tested.

## 2026-11-23 — mobile cart and home editorial follow-ups

The mobile cart summary from `6d21ef6` and home editorial update from `3d08759` are live. The home curation selects four existing public dishes in a stable order and removes duplicate Omakase/sample-review blocks so the page moves from space directly to booking. Focused visual-fidelity checks passed for static, PHP, and public Pages home routes at desktop and mobile widths. Captures and the comparison with reference 01 are in `visual-qa/home-editorial-curation-2026-11-23/`. Tokyo Premium 2.0 remains in progress.

## 2026-11-23 — mobile cart checkout access

Added a single mobile-only checkout summary to the PHP cart template and exported Pages cart. It reads the total from the shared cart store, opens the existing checkout disclosure, transfers keyboard focus to its heading, and hides for an empty cart, expanded checkout, or open mobile navigation. The focused cart Playwright scenario passed at 390 px and checked fit at 320 px; PHP syntax passed and the PHP cart route returned HTTP 200 with the new markup. Eight existing full-page cart states plus an actual mobile viewport screenshot are in [`visual-qa/cart-mobile-summary-2026-11-23/`](visual-qa/cart-mobile-summary-2026-11-23/). This does not claim final cart/reference visual acceptance or completion of Tokyo Premium 2.0.

### Admin, account, and dish follow-up — published in `8867502`

The operational lists in `admin.php` page through orders, bookings, reviews and contact messages in batches of 25. Each section reports the total for its active search/status filter, clamps stale page numbers, and keeps the relevant filter when moving between pages. The booking-email KPI now opens only bookings with pending or failed delivery, with retry controls in their records. Responsive previous/next controls were added to `premium.css`. The account dashboard and dish detail pages gained scoped desktop/mobile layout refinements. Run 44274931548 passed focused PHP/SQLite, admin/account feature checks, PHP lint, form/admin smoke, static export, and deployment. The full browser suite was intentionally skipped for this fast publish; it remains available as a manual workflow option.

The full modernization remains open. The comparison identifies concrete follow-up work for dish photography/information, home/account density, and the booking seating visual; it does not claim pixel-perfect parity. A consolidated security/performance review and deployment of the PHP/SQLite runtime on an actual PHP host are still outstanding if a host is configured. GitHub Pages stays a browser-local demonstration; payment, live seating, and outbound email are not configured. Runway reports that this workspace has no video models or credits, so a hero video cannot be generated here; keep the existing still-image fallback.

## Foundation preserved

The project remains the existing PHP 8+ application with SQLite. Public PHP routes, `bootstrap.php`, the admin area and the shared cart store remain in place. GitHub Pages is still generated from those routes by `tools/export-pages.php`. Database changes are additive and versioned in `app_migrations`.

## Implemented modules

- Shared motion styling, reduced-motion behavior and responsive editorial components.
- Omakase course selector with five scenes; set builder with server-configured variants and shared-cart integration; rules-based “perfect evening” wizard.
- SVG seating plan with zone/table preference. It never reports live availability; a change to date, time, guest count or zone clears the previous table preference.
- Quick view for actual catalog records, search metadata, campaign/collection routes, server-validated promotion quotes, Secret Menu access checks and threshold gift options.
- Loyalty tiers/ledger, completed-order event handling, optional birthday consent, referral association and non-financial certificate previews with rate limiting.
- Marketing & Loyalty admin forms for campaigns, codes and collections behind existing admin session and CSRF protection.
- Account order history has server-side repeat-order payloads built from current eligible dishes and saved custom-set components; the client applies the payload atomically and never submits historical prices.
- Browser-local counterparts on Pages for supported presentation scenarios. These do not claim server delivery, authentication, payment, live availability or a redeemable certificate.
- Secret Menu visibility is filtered server-side from public catalog/detail/collection/API routes; PHP unlocks are session-backed and rechecked during order quotes. The Pages preview payload is explicitly public demo content, not access control.
- Promotion stacking, bonus-points promo eligibility and completed-order loyalty events are checked transactionally so a status cycle cannot award the same order repeatedly.

## Verification order

1. PHP syntax and isolated PHP/SQLite integrity tests.
2. Cart-store unit tests.
3. Browser regression for 11 route types, 21 dishes, cart/checkout/profile/navigation and responsive widths.
4. `test:tokyo20` against PHP and static export, including offers, collection, Omakase, set builder, recommendation wizard, booking preference, gift preview, quick view and populated cart.
5. Inspect captured screenshots before updating the evidence index.
6. Verify a clean CI dependency install, static Pages export and published deployment after pushing the release branch.

## Current verification record

- PHP + SQLite integration suite: 114 assertions passed against an isolated database; six simultaneous PHP processes first initialize a clean database, WAL mode is verified, and the suite passed locally in UTC. Hosted CI run 43341069860 passed with its multi-worker HTTP server. Custom-set checkout verifies server-side prices, item snapshots and rejection of tampered compositions.
- Cart state tests: 12 passed.
- Browser suites passed for the 11 existing route types, 20 dish details (18 public plus two after an authorized Secret Menu unlock), Tokyo 2.0 interactions, reviews, cart states, responsive layouts and visual states.
- Responsive checks covered 11 page types at widths 320, 360, 375, 390, 430, 768, 900, 1024 and 1440 px; 1920 px was included in the dedicated responsive run.
- Static export generated 36 pages. Locked PHP dish routes are omitted; the Pages Secret Menu is a clearly bounded browser demo.
- Local `npm ci` completed successfully with the pinned Playwright 1.65.0 dependency; browser suites ran against the current PHP app and a freshly exported 36-page static build.
- Cinematic hero follow-up: manual three-scene controls, same-origin route mapping for static Pages, keyboard arrows, queued rapid input, no autoplay, reduced-motion behavior, and pointer-limited parallax are implemented and covered by `test:motion` at eight responsive widths in both modes.
- Screenshot evidence is stored in `project-docs/visual-qa/`. Screenshots were inspected for selected states; this is not a pixel-by-pixel comparison of every section against all 11 reference PNGs.
- Current published checkpoint `431a0a6`: 14/14 cart-store tests passed; hosted PHP/SQLite integration, browser regression/visual-QA, static export and deployment all passed in run 44221178924. The local Playwright package was absent in this checkout, so the repeat-order browser scenario was verified in hosted CI rather than locally.
- Current published checkpoint `50f7ade`: hosted run 44241369666 passed PHP/SQLite integrity, the 11-route/21-detail browser regression, Tokyo 2.0 interactions, responsive and visual-fidelity checks, static export (37 pages), and Pages deployment. Booking FAQ disclosure, mobile layout and the SVG asset are included. Fresh static captures for all 11 page types at desktop and mobile widths, paired approved-reference contact sheets, and visual review notes are in `project-docs/visual-qa/full-static-2026-11-18/`. This is a visual review, not a pixel-difference audit or PHP rendering pass. Cart empty and populated states are kept in separate evidence folders.
- Current published checkpoint `8867502`: run 44274931548 passed the focused fast-publish checks and deployment. Browser tests were skipped by the new default workflow profile; cache-busted public home, account, and dish routes returned HTTP 200, and the account/dish pages included their scoped refinement styles. Full browser QA remains manually runnable.

- Current published checkpoint `c3a9ada`: run 44277530746 succeeded. Targeted local PHP and JavaScript syntax checks plus `git diff --check` passed; no long browser suite was run. Cache-busted public home, account, premium.css, and tokyo20.css returned HTTP 200. GitHub Pages remains static and does not expose the PHP admin runtime.
- Current published checkpoint `51a16c3`: workflow 44278543876 succeeded. The admin image-preview release passed hosted focused checks and Pages deployment. Cache-busted public home and updated premium.css returned HTTP 200, with the new admin image-preview styles present. No browser screenshot or full visual suite was run for the PHP-only admin interface.
- Post-release admin preview hardening: local preview paths now match PHP’s case-sensitive WebP rule, while HTTPS Unsplash previews reject encoded separators and dot segments. `node --check admin-dashboard.js` and `git diff --check` passed. This is PHP-admin-only source code; CI and browser checks were not rerun, and the published Pages artifact is unchanged.

## 2026-11-23 — account order-history density

The static account's order cards now keep the local-demo label, order ID and date on a compact wrapping line, reducing repeated vertical space while retaining totals, composition details and repeat-order actions. Root and exported CSS are byte-identical. The focused static account visual-fidelity route passed locally and on GitHub Pages at desktop and mobile widths; captures and the honest reference comparison are in `visual-qa/account-density-2026-11-23/`. PHP account markup was not changed. Commit `1f30d3a` is live; GitHub Actions run [45121694677](https://github.com/altento/tokyo-premium/actions/runs/45121694677) succeeded.

## 2026-11-23 — dish composition density

Dish ingredient cards now use a centered auto-fit grid based on confirmed catalog components, so a two-item composition no longer leaves a third blank track. The exported asset was synchronized with the PHP source; the focused static detail route passed at desktop and mobile widths. Captures and the comparison with reference 03 are in `visual-qa/dish-composition-density-2026-11-23/`. Nutrition and review figures remain absent where the catalog has no verified values.

Commit `167c1d5` is live; GitHub Actions run [45122541776](https://github.com/altento/tokyo-premium/actions/runs/45122541776) passed. The public detail route then passed the same focused desktop/mobile Playwright scenario, with screenshots in the `public/` subfolder.

## 2026-11-18 — admin workflow export

The protected operations dashboard now has CSV exports for orders, bookings, reviews and contact messages. Each export uses the same session search, status filter, or notification scope as its section, streams all matching rows rather than only the visible page, includes a UTF-8 BOM, and prefixes spreadsheet formula-like text values. Downloads are private and non-cacheable. PHP lint, `git diff --check`, CI build and deployment passed. The authenticated export route itself is not covered by the CI smoke steps, and the feature does not expose the PHP admin through GitHub Pages.

Promo code rows now include a copy-to-clipboard action with an accessible result message and legacy-browser fallback. PHP/JS syntax and diff checks passed; no browser interaction was run for this control.

## 2026-11-18 — admin action context

Successful admin updates now return to the affected section instead of resetting to the dashboard top. List actions retain only validated status/notification filters and the current page, and the one-time confirmation appears inside the section the administrator just changed. This focused change passed PHP syntax and whitespace checks; browser interaction is not available for the PHP admin in the Pages runtime.

Operational controls now fit narrow admin cards better: status selection and update stay on one row, while confirmation and email-retry actions keep a 44 px minimum target. Red section dividers remain unchanged.

## Boundaries

The restaurant, prices, campaign defaults, guest examples and address are fictional school-project data. Only a completed server order event changes the PHP loyalty ledger. Pages stores supported demo data in the current browser's `localStorage`; it is not encrypted or synced with PHP. No external mail provider or payment gateway is configured, and a booking request is not a confirmed reservation.
