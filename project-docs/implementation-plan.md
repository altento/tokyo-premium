# Tokyo Premium 2.0 implementation record

Updated 2026-11-18

## Current release checkpoint

The latest verified public release is `9efab56` (GitHub Actions run [44234776222](https://github.com/altento/tokyo-premium/actions/runs/44234776222)). The PHP account keeps its order history full-width beside navigation/status cards, while the Pages account export places the updated masthead, loyalty summary and account panels in one responsive dashboard layout. The exporter now preserves the masthead and emits one loyalty summary. PHP and static account regression passed, the full hosted browser/PHP/SQLite workflow passed, 37 static pages were exported, and the public account route returned HTTP 200 with the updated dashboard wrapper.

The full modernization remains open. The remaining acceptance work includes a fresh full-page visual comparison against all 11 approved references, a consolidated security/performance review, and deployment of the PHP/SQLite runtime on an actual PHP host if one is configured. GitHub Pages stays a browser-local demonstration; payment, live seating, and outbound email are not configured. Runway reports that this workspace has no video models or credits, so a hero video cannot be generated here; keep the existing still-image fallback.

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
- Current published checkpoint `9efab56`: hosted run 44234776222 passed PHP/SQLite integrity, the 11-route/21-detail browser regression, Tokyo 2.0 interactions, responsive and visual-fidelity checks, account-demo state tests, static export (37 pages), and Pages deployment. The latest public account route returned HTTP 200 and included the dashboard content wrapper. CI screenshot files remain available in the workflow's visual-QA artifact; this is not a pixel-by-pixel manual comparison against all 11 approved PNGs.

## Boundaries

The restaurant, prices, campaign defaults, guest examples and address are fictional school-project data. Only a completed server order event changes the PHP loyalty ledger. Pages stores supported demo data in the current browser's `localStorage`; it is not encrypted or synced with PHP. No external mail provider or payment gateway is configured, and a booking request is not a confirmed reservation.
