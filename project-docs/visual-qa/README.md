## Visual QA evidence

Catalog ordering and add feedback: [2026-11-19 evidence](menu-pagination-2026-11-19/comparison.md), reproduced wrong cheapest-page membership, silent partial quantity acceptance and inaccessible modal feedback; corrected desktop/mobile catalog and modal states.

Booking clock/table parity: [2026-11-19 evidence](booking-time-2026-11-19/comparison.md), reproduced elapsed-time acceptance, sticky date error and table42 rejection; corrected clock states and honest map-failure fallback in desktop/mobile captures.

Account resilience: [2026-11-19 evidence](account-resilience-2026-11-19/comparison.md), lost-draft/null-profile/blank-address/hidden-history reproduction, native two-tab and storage-failure states, transactional admin settings and inspected desktop/mobile screenshots.

Marketing integrity: [2026-11-19 evidence](admin-final-2026-11-19/comparison.md), eight reproduced failure conditions, 18 passing HTTP checks, authenticated promo/collection/inventory flows and expanded desktop/mobile promo forms.

Review composition: [2026-11-19 evidence](reviews-density-2026-11-19/comparison.md), measured before/after density and complete PHP/static desktop/mobile screenshots compared with the approved review concept.

Reviews and contacts: [2026-11-19 evidence](reviews-contacts-2026-11-19/comparison.md), reproduced draft loss/short-message acceptance, full desktop/mobile captures, local review and empty-topic states, six-width bounds and PHP/static form checks.

PHP admin/mail follow-up: [2026-11-19 evidence](admin-mail-2026-11-19/comparison.md), stale-email reproduction, pending-review filter, confirmed guest badge and cancelled admin/mobile state.

Notification persistence: [2026-11-19 evidence](notifications-2026-11-19/comparison.md), reproduced loss of read receipts and desktop/mobile account notification states after migration.

Ideal Evening: [2026-11-19 evidence](ideal-evening-2026-11-19/comparison.md), reproduced preference/guest issues, complete populated-result desktop/mobile captures and the actual plant-preference empty state.

Set-builder summary: [2026-11-19 evidence](set-builder-2026-11-19/comparison.md), before-fix values and desktop view, complete desktop/mobile layouts, the native mobile bottom bar and open-navigation state.

Offers/quote consistency: [2026-11-19 evidence](offers-checkout-2026-11-19/comparison.md), before-fix state values, complete offers and discounted-cart captures at 1440/390 px, and a paired cart-concept comparison.

Omakase browsing update: [2026-11-19 comparison](omakase-2026-11-19/comparison.md), complete 1440/390 px captures with course 2 selected, baseline capture and paired approved-reference comparison.

Latest booking/gallery follow-up: [2026-11-19 comparison](booking-gallery-2026-11-19/comparison.md), including complete 1440/390 px captures, selected-table and modal states, baseline reproduction and paired reference images.

Browser captures come from the locally served PHP preview and its generated static Pages export. `npm run test:browser` checks the 11 page types, all 20 dish details, and cart, booking, account, navigation, and menu flows. `npm run test:responsive` checks 320, 360, 375, 390, 430, 768, 1024, 1440, and 1920 px and checks text range rectangles for clipping.

- `01-home-…` through `11-account-…` are the route screenshots. The account route screenshots include the PHP account implementation; use the paired `11-account-static-desktop.jpg` and `11-account-static-mobile.jpg` when comparing the static Pages profile to `design-references/11-account.png`.
- Use `06-space-default-desktop.jpg` and `06-space-default-mobile.jpg` for the unfiltered gallery comparison. `06-space-filter-details-mobile.jpg` records the separate filter state.
- The cart state suite creates eight full-page screenshots at fixed viewport widths: desktop is 1440 CSS px and mobile is 390 CSS px (device scale factor 1). It asserts the viewport before writing each file. States are empty desktop/mobile, populated desktop/mobile, checkout validation desktop/mobile, pickup desktop, and delivery desktop. The populated view has three distinct dishes, one quantity of two, loaded food photos, the recalculated 12,700 ₸ subtotal, and three suggestions that do not duplicate cart items.
- Full-page capture helpers disable smooth scrolling, reset to scroll position zero, and render the header in normal document flow so a sticky header cannot be stitched into the middle of the evidence image. This is a capture-only override; responsive runtime tests still exercise the sticky header.
- `cart-delivery-desktop.png` is captured at 1440 CSS px with delivery selected and the required address field visible; the separate validation captures preserve the empty-address blocked state. The regression checks the viewport, form state, and that the sticky header is placed in document flow for full-page screenshots.
- `cart-hero-refinement-2026-11-17/` contains the latest eight cart-state captures. The dedicated generated hero photograph replaces a reused close-up food image and leaves dark space behind the title on mobile and desktop.
- `07-booking-heading-320.jpg`, `07-booking-heading-390.jpg`, menu-open captures, and header captures document short-screen navigation and heading geometry.

GitHub Actions uploads the JPEG and PNG evidence as `tokyo-premium-visual-qa`. The page-by-page comparison and known visual gaps are in `project-docs/final-acceptance-report.md`.

## Tokyo Premium 2.0 captures

`booking-current-2026-11-18/` contains a focused desktop/mobile booking-page capture and comparison with `design-references/07-reservation.png`. The FAQ now pairs an original labeled SVG landscape with an accessible accordion; the first answer discloses the confirmation boundary, and the email answer states the SMTP requirement. This improves the reference's lower scenic-and-FAQ composition; the page as a whole still differs and is not claimed to have pixel parity.

`12-php-*` and `12-static-*` are the current interactive feature captures generated by `npm run test:tokyo20`: offers, collection, Omakase stage selection, filled set builder, recommendation result, chosen booking-table preference, certificate preview, and custom-set cart. The booking test checks initial copy, selects a table, changes date/time/guest count/zone (which clears the preference), and selects again. The PHP and static profiles deliberately disclose their different data boundaries.

`12-php-secret-menu-unlocked-*` and `12-static-secret-menu-unlocked-*` show the interactive unlock state. In PHP, this follows a server-validated code and the cart reuses the shared catalog. Static screenshots show only a client-side preview; the data is public and does not grant server access.

The cart suite's `cart-*.png` captures contain empty and populated desktop/mobile states, validation errors, pickup and delivery. The static counterpart is prefixed `static-`. Re-run into a temporary output directory with `TOKYO_EVIDENCE_DIR` to avoid overwriting checked-in captures. These files are automated regression evidence; only the cart, set builder, offers, static account, booking heading, and booking-table selected states were visually spot-checked in the latest local pass. See `project-docs/testing-report.md` for the verification limits.

## Latest hosted account capture — 2026-11-18

GitHub Actions run [44221178924](https://github.com/altento/tokyo-premium/actions/runs/44221178924) saved `11-account-populated-desktop.jpg` and `11-account-populated-mobile.jpg` in the `tokyo-premium-visual-qa` artifact. The PHP browser scenario creates an owned test order, invokes the new server-history repeat action through the shared cart store, and captures the authenticated history at both widths. The local checkout had no Playwright package, so these release captures are retained in the hosted artifact rather than copied into this folder. They verify the tested state; they do not establish full visual parity with `design-references/11-account.png`.

## 2026-11-13 focused visual remediation

The following current-source screenshots were reviewed after fixing initial-render defects in the catalog, dish detail, Omakase and account:

- `tokyo-menu-1440-initial.png`, `tokyo-menu-390-initial.png`, `tokyo-menu-320-initial.png`: all six items on the current menu page are visible before scrolling; action links and favorite controls do not overlap.
- `03-dish-current-desktop.jpg` and `03-dish-current-mobile.jpg`: active same-category recommendations appear with their catalog photos.
- `04-omakase-current-desktop.jpg` and `04-omakase-current-mobile.jpg`: first course image and catalog-backed story are present. `04-omakase-stage-2-php-desktop.jpg` and `04-omakase-stage-2-static-desktop.jpg` show the selected second course in each delivery mode.
- `11-account-populated-desktop.jpg` and `11-account-populated-mobile.jpg`: authenticated synthetic QA account with two saved demo orders (composition expanded) and one browser-local favorite. The existing `11-account-mobile.jpg` remains the anonymous login/register state.

The focused captures are paired with automated checks in `tests/menu-visual-regression.cjs`, `tests/tokyo20-regression.cjs`, and `tests/browser-regression.cjs`. The menu check covers 1440, 390 and 320 px; the account check captures real authenticated data separately from the static browser-local profile. This is a focused re-review of four affected page types, not a new pixel-by-pixel comparison of all 11 source designs.

## 2026-11-16 focused account layout pass

`11-account-rebalanced-desktop.jpg` and `11-account-rebalanced-mobile.jpg` show the PHP account with one synthetic completed checkout. The dashboard now gives orders and bookings a wide paired row, uses two balanced columns for account panels on desktop, adds a dark garden photograph behind the profile identity and fills the tall section navigation with the same local room image. The long account email truncates visually while its full value remains in the element title and text.

The screenshots were captured in Chromium at 1440 px, 390 px and 320 px and inspected. The mobile document width equals the viewport, the main heading stays in bounds, and there were no page errors. This is a page-specific visual review, not a rerun of the broad responsive suite or a full comparison against all eleven references.

## 2026-11-17 account notifications

`11-account-notifications-unread-desktop.jpg` and `11-account-notifications-mobile.jpg` show the notifications panel with unread entries. `11-account-notifications-public-mobile.jpg` is a clean-context capture of the deployed GitHub Pages account after selecting the notifications tab. The static records are labeled as browser-local examples and state that no order is paid or sent to the restaurant and that a saved booking request is not a confirmed reservation. A public Chromium smoke check found four seeded local examples, no horizontal overflow at 390 px, and no page/console errors. The PHP account uses database-backed status changes and owner-scoped read actions; its account and SQLite behavior is covered by `tests/php_integrity_integration.php`. These captures document the notification state, not full visual parity with `design-references/11-account.png`.

## 2026-11-13 Tokyo 2.0 visual-fidelity pass

`2.0-visual-fidelity/` contains full-page PHP browser captures at desktop and 390 px mobile for home, menu, Omakase, philosophy, space, booking and contacts. The Playwright test scrolls through each page so reveal animations and lazy photos are actually exercised before capture. It separately opens the seven routes in the exported static build, checks image loading, gallery bounds, floorplan rendering, headings, overflow and browser errors.

Focused comparison notes against the approved concepts:

- **Home:** `home-hero-salmon-nigiri-v3.webp` adds a distinct editorial nigiri/chopsticks frame, with the dish to the right and calm copy space at left. The four recommendation cards are verified after a real scroll; the previous full-page capture had missed them.
- **Menu:** `menu-hero-sushi-assortment-v1.webp` now gives the catalog its own sushi platter scene instead of reusing the homepage photo. Existing searchable, filterable catalog cards remain the primary content; this is not a pixel recreation of every toolbar detail in `02-menu.png`.
- **Philosophy:** the hero now uses the garden terrace photograph to match the reference's image-led opening; existing editorial story and chef sections remain intact.
- **Space:** the gallery no longer inherits the legacy 390 px homepage-gallery height. All eight tiles fit within the gallery section, and all eight photos load. This corrects actual content clipping visible in earlier screenshots.
- **Booking:** the benefits strip uses less vertical padding. The test verifies the five-stage preference form has the rendered SVG map and labels selection as a preference, not availability.
- **Contacts:** the hero uses an exterior image, the map is explicitly a fictional schematic, and the browser test checks the local/demo contact form. The new section spacing is shorter while retaining the page's distinct sections.
- **Omakase:** five image cards give a compact overview of the same catalog-backed items shown by the interactive course selector. No fake video or invented dish data is introduced.

Evidence filenames are `<page>-php-desktop.jpg` and `<page>-php-mobile.jpg`. The static build is behavior-checked, but this pass does not claim a complete pixel-difference comparison of all 11 reference PNGs or equal visual parity for every page.

### Public post-deploy browser captures

`home-public-postdeploy-desktop.jpg` and `home-public-postdeploy-mobile.jpg` are full-page Chromium captures of the cache-busted GitHub Pages release `4256718a68f8016ff7e9b5dafb0ffa4d33a917c3`. The browser scrolled through the page before capture so reveal animations and lazy-loaded images were visible. The public route check covered home, menu, Omakase, offers, Perfect Evening, set builder, booking and account; all had a main heading and no console/page errors. The home hero image returned HTTP 200 and decoded successfully. The screenshots were visually inspected after capture; mobile width was 390 CSS px.

### Server set validation follow-up

The local PHP/SQLite integration suite includes forged-price and invalid-composition cases for custom sets, persisted component price snapshots, and rejection without partial order creation. The 114-assertion suite passed hosted CI run 43341069860 and was merged as `e09a6bd4d348211d155447622650fd52bac30e3f`; public deployment verification is tracked separately.

## 2026-11-13 cinematic homepage follow-up

`2.0-motion/` contains PHP and static screenshots for the homepage at desktop and mobile, plus selected scenes. The full-page images were captured after scrolling through the page to trigger lazy images and reveals. `story-php-desktop.jpg`, `story-php-mobile.jpg`, `story-static-desktop.jpg` and `story-static-mobile.jpg` document the coordinated scroll-story follow-up. `tests/motion-system-regression.cjs` checks both modes, three scenes, previous/next and dot controls, keyboard arrows, rapid queued input, changing links and image alt text, no autoplay, reduced motion, story-progress/chapter state, all story reveals and layout bounds at eight widths. The captures were visually inspected. This is a focused home-page review, not completion of the outstanding full comparison against all eleven design references.

## 2026-11-16 GSAP motion follow-up

The latest local `test:motion` run passed on PHP and freshly exported static pages. It verifies GSAP/ScrollTrigger initialization, no ScrollTrigger instances under reduced motion, hero scenes, scroll-story state, keyboard input and eight viewport widths. The test refreshed the current desktop/mobile and hero captures in `2.0-motion/`; the seven-route `test:visual-fidelity` suite also passed in both delivery modes and refreshed PHP captures in `2.0-visual-fidelity/`. The home desktop/mobile captures were inspected next to `01-home.png`. The page retains the separate, more restrained editorial implementation; this motion-only change does not represent a full visual redesign or pixel comparison.

## 2026-11-16 personal-offer admin follow-up

This was a server-side admin/account and SQLite flow change. The admin can search older accounts; the account page exposes only its assigned offer code and a direct checkout link; private codes are absent from the public Offers list. A focused helper regression and PHP syntax checks passed; route assertions are included in `tests/php_integrity_integration.php` but were not run in the shortened cycle. No new browser screenshot was produced, so this is functional verification context and not a visual acceptance claim.

## 2026-11-16 Omakase review

`04-omakase-current-2026-11-16-desktop.jpg` and `04-omakase-current-2026-11-16-mobile.jpg` were captured from the local PHP route after scrolling through the page so lazy images and reveals were visible. Both were reviewed against `design-references/04-omakase.png`. The current route keeps the cinematic chef opening, interactive five-stage course story, catalog photography, set tiers and clear booking action. Its editorial section order and hero composition differ from the approved concept; these captures confirm visual continuity and content visibility, not pixel-level equivalence. See `omakase-comparison-2026-11-16.md` for page-specific notes.

## 2026-11-16 menu-focused visual pass

`02-menu-current-php-desktop-2026-11-16.jpg`, `02-menu-current-php-mobile-2026-11-16.jpg`, `02-menu-current-static-desktop-2026-11-16.jpg` and `02-menu-current-static-mobile-2026-11-16.jpg` are fresh full-page captures at 1440×1000 and 390×844 CSS px. A single focused Playwright command, `npm run test:menu-visual`, checked four PHP/static and desktop/mobile cases: HTTP 200, 432 px desktop / 370 px mobile hero, loaded responsive hero, breadcrumb, six visible first-page dishes, two decoded editorial photos, working set-builder route, no horizontal overflow and no browser errors.

The new text-free, AI-generated menu photograph is exported as responsive 640/960/1280/1600 px WebP variants. The menu now has a compact image-led hero, breadcrumb, and a two-photo author-set story with a direct builder link. Compared with `design-references/02-menu.png`, the page keeps the dark/gold food-led direction and adds the missing authored-set transition; its category controls, card detail and exact section proportions still differ from the mockup. This is a focused page comparison only, not a pixel match or completion of the remaining ten-page review.

## 2026-11-16 dish-detail focused pass

Fresh full-page screenshots are saved as dish-php-desktop.jpg, dish-php-mobile.jpg, dish-static-desktop.jpg and dish-static-mobile.jpg. The single-route Playwright run checked PHP and exported static output at desktop and 390 px mobile. It confirmed one product heading, loaded dish and editorial images, and a working add-to-cart control; the run passed without browser errors or horizontal overflow.

Compared with design-references/03-dish.png, the product title now sits with the main dish image, the order controls remain prominent, and an editorial preparation image supports the story. The detail page honestly states that only one dish angle is available. This is a focused visual review, not a pixel-perfect match or a review of all 20 cards.

## 2026-11-16 Omakase mobile-stage pass

The mobile capture showed that the five-stage selector clipped the third stage and depended on an ambiguous horizontal swipe. It now wraps into a two-column grid on narrow screens, with all five choices visible before the course panel. Updated captures: omakase-php-desktop.jpg, omakase-php-mobile.jpg, omakase-static-desktop.jpg and omakase-static-mobile.jpg. The focused comparison retains the existing image-led premium direction; it is not a pixel-perfect reproduction of 04-omakase.png.

## 2026-11-16 Omakase hero composition pass

The existing original chef photograph is now mirrored only in the Omakase hero, placing the chef on the right and leaving calm copy space on the left, closer to design-references/04-omakase.png. The later editorial chef photograph remains unmirrored. The five mobile stage tabs also remain fully visible in a two-column layout. Current PHP and static captures: omakase-php-desktop.jpg, omakase-php-mobile.jpg, omakase-static-desktop.jpg and omakase-static-mobile.jpg. The wider page section order and layout still differ from the reference; this is a focused comparison, not a pixel-perfect match.

## 2026-11-16 contacts focused layout pass

`contacts-current-2026-11-18/contacts-static-desktop.jpg` and `contacts-static-mobile.jpg` are fresh full-page captures against `design-references/09-contacts.png`. The hero now has direct call and message actions: the phone target follows the configured demo number, and the second action focuses the form. The static contacts visual-fidelity route passed at 1440 px and 390 px with required-field, loaded-image, map-disclaimer, mobile-bound, and no-console-error assertions. The map remains an original decorative SVG because the address is fictional; no false real route or social destination is added. This is a focused page comparison, not a pixel match or completion of the all-11 reference review. See `contacts-current-2026-11-18/comparison.md`.

## 2026-11-16 account and reviews visual follow-up

`account-hero-2026-11-16/` contains PHP and static full-page captures at 1440 px and 390 px after adding a photo-led PHP account masthead. The authenticated greeting, email, membership date and logout remain visible; all account data panels are retained. The isolated PHP fixture had no orders or bookings, so those panels show honest empty states. The static capture uses explicitly local demo records. The account-only browser run checked both modes and mobile bounds; see `comparison.md`.

`08-reviews-compact-hero-2026-11-16/` compares the reviews page against `design-references/08-reviews.png`. A focused PHP/static route check confirmed the compact 370 px desktop/mobile hero, four displayed demo reviews, functioning cuisine filter and sort, and no browser errors or page overflow. The five mobile category filters now wrap into two columns and remain visible; a follow-up mobile-only check confirmed the Omakase filter still returns its card. The rating band and hero now follow the reference rhythm more closely. The review-card arrangement and the reference's video-review block are still different; no real review video assets are available, and this pass does not claim a full-page pixel match.

## 2026-11-16 populated cart flow layout

`10-cart-compact-2026-11-16/` contains a populated cart with three distinct catalog dishes, a double-quantity line, a matching 12,700 ₸ subtotal, recommendations, quote/gift controls and checkout fields. A single cart-only static browser regression refreshed the eight cart state captures (empty, populated, validation, pickup and delivery); the populated desktop and mobile screenshots were visually inspected beside `design-references/10-cart.png`. Recommendations now follow the order/checkout layout, so mobile checkout is not interrupted by merchandising cards. The PHP template passed syntax lint. A PHP browser capture could not run in the current runtime because `pdo_sqlite` is unavailable; this is not PHP visual acceptance. The desktop hero remains taller than the reference and the mobile checkout is still a long form; see `comparison.md`.

## 2026-11-16 philosophy hero follow-up

`05-philosophy-hero-2026-11-16/` contains focused desktop/mobile hero crops and full-page static captures. The hero now follows the reference's concise title/subtitle hierarchy and has working story/principles links. A single route check covered 320, 390, 768 and 1440 px; it verified title bounds, no horizontal overflow, no mark collision, valid anchors, four loaded content images and no failed assets/browser errors. PHP syntax lint passed, but the PHP route was not rendered in a browser. The lower page remains an editorial adaptation; see the comparison note in that directory.

## 2026-11-16 booking hero copy follow-up

`07-booking-subtitle-2026-11-16/` contains current desktop/mobile hero crops after shortening the booking subtitle to the wording in `07-reservation.png`. The static route was checked once at 320, 390 and 1440 px: title, subtitle, lead, CTA and page bounds fit; both page images loaded without browser errors or failed resources. PHP syntax lint passed; the PHP route was not browser-rendered. See its comparison note.

## 2026-11-16 space gallery hero follow-up

`space-page-refine-2026-11-16/` contains full static page screenshots at 1440 px and 320 px. The hero now follows the compact title/subtitle/CTA hierarchy in `06-gallery.png`, its desktop title stays on one line, and the CTA targets an explicit gallery anchor. The zone cards, photo filters, and honest “no 360° tour” note remain. One focused Playwright check reported HTTP 200, no console/page errors or failed requests, and no horizontal overflow at both widths; PHP lint and static/source CSS parity passed. The lower page remains an adaptation; see `comparison.md`.

`space-zones-2026-11-16/` adds fresh 1440 px and 390 px captures after matching the four zone labels to the reference and replacing repeated generic card copy with distinct descriptions. A single focused static check confirmed labels, copy, and mobile page width. Capacity values are explicitly illustrative within the existing educational-concept disclosure.

## 2026-11-16 Omakase refinement stylesheet fix

`omakase-refinement-css-2026-11-16/` records a shared-template omission: `luxury-refinements.css` was copied by the exporter but not linked by PHP pages. The shared head now loads it; the current static Omakase route also links it pending a database-backed full re-export. Focused static checks at 1440/390 px confirm the chef-facing hero treatment, all five mobile course tabs, HTTP 200 stylesheet load, no browser errors and no horizontal overflow. See the comparison note for limits.

## 2026-11-16 Omakase compact hero

`omakase-compact-hero-2026-11-16/` contains desktop and 320 px full-page captures, focused hero captures, and a comparison against `design-references/04-omakase.png`. The desktop hero is 460 px high and the 320 px hero is 449 px; title/copy bounds fit, five course tabs remain visible, the mirrored chef image is active, and a focused static-browser check reported HTTP 200 with no errors, failed requests, or horizontal overflow. The complete lower page remains an adaptation of the concept. PHP browser rendering, a full export, broad suite, hosted CI, and public deployment were not run.

## 2026-11-16 Omakase stage selector balance

`omakase-current-2026-11-16/` contains fresh 1440 px desktop and 390 px mobile static captures. The fifth course preview now centers beneath the two-column mobile selector rather than sitting alone at the left edge. The route-filtered visual-fidelity check passed for the static export: all five distinct course photographs loaded, clicking a stage displayed its matching panel, ArrowRight moved selection and focus, all tabs remained visible, and the page had no horizontal overflow or console errors. The source and exported `tokyo20.css` hashes match. PHP browser mode could not be used in this environment because its built-in server served `.php` as a download; PHP/SQLite acceptance and public deployment were not repeated.

## 2026-11-16 static account refinement delivery

The custom Pages account template had omitted `luxury-refinements.css`; the exporter now includes it and the Pages workflow checks the generated account HTML for the link. The checked-in static route mirrors the template fix. One focused local browser check passed at 1440 px and 390 px: stylesheet loaded, overview uses two columns on desktop and one on mobile, no horizontal overflow, console errors, or failed requests. Captures and a reference comparison are in `visual-qa/account-refinement-static-2026-11-16/`. This is a focused account styling fix; full profile fidelity, PHP rendering, hosted CI, and public deployment were not verified.

## 2026-11-16 account empty states

`account-empty-state-2026-11-16/` contains desktop and mobile full-page captures plus a comparison against `11-account.png`. Empty order and booking histories now provide explanatory copy and direct actions; empty favorites links to the menu. A single static Chromium check at 1440 and 390 px verified route targets, zero horizontal overflow and no page errors. The dashboard remains an honest browser-local profile and does not seed fictitious history; full reference parity and PHP route coverage remain outstanding.

## 2026-11-16 menu spacing and visual comparison

`menu-visual-follow-up-2026-11-16/` contains eager-loaded 1440 px and 390 px captures compared against `02-menu.png`. The first full-page capture showed blank lower-card images because the manual screenshot did not scroll to trigger native lazy loading; the replacement capture explicitly decoded all 22 images before taking the screenshot. No asset was missing. Menu-section bottom padding is now limited to 58 px desktop / 32 px mobile. The focused static route had HTTP 200, no overflow, browser errors, or HTTP failures. PHP rendering, full interaction regression, CI, and Pages deployment were not checked.

The category controls now omit empty categories and expose “Собрать сет” beside “Все блюда”. Cropped static captures `category-controls-1440.png` and `category-controls-390.png` show the visible shortcut on desktop and mobile. A focused browser check found zero empty category filters, loaded the linked `set-builder.html`, and reported no browser errors. The mobile category strip intentionally scrolls horizontally; the page itself remains within the viewport in the preceding full-page check.

`menu-grid-list-2026-11-16/` contains desktop and mobile crops of the new catalog list mode. The static focused test toggles both modes, checks the list card layout, and verifies that search still hides nonmatching cards in list mode.

The menu now adds six ingredient filters from the approved reference. The focused route test checks the category + ingredient + search combination and the empty state when changing category under the same filters. Current menu screenshots are refreshed by the same browser pass.

## 2026-11-16 contact visit planning strip

`contacts-visit-strip-2026-11-16/` contains the refreshed static contact page at 1440 px and 390 px. A garden image and booking CTA introduce a compact visit-planning section; transport, parking and accessibility are explicitly marked as unknown in this fictional training concept. A focused local Chromium check confirmed the image loaded, all three disclosures are present, there is no page overflow, and no console/page errors occurred. PHP browser rendering and Pages deployment were not run.

## 2026-11-16 philosophy medallions and quote band

`philosophy-visual-pass-2026-11-16/` contains updated 1440 px and 390 px captures reviewed against `design-references/05-philosophy.png`. Four principles now use gold Japanese medallions and the chef quote has a warm editorial band, closer to the reference. A focused static Chromium check confirmed all four marks, loaded photos, no horizontal overflow, and no browser errors. The full page still differs from the reference in its editorial photography and composition; no PHP browser render or Pages deployment was run.

## 2026-11-16 Omakase panorama replacement

`omakase-latest-review-2026-11-16/` contains current full-page desktop and mobile screenshots plus the focused comparison note. This supersedes the earlier mirrored-portrait hero treatment: the hero now uses a newly generated, wide chef-at-counter photograph, responsive WebP variants, and a right-anchored mobile crop. The static page was opened once at 1440×1000 and 390×844; both layouts had no horizontal overflow, the mobile title and all five course tabs stayed in bounds, and there were no page/console errors or failed requests. This is a one-page local static check; it does not claim PHP browser coverage, pixel-perfect full-page fidelity, hosted CI, or publication.

## 2026-11-16 catalog-backed product pairings

`dish-pairings-2026-11-16/` contains full-page captures of the static salmon-nigiri detail route at 1440 px and 390 px, plus a page-specific comparison note. The page shows configured active catalogue pairings (including yuzu lemonade and tuna nigiri), then fills the remaining four-card layout from active menu items. Screenshot review confirmed the images load and the mobile page stays within 390 px. The focused static browser interaction also confirmed add-to-order still increments the shared cart count. PHP browser rendering and the PDO SQLite migration regression are not claimed in this local runtime.

## 2026-11-16 explicitly seeded account demo

account-favorites-preview-2026-11-16/11-account-seeded-desktop.jpg and 11-account-seeded-mobile.jpg show the static profile after sample data is explicitly created. The dashboard now surfaces three browser-local favorites directly below order/booking history, with live catalog photos, prices and working quick-add; the full Favorites tab remains available. A focused Playwright regression passed at 1440 px and 390 px, including cart quick-add, mobile overflow and clear-data behavior. This remains a browser-local demo, empty by default; visual hierarchy still differs from the approved reference.

## 2026-11-16 homepage mobile framing

`home-mobile-booking-quickfix-2026-11-16/static-hero-mobile.jpg` and `static-mobile.jpg` show the mobile-specific image framing and compact visit form. The crop follows the active hero scene so the salmon, seasonal set and chef each stay in frame. The homepage form keeps every field; name/phone, date/time and guests/zone are paired, while email and submit span the form width.

The existing motion regression passed in static-only mode at eight viewport widths, including hero controls, keyboard use, reduced motion, reveal completion and overflow checks. The PHP version was not exercised because this local PHP CLI lacks `pdo_sqlite`. The captures document a focused homepage pass, not a full visual comparison of all 11 references.

## 2026-11-16 gallery layout and image mapping

`gallery-balanced-grid-2026-11-16/` contains full-page static captures at 1440 px and 390 px. The approved reference uses a balanced eight-image gallery; the implementation now uses four columns by two rows on desktop and two by four on mobile. Each caption/lightbox now points to its matching, distinct image. A redundant navigation script had been replacing those sources after load; that override was removed from both PHP/static script copies. A focused Chromium pass confirmed all 12 page images load, the principle strip sits outside the hero, no horizontal overflow occurs, the details filter shows three tiles while all four room cards remain, and Escape closes the lightbox without console errors. Compared with `design-references/06-gallery.png`, the grid count, order, and desktop rhythm align more closely; the composition and hero remain different, and the reference's 360°-tour artwork is intentionally omitted because no real tour asset exists. PHP rendering and Pages deployment were not run.

## 2026-11-16 eleven-route visual capture

`2.0-visual-fidelity-2026-11-16/` contains full-page desktop (1440 px) and mobile (390 px) captures for home, menu, dish, philosophy, space, booking, contacts, Omakase, reviews, cart and account. The focused static-only Playwright run covered all 11 routes across three route batches; stale contact-section and cart-fixture expectations were corrected before the affected routes passed. The test now checks `[data-story-reveal]` targets after scrolling, so a screenshot cannot silently omit those animated sections. No console/page errors or page-width overflow were reported.

The same folder includes the eight populated/empty/checkout-validation/pickup/delivery cart captures from the existing cart visual regression. The populated state has three distinct dishes, one quantity of two, decoded product photos and a displayed total matching the shared store. The homepage and populated-cart captures were opened side-by-side with `01-home.png` and `10-cart.png`; the hero/copy/feature-strip composition and the cart's fulfillment control/recommendation choices still differ from those references. These are comparative observations, not a claim of pixel parity. The PHP browser path was not run in this pass, and GitHub Pages was not deployed.

## 2026-11-16 menu and Omakase focused comparison

`visual-fidelity-followup-2026-11-16/` contains static GitHub Pages captures at 1440 px and 390 px for menu and Omakase. Both pages were visually checked alongside `design-references/02-menu.png` and `design-references/04-omakase.png`. The menu now leads with a distinct sushi assortment photograph. Omakase shows five catalog-backed course previews in its interactive selector. The focused static test checks all five distinct photo sources load, mobile tabs remain in bounds, and the “desktop” capture is actually 1440 CSS px. This pass is not full PHP coverage or full pixel parity for all 11 references.

## 2026-11-16 philosophy refinement evidence

`philosophy-refinement-2026-11-16/philosophy-static-desktop.jpg` and `philosophy-static-mobile.jpg` show the route after aligning its hero photo and editorial color rhythm with `05-philosophy.png`. The story copy now leads the photo/timeline row on desktop and mobile; the chef and ingredient sections use dark backgrounds; only the quote remains on a warm cream band. The route-specific Playwright check passed at 1440 and 390 px and asserts the entrance photograph, visible heading bounds, loaded images and no horizontal overflow or browser errors. Visible differences remain in the exact page proportions and decorative Fuji/sakura artwork; no complete 11-reference parity is claimed.

## 2026-11-16 static account hero refinement

`account-hero-refinement-2026-11-16/` contains seeded full-page static captures at 1440 px and 390 px. The desktop hero was visually compared side by side with `design-references/11-account.png`: the entrance image, title/subtitle hierarchy, and local-demo privacy explanation now follow the reference more closely. The dashboard and navigation remain structurally simpler than the approved concept; this is a focused improvement, not full account parity. `npm run test:account-demo` passed at both widths, checking the decoded image, title bounds, sample history/favorites, idempotent seeding, local-only notice, clear-data flow, and no page errors or mobile overflow. PHP rendering and public Pages were not checked.

## 2026-11-16 account tab count badges

`account-tab-count-refinement-2026-11-16/11-account-seeded-desktop.jpg` and `11-account-seeded-mobile.jpg` show the populated local-demo account after count badges were separated from the History, Favorites, and Addresses labels. I visually inspected both captures. The focused `npm run test:account-demo` pass asserts badge-label spacing and button bounds at desktop and 390 px mobile widths. The wider account dashboard remains less detailed than `11-account.png`; no PHP or public Pages check was performed.

## 2026-11-16 Ideal Evening behavior and local order labeling

`ideal-evening-budget-desktop-2026-11-16.png` and `ideal-evening-budget-mobile-2026-11-16.png` show the current static recommendation result. I inspected both captures; the mobile cost disclosure wraps inside the result card without horizontal overflow. The focused regression confirms restart gating/default values and different rankings for romantic versus special-occasion answers. `npm run test:tokyo20` also passed in PHP and static modes and checked that static form-created orders/bookings keep their local-demo badges in account history. These captures do not claim parity against the 11 restaurant-page references.

## 2026-11-16 gallery lightbox caption

`gallery-caption-followup-2026-11-16/gallery-caption-desktop.png` shows the lightbox after advancing to the second image, with the “Суши-бар” caption visible below the photo. I inspected the screenshot. `npm run test:gallery-caption` also confirmed the initial “Зал и сервировка” caption, its update on next-image navigation, Escape dismissal, and no browser errors in the static Pages route. CI is configured to run this narrow check against both PHP and Pages routes.
The paired `gallery-caption-followup-2026-11-16/gallery-caption-mobile.png` capture was also inspected at 390 px; the caption remains inside the dialog viewport.

## 2026-11-16 targeted route follow-up

`dish-related-grid-2026-11-16/` contains the dish-detail recommendation grid at 1440, 900 and 390 px after matching the CSS selector to the actual page class. `reviews-density-follow-up-2026-11-16/` contains refreshed current-export screenshots; the stale preview that prompted the review was not representative of the exported Pages route. `10-cart-compact-2026-11-16/` contains refreshed cart screenshots, including the populated state compared with `10-cart.png`. `11-account-seeded-desktop.jpg` and `11-account-seeded-mobile.jpg` show the revised account hierarchy and live local-demo points after seeding three sample orders. Focused route regressions passed; PHP browser and public deployment checks remain separate.
## 2026-11-17 booking title and motion follow-up

The booking heading repair has full-page browser captures at 320 and 390 CSS px in 07-booking-heading-fix-2026-11-17/; its focused check passed nine widths from 320 to 1920 px. The screenshot comparison confirms the heading remains one line with no hyphenation and stays inside the viewport.

The static motion suite passed after its crop assertion was aligned with the current visually reviewed 82% salmon framing. Home desktop/mobile, hero desktop/mobile and the Omakase scene captures are in 2.0-motion-2026-11-17/. Hosted PHP responsive verification is pending for the current repair.
## 2026-11-17 review load-more visibility

- Confirmed the review page showed `Показать ещё` while only four seeded demo reviews were present. The button had the `hidden` attribute, but its `.button` display rule overrode the browser default.
- Added an explicit hidden-state rule to the PHP source stylesheet and the committed Pages stylesheet. Added a Playwright assertion for the four-review state in `tests/reviews-regression.cjs`.
- `npm run test:reviews` passed against the local static route: review submission, browser-local persistence, XSS-safe rendering, clearing, unchanged public score, and hidden load-more state. Captures are in `project-docs/visual-qa/reviews-load-more-fix-2026-11-17/`.
- This is a focused review-page regression check, not full-site acceptance or a public deployment check.
- The PHP `reviews.php` route was also checked in Chromium with SQLite enabled for the isolated local server: four cards, `hidden=true`, computed `display:none`, no JavaScript errors. Screenshot: `project-docs/visual-qa/reviews-load-more-fix-2026-11-17/08-reviews-php-desktop.jpg`.

## 2026-11-17 compact menu hero

- The menu hero was oversized against approved reference 02. Reduced the desktop minimum/height scale and headline/subtitle scale while preserving the mobile composition.
- Tightened `tests/menu-visual-smoke.cjs` so it enforces a 350 px desktop ceiling and 370 px mobile ceiling; screenshot filenames no longer carry a stale date.
- `npm run test:menu-visual` passed all four PHP and static cases at 1440 px and 390 px. Measured hero heights were 333 px desktop and 369 px mobile; the hero image loaded, catalog controls rendered, and no horizontal overflow was detected.
- Current captures: `project-docs/visual-qa/menu-hero-compact-2026-11-17/` (`02-menu-current-php-desktop.jpg`, `02-menu-current-static-desktop.jpg`, and mobile counterparts). This focused pass does not complete the full 11-reference visual acceptance.

## 2026-11-17 mobile booking map order

`booking-map-order-2026-11-17/` contains full-page PHP and static mobile captures plus selected-seat desktop captures. I inspected both mobile versions: the preference map and availability disclosure precede the final submit action. `npm run test:tokyo20` passed against the local PHP/SQLite route and static export, including the 390 px geometry assertion and static `requestSubmit()` path. This is one focused booking check, not full-site visual acceptance.

## 2026-11-17 home hero reference pass

`home-hero-reference-2026-11-17/` contains PHP and static hero captures at desktop and mobile widths. The title now follows reference 01's TOKYO PREMIUM hierarchy and the hero is shorter on desktop and tablet. I inspected all four captures. `npm run test:motion` passed across PHP and Pages modes, including reduced motion and eight responsive widths. This pass covers the hero only, not full homepage reference parity.

## 2026-11-17 static account first-visit preview

The populated static Pages account after first visit is captured at `account-first-visit-2026-11-17/11-account-seeded-desktop.jpg` (1440 px) and `account-first-visit-2026-11-17/11-account-seeded-mobile.jpg` (390 px). The focused browser regression verifies sample labels, first-visit local seeding, preservation of existing profile and orders, clear-and-reload behavior, explicit restore and all thumbnails before capture. Compared with `design-references/11-account.png`, the dashboard now shows catalog-backed history, a local booking, points and favorites; the layout remains distinct from the reference.

## 2026-11-17 tablet navigation and menu comparison

`menu-header-tablet-2026-11-17/` contains the pre-change 1024 px capture and fresh 1440, 1024, 1023, 900, and 390 px captures. The 1024 px header now matches the desktop reference navigation pattern with profile, cart, and a compact booking control visible; at 1023 px the accessible mobile menu takes over. The focused static menu regression passed all five widths, verifies header bounds and no overlap, and checks opening the mobile menu, reaching booking, Escape close, and focus restoration. This corrects header collapse alone; it does not claim full page parity with reference 02.

### PHP account dashboard polish — 2026-11-17

Compact PHP account navigation, active-section accessibility state and server-status pills are captured at desktop/mobile in `account-dashboard-polish-2026-11-17/`. The comparison records remaining differences from reference 11; no pixel-perfect claim. The focused and broad local checks passed; hosted CI and publication are pending.

## 2026-11-17 dish and Omakase layout follow-up

`2.0-visual-fidelity/dish-php-{desktop,mobile}.jpg` and `omakase-php-{desktop,mobile}.jpg` are fresh captures after fixing the dish purchase-column gap and compacting the interactive Omakase story panel. The PHP and static routes passed the focused visual-fidelity regression. The updated screenshots were opened and compared with references 03 and 04. Details and the limited verification scope are in `reference-repair-followup-2026-11-17.md`; this does not close visual QA for every reference.

## 2026-11-17 PHP account density pass

`2.0-visual-fidelity/account-php-{desktop,mobile}.jpg` shows the PHP dashboard after tightening the hero and empty history states. The focused account visual-fidelity regression passed in PHP and static modes, including the desktop two-by-two metric layout and mobile overflow check. The dashboard remains richer and longer than reference 11, so this is an improvement rather than a full parity claim.- The 2026-11-17 gallery follow-up uses distinct photographs in the eight-tile gallery and checks that none repeat the four room-card photos. PHP and static screenshots are `2.0-visual-fidelity/space-php-desktop.jpg`, `space-php-mobile.jpg`, `space-static-desktop.jpg`, and `space-static-mobile.jpg`; this focused pass does not establish full visual parity with reference 06.- The philosophy follow-up adds an original enso/Fuji/sakura SVG to the chef chapter. Captures: `2.0-visual-fidelity/philosophy-php-desktop.jpg`, `philosophy-php-mobile.jpg`, `philosophy-static-desktop.jpg`, and `philosophy-static-mobile.jpg`; desktop places the art beside the chef story, mobile preserves photo → story → art.
## 2026-11-18 Omakase editorial follow-up

Compared `design-references/04-omakase.png` with fresh PHP and static captures after selecting course stage 2. The selected-stage story remains interactive but is now a compact horizontal editorial panel on desktop and a shorter stacked card on mobile; its note was shortened without removing the demo/allergen disclosure. Added an original SVG landscape to the closing parchment quote strip and darkened its text for contrast. `npm run test:tokyo20` passed for PHP and Pages, including all 21 PHP and 19 static dish routes, Omakase course/photo behavior, desktop/mobile panel-height ceilings, quote-art availability and text color, plus responsive overflow checks. Screenshots reviewed: `12-php-omakase-stage-2.jpg`, `12-php-omakase-mobile.jpg`, `12-static-omakase-stage-2.jpg`, and `12-static-omakase-mobile.jpg`. Reference rhythm is closer, though the selectable course detail remains an intentional extra section absent from the reference; full-page parity, CI and public deployment are not claimed.

## 2026-11-18 — account dashboard follow-up

Current evidence for the account layout update is under `account-dashboard-layout-2026-11-18/`: populated desktop and phone captures plus a comparison against reference 11. This follows the earlier `account-refresh-2026-11-17/` pass; the newer captures should be used for the current static layout. The narrow account regression passed and PHP syntax passed. The screenshots show a browser-local demo, not an authenticated account or live server history.

## 2026-11-18 — full static route comparison

`full-static-2026-11-18/` contains fresh full-page static captures for all 11 reference page types at 1440 px and 390 px, plus two paired contact sheets visually reviewed against `design-references/01-home.png`–`11-account.png`. Page-specific differences and limits are in that folder's `comparison.md`. The page-level static route run passed before capture. Cart screenshots in this set intentionally show an empty first visit; `cart-progressive-checkout-2026-11-17/` contains populated, delivery, pickup and validation states. This does not establish PHP rendering parity, pixel identity, or responsive acceptance at every width.

## 2026-11-18 — account dashboard addresses

`account-addresses-overview-2026-11-18/` contains fresh desktop/mobile account captures with two clearly fictional saved addresses surfaced in the overview. The address button opens the existing editor; the focused browser regression passed clear/restore and escaped-text cases. Marketing & Loyalty now has direct navigation to all nine management sections. Local PHP/SQLite integration was not available because the PHP CLI lacks PDO SQLite.
