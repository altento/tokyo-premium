# Catalog ordering and cart feedback · 2026-11-19

## Reproduction and repairs

[Before](before.json) records actual public-browser observations:

- Ascending first page showed prices 1200/2500/2800/4500/4800/4900 while the six global cheapest were 1200/1500/1800/2200/2300/2400. Sorting moved DOM nodes, but filtering/pagination still indexed the initial array. Page membership now uses current sorted DOM order.
- Favorite refresh reset the current page. It now retains the page, clamping only when filtered results shrink. Search trims outside spaces; filter selection is exposed with `aria-pressed`, and a live result count explains the current page/empty state. Pager focus follows the current-page button, boundary arrows disable, and reduced-motion paging scrolls immediately.
- With 19 units already in the cart, requesting two silently produced 20 and reported success; another click at 20 still reported success. UI additions now reject the over-cap request and explain remaining capacity, using the existing atomic `addMany` path for accepted additions. `cart-store.js` and its legacy clamp contract were retained.
- Quick-view feedback used an external toast excluded from the accessibility tree while the native dialog was modal. The dialog now owns a live status for success/errors, cleared when a new dish is opened. The detail link also restores its visibility when a linked card is opened.

## Actual checks

`test:menu-state` passed against the local PHP/SQLite application and separate static export. It concatenates every visible page and compares the complete catalog order for ascending/descending/name sorts, restores the default, composes search/category/sort/favorites, checks empty results, selected ARIA and keyboard pager focus. It verifies reject-without-partial-change at 19+2 and at the full limit, accepted 19+1, native modal accessibility through Chromium CDP, mobile feedback bounds and Escape focus restoration. Both modes passed 320/390/768/1024/1440/1920 px. [Result](browser.json).

Existing `test:menu-visual` passed seven widths in each mode, including loaded photos, header controls, composition, red heading divider, view switches and ingredient filters. Tokyo 2.0 passed PHP + static, including 21 PHP and 19 static detail-route checks; cart-store passed 14 checks. Static export produced 37 pages. JS syntax and diff checks passed. This pass did not add backend pricing/discount logic or rerun local PHP integration unnecessarily; deployment retains its normal server checks.

## Inspected visual evidence and scope

Opened `design-references/02-menu.png` and inspected [ascending desktop catalog](menu-price-ascending-desktop.jpg), [mobile catalog](menu-price-ascending-mobile.jpg), [modal success](quick-view-success.jpg) and [mobile full-limit feedback](quick-view-mobile.jpg). The first page now actually contains the cheapest six available catalog items. The new result line fits above the grid; red heading rules, the category column, three-column desktop layout and existing photos remain. Modal feedback is readable inside the window rather than behind its backdrop.

The approved concept defaults to a sushi section and uses different food/ornament arrangements. This view deliberately shows all categories sorted by price; it is a functional state comparison, not a pixel-parity claim or new eleven-page redesign. No photos were generated or replaced. Full-page captures temporarily normalize the sticky header and decode lazy images/fonts, without changing the shipped header.

Publication/public verification will be appended after the release.

## Publication

Implementation `270e6ef` was pushed to main. [Actions 44540680387](https://github.com/altento/tokyo-premium/actions/runs/44540680387) passed its configured build/server checks and Pages deployment. Optional full browser CI was skipped; local PHP/static browser results are recorded above.

The same focused menu test then passed in static-only mode against the actual public Pages origin, including complete pagination/order, composed filters, preserved favorite page, accessible modal status/mobile bounds/Escape focus and strict UI quantity acceptance. [Public verification](public-verification.json) records the origin, commit, time, actual selected test mode and zero page errors. All cart/favorite mutations used a fresh browser-local fixture. This does not claim a public PHP host or a new server pricing/payment test.
