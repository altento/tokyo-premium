# Menu and dish detail — 2026-11-19

Opened approved references 02 and 03. Captured the published baseline, then current PHP/static pages. Inspected complete desktop dish and mobile dish captures, the small-mobile menu, and paired reference comparisons. Final full-page captures wait for photographs/fonts, temporarily put the sticky header in normal document flow, scroll to the top and wait for transient add notifications to disappear. This capture treatment does not change the product's header behavior.

## Changes

- Menu card links have 44 px touch targets; image proportions, rounded cards and heading scale are more consistent. The separate list view remains supported. At 320 px the hero uses less padding without hiding its description or demo note.
- Renamed default sorting to «Порядок меню»: its actual behavior restores the original catalogue order, rather than grouping categories. Restoring that order is now tested explicitly.
- Set-builder story copy describes the dining experience instead of PHP implementation details.
- Product purchase button now uses the primary red treatment, with visible keyboard focus and 44 px quantity controls. Chef, ingredient and related-product sections have tighter spacing and smaller headings; recommendation add controls remain easy to tap.
- Corrected two test assumptions discovered while extending menu coverage: the PHP menu's booking URL is `pages.php?page=booking`, not `booking.html`; the existing 320 px layout uses one card column rather than two. These were test errors, not broken booking navigation or catalogue layout.

## Verification and evidence

`menu-visual-smoke.cjs` covers PHP and static menus at 320, 390, 900, 1023, 1024, 1440 and 1920 px. It checks navigation, photos, filtering, pagination/view controls, red section divider, overflow and the default-sort restore, search/category/favorites/sort combination and quick-view add-to-cart.

`dish-detail-focused.cjs` passed for 19 public catalogue detail routes per mode, exact titles, loaded principal photographs, three distinct salmon photographs and switching, quantity two reaching the shared cart, and representative detail layout at six widths from 320 to 1920 px. The two server-restricted Secret Menu details were not included in this focused run. No missing HTTP assets or page errors were reported.

Export generated 37 pages. Screenshots: `after/02-menu-current-{php,static}-*.jpg`, `after/03-dish-{php,static}-{390,1440}.jpg`. Reference pairs: `menu-comparison.jpg`, `dish-comparison.jpg`. Baselines: `menu-before.jpg`, `dish-3-before.jpg`.

The pages still differ from the approved art direction: images/decorative density are different, the ingredient list uses actual catalogue records, and no fictional rating, nutrition or video is added. This is a scoped improvement, not complete visual acceptance of all eleven pages. Browser-local checkout remains a demo; no payment integration was activated.

Publication: implementation `2ae3c66`; [build/deploy 44463585674](https://github.com/altento/tokyo-premium/actions/runs/44463585674) succeeded. Fresh public Chromium checks confirmed menu at 320 px (HTTP 200, no horizontal overflow, 320 px hero, 44 px card action, corrected default-sort label) and dish 3 at 390 px (HTTP 200, no overflow, red purchase treatment and quantity two in the shared cart). The purchase hover uses a solid red background, while its normal state is a red gradient.
