# Tokyo Premium 2.0 — verification record

## 2026-11-25 — site finish candidate

Built from `main` at `102750d` in an isolated worktree. `node tests/visual-fidelity-regression.cjs` passed all 11 route types in PHP and static Pages modes. Focused checks passed for menu state in both modes; menu visual behavior in both modes at 320, 390, 768/900, 1023/1024, 1440 and 1920 px; the progressive home-booking flow at desktop/mobile; five Omakase courses in PHP/Pages; browser-only account data; autoplay/manual pause/reduced-motion behavior in PHP/Pages; PHP/Pages booking map; philosophy at six widths; and all 50 public dish details in PHP and Pages at six widths.

`tests/php_integrity_integration.php` passed 236 assertions using a temporary PHP config that loaded the installed PDO SQLite extension; its temporary database was isolated. The CLI demo seeder was separately checked on a fresh throwaway SQLite file: it created 1 user, 12 orders, 8 bookings, 10 contact messages and 6 total reviews, returned zero foreign-key errors, and a second run left counts unchanged. The test file and captured one-time password were removed. The local review database independently has schema v22, 52 dish rows (50 returned by the public `dishes()` helper), two active demo collections with ten dish links, 12 orders, 8 bookings, 10 messages and 10 reviews; `PRAGMA foreign_key_check` returned no rows.

Changed PHP files passed `php -l`; changed JS files passed `node --check`; `git diff --check` passed. One early menu-state run reported a 320 px overflow assertion immediately after navigation; an isolated state run, a full PHP+Pages rerun, and separate seven-width visual runs passed, with the measured document width equal to the 320 px viewport. No persistent overflow was reproduced. PHP syntax/integration and local rendered routes were checked; this candidate has not yet been pushed, so GitHub Actions, the public Pages release, and SprintHost are not verified by this record.

## 2026-11-24 — продолжение с GitHub main, фото каталога и Pages

Исходная точка — чистый `origin/main` на `8e745b31bf59524b5d5e9f25647b4d7bd896db74`; три последних CSS-файла уже были в Git. Добавлена миграция v21 для исправления старых путей фото оякодона и холодной сенчи. На копии базы проверено, что миграция сохраняет пользовательское фото, если путь уже меняли в админке. Два новых WebP — отдельные оригинальные изображения, 145–185 КБ каждый.

Полный `npm run test:browser` прошёл: 11 маршрутов, 52 PHP-страницы блюд после серверного unlock, PHP и статическая корзина/checkout, бронирование, профиль, отзывы, каталог и шесть ширин. `npm run test:tokyo20` прошёл в PHP + Pages (52/50 страниц блюд и основные сценарии предложений, Omakase, профиля и бронирования). `test:review-contact-forms`, `test:reviews`, `test:admin-marketing-browser` и `test:admin-booking-browser` также прошли на временных/демонстрационных данных; SMTP не запускался. PHP lint и diff-проверка прошли.

Исправлены три найденных дефекта: Pages export пропускал `pages-demo.js` после cache-busted `pages-extra.js` и отправлял checkout в несуществующий API; пагинация меню давала горизонтальный overflow на 320 px; браузер запрашивал отсутствующий favicon. Два визуальных замечания перепроверены: hero философии уже содержит фото, а страница блюда уже показывает подробную карточку с галереей и составом. Скриншоты, включая выбранные booking/Omakase/admin состояния и оба новых блюда на desktop/mobile: [результаты visual QA](visual-qa/resume-audit-2026-11-24/comparison.md).

Ограничения: дополнительная проверка каталога нашла повторно используемые пути у других существующих позиций; в этом изменении исправлены только два подтверждённых неправильных фото. В Chromium этой cloud-среды недоступны Google Fonts, поэтому применялись запасные системные шрифты. Прямой запрос к `altento.github.io` заблокирован egress policy; публикацию проверяем по GitHub Actions отдельно.

## 2026-11-24 — меню до 50 позиций, визуальный проход и перенос проекта

PHP-миграции v19–20 проверены на новой временной SQLite-базе: `dishes()` вернула 50 публичных позиций из 8 категорий. Та же локальная версия на `127.0.0.1:8081` открыта в браузере: меню показывает счётчик 50; Omakase показывает пять выбираемых этапов и три формата; главная сохраняет полноэкранный hero и подсказку прокрутки. Мобильные снимки меню, Omakase и главной осмотрены на встроенном браузере. `php -l` прошёл для `bootstrap.php`, `lib/commerce.php` и `tools/export-pages.php`; `node --check` прошёл для изменённых `luxury.js` и `pages-extra.js`; `git diff --check` чистый. Полный Playwright-набор и опубликованный Pages в этом проходе ещё не проверялись. Дополнительные 29 карточек пока используют наиболее близкие существующие фото; уникальные фото для них остаются ограничением. Подробные шаги переноса и запуска: [`continue-on-another-computer.md`](continue-on-another-computer.md).

## 2026-11-23 — Homepage cadence follow-up

A desktop-only CSS pass preserves all homepage sections while reducing oversized recommendation cards, editorial blocks, room story and booking padding. At 1440 px the full page fell from 2841 to 2617 px; the 390 px layout remained 4708 px. `tests/visual-fidelity-regression.cjs` passed locally and on public Pages at both widths; no overflow, failed requests or page errors were found. It now waits for `DOMContentLoaded`, scrolls and decodes images before checking reveal visibility; this avoids a `networkidle` timeout on the public host. Captures and measured remaining reference gap: [`home-final-cadence-2026-11-23`](visual-qa/home-final-cadence-2026-11-23/comparison.md).

## 2026-11-23 · Omakase capture correction

The 23 November full-page parity screenshot initially omitted the offscreen lazy-loaded chef photograph because that capture did not scroll or decode it before taking the image. The page itself was not broken. Retook the static export at 390 and 1440 px using `test:omakase-focused`; added an assertion that the backstage image is loaded and visible before capture. The focused run passed five-stage selection, keyboard navigation, all three booking-format handoffs after refresh, reduced motion, and both widths. Corrected screenshots and the retained original capture artifact are in `visual-qa/static-export-parity-2026-11-23/`. No production code changed; PHP and public Pages were not re-tested in this documentation/evidence pass.

## 2026-11-23 · Philosophy desktop spacing

Focused `tests/philosophy-visual.cjs` passed on the checked-in Pages export and the cache-busted public route at 390 px and 1440 px. All page images decoded; headings remain within bounds; all four crimson rules are visible; there is no horizontal overflow or page error. Fresh metrics show 2864 px desktop height versus the previous saved 3065 px baseline; mobile remained unchanged at 5821 px. Commit `0d6988e` deployed successfully in [Actions run 45171370418](https://github.com/altento/tokyo-premium/actions/runs/45171370418). Captures and reference notes: [`philosophy-density-2026-11-23`](visual-qa/philosophy-density-2026-11-23/comparison.md). PHP rendering was not tested in this pass.

## 2026-11-19 · Catalog sort/pagination and modal cart feedback

New `test:menu-state` passed in PHP/static: complete ascending/descending/name order across every page, default restoration, trimmed search/category/sort composition, favorite refresh without page reset, empty states, ARIA/focus and six widths. Modal status is included in Chromium's accessibility tree, mobile feedback fits, Escape restores trigger focus, and detail/quick-view over-cap additions preserve the cart rather than silently adding part of the requested quantity. Existing menu visual smoke passed seven sizes in each mode; Tokyo 2.0 passed PHP + static (21/19 detail routes), and cart-store passed 14 checks. Static export produced 37 pages; JS syntax/diff checks passed. No new local PHP integration run was needed for this JS-only behavior change; the configured PHP checks still run on deployment. [Evidence](visual-qa/menu-pagination-2026-11-19/comparison.md).

## 2026-11-19 · Future visit validation and 42-table parity

New backend suite passed 64 deterministic/HTTP assertions for restaurant-clock boundaries, equality/past rejection, leap/calendar/365-day limits, all SVG table boundaries/zones, valid table42 persistence, unknown/mismatched table rejection, blank-zone inference, CSRF and idempotency/no-side-effect errors. Browser checks passed PHP/static home/full-form rollover, corrected dates, differing browser timezone, leap day, elapsed slots, final submit-time checks, reset, success persistence and six-width time-hint bounds, plus map-failure fallback. Existing home-step, notification-identity, floorplan and 14 cart checks passed. Combined PHP/static Tokyo 2.0 passed; after table validation changed, PHP mode and consolidated 229 PHP/SQLite assertions passed again. Syntax, diff hygiene and a 37-page static export passed. Booking fixtures that test other behaviors now choose tomorrow rather than accidentally relying on an elapsed same-day default. [Evidence](visual-qa/booking-time-2026-11-19/comparison.md).

## 2026-11-19 · Account resilience and transactional settings

New account browser regression passed dirty-draft preservation, native two-tab sync/conflict notice, corrupted profile shapes, failed storage writes/read receipts/clear, address whitespace/duplicates/capacity, stale-index deletion and six-width bounds. It checks that exactly one account panel is visible. Existing account-demo and notification-identity suites passed. New admin-settings HTTP regression passed 26 assertions (auth/CSRF, omitted keys, full payload, types/UTF-8/length/fee bounds and injected database rollback). Consolidated PHP/SQLite integration passed 229 assertions; syntax and diff hygiene passed. Static export produced 37 pages. [Evidence](visual-qa/account-resilience-2026-11-19/comparison.md).

Tokyo 2.0 passed in the combined PHP + static run, including 21 PHP and 19 static detail-route/add-to-cart checks and the existing ordering, profile and interactive flows. The normal fast deployment workflow now includes the focused settings regression; the account browser regression is in optional full QA.

## 2026-11-19 · Marketing mutations and review density

New isolated HTTP/SQLite marketing regression: 18 assertions passed, following a recorded pre-fix run with eight failures. Authenticated Chromium passed stock preservation with an intervening consumption update, promo create/duplicate/edit/disable, collection create/edit/membership, keyboard details controls and six widths. Existing gift quantity (19), pricing (6), loyalty-tier admin (8) and consolidated PHP integration (229) assertions passed. The old tier harness was updated to follow the existing successful POST/redirect/GET flow instead of expecting the HTML in the POST response. Reviews/form regression and four-seed review regression passed, including text clipping, local state, red rules and preserved filtering after the card layout change. Export produced 37 pages. [Admin evidence](visual-qa/admin-final-2026-11-19/comparison.md), [visual measurements](visual-qa/reviews-density-2026-11-19/comparison.md).

Cart-store checks passed 14/14. Tokyo 2.0 passed in separate PHP and static mode runs, including 21 PHP/19 static detail-route checks and interactive commerce/layout scenarios. The initial combined run failed on one transient static console connection-refused resource; it was not counted as a pass. Both modes then passed independently. No Lighthouse or provider/SMTP live transaction was performed.

## 2026-11-19 · Review/contact lifecycle and validation

`test:review-contact-forms` passed delayed real PHP response/edit scenarios for both forms, unedited reset, error preservation, Cyrillic min/max boundaries (review 19/20/1000/1001 and contact 4/5/1000/1001), static short/whitespace rejection, browser-local saving, safe review display/navigation, persistence, clearing, sorting and a simulated empty moderated topic. Both routes passed document, heading-text-fragment/ancestor-clipping and form-control bounds at 320/390/768/1024/1440/1920 px. Existing `test:reviews` passed; consolidated PHP/SQLite integration passed 229 assertions. Static export produced 37 pages; changed PHP/JS syntax and diff hygiene passed. Full-site motion/commerce suites were not repeated for this review/contact-only change. [Screenshots and scope](visual-qa/reviews-contacts-2026-11-19/comparison.md).

## 2026-11-19 · Admin booking/email integrity

Reproduced a cancelled booking's old confirmation consuming an SMTP attempt on retry, using only loopback port 1. Added v17 outbox state/migration and booking-state checks. Passed 21 focused migration/cancellation assertions, 229 isolated PHP/SQLite integration assertions (including local fake SMTP/leases), authenticated admin/guest browser flow, Cyrillic search, pending-review dropdown, confirmation/cancellation badges and two durable notifications. PHP-mode Tokyo 2.0 regression passed 21 detail routes and interactive/cart flows; static export produced 37 pages. The first full PHP invocation exposed child-process extension configuration on Windows; rerunning with a temporary PHPRC loaded SQLite for all workers. [Evidence and boundaries](visual-qa/admin-mail-2026-11-19/comparison.md).

## 2026-11-19 · Local notification identity

Reproduced a read booking reverting to unread when another booking was prepended (1 read became 0 read/2 unread). `test:notification-identity` now passes legacy receipt migration, durable IDs, prepend/reorder/reload, malformed storage, newly submitted local-booking metadata and desktop/mobile layout. Existing account-demo regression passed, including escaping, preserved data and clear-data behavior. Tokyo 2.0 PHP/static regression passed 21 PHP/19 static product routes and interaction flows. Export: 37 pages. [Evidence and limitations](visual-qa/notifications-2026-11-19/comparison.md).

## 2026-11-19 · Ideal Evening preferences

Reproduced plant preference returning miso with dashi plus tuna nigiri, and empty guest input allowing progression. Focused PHP/static checks now pass conservative ingredient screening/empty state, guest validation, edit preservation, focus/reset, cart batching, budget warning and booking prefill. Synthetic catalog cases separately verify dessert classification and stale pairing-type rejection; they do not claim real menu availability. Six-width text bounds/overflow and full desktop/mobile captures passed after waiting for viewport style recalculation. Tokyo 2.0 regression passed 21 PHP/19 static detail routes and interactive/cart flows; final typography/contrast/grid changes were then checked by the focused suite. Export: 37 pages. [Evidence and limitations](visual-qa/ideal-evening-2026-11-19/comparison.md).

## 2026-11-19 · Set-builder summary

Released as `ffd6c59`; [Actions 44475808433](https://github.com/altento/tokyo-premium/actions/runs/44475808433) passed build/deployment. Public Chromium passed summary/reload, 12,700 ₸ cart transfer, draft clearing without cart loss and mobile menu/bar behavior at 390/1440 px, without page errors or order submission.

Reproduced three selected dishes with a valid composition but sidebar total 0 ₸, count 0/8 and no progress value. The builder now updates summary elements in the shared layout rather than searching only inside its catalog. Extended `test:set-draft` passed in PHP/Pages for price/count/progress, reload, per-item/portion/distinct limits, clear, cart transfer, invalid draft IDs and preserving cart contents when a draft is cleared. Six-width checks include heading bounds, overflow, mobile summary navigation and hiding the bottom bar with open navigation. Tokyo 2.0 regression passed (21 PHP/19 static details plus cart and interactive flows); final price wrapping and menu-overlay checks were verified by the focused suite. Export produced 37 pages. [Reproduction and visual evidence](visual-qa/set-builder-2026-11-19/comparison.md).

## 2026-11-19 · Offer quote consistency

Released as `5946e9c`; [Actions 44471466815](https://github.com/altento/tokyo-premium/actions/runs/44471466815) passed build and deployment. Public Chromium passed promo-to-cart, edit invalidation, removal/reset/refresh and recalculation without a code at 390/1440 px; no page errors or public order submission.

Reproduced an old discount remaining visible after promo editing and a delayed PHP quote restoring the old total after a cart edit. The focused PHP/static regression now passes code-to-cart navigation, invalid-code edits, fulfillment changes, removal, native reset, refresh and six widths; its PHP route test releases an old server response after a cart edit and checks valid gift options remain usable after selection/quantity changes. Gift-option rendering in that last test uses explicit mock API responses; it is not an inventory integration claim. Tokyo 2.0 regression passed against PHP and current Pages export (21 PHP/19 static product routes and cart/interactive flows); the final removed-code reset edge was then checked by the focused suite. Export produced 37 pages, with the new promo query mapped to a static route. [Reproduction and screenshots](visual-qa/offers-checkout-2026-11-19/comparison.md).

## 2026-11-19 · Omakase sequential browsing

Released as `4a2c43b`; [Actions 44469632236](https://github.com/altento/tokyo-premium/actions/runs/44469632236) passed build and deployment. Public Chromium checks passed all five sequential stages and seasonal booking/refresh at 390/1440 px without page errors or public submissions.

Focused PHP/static checks passed: five distinct catalog photographs; sequential controls and endpoint disabled/focus states; tab keyboard navigation; progress/status updates; all three format links and refresh; six widths (320–1920 px), heading text bounds, no horizontal document overflow; reduced/normal-motion interaction; no page errors or failed HTTP responses. Tokyo 2.0 regression passed against the current PHP app and current static export, including 21 PHP/19 static detail routes. Export produced 37 pages; PHP/JS syntax and diff checks passed. A stale local static-server instance was detected and stopped before accepting the final results. [Screenshots and comparison](visual-qa/omakase-2026-11-19/comparison.md).

## 2026-11-19 · Booking reset and gallery albums

Released as `35bbec6`; [run 44467862326](https://github.com/altento/tokyo-premium/actions/runs/44467862326) passed build and deployment. Fresh public Chromium checks passed booking reset and gallery album/filter behavior at 390/1440 px without page errors. No public data was submitted.

Reproduced stale table/photo/highlight after native form reset and stale room-preview captions after opening a gallery image. Fixed both and extended the existing floorplan and gallery regressions; both pass in PHP and Pages modes, including focus restoration, filtered album navigation and horizontal/vertical gesture distinction. Added a layout check at 320, 390, 768, 1024, 1440 and 1920 px in both modes, covering actual heading/caption text bounds, photo decoding and form alignment. This found and fixed a clipped mobile gallery caption. Tokyo 2.0 regression also passed (21 PHP/19 static detail routes, cart and interactive modules). [Captures, reproduction and reference comparison](visual-qa/booking-gallery-2026-11-19/comparison.md).

## 2026-11-19 · Menu and product detail

Focused menu coverage includes seven widths, original-order restore, combined search/category/favorites/sort, quick-view add and mobile navigation in PHP and static modes. Product checks passed for 19 public detail routes in each mode, real photo switching, quantity/add and six widths; no page errors or missing assets. Export produced 37 pages. Full-page images and reference comparisons: [menu/dish evidence](visual-qa/menu-dish-2026-11-19/comparison.md). Restricted Secret Menu routes and live payment were outside this scoped check.

## 2026-11-19 · Account and cart follow-up

Focused account and eight-state cart browser regressions passed after fixing overlapping order headers, dashboard alignment, a too-tall masthead and recommendation tap targets/flow. Screenshots and paired approved-reference comparisons were inspected: [evidence](visual-qa/account-cart-2026-11-19/comparison.md). The broader Tokyo 2.0 run exposed missing exported dish CSS; the exporter now copies asset stylesheets and checks local stylesheet references. The corrected PHP/static Tokyo 2.0 regression passed, including 21 PHP/19 static dish routes and add-to-cart actions. Export produced 37 pages; changed PHP/JS syntax and whitespace checks passed.

## 2026-11-19 · Philosophy visual follow-up

PHP and exported Pages layouts passed focused checks at six widths (320–1920 px), including heading text bounds, loaded photographs, overflow and page errors. Export produced 37 pages. The approved reference, complete desktop/mobile captures and paired comparison were inspected; the page remains taller and less decorative than the approved PNG. Evidence: [philosophy comparison](visual-qa/philosophy-2026-11-19/comparison.md). No broad site-wide suite was repeated for this scoped copy/CSS update.

## 2026-11-19 · Payment integrity and home hosting

Local checks passed: 27 isolated payment/router HTTP checks, 229 PHP/SQLite integrity assertions, 19 admin-search/referral assertions, 15 admin set-format assertions and 14 cart checks. PDO SQLite is installed locally and was enabled explicitly. Playwright MCP did not return a result; no fresh visual verification is claimed. Full details and remaining limitations: [final-pass-2026-11-19.md](final-pass-2026-11-19.md).

## 2026-11-18 booking email triage in admin

Commit `8867502` adds an actionable `booking_emails` filter from the dashboard mail KPI. It lists bookings with pending or failed outbox mail and leaves the existing retry controls available. `php -l admin.php` and `git diff --check` passed. Hosted run [44274931548](https://github.com/altento/tokyo-premium/actions/runs/44274931548) passed targeted PHP/SQLite and admin/account regressions, syntax, smoke checks, static export, and Pages deployment; the long browser suite was skipped by the fast publish profile. Cache-busted public home, account, and dish routes returned HTTP 200. The PHP-only admin itself is not hosted on GitHub Pages.

## 2026-11-18 fast publish profile and admin/account/dish update

Public commit `5af1fc8` passed GitHub Actions run [44271707784](https://github.com/altento/tokyo-premium/actions/runs/44271707784): cart state, PHP/SQLite integrity, loyalty ledger, account profile, gift quantity, personal offers, commerce pricing, Cyrillic admin/account search, set-format administration, PHP syntax, PHP form/admin smoke, static export, and Pages deployment. Chromium installation and the long browser/visual suite were skipped; those checks remain available via manual `workflow_dispatch` with `full_qa=true`. Cache-busted public home, account, and dish routes returned HTTP 200; account and dish markup included their scoped styles. This is not a claim that the full browser suite ran on this release.

## 2026-11-18 admin login protection and booking map

Commit `59b9258` adds a transactional SQLite login cooldown (five failures per IP per 15 minutes and an account-level distributed-guessing limit), migration v15, and a wider desktop booking floorplan. Local PHP lint and `git diff --check` passed. The local PHP CLI lacks PDO SQLite, so the database/HTTP regression ran once in hosted CI instead of being rerun locally. GitHub Actions run [44263546045](https://github.com/altento/tokyo-premium/actions/runs/44263546045) passed PHP/SQLite integrity, admin regressions, PHP syntax, browser regression/visual QA, static export and deployment. A cache-busted published booking page returned HTTP 200 at 1440 px and 390 px with no browser errors or horizontal overflow. Screenshots and the scoped reference comparison are in `visual-qa/booking-floorplan-2026-11-18/`. This does not complete the broader visual or Tokyo Premium 2.0 acceptance work.

## 2026-11-18 account dashboard export and release

The PHP account layout and Pages dashboard export were aligned, and the exporter was corrected to preserve the new masthead and emit the loyalty summary once. Focused local checks passed: `php -l tools/export-pages.php`, `git diff --check`, and `npm run test:account-demo` against the exported static page. Hosted run [44234776222](https://github.com/altento/tokyo-premium/actions/runs/44234776222) passed PHP/SQLite checks, browser regression for 11 routes and 21 details, Tokyo 2.0 interactions, responsive/visual-fidelity checks, account-demo tests, static export (37 pages), and Pages deployment. The public `/account.html` returned HTTP 200 with the expected dashboard wrapper. Runway and payment/email/live-seating configuration remain unavailable; complete manual comparison against all 11 design PNGs remains open.

## 2026-11-18 Set-format administration

PHP syntax passed for admin-marketing.php and the focused regression. The local test runner explicitly skipped its SQLite assertions because this PHP installation has no pdo_sqlite driver. Hosted GitHub Actions run [44211881185](https://github.com/altento/tokyo-premium/actions/runs/44211881185) installed SQLite and passed the focused regression, PHP/SQLite integrity suite, browser/visual QA and deployment for commit `7d6e9e147683d3e4a7ee16efc5d72983ec59633b`.

The focused regression covers anonymous rejection, CSRF, create/edit, duplicate-slug and bound validation, transaction rollback, activation visibility in the PHP set builder, and protection against disabling the last active format. The public set-builder page returned HTTP 200 after deployment. The PHP-only admin route cannot run on GitHub Pages. Overall Tokyo Premium 2.0 acceptance remains incomplete.
## 2026-11-18 Tokyo Privilege ledger

The focused PHP/SQLite regression passed **13 assertions** with PHP 8.4.25 and `pdo_sqlite`; it covers search (including hash-prefixed operation IDs), pagination, current balances, order/referral links, operation types, Asia/Qyzylorda inclusive date boundaries, literal wildcard handling, invalid calendar dates, escaped user text, and mobile scroll guidance. PHP lint passed for both the admin route and regression script. Playwright rendered the protected synthetic preview at 1440 px and 390 px: both reported `documentElement.scrollWidth === viewportWidth`, 25 rows on the first page, no browser errors; the mobile table uses a 900 px internal scroll area in its 340 px container and the visible hint appears only at the mobile breakpoint. The mobile table was captured at both horizontal ends. Evidence is under `project-docs/visual-qa/admin-loyalty-ledger-2026-11-18/`. Hosted run [44196190407](https://github.com/altento/tokyo-premium/actions/runs/44196190407) passed the full build/browser/visual gate and deployed the Pages artifact. Public Chromium checks returned HTTP 200 on home and Offers at desktop/mobile sizes with no page/console errors or horizontal overflow; PHP admin routes correctly remain unavailable on the static host. This focused feature check is not a full Tokyo Premium 2.0 acceptance pass.

## 2026-11-17 account notifications

PHP order and booking status changes now create durable, owner-scoped account notifications in the same database transaction as each real status transition. Repeating an unchanged status creates no duplicate notification. The authenticated account can mark its own notifications read through a CSRF-protected action. In GitHub Pages, the notification panel reflects only browser-local demo orders and booking requests; it explicitly says those records are not sent to the restaurant and do not confirm a table.

Focused verification passed: `npm run test:account-demo` covered local notification rendering, read state across reload, clear-data behavior, safe rendering of hostile text, and desktop/mobile captures. `tests/php_integrity_integration.php` passed 214 assertions using PHP 8.4 with SQLite, including notification ownership, status transitions, duplicate suppression, account rendering, CSRF-protected mark-read, booking confirmation, and the existing mail outbox checks. Locally, `npm run test:browser` passed across 11 routes and 21 dish details, and `npm run test:visual-fidelity` passed for the PHP account route with its seven navigation links and notification-first scroll state. PHP lint and `git diff --check` passed. Hosted run `44127863335` completed successfully, including the full browser/visual job and deployment. A public Pages Chromium smoke check returned HTTP 200 for commit `de4d3cc`, showed four local demo notices at 390 px, found no horizontal overflow or browser errors, and confirmed the non-confirmation disclosure. Public capture: `11-account-notifications-public-mobile.jpg`; local captures: `11-account-notifications-unread-desktop.jpg` and `11-account-notifications-mobile.jpg`. Earlier workflow runs `44123614560` and `44125976757` exposed stale account layout assertions; both were corrected before the successful run.

## 2026-11-17 contacts artwork and cart recommendations

Replaced the repeated terrace photo in the PHP and Pages contact visit block with an original 1600 × 800 WebP landscape illustration (154 KB), captioned to clarify that it is not the restaurant location. The cart now shows four catalog-backed suggestions in a four-column row across the full cart width, after the order and checkout columns. Updated the existing cart visual regression expectations for this layout.

Focused static Chromium verification passed: three cart products, four distinct suggestions, loaded food and contact images, full-width recommendation row, and no JavaScript errors. At 430 px the suggestions use two columns; at 390 px they use one, with no horizontal overflow at 390 or 320 px. PHP/JS syntax and diff checks passed. Desktop cart and mobile contact captures plus notes are in `visual-qa/cart-contact-refresh-2026-11-17/`. PHP browser rendering, full cart screenshot suite, PHP/SQLite integration, CI and public Pages deployment were not run for this change.

## 2026-11-17 account-history layout follow-up

Changed the populated account history to use the full dashboard width, with booking requests below the orders. Local Chromium regression passed against the checked-in Pages export at 1440, 390 and 320 px; it also covered local sample persistence, favorite-to-cart, clear-data and sample restoration. Browser console/page errors were empty. Manually captured desktop and mobile history-panel evidence is in `visual-qa/account-order-flow-2026-11-17/`; these are panel captures, not full-page parity proof. The focused test skipped its expensive full-page screenshot step for this local run. PHP syntax, JavaScript syntax and diff hygiene are checked before commit. No PHP/SQLite integration, hosted CI, 11-reference visual acceptance, or public deployment is claimed for this follow-up.

## 2026-11-17 static account dashboard reference pass

Compared the populated GitHub Pages account with `design-references/11-account.png`. The masthead, browser-local identity cue, privilege cards, metric row and quick actions now use a tighter dashboard rhythm; order and favorite content remain visible and functional. Responsive actions use four desktop columns and two phone columns. Source account CSS and the checked-in Pages copies are synchronized so a future export retains the same styling.

`npm run test:account-demo` passed against `http://127.0.0.1:8765`. The focused regression checked 1440 px masthead geometry, four/two-column quick actions, 390 px and 320 px layout bounds, local record labels and persistence, loaded order/favorite photos, favorite-to-cart, clear-data and sample restoration. Desktop and mobile captures were visually inspected in `visual-qa/account-refresh-2026-11-17/`. This is progress on reference 11, not complete pixel-level parity.

## 2026-11-17 cart checkout and booking notification follow-up

The cart checkout/contact section is now a native disclosure to keep the populated order summary compact. PHP and Pages markup use the same structure; the delivery/pickup selector and total remain visible. The browser regression opens the disclosure before filling checkout fields, then checks pickup reset, repeated delivery validation, and state after route changes/reload. Restaurant booking notification email status and retry are now visible in the admin booking row; the PHP integration suite includes an outbox fixture and retry-action assertion.

Focused verification: `npm run test:cart-visual` passed and captured eight desktop/mobile cart states under `visual-qa/cart-progressive-checkout-2026-11-17/`. `npm run test:browser` passed across 11 routes, 20 dish details, cart/checkout, favorites, navigation, filters, and six viewport widths; captures are under `visual-qa/cart-checkout-browser-2026-11-17/`. `npm run test:tokyo20` then passed in PHP and static modes after updating its checkout scenario to expand the disclosure. PHP syntax, JS syntax, and `git diff --check` passed. Hosted CI passed the PHP/SQLite suite with 198 integration assertions but caught the collapsed-checkout expectation in the Tokyo 2.0 browser test; that test now passes locally and is pending the next hosted run. This machine's PHP CLI lacks `pdo_sqlite`, so it cannot run the integration suite locally.

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

Ingredient quick-filters combine with category and text search. The focused menu test verifies category, ingredient, and text-filter combinations. In the 2026-11-17 follow-up, a photographed scallop nigiri was added through SQLite migration v10; the scallop filter is now visible and returns that dish in PHP and Pages modes. The same focused Playwright run checks 1440, 1024, and 390 px, including the three-column menu grid at 1024 px and PHP/static detail-page image loading. No full suite was repeated.

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

## Draft PR browser release gate — 2026-11-16

The latest prior draft run (`43933421739`) passed its build job but skipped browser QA and deployment because of workflow conditions. Removed the draft-based skips for browser dependency installation and browser/visual regression; deployment remains restricted to non-PR events. The first run with that gate (`43934043720`) passed PHP/SQLite integrity, focused campaign/pricing/admin regressions, PHP syntax, HTTP smoke checks and static export, then stopped at an inaccurate menu regression assertion: it tested visible card text rather than the hidden `data-search` metadata that the search supports. A focused static Playwright reproduction confirmed the feature returns only matching-category cards whose search metadata includes the query. Updated the assertion to match the catalog search contract. The rerun is pending; no browser QA or deployment success is claimed yet.
## 2026-11-17 — native transitions, booking title, and motion crop

Hosted run 43937788170 (https://github.com/altento/tokyo-premium/actions/runs/43937788170) on 7ff2ba1 passed the PHP/SQLite integrity suite (196 assertions), gift/personal-offer/commerce/admin regressions, PHP lint and smoke checks, static export, the 11-route/20-dish browser regression, and all eight cart visual states. It stopped in the responsive suite because the shared editorial heading rule still reported hyphens: auto at 320 px. The booking title itself fit visibly, but the automatic hyphenation setting contradicted the mobile heading requirement.

The shared rule in editorial.css and the Pages copy now uses hyphens: none while preserving overflow-wrap: anywhere. A focused static browser check passed at 320, 360, 375, 390, 430, 768, 1024, 1440 and 1920 px: one-line title, in-viewport text bounds, no horizontal overflow. Captures are in project-docs/visual-qa/07-booking-heading-fix-2026-11-17/.

The first static-only npm run test:motion exposed an outdated assertion for a 75% hero image crop; the current CSS and screenshot show 82%, with the salmon clearly in frame. The assertion now accepts a narrow 78–86% framing window and also checks image geometry. The static motion regression passed; five captures are in project-docs/visual-qa/2.0-motion-2026-11-17/. Hosted PHP responsive verification for the heading repair is pending.
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

- Moved the booking submit button after the preferred-seat map and its disclosure. The form still owns the fields and browser validation through the button's `form` attribute; the desktop and mobile routes use the same flow.
- The shared PHP and Pages script now locates an externally associated submit button if a submit event has no `event.submitter` (including `form.requestSubmit()`).
- `npm run test:tokyo20` passed against an isolated PHP 8.5 + SQLite server and the static export. It checks that the form precedes the map and that the submit control follows the map caption at 390 px, and submits a static booking via `requestSubmit()`.
- I visually inspected the PHP and static mobile captures and the selected-seat captures in `project-docs/visual-qa/booking-map-order-2026-11-17/`.
- This targeted regression does not represent full-site acceptance or a public Pages deployment check.

## 2026-11-17 home hero reference pass

- Replaced the oversized three-line Russian hero title with the approved TOKYO PREMIUM brand lockup, shortened the supporting copy, and reduced the desktop/tablet hero height to a width-based scale closer to reference 01. The mobile crop and scene controls remain intact.
- Regenerated only the static homepage and its stylesheet into `docs/` from the PHP exporter.
- `npm run test:motion` passed in PHP and static modes: scene controls, keyboard interaction, reduced motion, image framing and eight responsive widths.
- PHP/static hero captures at desktop and 390 px mobile are in `project-docs/visual-qa/home-hero-reference-2026-11-17/`; I inspected all four and compared the desktop framing with `design-references/01-home.png`.
- This is a focused first-screen improvement; the remaining homepage sections have not been brought to full pixel parity with the reference.

## 2026-11-17 order-history photo previews

The PHP account query now joins order items to the current catalogue image while preserving saved order names, quantities, prices, and totals. The static browser demo resolves thumbnails from catalogue dish IDs and never trusts a persisted image URL. The focused static account regression passed with three sample records and verified a loaded catalogue photograph; `php -l account.php`, `node --check account-demo.js`, and `git diff --check` passed. Desktop (1440 px) and mobile (390 px) seeded account screenshots were inspected in `visual-qa/account-order-previews-2026-11-17/`. PHP browser rendering and public Pages publication were not exercised in this pass.

## 2026-11-17 booking floorplan preference picker

- Expanded the booking SVG into four clearly labeled zones with 42 illustrative table choices: 20 main hall, 8 sushi bar, 4 private room, and 10 terrace. These are preferences only; neither static nor PHP mode claims real-time availability.
- Added a mobile-friendly zone/table picker derived from the same SVG data. Choosing a zone or table updates the existing booking preference state; touch controls are at least 44 px high.
- `npm run test:floorplan` passed against both the local PHP app and GitHub Pages static export. It checks all four zone counts, selection propagation, availability disclosure, mobile control size, and horizontal overflow. `node --check`, `php -l pages.php`, and `git diff --check` passed.
- Desktop and mobile screenshots were visually inspected in `visual-qa/booking-floorplan-42-2026-11-17/`. Full booking-page parity, live table availability, and public GitHub Pages deployment were not tested.
- The first hosted CI run found a stale browser-test selector that assumed a table ID appeared only once. The mobile picker intentionally reuses SVG table IDs, so the booking reset assertion now scopes itself to the SVG. `npm run test:browser` then passed locally across 11 routes, 20 dish details, the core cart/checkout interactions, and six viewport widths; its screenshots are in `visual-qa/browser-regression-current/`. The focused test change does not claim public deployment.

## 2026-11-17 account dashboard hierarchy

- Compared the authenticated, populated PHP account page with `design-references/11-account.png`. The main order history and booking history had been squeezed into two equal columns; order cards now use the full primary column, with secondary account panels retaining their two-column desktop layout. Reduced the account hero's maximum height from 310 px to 280 px to bring the first screen closer to the approved reference.
- Kept the shared account selectors in sync in `premium.css` and `docs/premium.css`. The GitHub Pages account template uses separate demo markup, so this grid adjustment affects the PHP dashboard only.
- Added a browser geometry regression that confirms the two primary history sections stack and the order history exceeds 900 px at the desktop test viewport. `npm run test:browser` passed against the local PHP and static routes; it covered 11 routes, all 20 dish detail pages, cart/checkout, navigation, favorites, filters, and six responsive widths. The focused desktop account assertion ran against the authenticated PHP dashboard.
- Captured and visually inspected populated account screenshots at 1440 px and 390 px in `visual-qa/account-dashboard-layout-2026-11-17/`. The order/booking hierarchy is now readable and remains stacked on mobile. The screenshot still shows the expanded 2.0 account feature set and is not a claim of exact pixel parity with the older reference. Public Pages was not updated or checked in this pass.

## 2026-11-17 static account first-visit preview

- A clean GitHub Pages browser now opens the account with three catalog-backed example orders, one example booking request and three favorites. Each generated record is marked as a local educational example. This behavior is limited to the static account route and does not write PHP/server account data.
- First-visit seeding is skipped when browser-local account data, cart items, custom sets or favorites already exist. The clear-data action records a dismissal, so reload stays empty; the visitor can explicitly restore the sample afterward.
- Fixed the CSS cascade for the seed prompt `hidden` state. The focused regression checks visible state, existing-data preservation, persistence, explicit restoration and successful loading of all order thumbnails before capture.
- `npm run test:account-demo` passed against the local static export. JavaScript syntax checks and `git diff --check` passed. Desktop (1440 px) and mobile (390 px) captures in `project-docs/visual-qa/account-first-visit-2026-11-17/` were inspected.
- This is a focused static-page improvement; PHP account rendering, hosted CI, public Pages and full 11-reference acceptance remain open.

## 2026-11-17 Tokyo 2.0 checkout and first-visit account regression

The new first-visit examples changed the expected static account history from one checkout row and one booking to three labeled sample rows plus the newly submitted order and booking. Updated `tests/tokyo20-regression.cjs` to verify the submitted guest order itself and both sets of local records. `npm run test:tokyo20` passed against an isolated PHP + SQLite server and the current Pages export. Hosted run 43986099018 exposed the stale count assertions; the correction passed CI in replacement run 43988516200. Artifact upload and deployment were skipped because PR #19 remains a draft.

## 2026-11-17 promo-code administration

The Marketing & Loyalty page now displays each code's persisted use count, global and per-account limits, validity dates in `Asia/Qyzylorda`, and first-order restriction. Every existing code has a prefilled edit form; its code string is read-only there to avoid accidentally creating a replacement while leaving the old code enabled. `tests/php_integrity_integration.php` passed all 199 assertions under PHP 8.4 + SQLite, including a new assertion that matches the seeded `DEMO10` redemption counter and verifies the prefilled editor. `php -l admin-marketing.php`, matching PHP/Pages `tokyo20.css` hashes, and `git diff --check` passed. Hosted CI for this latest change and public Pages deployment have not run; deployment remains skipped while PR #19 is draft.

## 2026-11-17 cart hero refinement

Compared the populated cart against reference 10 and replaced the reused close-up set image with a dedicated generated dining-scene WebP. The PHP route and static Pages export now share `assets/generated/cart-hero.webp`. `npm run test:cart-visual` passed all eight empty/populated/checkout-state captures against the local static export and now asserts that this hero asset loads. `php -l pages.php`, `node --check tests/cart-visual-regression.cjs` and `git diff --check` passed. The inspected 1440 px desktop and 390 px mobile captures are in `project-docs/visual-qa/cart-hero-refinement-2026-11-17/`. Hosted run #177 exposed a stale `test:visual-fidelity` expectation for the old cart social image; the assertion now matches the new cart asset. A hosted rerun is pending. This focused change does not complete the all-11-reference comparison or update the public deployment.

## 2026-11-17 menu tablet header

The earlier header switched to mobile mode at 1024 px, although reference 02 shows desktop navigation at that width. CSS and `navigation.js` now share a 1023 px cutoff, with a compact booking action at 1024 px and an explicit accessible name. `npm run test:menu-visual` passed the focused static route at 1440, 1024, 1023, 900, and 390 px: the 1024 px navigation, profile, cart, and booking controls fit inside header padding without overlap; the 1023/900/390 px mobile menu opens, exposes booking, closes on Escape, and restores focus. The existing browser-fidelity regression was extended with the same 1024/1023 boundary checks and passed for the static menu route, including the booking accessible name. The catalog retained 3/3/2/2/2 columns with no page overflow. Captures and side-by-side notes are in `project-docs/visual-qa/menu-header-tablet-2026-11-17/`. This focused pass did not render PHP locally; the current hosted gate will check PHP and static routes.

Hosted run #178 for `f45a3ecc8fb6ecdce676a3bdb4a3d0b35c7a499f` completed successfully. PHP/SQLite integrity, cart state, browser regression, visual QA, and static export passed; deployment was skipped because PR #19 remains a draft. This confirms the cart-image assertion repair only; it does not cover the later tablet-header changes.

Hosted run #179 for `01b63cff15478ecdec8ccdb5b7cbc05b63c92631` passed PHP/SQLite integrity, static export and the 11-route/20-dish browser regression, then failed in the responsive suite because its breakpoint assertions still treated 1024 px as mobile. The header change intentionally keeps desktop navigation at 1024 px to match reference 02. Updated the responsive regression to test the real 1023/1024 boundary in both PHP and Pages modes. A focused static Playwright visual-fidelity run passed the menu at 1024 and 1023 px; PHP + SQLite responsive verification is pending the next hosted run. No deployment was attempted because PR #19 is draft.

## 2026-11-17 contact layout and visual-fidelity selector follow-up

Hosted run #180 (`ad740e906c4df326714d22d67a758eed3d756d16`) passed PHP/SQLite integrity, cart state, static export, core browser regression, cart visuals, and the responsive suite. It then failed in visual fidelity because the test selected the static-only `booking.html` link while running the PHP route. Commit `9c8ee958e9a846b7bc675e50750f043cc7e011d2` changed the selector to accept the shared booking link in both modes and added the contact layout refinement. A focused static Playwright visual-fidelity run passed for menu and contacts. Contact hero/map dimensions and desktop map-to-summary proportion are asserted; 1440 px desktop and 390 px mobile captures were inspected against reference 09. Evidence and the comparison are in `project-docs/visual-qa/contacts-reference-followup-2026-11-17/`. Public deployment remains pending.

Hosted run #181 for `9c8ee958e9a846b7bc675e50750f043cc7e011d2` passed the PHP/SQLite, cart, core-browser, static-export, and responsive jobs, then failed at the visual-fidelity check immediately after resizing from 1024 to 1023 px: the test read `inert` before the asynchronous `matchMedia` change handler had applied it. The existing navigation handler synchronizes `inert` on that breakpoint change; a focused static run for the home route passed locally. Added a bounded browser wait for the inert state so CI verifies the resulting behavior instead of racing the event loop. Hosted verification of that test adjustment is pending.


## Current-head verification — 2026-11-17

Hosted GitHub Actions run [#182](https://github.com/altento/tokyo-premium/actions/runs/44023162925) passed for `7d888d1b24e70e6ec593a2083930522d9db08e58`: PHP/SQLite integrity, cart state, gift threshold, birthday/tier restrictions, capped set pricing and promotion attribution, admin Cyrillic search/referral login, PHP syntax, form/admin smoke, static export, browser regression and visual QA. The Pages deployment job was skipped because PR #19 remains a draft.

A current local static Playwright visual-fidelity pass passed all 11 page routes at 1440 px desktop and 390 px mobile. Full-page captures and four reference comparison sheets are in `project-docs/visual-qa/2.0-visual-fidelity-current-2026-11-17/`; route-by-route findings are in that folder's `comparison.md`. This is a structural/visual review, not pixel-perfect acceptance.

The populated-cart browser capture was created by adding three actual catalog items (Philadelphia ×2, salmon nigiri ×1, matcha latte ×1). The subtotal shown is 12,700 ₸. Desktop and mobile captures show loaded recommendation photography, quantity controls, promo and fulfilment controls, and checkout. The first capture attempt raced lazy image loading; after waiting for image decode the recommendation image rendered correctly, so no asset correction was needed.

Public GitHub Pages is still unchanged because PR #19 is open as draft and deploy was skipped. The full comparison, PHP rendering on this workstation (no local pdo_sqlite), SMTP delivery configuration, and public-site post-deploy checks remain outstanding.

## 2026-11-17 dish ingredient composition follow-up

- Added additive migration v11 to populate the normalized ingredient relations for 19 individually described educational dishes. Set pages keep an honest “components not listed” note; the detail page explicitly says allergen data are not verified. The migration only inserts missing ingredient and dish links.
- The PHP dish detail now reads ingredient names from SQLite. A local static preview of the salmon nigiri page shows salmon and sushi rice, with no synthetic photo gallery. Full generated static export of all detail pages is delegated to the hosted exporter.
- Focused static Playwright visual-fidelity check passed for the dish route at 1440 px desktop and 390 px mobile. Captures: `project-docs/visual-qa/dish-composition-2026-11-17/dish-static-desktop.jpg` and `dish-static-mobile.jpg`; both were visually inspected against reference 03.
- PHP syntax checks passed for `lib/commerce.php`, `bootstrap.php`, `pages.php` and `tests/php_integrity_integration.php`; both updated browser test files pass `node --check`; `git diff --check` passed.
- SQLite integration assertions now cover the two listed salmon nigiri ingredients, idempotent migration retry and migration of an existing v6 database. They were not run locally because PHP here lacks `pdo_sqlite`; hosted CI is required to verify the database path.
- This targeted detail-page improvement does not close the remaining all-reference visual gaps or update the public Pages deployment.

## 2026-11-17 detail and Omakase visual repairs

- Compared fresh PHP screenshots with references 03 and 04. On dish detail, the desktop grid had split the heading and purchase information into separate rows, leaving a large empty gap. Grouped the content into one desktop column while retaining mobile reading order (heading, photo, purchase).
- On Omakase, the selected course panel added substantial repeated imagery and height, and the booking-band gradient hid most of the room. Reduced the panel to a compact editorial composition and moved the stronger shade to the copy side so the interior remains visible.
- Added browser assertions for the dish title-to-description spacing, desktop column, mobile order, visible cart controls, and a maximum 360 px desktop Omakase panel. Existing assertions still exercise all five distinct course photos, mouse/keyboard selection, and matching story panels.
- `php -l pages.php` passed. Focused `npm run test:visual-fidelity` passed for dish in PHP and static modes and Omakase in both modes. It saved fresh 1440 px desktop and 390 px mobile captures under `project-docs/visual-qa/2.0-visual-fidelity/`; I opened and inspected the updated dish and Omakase screenshots.
- This is a two-page visual repair, not full 11-reference acceptance. The PHP/SQLite integration suite and hosted Pages deployment were not rerun in this pass; remaining visual differences are recorded in `project-docs/visual-qa/reference-repair-followup-2026-11-17.md`.
# 2026-11-17 — dish detail layout follow-up

- `php -l pages.php` and `node --check tests/tokyo20-regression.cjs` passed; `git diff --check` reported no whitespace errors.
- Focused Playwright browser regression passed on the local PHP + isolated SQLite runtime and the freshly exported static Pages build (`TOKYO20_MODES=php,static`, `TOKYO20_DISH_ONLY=1`, `npm run test:tokyo20`). It checked 21 PHP and 19 static dish-detail routes, including add-to-cart, favorite state, quantity, displayed total, factual detail data and mobile fit.
- Full-page screenshots were captured at 1440 px and 390 px for PHP and static routes and visually inspected against `design-references/03-dish.png`; details and remaining visual gaps are documented in `visual-qa/dish-detail-layout-2026-11-17/README.md`.
- This is a focused product-detail regression only. It is not a rerun of the complete site suite, the PHP admin flow, or public GitHub Pages; the working-tree changes are not deployed.

## 2026-11-17 PHP account dashboard polish

The PHP profile navigation is now compact, icon-led, and marks the section currently in view; order states use server-backed status pills. Focused PHP account visual-fidelity passed at desktop and mobile widths. The full browser regression (11 route types, 21 detail routes, six viewport widths) and Tokyo 2.0 PHP/static regression passed. Static export regenerated 37 pages and passed the rerun. Local PHP/JS syntax and diff checks pass. Screenshots and remaining comparison gaps: `project-docs/visual-qa/account-dashboard-polish-2026-11-17/README.md`. This checkpoint is still local and has not yet passed hosted CI or updated Pages.

## 2026-11-17 PHP account dashboard density

The account route passed `npm run test:visual-fidelity` in both PHP and static modes. The PHP desktop capture checks a hero height at or below 320 px, a two-by-two metric grid, concise empty history panels, and all seven dashboard navigation targets; the mobile route retains the no-overflow check. Screenshots are in `project-docs/visual-qa/2.0-visual-fidelity/account-php-{desktop,mobile}.jpg`. This is a focused visual pass; it does not verify every account transaction or the full PHP/SQLite integration suite.
## 2026-11-17 галерея пространства: отдельные фотографии

Первые четыре кадра галереи больше не повторяют фотографии карточек залов. Добавлены проверки уникальных источников и отсутствия дублей между галереей и карточками зон. `npm run test:visual-fidelity` прошёл для PHP и статической версии на маршруте `space`; `npm run test:gallery-caption`, PHP lint, JS syntax и `git diff --check` прошли. Просмотрены desktop/mobile скриншоты обеих версий в `project-docs/visual-qa/2.0-visual-fidelity/`. Сравнение с `design-references/06-gallery.png` подтверждает сохранение общей структуры секций, но не полный визуальный паритет.
## 2026-11-17 философия: векторный акцент Фудзи

Добавлена оригинальная SVG-композиция энсо/Фудзи/сакуры в страницу философии, размещена третьей колонкой desktop и после chef story на мобильном. Точечная visual-fidelity проверка прошла для PHP и Pages, включая загрузку SVG, декоративную доступность, мобильный порядок секции и отсутствие overflow. Созданы и просмотрены четыре скриншота в `project-docs/visual-qa/2.0-visual-fidelity/`. Полное визуальное совпадение с reference 05 не заявляется.

## 2026-11-18 Ideal Evening, campaign assets and server validation

- Focused cart unit tests: `node --test tests/cart-store.test.cjs` passed 13/13, including atomic multi-item recommendations and custom-set validation.
- `npm run test:offers-evening` passed against the freshly exported Pages build on the local static server. It checks a real main-and-drink recommendation, per-guest budget wording, atomic cart addition, and booking handoff including Omakase format.
- `tests/php_integrity_integration.php` passed 217 assertions with PHP 8.4 + PDO SQLite, including migration 13, preservation of admin-selected campaign images, Chef's Secret Drop target, and transactional/idempotent integrity.
- The preceding focused `npm run test:tokyo20` run passed PHP and static mode, including 21 PHP and 19 static dish-detail routes, the custom Omakase set, booking handoff and responsive checks. It is a focused integration regression, not a full acceptance run.
- Static export contains 37 pages. New result captures are in `project-docs/visual-qa/12-php-perfect-evening-{result,mobile}.jpg` and `12-static-perfect-evening-{result,mobile}.jpg`.
- `git diff --check` passes. Public Pages, hosted CI and the complete 11-reference visual acceptance have not yet been verified for this working change.

## 2026-11-18 hero image failure recovery

- Reproduced a home-hero state mismatch when a next-scene photo fails to decode. The scene copy and selected control previously advanced while the visible photo remained on the previous scene.
- `motion-system.js` now decodes the incoming image before changing the selected scene in both normal and reduced-motion modes. On failure it keeps the current image, text, index and pressed dot together, clears the failed preloader, removes `aria-busy`, and announces the fallback in the existing live status.
- `npm run test:motion` passed in PHP and static modes. The added Playwright scenarios force the Omakase image to return 404 and verify that copy, photo description, index and controls stay synchronized with both motion preferences. The existing hero/scroll-story checks and eight viewport widths also passed.
- Regenerated static export: 37 pages. No pixel layout changed. The complete 11-reference comparison and public deployment for this fix remain outstanding pending GitHub Actions.

## 2026-11-18 published checkpoint — 7828998

GitHub Actions run [44179154931](https://github.com/altento/tokyo-premium/actions/runs/44179154931) completed successfully for commit `7828998c75f1dbb2d386af56683ce9a72cf3557e`; both build and deploy jobs passed. Cache-busted public checks returned HTTP 200 for the Ideal Evening static page (including the active set-variant payload) and the new Chef's Secret Drop WebP asset. The subsequent hero image-failure fix recorded above is a later local change and is not yet part of the deployed commit.

## 2026-11-18 — account repeat-order release

- Added migration v14 to preserve the custom-set format slug on saved order lines and backfill older lines by their stored name. The account only reconstructs current eligible catalogue IDs and quantities; historical prices are never passed to the cart.
- Repeat-order now merges dishes and custom sets in one cart-store write. Invalid/unavailable components, quantity overflow, or line limits reject the entire operation without a partial cart update. The browser-local account uses the same API.
- `npm run test:cart` passed 14/14; PHP lint passed for `account.php`, `lib/commerce.php`, and the SQLite integration test. GitHub Actions run [44221178924](https://github.com/altento/tokyo-premium/actions/runs/44221178924) passed PHP/SQLite integration, browser regression/visual QA, static export, and Pages deployment.
- Cache-busted public requests for the home page, `account.html`, `cart-store.js`, and `luxury.js` returned HTTP 200 and contained the expected release code. This is static Pages verification; the repeat-order PHP route is exercised in hosted CI, not on Pages.
- A local browser run could not start because this checkout has no installed `playwright` package. The workflow installed its pinned browser dependencies and passed the project browser suite. Pixel-by-pixel comparison of all 11 references remains incomplete.

## 2026-11-18 account dashboard alignment follow-up

The Pages account now aligns its section navigation beside the member/loyalty summary from the first dashboard row, tightening saved-order cards on desktop while retaining the mobile single-column sequence. Fresh 1440 px and 390 px populated screenshots and a focused comparison against `design-references/11-account.png` are in `visual-qa/account-dashboard-layout-2026-11-18/`. `npm run test:account-demo` passed, including 320 px overflow and desktop navigation/summary alignment; `php -l account.php` passed. Local PHP rendering was not available because the installed CLI lacks PDO SQLite. The account remains visibly different from the approved mockup and this focused pass does not establish site-wide acceptance.

## 2026-11-18 booking FAQ visual follow-up

The booking page's plain full-width FAQ now uses an editorial landscape-and-accordion composition. The custom SVG is labeled as educational artwork, the first answer explains the confirmation boundary, and the email answer distinguishes configured PHP SMTP from the browser-local Pages demo. `php -l pages.php`, `node --check tests/visual-fidelity-regression.cjs`, `git diff --check`, and static booking visual-fidelity passed. `npm run test:tokyo20` passed the complete static-mode feature regression, including booking preference selection, Omakase handoff, offers, set builder, cart and profile. Fresh desktop/mobile screenshots and a section-scoped reference 07 comparison are in `visual-qa/booking-current-2026-11-18/`. PHP/SQLite rendering is deferred to hosted CI because this workstation's PHP CLI lacks PDO SQLite.

## 2026-11-18 — full static route capture and comparison

`node tests/visual-fidelity-regression.cjs` passed: 11 static route types were validated and captured at desktop (1440 px) and mobile (390 px), for 22 full-page images in `visual-qa/full-static-2026-11-18/`. Two contact sheets pairing current pages with the 11 approved PNGs were reviewed. The comparison notes concrete remaining visual differences and does not claim pixel identity or PHP rendering. The default cart captures are intentionally empty; eight populated, delivery, pickup and validation screenshots from the cart visual regression are in `visual-qa/cart-progressive-checkout-2026-11-17/`. This updates the earlier note that a fresh all-11 visual review had not yet been done.

## 2026-11-18 focused account and admin finish

The static account overview now includes up to two saved local addresses alongside the dashboard, with a working jump to address management. The focused `npm run test:account-demo` passed. Assertions cover sample seeding, the address-tab action, clear and restore behavior, retention of user data, escaped address text, and seeded desktop/mobile captures in `visual-qa/account-addresses-overview-2026-11-18/`. Marketing & Loyalty navigation exposes all nine management sections directly. Local PHP/SQLite integration was not re-run in this quick pass because this CLI lacks PDO SQLite; the existing hosted run provides the PHP integration result for its checkpoint. This narrow change does not complete the full Tokyo Premium 2.0 acceptance list.

## 2026-11-18 admin status filters and daily dashboard

For commit `687ccc3`, local checks were limited to `php -l admin.php`, `php -l tests/php_integrity_integration.php`, and `git diff --check`. A focused integration assertion for the booking status filter was added to the existing PHP/SQLite suite. GitHub Actions run [44258565830](https://github.com/altento/tokyo-premium/actions/runs/44258565830) passed the complete configured PHP/SQLite integration, admin smoke and browser/visual regression jobs; static export and deployment also succeeded. The public homepage was confirmed with a cache-busted HTTP 200 request. No separate local full-suite rerun was performed.

## 2026-11-18 campaign performance admin

The Marketing & Loyalty dashboard now derives campaign uses, distinct non-cancelled orders, attached discounts, active state and last-use time from SQLite. `tests/admin-loyalty-ledger-regression.php`, already included in the normal hosted workflow, now also covers one completed and one cancelled campaign redemption and asserts the displayed totals. `php -l` passed for the route and test; local SQLite execution is unavailable because the installed PHP CLIs do not load PDO SQLite. The hosted workflow on this commit is the database verification gate. The long browser/visual workflow was not requested for this focused admin change.

The first hosted attempt, [44301886978](https://github.com/altento/tokyo-premium/actions/runs/44301886978), caught a test-selector collision because campaign rows initially reused the ledger-row class. The rows were separated while preserving live search. Follow-up run [44302462169](https://github.com/altento/tokyo-premium/actions/runs/44302462169) passed the protected admin regression, full configured PHP/SQLite checks, syntax, smoke checks, static export and Pages deployment. Browser/visual QA remained skipped by the fast workflow profile. The public Offers demo returned HTTP 200; the PHP-only admin itself is not available on Pages.

## 2026-11-18 — protected admin CSV export

Added filtered CSV downloads for the four operational record lists. `php -l admin.php` and `git diff --check` passed locally. GitHub Actions run [44306169527](https://github.com/altento/tokyo-premium/actions/runs/44306169527) passed build and deployment. It ran existing SQLite integrity/admin smoke checks, but did not exercise this authenticated export route; the workstation lacks PDO SQLite. Public home, offers and menu returned HTTP 200. No full browser matrix was run.

## 2026-11-18 — promo code quick copy

Added Clipboard API copy with a legacy selection fallback and screen-reader status text. `php -l admin-marketing.php`, `node --check admin-marketing-search.js` and `git diff --check` passed. No browser interaction or PHP login session was available for this quick pass.

GitHub Actions run [44307539160](https://github.com/altento/tokyo-premium/actions/runs/44307539160) for commit `261ec7e` passed build and deployment. Cache-busted public home, Offers and Menu returned HTTP 200. Full browser/visual QA was skipped by the fast profile; the PHP admin routes are unavailable on Pages.

## 2026-11-18 — admin action context

`php -l admin.php` and `git diff --check` passed. The change routes successful admin saves back to their section, preserves validated list filters/page state, and places the one-time success notice beside that section. GitHub Actions run [44310591339](https://github.com/altento/tokyo-premium/actions/runs/44310591339) passed the configured checks and deployment for commit `8d63abf`; the authenticated admin route was not browser-tested.

Mobile admin action controls received a focused `git diff --check`. No broad or screenshot-based test was run for this small CSS adjustment.

## 2026-11-18 — focused homepage visual follow-up
Compacted the desktop homepage rhythm and aligned the recommendation row to four columns from 1024px upward. Checked the static page in Playwright at 1440px, 1024px and 390px; viewport/document widths matched, and the 1440px page reported no JavaScript errors. Inspected the 1440px hero/menu viewport and saved a full-page capture in `project-docs/visual-qa/`. No full browser matrix, PHP test suite, or mobile screenshot review was run for this CSS-only adjustment.
## 2026-11-18 — admin booking queue and contact actions
Booking cards and CSV now order upcoming visit times first (nearest to furthest), followed by past visits in reverse chronological order, using the restaurant's Asia/Qyzylorda clock. The daily booking KPI excludes cancelled records. Valid guest email addresses and phone numbers are actionable `mailto:` and `tel:` links, with mobile touch targets and visible keyboard focus. `php -l admin.php`, `git diff --check`, and a small isolated SQLite ordering/count check passed. The authenticated dashboard was not browser-tested in this local environment; no full suite was run.
The admin record timestamp display now converts stored UTC creation times to `Asia/Qyzylorda` and includes an explicit local-time label. `php -l admin.php` and `git diff --check` passed. Authenticated rendering remains unverified locally.
## 2026-11-18 — isolated PHP admin browser check
Enabled the locally installed PDO SQLite extension only for a temporary PHP process, created a throwaway admin and isolated SQLite database under the OS temp directory, and checked the actual authenticated dashboard in Playwright. Four synthetic bookings confirmed upcoming-first ordering, cancellation exclusion from the daily KPI, confirmed-state badge, `tel:`/`mailto:` actions, restaurant-local timestamps, mobile width 390 without overflow, and zero page errors. Desktop and mobile screenshots plus a compact JSON result are in `project-docs/visual-qa/admin-operations-2026-11-18/`. No full site test matrix or mail-delivery test was run.
## 2026-11-18 — Marketing & Loyalty admin browser check
Authenticated into the local PHP Marketing & Loyalty admin on the same isolated SQLite fixture. Search for `TOKYOHOUR` matched one record; `/` focused the search box; `Escape` cleared the query; copying a promo code announced success and placed `DEMO10` on the clipboard. No page errors were observed. Desktop and 390 px mobile screenshots and a compact JSON report are included in `project-docs/visual-qa/admin-operations-2026-11-18/`. This was focused interaction QA, not an exhaustive admin mutation or responsive matrix.

## 2026-11-18 — live operational notifications
The authenticated operations dashboard refreshes pending-order, booking, review, contact-message and email-queue counts every minute without reloading forms; a manual refresh is also available. A read-only JSON response exposes counts and the restaurant-local timestamp only, returns `401` without an admin session, and is marked private/no-store. The existing admin/referral HTTP regression now covers both access states. One focused PHP/SQLite run passed 19 assertions; `php -l`, `node --check`, and `git diff --check` passed. Full browser and visual suites were intentionally skipped for this small admin-only change.

## 2026-11-18 — accelerated release profile and admin operations

The default Pages push workflow now runs the cart-store checks, consolidated PHP/SQLite integrity, PHP syntax, one endpoint smoke pass, and static export/deployment checks. Seven additional focused feature suites and all Playwright/visual suites remain available through the manual `full_qa=true` workflow; test files were retained. The PHP Marketing & Loyalty screen now has searchable, paginated history for personal offers, referral activity, and read-only certificate previews. Campaign popularity excludes cancelled orders and zero-priced gift lines. Local PHP syntax and the 14 cart checks passed. PDO SQLite is unavailable in the local PHP runtimes, so authenticated SQL rendering is left to the hosted CI run.

An Omakase density refinement was checked at 1440px and 390px. Both layouts stayed within the viewport, the five course images and story image loaded, and no page errors occurred. Captures: `project-docs/visual-qa/omakase-compact-2026-11-18/`.

## 2026-11-19 — FreedomPay hosted checkout adapter

Added an optional PHP/SQLite FreedomPay KZ hosted-payment flow with server-calculated order totals, a hashed checkout capability, signed request/response and callback checks, KZT/amount matching, repeat-notification handling, and a serialized payment-session creation guard. Local checks passed: PHP syntax for changed endpoints/helpers, `node --check luxury.js`, `git diff --check`, and five provider-signature/amount assertions. Hosted run [44423394180](https://github.com/altento/tokyo-premium/actions/runs/44423394180) passed the configured PHP/SQLite integrity and migration checks, PHP syntax, endpoint smoke, static export, and Pages deploy; browser/visual QA was skipped by the fast workflow profile. No merchant credentials or public PHP host are configured; no real payment or browser-to-provider transaction was tested. GitHub Pages remains a static demo and offers no online payment.


## 2026-11-19 — gallery image loading

PHP and separate static export passed the new gallery-loading regression (network failure/retry, stale responses, close during loading, six viewport widths, room-to-booking links and script fallback). Existing gallery-caption, Tokyo 2.0 PHP/static and 14 cart checks passed. Evidence: `visual-qa/gallery-loading-2026-11-19/comparison.md`. No local server integrity rerun was required for this frontend-only change; the default deployment workflow retains it. Publication results follow after deployment.


Gallery release `88aef6c`: [workflow 44542921835](https://github.com/altento/tokyo-premium/actions/runs/44542921835) passed the fast server/export/deploy checks. The focused loading/retry/navigation/fallback and six-width browser suite then passed on the public Pages site. Evidence: `visual-qa/gallery-loading-2026-11-19/public-verification.json`. Optional full-browser CI remained skipped; local PHP/static browser results are recorded above.


## 2026-11-23 — bounded admin browser pass

The authenticated booking/operations and Marketing & Loyalty browser regressions both passed on isolated SQLite fixtures. Coverage and limits are recorded in `final-pass-2026-11-19.md`; SMTP is not configured and the test deliberately sent no real email. This was a focused acceptance check, not a rerun of the full browser matrix.

## 2026-11-23 — focused checkout reset command

Added `npm run test:checkout`, a self-contained Playwright check that serves the current static export on an ephemeral local port. It passed pickup checkout/reset, cart clearing, delivery address visibility/required state, invalid delivery rejection, successful repeat delivery, pickup/delivery switching, route changes, reload persistence, and zero page errors. `node --check tests/checkout-reset-regression.cjs` and `git diff --check` passed. This verifies the Pages demo only; the PHP/API checkout scenario remains in the broader `tests/browser-regression.cjs` and needs a SQLite-enabled PHP server.
## 2026-11-23 — static birthday preference

The GitHub Pages profile now offers an optional month/day birthday preference and separate opt-in. It stores no birth year, stays in this browser, can be withdrawn independently, and makes no coupon, bonus, email, or server request. A focused Playwright check passed save, reload, leap day, April day limits, opt-out and clear-data behavior. Desktop and 390 px profile screenshots are in `visual-qa/account-birthday-2026-11-23/`. PHP birthday persistence remains covered by its existing server profile flow; it could not be exercised against SQLite locally because this environment has no PDO SQLite driver. No broad test suite was run.

## 2026-11-23 — certificate preview data minimization

The certificate preview no longer asks for a recipient email. The PHP API ignores a forged `recipient_email` POST field; its helper stores an empty value and omits that field from the response. The protected marketing view no longer searches or displays legacy recipient emails. Existing database rows and the legacy column are preserved. Added focused SQLite assertions and a PHP-mode browser regression for a forged email field; local SQLite execution is unavailable here, and the broad Tokyo 2.0 browser suite was not rerun in this pass.

After synchronizing the generated account scripts with the current exporter source, the existing browser-only account regression also passed against the local static export with screenshots disabled. It covered history, favorites, order repeat, notifications, local-data clearing, and escaped user content.

## 2026-11-23 — booking zone and reset parity

The focused static booking regression exposed two stale-export gaps: the initial zone status was not synchronized, and form reset left the selected table in place. The Pages copy now matches PHP for both states; the duplicate form status is hidden so only the map announces the current choice. `TOKYO_FLOORPLAN_MODE=Pages node tests/floorplan-regression.cjs` passed for all 42 preference markers, zone/photo synchronization, table selection/reset, mobile selection, overflow and browser errors. Fresh desktop/mobile captures were visually inspected in `visual-qa/booking-status-2026-11-23/`. Only Pages was browser-tested; local PHP browser execution is unavailable without PDO SQLite.

## 2026-11-23 — regenerated static feature parity

The checked-in Pages export lagged behind current PHP templates and frontend sources. Regenerated all 37 public static routes from the current PHP source with a temporary SQLite database, restoring Omakase course navigation and current set-builder controls in the published artifacts. Focused Playwright checks passed for PHP and Pages Omakase and set-builder at 390 px and 1440 px, covering course navigation, booking-format transfer/persistence, draft restore/clear, quantity limits and cart transfer. Captures were visually inspected and saved in [`visual-qa/static-export-parity-2026-11-23/`](visual-qa/static-export-parity-2026-11-23/); this was a feature regression pass, not a full visual acceptance run.

## 2026-11-23 — booking form pairing

The existing `test:floorplan` regression now asserts the reservation form's semantic order and full-width email row. It passed against the local PHP/SQLite server and the regenerated static export, including 42 table markers, form/table synchronization and reset, mobile table selection, viewport fit and no browser errors. Fresh desktop/mobile selected-table screenshots were visually reviewed in [`visual-qa/booking-form-pairing-2026-11-23/`](visual-qa/booking-form-pairing-2026-11-23/). PHP lint and static export succeeded using a disposable database; full-project suites were skipped for this scoped change.

After commit `c7ffe62` deployed successfully in workflow [45143146646](https://github.com/altento/tokyo-premium/actions/runs/45143146646), the same Playwright scenario passed on public Pages at 1440 px and 390 px. Public captures are in [`visual-qa/booking-form-pairing-2026-11-23/public/`](visual-qa/booking-form-pairing-2026-11-23/public/). The browser connector was unavailable; the local Playwright runner navigated the published site directly. This is public verification of the booking change only.
## 2026-11-23 — Admin payment attempt recovery

- `php -l admin.php` passed; `tests/admin-order-status-regression.php` passed 8 isolated assertions with PHP 8.5.11 and SQLite 3.53.4. The fixture verifies the latest uncertain FreedomPay attempt is visible and searchable by provider order ID.
- `python tests/payment-home-http.py` passed 27 isolated HTTP checks; `php tests/freedompay-signature-test.php` passed 5 signature/amount checks. These are targeted checks, not a consolidated security audit or live-provider transaction.
- Gemini's read-only review bridge could not start (`spawn powershell.exe ENOENT`). No payment credentials or external requests were used.

## 2026-11-23 — contact-page density

- Compared the current `/docs/contacts.html` static route with `design-references/09-contacts.png` and inspected fresh 1440 px and 390 px full-page screenshots.
- Reduced the measured desktop contact response section from 694 px to 542 px and the map-note-to-form gap from 88 px to 55 px. The mobile form retains 46 px touch controls; the static route has no horizontal overflow or browser-console errors.
- `node tests/visual-fidelity-regression.cjs` passed for the `contacts` route in static mode. This was one focused visual check; PHP rendering and the full route matrix were not rerun.
- Screenshots and comparison notes: `visual-qa/contacts-reference-followup-2026-11-23/` and `visual-qa/contacts-reference-followup-2026-11-17/comparison.md`.
- After commit `56c3bee`, workflow [45186061680](https://github.com/altento/tokyo-premium/actions/runs/45186061680) deployed the change. The same focused Playwright route passed on public Pages at 1440 px and 390 px; public screenshots were inspected and saved in `visual-qa/contacts-reference-followup-2026-11-23/public/`.

## 2026-11-23 — contacts reference order

Moved the transport/status strip before the visit-planning banner in the PHP template and static export to follow reference 09. Local contact-route visual regression passed at 1440 px and 390 px; the full-page captures were inspected and the DOM order is asserted. The Pages workflow [45187820484](https://github.com/altento/tokyo-premium/actions/runs/45187820484) passed, and the public route passed the focused browser regression at 1440 px and 390 px. PHP browser rendering was not run locally. Evidence: `visual-qa/contacts-strip-order-2026-11-23/`.

## 2026-11-23 — philosophy desktop rhythm follow-up

The focused philosophy visual test passed at 390 px and 1440 px locally and publicly after aligning the desktop timeline years beside events, keeping descriptions at 12 px, and reducing the story image and principles/CTA spacing. Images loaded, headings and four red rules stayed visible, and neither viewport overflowed. Mobile document height remained 5821 px; local/public desktop measurements were 2543/2526 px. Pages workflow [45191130338](https://github.com/altento/tokyo-premium/actions/runs/45191130338) passed build and deploy. The page-density gap remains. PHP rendering was not run locally. Evidence: `visual-qa/philosophy-density-final-pass-2026-11-23/`.

## 2026-11-23 — booking floorplan legibility

Increased map-zone labels, explanatory notes and table numbers in both source and static CSS; moved the private-room title to avoid collision with its note. Added an SVG text-overlap assertion to the focused floorplan regression. Local and public Pages Playwright runs passed all 42 preference markers, zone/photo synchronization, reset and mobile-picker checks. Desktop/mobile captures were reviewed against reference 07; map text is clearer, but the whole-page composition remains a known visual gap. Workflow [45193974873](https://github.com/altento/tokyo-premium/actions/runs/45193974873) passed build and deployment. PHP browser rendering was not run. Evidence and comparison: `visual-qa/booking-floorplan-legibility-2026-11-23/`, including public captures.

## 2026-11-23 — home desktop density follow-up

The static home route passed the focused visual-fidelity regression locally and on public Pages at 1440 px and 390 px. Desktop full-page height decreased from 3100 px to 2841 px while the four public product cards and route checks remained valid; the mobile screenshot remains 390×4708. Public screenshots were inspected against reference 01. The home page is still about 681 px taller than the width-scaled reference and does not claim full visual parity. Workflow [45196587167](https://github.com/altento/tokyo-premium/actions/runs/45196587167) passed build and deployment. Evidence and comparison: `visual-qa/home-density-followup-2026-11-23/`, including public captures.
