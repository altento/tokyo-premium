# Account and populated cart — 2026-11-19

Opened references `11-account.png` and `10-cart.png`. Captured current populated pages, inspected the resulting desktop account and desktop/mobile cart, and inspected both paired reference comparisons. Source is the existing PHP application and its static export; every displayed price comes from the project catalogue.

## Confirmed fixes

- The new nested order header was constrained by legacy direct-child grid rules. Order amount and sample label overlapped in the narrow column. The header now spans its row; history occupies the full dashboard width; sample and browser-saved records have different badge classes. A saved record remains labeled as browser-local.
- The first dashboard row had a 9 px vertical mismatch; the sidebar and member summary now align. The desktop masthead was over the existing 330 px limit; compacted its spacing and heading without hiding privacy information.
- Favorites use a full-width three-card row, with larger add controls; address and settings previews sit below. Order history, favorite quick-add, notifications, saved addresses and clearing/restoring examples retain their existing logic.
- Cart controls have 44 px tap targets. Recommendation cards have balanced images, typography, bottom-aligned prices/buttons and an overlaid favorite control. On mobile the four recommendations form a keyboard-focusable horizontal rail rather than four tall stacked cards. Checkout remains before recommendations.
- Found an unrelated actual export defect during regression: dish detail pages referenced `assets/dish-detail-refinement.css`, but asset copying excluded CSS. Export now copies asset CSS and rejects pages whose local stylesheet files are missing.

## Evidence

`account-before.jpg` and `cart-before.jpg` show the starting composition. `after/` contains populated account desktop/mobile, notifications, and eight cart states: empty, populated, validation, pickup and delivery. The cart fixture contains three catalogue dishes with one quantity of two; before/after fixtures use different dishes and totals, so the comparison is structural. The approved reference has different fictional data and is not used to supply prices.

The account and cart focused browser regressions passed, including new overlap, full-width history, mobile recommendation rail and tap-target checks. Both paired comparisons were visually inspected. Pages remain intentionally different from the references: account includes browser-local disclosures, referral and booking history; checkout uses current catalogue data and a progressive form. This is not final visual acceptance of the whole site or a live payment check.

After fixing stylesheet export, `test:tokyo20` passed for PHP and static: 21 PHP and 19 static dish routes and add-to-cart actions, offers, Omakase, set builder, preference wizard, seating preference, gift preview, quick view, profile and responsive overflow. Export produced 37 pages. PHP syntax, JavaScript syntax and whitespace checks passed. The `integration/` captures were produced by this regression; the visual comparison above is specifically based on `after/`.

Published implementation: `af7caad`; [build/deploy 44459741707](https://github.com/altento/tokyo-premium/actions/runs/44459741707) succeeded. Fresh public Chromium checks passed for account at 390/1440 px (no order-title/amount overlap; document width matches viewport; desktop masthead 324.8 px), populated cart at 390 px (focusable 1002 px rail within 342 px viewport; displayed total matches 12,700 ₸), and the previously missing dish stylesheet (HTTP 200).
