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

## Product pairings — 2026-11-16

Added an additive SQLite v9 migration with 40 example catalogue pairings. The insert is transactional and idempotent, preserves existing rows, and uses current dish IDs; fresh installs seed the same relations. PHP product detail routes load active paired products, respect the currently viewable catalogue (including Secret Menu restrictions), label each pairing, and fill the four-card layout with non-duplicate active menu items. The static detail exports were updated for the same catalogue defaults. Screenshot evidence and a focused visual note are in `project-docs/visual-qa/dish-pairings-2026-11-16/`.

The protected admin now groups pairings by source dish, supports catalogue-wide search, create/upsert, type/order edits and deletion. Self-links, unknown dish IDs, invalid types and out-of-range sort orders are rejected; edits to existing relations are atomic. A focused HTTP integration regression was added for create, edit, self-link rejection and delete, but could not be executed locally because PHP lacks `pdo_sqlite`.

## Secret Menu admin rule editing — 2026-11-16

Completed the existing partial Secret Menu admin flow: rules can now be edited in place in a collapsible form as well as created and toggled. Rule-local dates are parsed strictly in the restaurant timezone, enforced when access is granted, and impossible calendar values and missing rule IDs are rejected. Added integration coverage for create, edit, active/future/expired windows, strict-date rejection, form presence and disabling. PHP lint passes; the HTTP integration assertions could not run locally because the PHP CLI lacks `pdo_sqlite`. No broad tests or deployment were run.

The code-rule path also provisions an active promo code in the selected Secret Menu campaign atomically with the rule. Existing codes cannot be silently reassigned across campaigns; enabling a rule checks that its code was not separately disabled. Focused assertions cover provisioned code storage and the server unlock decision, but the PHP/SQLite integration file could not be executed in this local runtime because `pdo_sqlite` is missing.

### Account quick actions — 2026-11-16

Changed the static profile's first quick action from an empty cart to the menu, added direct access to privileges, and ordered the four actions as menu, booking, favorites, privileges. Updated the Pages export template as well. At 390 px all four controls fit one line each without overflow, and the favorites action opened the expected panel. Screenshot: [Account quick actions at 390 px](visual-qa/account-quick-actions-390-2026-11-16.png). Exporter PHP syntax and diff whitespace checks passed; the server-authenticated PHP dashboard was not changed in this slice.

### Contacts mobile map card — 2026-11-16

The desktop and mobile contacts page were compared with reference 09. The 390 px view showed the fictional destination label clipped by the schematic-map edge; mobile placement now keeps the complete card inside the map. A focused browser check found no horizontal overflow or page errors. Desktop and mobile captures plus the limited comparison are in [the contacts review](visual-qa/contacts-review-2026-11-16.md). `pages.php` lint and static CSS parity passed; no full regression or authenticated PHP browser pass was run.

## Account empty-state UX — 2026-11-16

The browser-local account no longer leaves order and booking history blank when there are no records. It explains that saved entries appear after a demo action and links to the corresponding menu or booking route; the empty favorites state also links back to the menu. No fictitious history is preloaded. Source and exported JS/CSS copies are synchronized. A focused local Chromium check covered the static account at 1440 and 390 px, verified all three links, zero horizontal overflow and no page errors; PHP rendering and full visual parity with the populated account reference remain outstanding.

Focused checks passed: PHP syntax lint for the touched PHP files, JavaScript syntax, `git diff --check`, and Python SQLite execution of `schema.sql` + `seed.sql` (40 pairing rows; `PRAGMA foreign_key_check` clear). A static browser check of salmon nigiri and its four recommendation cards passed on desktop/mobile, checked two add-to-order actions and found no page errors, failed requests, or horizontal overflow. The local PHP runtime lacks `pdo_sqlite`, so the PHP migration integration suite and authenticated PHP browser route could not run here; no broad suite, hosted CI, or GitHub Pages deployment was run. This is a focused incremental feature; Tokyo Premium 2.0 remains in progress.


## Campaign editor follow-up — 2026-11-16

Marketing campaign records can now be edited in place from the admin list with their current settings and include/exclude targets prefilled. Existing schedules are preserved for same-type edits, and active Secret Menu rules block incompatible campaign-type changes. Campaign, promo-code and collection forms share strict restaurant-timezone datetime validation and reject impossible or reversed ranges. Syntax checks passed; the SQLite regression remains unexecuted locally because `pdo_sqlite` is unavailable.


## Delivery tariff configuration — 2026-11-16

The protected site settings now expose a bounded delivery charge, and new campaigns can select the free-delivery reward; the promotion works when a positive rate is configured. A zero rate remains free and the server remains authoritative. Focused integration assertions were added; PHP syntax and whitespace checks passed, while the SQLite HTTP assertions could not run locally without `pdo_sqlite`.

## Omakase section-order follow-up — 2026-11-16

Reordered the Omakase content toward reference 04: philosophy/principles, course-card overview, set tiers, booking handoff, interactive course detail, honest still-photo editorial interlude, and closing quote. Added responsive layout for the new philosophy and reservation sections; both the PHP template and static Pages route are updated. Syntax and whitespace checks passed. The browser profile was unavailable, so this specific change has no fresh screenshot/visual review; PHP rendering and full regression remain unverified. Details: [Omakase follow-up](visual-qa/omakase-order-follow-up-2026-11-16.md).
## Mobile cart follow-up — 2026-11-16

Shortened the mobile cart hero and moved the optional checkout comment into an accessible native disclosure in both PHP and Pages templates. Lint, structural checks, CSS parity, and diff whitespace checks passed. Browser screenshots and the broad cart flow were not rerun in this accelerated pass; details: [mobile cart follow-up](visual-qa/cart-mobile-follow-up-2026-11-16.md).
## Reviews density follow-up — 2026-11-16

Made review cards denser and responsive, and added an editorial still-photo interlude with an explicit no-video disclosure. The Pages exporter now keeps the gallery anchor on the converted static route. Focused PHP lint, exporter lint, structural checks, CSS parity, and diff whitespace checks passed; no browser visual review or full reviews interaction run is claimed. Details: [reviews follow-up](visual-qa/reviews-density-follow-up-2026-11-16.md).
Omakase follow-up correction: the Pages route was opened in the in-app browser at a narrow viewport. The accessibility tree confirmed the updated section order and five stage tabs; the screenshot review caught a malformed stage heading, which was corrected in both PHP and Pages. No screenshot file was persisted, and the PHP view remains unrendered without `pdo_sqlite`.

### Philosophy editorial grouping — 2026-11-16

The philosophy page now presents its existing image and story beside the four chapter timeline on wide screens, with a two-column tablet layout and a single-column mobile flow. The timeline remains clearly labeled as fictional course history. PHP and static markup use matching section IDs and ARIA labels; the PHP templates linted, the static outline/unique-anchor check passed, and `premium.css` matches the generated Pages copy. The PHP/SQLite page render and full browser visual capture were not run in this focused pass.

### Booking room vocabulary — 2026-11-16

Unified room labels across the Space page, home and booking selectors, gallery, recommendation flow, SVG floorplan, and Pages output: `Основной зал`, `Суши-бар`, `Приватная комната`, `Летняя терраса`. Selecting a room in the booking form now updates the highlighted SVG zone and its photo; a room passed from the recommendation link is preselected in the static flow and PHP template. Older API labels are normalized for compatibility. The floorplan's existing table numbers remain stable, and server validation now accepts table 13.

A focused static browser smoke at 390 px verified the terrace query handoff/photo, changing to the private room/photo, table 10 selection, and zero JavaScript page errors. PHP lint, JS syntax, static asset equality, SVG parsing/zone mapping and whitespace checks passed. PHP/SQLite endpoint integration was not rerun in this slice; no full suite was run.

### Menu card refinement — 2026-11-16

Adjusted menu photography to wider card proportions, reduced the mobile card's redundant navigation, and made the add control a 44×44 px accessible circle with a dish-specific screen-reader label. Fixed the Pages menu head so it loads the shared refinement stylesheet. Static Chromium smoke at 1440, 390 and 320 px found all 18 cards labeled, no horizontal overflow or page errors, the add action updated the cart count, and the image ratios followed the desktop/mobile layout. Screenshot: [390 px menu cards](visual-qa/menu-cards-mobile-390-2026-11-16.png). PHP template syntax passed; PHP/SQLite, the full catalogue browser flow, broad regression and hosted Pages were not retested in this accelerated slice.

### Space-to-booking zone handoff — 2026-11-16

Added a direct “Предпочесть эту зону в заявке” action to each of the four illustrative room cards. The chosen room is carried into the booking form in PHP and GitHub Pages; static export rewrites the route, and the Pages initializer accepts only the four known room labels while preserving the existing Omakase-format handoff. Links have a 44 px minimum touch height and visible keyboard focus. Focused checks passed for PHP syntax, JS syntax, all four exported links, valid and invalid query values, source/Pages CSS and JS parity, and diff whitespace. No full browser or regression suite was run for this small change.
## Reference review: home and dish detail — 2026-11-16

Compared the home reference against the complete PHP motion captures at desktop and mobile, and compared the salmon-nigiri detail reference against the current static detail route. The apparent blank homepage sections in the untracked rough capture were caused by taking a full-page screenshot before scrolling through ScrollTrigger reveals; the existing motion regression scrolls and confirms those sections appear. The dish page keeps one genuine photo and omits unsupported nutrition claims instead of presenting a fake gallery or invented facts. Comparison notes: [home](visual-qa/01-home-reference-review-2026-11-16.md) and [dish detail](visual-qa/03-dish-reference-review-2026-11-16.md). This was evidence review only; no broad suite or pixel-diff run.

## Focused functional repairs — 2026-11-16

Fixed three verified edge cases: the Offers route was reading `type` even though its query returns `campaign_type`; assigned personal/birthday campaign cards could remain visible after their only code was disabled, expired or exhausted; and Ideal Evening treated the per-guest budget as a group cap. The exported `docs/offers.html` had a persisted PHP warning, which was removed; the source template now renders the right campaign label. Personal offer eligibility now checks active codes, code date windows and remaining uses. Ideal Evening scores against the budget per guest, presents the multiplied group allowance, and warns only when the estimate exceeds that allowance.

Verification: focused static Playwright regression passed for both static routes, clean Offers output, a three-guest/₸10,000-per-guest recommendation, the ₸30,000 group allowance, and no false budget warning. Desktop and mobile captures are in `visual-qa/ideal-evening-budget-{desktop,mobile}-2026-11-16.png`. PHP and JavaScript syntax checks plus `git diff --check` passed. SQLite-specific personal-offer regression was added but skipped locally because this PHP installation has no `pdo_sqlite`; the server-rendered personal-offer assertion was added to the existing PHP integration suite but not executed here. Full suites, hosted CI and Pages deployment were not run. Tokyo Premium 2.0 remains in progress.

## Homepage mobile composition — 2026-11-16

Applied scene-specific mobile image framing to the existing cinematic hero and compacted the homepage visit form without removing fields or changing its submission route. The salmon scene now brings the dish into view above the copy; the seasonal and chef scenes have their own focal positions. On mobile, name/phone, date/time and guest count/zone are paired; email, submit and status remain full-width.

The focused `npm run test:motion` run passed in static-only mode at eight viewport widths, including scene selection, keyboard controls, reduced motion, reveal completion and horizontal overflow checks. Screenshots are in `project-docs/visual-qa/home-mobile-booking-quickfix-2026-11-16/`. Local PHP rendering was not included because `pdo_sqlite` is unavailable. Tokyo Premium 2.0 and full 11-reference visual acceptance remain in progress.

## Booking and static-account visual follow-up — 2026-11-16

The booking hero now uses the existing sakura-lit interior image in both PHP and the GitHub Pages export, replacing the unrelated private-room image. A focused Chromium check at 390 px confirmed the hero asset loaded, the heading remained visible, page width matched the viewport, and no JavaScript errors occurred. PHP syntax and `git diff --check` passed.

The static account now places its orders and booking dashboard directly after the profile overview, moving local loyalty/referral cards below the useful history. The same order is applied by `tools/export-pages.php` and the checked-in static page. Seeded Playwright captures at 1440 and 390 px verified three local example orders, one booking request, the intended section order, no horizontal overflow, and no page errors. Updated captures: `project-docs/visual-qa/11-account-static-desktop.jpg` and `11-account-static-mobile.jpg`.

This was a focused UI slice. The PHP SQLite exporter was not run locally because the available PHP CLI lacks `pdo_sqlite`; full pixel comparison of all 11 references, hosted CI, and a public Pages update are still open. The current PR remains a draft.

## Mobile homepage hero crop — 2026-11-16

Moved the first-scene mobile image framing from 75% to 82% in both the PHP stylesheet and Pages copy so the nigiri is visible behind the hero copy on a narrow screen. Chromium at 390 px confirmed the correct image loaded, the crop rule was active, the page had no horizontal overflow, and no browser errors occurred. Screenshot: `project-docs/visual-qa/01-home-hero-mobile-390-2026-11-16.png`.

## Space page section rhythm — 2026-11-16

Reduced excess vertical padding around the room cards and gallery in the PHP and Pages stylesheets. Current static captures at 1440 px and 390 px show the section flow at 54 px and 44 px between the room cards and gallery heading, respectively; content remains aligned to its container, with no horizontal overflow or browser errors. Captures: `project-docs/visual-qa/space-gap-followup-2026-11-16/gallery-{desktop,mobile}.png`. This is a focused visual correction, not a full reference acceptance pass.

## Menu set-builder story rhythm — 2026-11-16

Reduced the three-image set-builder story from a 310 px minimum desktop frame to a compact 250 px target, tightened copy sizing, and hid the secondary platter image on narrow screens while retaining the chef image and working set-builder CTA. Static Chromium captures measured 326 px at 1440 wide and 449 px at 390 wide; CTA destination remained `set-builder-signature.html`, with no page errors, failed requests, or horizontal overflow. Captures: `project-docs/visual-qa/menu-set-story-compact-2026-11-16/menu-{desktop,mobile}.png`. This is a focused alignment improvement; the full menu reference pass remains open.

## Homepage booking conversion — 2026-11-16

The homepage reservation form now reveals contact fields only after the guest selects a date, time, party size and preferred zone. The existing server or static-demo submission path is preserved, including validation and honest pending/demo messaging; successful demo submission resets the form. One focused browser check passed on Pages at desktop and mobile widths. Evidence: `project-docs/visual-qa/home-booking-progressive-2026-11-16/`. Full PHP browser validation and public release are still pending.
