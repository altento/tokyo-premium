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

## Additional visual-fidelity pass — 2026-11-13

The user restated the complete Tokyo Premium 2.0 directive; work continued on the existing application and both delivery modes. This follow-up adds two original WebP hero photographs (distinct home and catalog scenes), gives the philosophy hero a garden image, adds a five-card catalog-backed Omakase overview, and fixes a confirmed CSS clipping defect that hid the lower half of the gallery. Booking and contact page spacing was tightened. The two new images are text-free and copied by the existing static exporter.

The new `npm run test:visual-fidelity` browser suite passed on seven pages in PHP and static Pages mode, writing 14 full-page PHP desktop/mobile screenshots under `visual-qa/2.0-visual-fidelity/`. It verifies hero sources, home recommendations after scrolling, the eight gallery tiles' bounds, the five Omakase photos, SVG floorplan and preference-only wording, the fictional contacts map, responsive title bounds, loaded images and console errors. These screenshots were reviewed. Existing local suites also passed in this follow-up: cart 12/12, Tokyo 2.0, reviews, eight cart states, 11×14 responsive checks, gallery/account visual states, menu at 1440/390/320, and 102 isolated PHP/SQLite assertions. PHP lint passed under PHP 8.4 with SQLite modules.

This is a focused qualitative comparison, not a full pixel-difference acceptance against all eleven reference PNGs. The latest public deployment still points to the previous merged revision; this branch must pass hosted CI and a public browser check before the release status changes. Production limits remain: no configured SMTP delivery, payment processor, live inventory or confirmed table availability.

## Release verification — 2026-11-13 (current)

The preceding pending-release statement is superseded. PR [#16](https://github.com/altento/tokyo-premium/pull/16) was merged as `4256718a68f8016ff7e9b5dafb0ffa4d33a917c3`. Hosted [workflow 43335988363](https://github.com/altento/tokyo-premium/actions/runs/43335988363) passed the pull-request build and test suite; [workflow 43336522224](https://github.com/altento/tokyo-premium/actions/runs/43336522224) passed on `main` and deployed Pages.

The published [GitHub Pages site](https://altento.github.io/tokyo-premium/) was checked in Chromium with a commit cache-buster. Home, menu, Omakase, offers, Perfect Evening, set builder, booking and account routes rendered their expected headings. The home hero image returned HTTP 200 and decoded at 1672 px wide; desktop (1440 px) and mobile (390 px) had no horizontal overflow, the mobile title remained in bounds, and the browser recorded no console or page errors. Full-page public home captures are saved in `visual-qa/2.0-visual-fidelity/home-public-postdeploy-desktop.jpg` and `home-public-postdeploy-mobile.jpg`; they were inspected after scrolling to trigger reveals and lazy images.

A subsequent local-only PHP test expansion now exercises valid and forged-price custom sets, unavailable dishes, inactive/unknown variants, duplicate IDs, quantity limits, persisted component snapshots, and rollback without creating an order. The isolated integration suite passes 114 assertions on this branch. That extra test coverage has not yet passed hosted CI or been published; the set-builder server validator itself was already present in the deployed release.

The whole Tokyo Premium 2.0 scope is not complete. In particular, full pixel-by-pixel comparison against all 11 approved PNGs, focused referral/birthday test cases, and a coordinated cinematic scroll narrative remain open. No live email, payment, stock or table-availability service is configured.
