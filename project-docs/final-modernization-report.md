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
- Responsive editorial components with reduced-motion support. The homepage scroll story uses locally vendored GSAP 3.15 and ScrollTrigger; other pages retain native CSS motion and do not load GSAP.
- Homepage hero has three coordinated manual scenes with text, image-alt and CTA changes; keyboard arrows, dots, queued rapid input, no autoplay, fine-pointer-only parallax and `prefers-reduced-motion` support. Static scene links resolve to static routes.

## Verification

The 2026-11-13 local verification record is in [testing-report.md](testing-report.md). At this update, 12 cart-store tests, 102 isolated PHP/SQLite integration assertions (including six simultaneous clean-database initializers and a WAL-mode assertion; the suite passed three consecutive runs after the worker and commit-lock fixes), 11 route types, all 20 dish detail scenarios (18 public plus two unlocked Secret Menu entries), six viewport browser checks, 11×9 responsive checks, PHP and static Tokyo 2.0 flows, review XSS/persistence checks and the eight-state cart visual suite passed. New screenshots are indexed in [visual-qa/README.md](visual-qa/README.md).

The static exporter generated 36 HTML pages. It omits direct Secret Menu detail routes, clears stale generated dish pages, and exposes a clearly demo-only client payload for the static unlock preview. PHP and JavaScript syntax checks passed. Concurrent schema initialization and SQLite commit-lock issues were fixed by serializing initialization and enabling WAL under the init lock.

The final hosted [Pages workflow run 43312293771](https://github.com/altento/tokyo-premium/actions/runs/43312293771) for commit d1bc5543d67ef04c8353d713eca1b0b3f7eea9e9 completed successfully: build, PHP/SQLite checks, browser regression, artifact upload, and Pages deployment all passed. The public site was then opened and checked in a browser; 15 static routes returned successfully, page assets loaded without console errors or warnings, and the Offers page heading remained within the viewport at 320 and 390 CSS px without horizontal overflow.

## Product and infrastructure boundaries

This is still an educational restaurant concept. The seeded menu, address, phone, reviews, prices, promotional codes and example privileges are fictitious. Pages actions store only local browser demo state. PHP bookings are requests pending admin action; a confirmed badge is only shown after an admin changes the database status. Orders are training records and there is no payment provider.

The PHP branch now contains an optional SMTP delivery adapter and durable email outbox, but delivery is not active until SMTP credentials and a verified sender are configured in the PHP service environment. Promotions, loyalty values, discounts and gift stocks must be reviewed by an administrator before any real operation. The static demo does not implement cross-device identity, secure loyalty balances or certificate redemption.

The PHP Secret Menu now hides targeted dishes from public catalog/detail/collection/API results until an active code, role, or tier rule authorizes access. A successful code check is stored in the PHP session and checked again for quoting and order persistence. GitHub Pages is public by design; its preview data is not a security boundary.

## Visual acceptance status

Real browser screenshots were reviewed for the cart, new set builder, offers, static account and narrow booking heading. Existing 11-page reference screenshots and prior gap notes remain available in `project-docs/visual-qa/` and `final-acceptance-report.md`. This cycle did not perform a new pixel-by-pixel comparison of every current route against all 11 reference PNGs; visual fidelity remains a review item rather than a claimed completed acceptance criterion.

### Focused follow-up on 2026-11-13

The menu, dish detail, Omakase and authenticated account were rechecked against their references and current browser output. The menu's second row was initially hidden until scroll and its action links overlapped; both were corrected and covered at 1440/390/320 px. Dish recommendations were made category-aware and visible without a reveal observer. Omakase scenes now bind to active catalog entries and show the selected entry's real demo image and data. The account now has a clearer dashboard hierarchy with server-owned history and explicitly browser-local favorites; authenticated desktop/mobile screenshots were captured.

The focused regression suites and full local PHP/SQLite, responsive, cart, review, Tokyo 2.0, and visual-state suites passed. Exact commands, screenshot inventory and scope are in [testing-report.md](testing-report.md) and [visual-qa/README.md](visual-qa/README.md). This did not complete the requested pixel-by-pixel comparison of all 11 design references.

The requested optional hero video was not created: the connected video workspace reported no available video models and zero credits. Existing optimized still photography remains the hero fallback; no video is simulated.

### Cinematic homepage follow-up — 2026-11-13

The home page now has three manually selected editorial scenes with coordinated photographs, alternative text, headings, lead copy and calls to action. Keyboard controls, queued rapid input, same-origin static route mapping and fine-pointer-only parallax are included; there is no autoplay. Reduced-motion behavior and layout at eight widths are covered in `test:motion`. Local PHP and static browser runs passed, and the captures are indexed in `visual-qa/README.md`. Hosted CI and publication verification are still pending for this follow-up. The later PR #17 backend suite expanded the SQLite integration count from the 102 assertions recorded above to 114; hosted run 43341069860 passed it.

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

## Booking email and migration follow-up — 2026-11-13

The follow-up found and reproduced a real upgrade defect on an existing v6 SQLite database: `bootstrap.php` could skip migration 7 because it only checked migration 6 and the order-idempotency table. Bootstrap now verifies migration 7 plus both new booking tables before skipping schema work. A v6 fixture upgrades through the real bootstrap path.

Local verification passed: 131 PHP/SQLite assertions and the browser regression over 11 routes, 20 dish details, cart/checkout, booking retry behavior, and six viewport widths. The SMTP concurrency test now gates the fake server after the first worker connects; a second worker must observe the fresh lease, and the outbox records one attempt. The Windows PHP built-in server serializes concurrent HTTP requests, so that specific race still needs the Linux multiworker CI run.

The SMTP transport was tested only against a local fake server. No real credentials or verified sender are configured. This backend follow-up does not complete the full Tokyo Premium 2.0 scope: the 11-reference pixel comparison, birthday/referral coverage, cinematic scroll story, external email, payment, live inventory and live table availability remain open. Hosted CI and the public Pages revision still need verification for subsequent code updates.

## Homepage scroll-story follow-up — 2026-11-13

Implemented a compact page-progress line, active-chapter color accents, and one-time reveals for the homepage menu, philosophy, space, Omakase and booking sequence. Image panels settle with a short scale transition; reduced-motion mode shows all content immediately and disables transitions. The static export was regenerated from PHP source. The focused motion regression passed for PHP and static modes, including chapter switching, progress updates, all reveal targets, keyboard operation, reduced motion, no autoplay and eight viewport widths. Four desktop/mobile captures are indexed in `visual-qa/README.md` and saved under `visual-qa/2.0-motion/story-*`.

This closes only the homepage scroll-story item. At that checkpoint, the full pixel comparison against all eleven approved references, birthday/referral coverage, hosted verification and external service configuration remained open; the later PHP account follow-up below updates the birthday/referral status.

## Referral and birthday follow-up — 2026-11-13

The one-invite-only referral behavior was replaced with stable inviter codes and separate referral relationship records, without a schema migration. PHP account pages preserve invitation links through registration and expose a same-origin copy link. Existing invitees can safely retry attachment; each account can attach only one inviter, and self-invitation is rejected. Verified completed-order events remain the only reward trigger, with cancellation reversal and idempotent ledger entries.

Birthday profile fields remain optional and are usable for personalized campaigns only after explicit consent. When birthday points are configured and the programme is enabled, account access inside the configured birthday window awards points once for that birthday year. A unique ledger event prevents duplicate grants; withdrawing consent clears its timestamp but retains already issued educational points. The focused PHP/SQLite suite passes 150 assertions, including referral and birthday award/idempotency scenarios. PHP lint, JavaScript syntax, and whitespace checks pass. This follow-up is pushed to the feature branch only; it is not yet merged or deployed. The full 11-reference visual comparison and broader Tokyo Premium 2.0 scope remain incomplete. There is still no configured real email sender, payment processor, live inventory, or confirmed table availability.

## Homepage GSAP integration — 2026-11-16

Added locally vendored GSAP 3.15 and ScrollTrigger to the homepage only. The scroll story now uses ScrollTrigger for one-time reveals, image settling, active chapters and reading progress; the IntersectionObserver fallback remains. Reduced-motion mode creates no ScrollTrigger instances. The static exporter copies both vendor files into `docs/assets/vendor/`.

The focused browser check passed against PHP and freshly exported static routes: all three hero scenes, keyboard controls, story progress/reveals, eight responsive widths and reduced-motion behavior. `test:visual-fidelity` passed seven route families in PHP and static modes. PHP 8.4 lint passed for `index.php`; the static exporter generated 36 pages; browser captures were saved under `visual-qa/2.0-motion/` and `visual-qa/2.0-visual-fidelity/`. This was a focused local check; no new hosted CI run, public deployment, or full 11-reference pixel comparison is claimed.
## Focused follow-up — 2026-11-16

Fixed the admin loyalty adjustment race: balance validation and the ledger write now occur inside one owned SQLite `BEGIN IMMEDIATE` transaction. A focused two-process regression passed 6 assertions, including parallel debits, overdraft rollback, balance preservation and an independent credit. The PHP account dashboard was also rebalanced to remove its narrow, stacked right rail; it now uses full-width dashboard rows, two-column content panels, a restrained local garden image and an ellipsized long email. Desktop and 390 px mobile screenshots were inspected; mobile had no horizontal overflow and no browser page errors.

Only the focused loyalty regression and page-specific browser capture were run to honor the request to shorten test cycles. The broad suites, all-reference visual comparison and publication check were not repeated in this pass. Screenshots: `project-docs/visual-qa/11-account-rebalanced-desktop.jpg` and `project-docs/visual-qa/11-account-rebalanced-mobile.jpg`.

## Personal campaign assignment — 2026-11-16

The PHP marketing admin now searches accounts by ID, name or email, and assigns active personal campaigns only when an active promo code has remaining uses. The account page shows only the assigned user's eligible codes, with copy and checkout links. Public code listings omit personal and birthday campaigns. Checkout validates the prefilling query string and the server still revalidates the promotion. The assignment helper updates unused records atomically and refuses reissue or revocation after use. Expiry is entered/displayed in the restaurant timezone and stored as UTC to match SQLite entitlement comparisons. The focused isolated PHP/SQLite regression passed 10 assertions, including an account older than the latest-200 list; syntax checks passed for changed PHP/JS files, and `git diff --check` passed. Route-level search and private-visibility assertions were added to the larger integration suite but not executed in this accelerated cycle. No browser capture, hosted CI, or public deployment is claimed. The overall Tokyo Premium 2.0 project remains incomplete.

## Configurable gift quantity — 2026-11-16

The admin-configured `max_per_order` for threshold gifts now reaches the server quote and checkout form. Both the per-order cap and live SQLite stock are enforced server-side; order items store the selected quantity and the transaction decrements stock by the same amount. The static demo continues to omit server-owned gift inventory. The focused isolated SQLite regression passed 9 assertions for multiple gifts, invalid quantities, stock bounds, persisted line quantity, transactional stock decrement, and compatibility with callers that omit `gift_quantity`. Changed PHP files passed lint under PHP 8.5.11; source/static JS syntax and diff whitespace checks passed. The broad suite, browser QA, CI and Pages publication were not run. Overall Tokyo Premium 2.0 remains incomplete.

## Set-builder draft persistence — 2026-11-16

Per-format builder drafts now survive refreshes in browser-local storage. Only IDs and quantities are retained; current menu entries and prices are reloaded for the estimate, and invalid or out-of-limit saved data is discarded. The shared cart reports whether it accepted a custom set, so the builder clears its draft only after successful transfer. The focused `npm run test:set-draft` browser check passed 16 assertions across local PHP and static modes. Broader suites, CI and public Pages were not run for this follow-up. Tokyo Premium 2.0 remains in progress.

## Omakase visual review — 2026-11-16

Captured and visually inspected the local PHP Omakase page at 1440 px desktop and 390 px mobile against `design-references/04-omakase.png`. All five catalog-backed course photos are visible after scroll reveals; mobile width is 390 px. The current composition remains intentionally distinct in section order and hero framing, and is not a pixel match. Evidence is saved as `project-docs/visual-qa/04-omakase-current-2026-11-16-{desktop,mobile}.jpg` with a page-specific comparison note. The other ten reference pages still need their own current-state comparison.

## Menu editorial follow-up — 2026-11-16

Replaced the menu hero with a new text-free, original AI-generated photograph and four optimized responsive WebP sizes, shortened its vertical footprint, and added a current-page breadcrumb. Added a two-photo editorial transition from the menu into the existing set builder, with copy that distinguishes the browser estimate from PHP's server-side price validation. The existing catalog, filters, and cart controls remain in place.

The focused `npm run test:menu-visual` check passed four PHP/static desktop/mobile cases (1440 and 390 CSS px). All four loaded the hero and both story photos, displayed six initial cards, preserved the builder link, had no horizontal overflow, and produced no browser errors. Captures were visually inspected beside `design-references/02-menu.png`; the menu composition is closer, while exact card/detail styling and the full 11-reference comparison remain outstanding. No broad suite, hosted CI, or Pages deployment was run. Tokyo Premium 2.0 remains in progress.

## Dish-detail visual follow-up — 2026-11-16

Removed the duplicate product title and oversized title band, aligned a single H1 with the product photograph on desktop, moved it before the image on mobile, retained clear quantity/favorite/order controls, and moved the educational nutrition disclosure below the action. Added a restrained chef-preparation editorial panel using an existing concept image with a clear fictional-content disclosure. The product image now uses responsive candidates and high fetch priority.

The focused PHP/static desktop/mobile browser check passed and four screenshots were reviewed. pages.php, the test script, and whitespace checks passed. The page is closer to design-references/03-dish.png, but the full 11-reference comparison and all-20-card review remain incomplete. The broader suite, CI and Pages deployment were not repeated in this accelerated pass.

## Omakase stage navigation — 2026-11-16

On mobile, the horizontal course selector exposed only part of the third stage and left the remaining stages discoverable only by swiping. The selector now becomes a two-column grid at narrow widths. A focused assertion in the existing visual-fidelity route check verifies all five tabs fit and no horizontal scrolling is required. The route passed in PHP and static mode at desktop and 390 px mobile; updated screenshots are saved under project-docs/visual-qa/. Broader checks and publication were not repeated.

## Omakase hero composition — 2026-11-16

The existing generated chef photograph now faces into the Omakase page: a route-specific class mirrors it in the hero, moving the chef to the right while preserving clear text space on the left. The lower editorial preparation photograph is unchanged. The prior mobile-stage grid remains in place. One filtered Playwright run passed in PHP and static modes at desktop and 390 px; screenshots were reviewed. The five-stage navigation and loaded course images remain intact. The overall route still differs from the reference in section order and density, so full visual acceptance remains open.

## Focused account and reviews visual refinements — 2026-11-16

The account masthead now follows the editorial photo/title/subtitle hierarchy in `11-account.png` while keeping authenticated identity, logout, and the existing orders, bookings, favorites, loyalty, address, referral and settings panels. Fresh PHP and static desktop/mobile captures are saved under `project-docs/visual-qa/account-hero-2026-11-16/`; an account-only Playwright pass checked 1440/390 layouts, a seeded static order/booking, PHP heading bounds and browser errors.

The reviews hero was shortened from an approximately 800 px opening to a 370 px image-led panel, with a single-line desktop title and tighter score band. At 390 px the five category filters wrap visibly into two columns; cuisine/Omakase filters and sorting remain operable. Four PHP/static desktop/mobile captures and comparison notes are under `project-docs/visual-qa/08-reviews-compact-hero-2026-11-16/`. The focused route checks passed; PHP lint and diff hygiene passed. The comparison is still partial: the reviews cards and video section differ from the reference, and the complete visual comparison of all eleven PNGs, broad suites, hosted CI and Pages deployment remain outstanding.

## Populated cart visual follow-up — 2026-11-16

The static populated cart now places recommendations after the order/checkout grid, compacts its hero/top spacing, and shows the red rule under “Ваш заказ”. One focused static cart browser regression passed and refreshed eight cart states; the populated desktop/mobile images were inspected against `10-cart.png`. The three item lines, double quantity, subtotal, promo/gift offer controls, fulfillment and checkout remain visible in their intended order. The PHP template passed syntax validation, but a PHP browser render was blocked by the current runtime's missing `pdo_sqlite` module. The PHP visual state is therefore unverified; the desktop hero is still taller than the reference and the mobile checkout remains long. No broad suite, hosted CI or public Pages deployment was run. Tokyo Premium 2.0 remains in progress.

## Philosophy hero visual follow-up — 2026-11-16

The philosophy hero now uses the reference's short title and italic subtitle, preserves the garden photograph, adds working links to the story and kitchen-principles sections, and places the Japanese mark where it does not cover the title or actions. A focused static browser check passed at 320, 390, 768 and 1440 px with no page overflow, console errors or failed resources; all four page images loaded in the captured desktop view. `php -l about.php` passed and the source/static CSS copies match. The PHP route was not browser-rendered. Full-page comparison remains partial: the current story/timeline and chef/ingredient section composition differs from the reference. No broad suite, hosted CI or public Pages deployment was run.

## Booking hero copy follow-up — 2026-11-16

The booking hero now uses “Забронируйте свой столик”, replacing a longer subtitle that nearly touched the right edge on phones and aligning the copy with the approved reservation concept. A route-focused static browser check passed at 320, 390 and 1440 px: hero text, button, anchor and page bounds fit, both photos loaded, and no browser errors or failed requests occurred. `php -l pages.php` passed. The PHP route was not browser-rendered, and no broad suite, CI or public deployment was run.

## Space gallery hero follow-up — 2026-11-16

The Space hero now follows the reference's “Пространство / Атмосфера современной Японии” hierarchy, has a compact desktop composition, and uses a CTA that resolves to the gallery section. The 320 px title remains in bounds. Existing zone and gallery content, fictional-data disclosure, and the explicit absence of a 360° tour remain intact. Static full-page captures and the comparison note are in `project-docs/visual-qa/space-page-refine-2026-11-16/`. This is a focused hero refinement; the complete lower-page layout still differs from the approved PNG, PHP browser rendering remains unavailable without `pdo_sqlite`, and Pages was not deployed.

The four space cards now match the reference's Sushi Bar, Main Hall, Private Room, and Summer Terrace naming and have distinct descriptions instead of duplicated filler. Their illustrative capacities are labeled as training-concept data. A focused static browser check passed at desktop and mobile; captures are in `project-docs/visual-qa/space-zones-2026-11-16/`.

## Omakase style delivery fix — 2026-11-16

`luxury-refinements.css` was present and copied by the static exporter but missing from the shared PHP page head, so the Omakase-only mirrored hero and several targeted refinements were not reaching users. The shared head now links the file; the static Omakase page was updated for local route QA. Its captured desktop hero now places the chef on the right with copy space on the left, and all five mobile course tabs display in a grid. The two-width static check passed. A fresh full Pages export and PHP browser render remain pending because this runtime cannot initialize PDO SQLite; this does not claim that the site was deployed.

## 2026-11-16 Omakase compact hero

`omakase-compact-hero-2026-11-16/` contains desktop and 320 px full-page captures, focused hero captures, and a comparison against `design-references/04-omakase.png`. The desktop hero is 460 px high and the 320 px hero is 449 px; title/copy bounds fit, five course tabs remain visible, the mirrored chef image is active, and a focused static-browser check reported HTTP 200 with no errors, failed requests, or horizontal overflow. The complete lower page remains an adaptation of the concept. PHP browser rendering, a full export, broad suite, hosted CI, and public deployment were not run.

## 2026-11-16 static account refinement delivery

The custom Pages account template had omitted `luxury-refinements.css`; the exporter now includes it and the Pages workflow checks the generated account HTML for the link. The checked-in static route mirrors the template fix. One focused local browser check passed at 1440 px and 390 px: stylesheet loaded, overview uses two columns on desktop and one on mobile, no horizontal overflow, console errors, or failed requests. Captures and a reference comparison are in `visual-qa/account-refinement-static-2026-11-16/`. This is a focused account styling fix; full profile fidelity, PHP rendering, hosted CI, and public deployment were not verified.

## 2026-11-16 menu spacing and visual comparison

`menu-visual-follow-up-2026-11-16/` contains eager-loaded 1440 px and 390 px captures compared against `02-menu.png`. The first full-page capture showed blank lower-card images because the manual screenshot did not scroll to trigger native lazy loading; the replacement capture explicitly decoded all 22 images before taking the screenshot. No asset was missing. Menu-section bottom padding is now limited to 58 px desktop / 32 px mobile. The focused static route had HTTP 200, no overflow, browser errors, or HTTP failures. PHP rendering, full interaction regression, CI, and Pages deployment were not checked.

### Menu category and set-builder shortcut follow-up

Removed the empty “Сеты 0” filter and added a direct “Собрать сет” link beside “Все блюда” in the PHP template and static export. The focused browser check captured the category rail at 1440 px and 390 px, confirmed the link is initially visible at both widths and opens the set-builder page, and found no browser errors. Crops are in `visual-qa/menu-visual-follow-up-2026-11-16/category-controls-{1440,390}.png`. PHP rendering, full suite, hosted CI and Pages deployment were not verified in this follow-up.

The menu toolbar now also has an accessible grid/list toggle, with horizontal cards at desktop and mobile widths. The focused static test covers both modes and confirms a search for the unique “Рамен с говядиной” result keeps nonmatching cards hidden in list mode. Crops are saved in `visual-qa/menu-grid-list-2026-11-16/`; PHP browser mode, broad suite, hosted CI and Pages deployment were not repeated.

Ingredient filters for tuna, salmon, eel, shrimp, scallop and roe are supported and use the current menu text/ingredient metadata; each visible filter must match at least one active dish. The scallop option is hidden because the seeded catalog currently has no scallop dish. The focused browser scenario verifies category + salmon + product-name filtering and the zero-result combination; no full-suite or PHP browser run was repeated.

## Omakase hero image follow-up — 2026-11-16

Replaced the portrait crop with an original panoramic chef-at-counter image, emitted responsive 640/1024/1920 WebP candidates from the PHP hero, and synced the static GitHub Pages route and assets. The mobile hero uses a right-anchored crop so the chef remains visible while the copy stays readable. Optimized variants total 113,524 bytes. A focused Chromium check of the static route at 1440×1000 and 390×844 passed: no horizontal overflow, no page or console errors, no failed requests, the title fits, and all five Omakase stages remain visible. `php -l pages.php`, CSS/source-export hash parity, image hash parity, and `git diff --check` passed. Screenshots and the reference comparison note are in `project-docs/visual-qa/omakase-latest-review-2026-11-16/`. PHP browser rendering, full visual acceptance, broad regression, CI, and Pages publication were not run. Tokyo Premium 2.0 remains in progress.