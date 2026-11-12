# Tokyo Premium 2.0 — modernization report

Updated 2026-11-13

## What was added to the existing project

The existing PHP 8 + SQLite application and its GitHub Pages exporter remain the foundation. The additions are organized around the existing catalog, shared cart store, server pricing and admin session rather than a second frontend application.

- Editorial offers and seasonal collection routes, a Secret Menu access path, demo promo codes, campaign targeting and threshold gifts.
- A five-scene interactive Omakase journey with the chosen format carried to booking.
- A rules-based “Perfect evening” selector using real catalog entries, an estimated budget and a booking handoff.
- A constrained set builder backed by active SQLite set variants. It estimates in the browser, then the server validates composition and current prices before order storage.
- A drawn SVG seating preference map. Selection is a preference only, is cleared when date/time/guests/zone changes, and never claims live availability.
- Catalog quick view and shared-store integration, account dashboard extensions, local loyalty/referral presentation, and certificate previews that are not payment instruments.
- A protected Marketing & Loyalty admin route for campaign, code, collection, gift-stock, Secret Menu and tier settings. Include/exclude targets can specify `dish:<id>`, `category:<slug>`, or `set:<slug>`; unknown targets and conflicting include/exclude rules are rejected. Target replacement shares a transaction with the campaign save.
- Additive SQLite migrations for campaigns, promo use, collections, custom sets, loyalty ledger, referrals and certificate preview records.
- Native CSS motion and responsive editorial components with reduced-motion support. The project does not add GSAP because the present sequences do not require a scroll-animation runtime.

## Verification

The 2026-11-13 local verification record is in [testing-report.md](testing-report.md). At this update, 12 cart-store tests, 102 isolated PHP/SQLite integration assertions (including six simultaneous clean-database initializers and a WAL-mode assertion; the suite passed three consecutive runs after the worker and commit-lock fixes), 11 route types, all 20 dish detail scenarios (18 public plus two unlocked Secret Menu entries), six viewport browser checks, 11×9 responsive checks, PHP and static Tokyo 2.0 flows, review XSS/persistence checks and the eight-state cart visual suite passed. New screenshots are indexed in [visual-qa/README.md](visual-qa/README.md).

The static exporter generated 36 HTML pages. It omits direct Secret Menu detail routes, clears stale generated dish pages, and exposes a clearly demo-only client payload for the static unlock preview. PHP and JavaScript syntax checks passed. Concurrent schema initialization and SQLite commit-lock issues were fixed by serializing initialization and enabling WAL under the init lock.

The final hosted [Pages workflow run 43312293771](https://github.com/altento/tokyo-premium/actions/runs/43312293771) for commit d1bc5543d67ef04c8353d713eca1b0b3f7eea9e9 completed successfully: build, PHP/SQLite checks, browser regression, artifact upload, and Pages deployment all passed. The public site was then opened and checked in a browser; 15 static routes returned successfully, page assets loaded without console errors or warnings, and the Offers page heading remained within the viewport at 320 and 390 CSS px without horizontal overflow.

## Product and infrastructure boundaries

This is still an educational restaurant concept. The seeded menu, address, phone, reviews, prices, promotional codes and example privileges are fictitious. Pages actions store only local browser demo state. PHP bookings are requests pending admin action; a confirmed badge is only shown after an admin changes the database status. Orders are training records and there is no payment provider.

Email delivery is not active: SMTP credentials and an external mail service are not configured. Promotions, loyalty values, discounts and gift stocks must be reviewed by an administrator before any real operation. The static demo does not implement cross-device identity, secure loyalty balances or certificate redemption.

The PHP Secret Menu now hides targeted dishes from public catalog/detail/collection/API results until an active code, role, or tier rule authorizes access. A successful code check is stored in the PHP session and checked again for quoting and order persistence. GitHub Pages is public by design; its preview data is not a security boundary.

## Visual acceptance status

Real browser screenshots were reviewed for the cart, new set builder, offers, static account and narrow booking heading. Existing 11-page reference screenshots and prior gap notes remain available in `project-docs/visual-qa/` and `final-acceptance-report.md`. This cycle did not perform a new pixel-by-pixel comparison of every current route against all 11 reference PNGs; visual fidelity remains a review item rather than a claimed completed acceptance criterion.

### Focused follow-up on 2026-11-13

The menu, dish detail, Omakase and authenticated account were rechecked against their references and current browser output. The menu's second row was initially hidden until scroll and its action links overlapped; both were corrected and covered at 1440/390/320 px. Dish recommendations were made category-aware and visible without a reveal observer. Omakase scenes now bind to active catalog entries and show the selected entry's real demo image and data. The account now has a clearer dashboard hierarchy with server-owned history and explicitly browser-local favorites; authenticated desktop/mobile screenshots were captured.

The focused regression suites and full local PHP/SQLite, responsive, cart, review, Tokyo 2.0, and visual-state suites passed. Exact commands, screenshot inventory and scope are in [testing-report.md](testing-report.md) and [visual-qa/README.md](visual-qa/README.md). This did not complete the requested pixel-by-pixel comparison of all 11 design references.

The requested optional hero video was not created: the connected video workspace reported no available video models and zero credits. Existing optimized still photography remains the hero fallback; no video is simulated.

## Release status

The latest menu, Omakase and account refinements are on main at `64061e4ff3f4c8fb4029e6ba95e3a5cce58444b8`. Pages workflow [43329426971](https://github.com/altento/tokyo-premium/actions/runs/43329426971) passed build, PHP/SQLite integrity, browser regression and deploy. The published [Tokyo Premium site](https://altento.github.io/tokyo-premium/) was checked with a revision cache-buster across home, menu, Omakase, cart and account; all checked routes returned HTTP 200 and no console/page errors were observed. The complete 11-reference pixel comparison remains open.
