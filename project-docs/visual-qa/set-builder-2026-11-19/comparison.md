# Set builder — 2026-11-19

Baseline `d4916b7`; restoration branch `backup/set-builder-20261119`.

## Reproduced defect and correction

[Before-fix values](reproduction.json) and [desktop view](before-desktop.jpg) record three selected dishes worth 8,200 ₸ while the summary still displays 0 ₸, 0/8 and no progress value. The composition list itself updated. The summary lives beside the catalog, but its price/count/progress queries searched only inside the catalog. All summary queries now use the shared set layout.

The one add-set action and its status moved beside the composition. The clear action removes only the current draft; it does not remove a previously accepted cart set. Increment/decrement controls expose actual per-dish, total-portion and distinct-dish limits. The format heading no longer assumes a fixed number of admin-managed variants.

## Visual inspection

Inspected the complete [desktop](builder-1440.jpg) and [mobile](builder-390.jpg) captures. The selected draft contains three dishes/four portions worth 12,700 ₸; quantities and price match catalog records. On desktop the catalog remains left and summary right, with the action adjacent to the price. Prices keep their currency on one line and controls wrap beneath them when needed.

On phones, composition appears before the catalog. The [native interaction view](builder-mobile-controls.jpg) shows its fixed total/jump link, which returns to the composition instead of duplicating the add action. The [open menu](builder-menu-open.jpg) shows that the bottom link is hidden during navigation. Full-page captures temporarily put the sticky header in normal flow and hide the fixed bottom link to avoid stitching artifacts; native interaction captures preserve both runtime positions.

This new route has no approved PNG among the original eleven. It uses the existing typography, red rules, gold lines, catalog photography and dark surfaces. It remains a long catalog rather than a rendered image of a fictional assembled platter.

## Actual checks

- Extended `test:set-draft` passed in PHP and static modes: live price/count/progress, restore after refresh, per-dish/portion/distinct limits, clear/disabled state, valid cart transfer and draft cleanup, preservation of cart sets and rejection of invalid saved IDs.
- Six widths (320, 390, 768, 1024, 1440, 1920 px): no document overflow or clipped heading text, one add action, consistent totals; mobile jump and menu open/Escape states passed.
- `test:tokyo20` passed current PHP/static routes: 21 PHP/19 static dish details and add actions plus offers, Omakase, builder, preference wizard, floorplan, certificate preview, quick view, profile and cart. Subsequent CSS price wrapping and the corrected menu selector were checked by the focused suite.
- Export: 37 pages. PHP/JavaScript syntax and diff hygiene passed. The focused test was added to optional full-QA CI; default fast deployment stays unchanged.

PHP continues to verify the set format, contents and current prices when quoting/saving orders. Browser drafts and Pages totals are estimates, not a paid order or guaranteed restaurant availability. No database migration, email or payment setup was performed in this update.

## Publication

Implementation `ffd6c59` was pushed to main. [Actions 44475808433](https://github.com/altento/tokyo-premium/actions/runs/44475808433) passed build and Pages deployment using the fast profile. The optional full browser CI suite was skipped; local browser checks above used both modes.

Fresh public Chromium verified the selected three-dish/four-portion draft and its 12,700 ₸ total, progress, reload restoration, cart transfer and clearing a new draft while retaining the accepted cart set at 390/1440 px. Mobile navigation also hid and restored the fixed summary link correctly. No page errors occurred and no order was submitted. [Public verification](public-verification.json).
