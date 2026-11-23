# Home desktop density follow-up — 2026-11-23

Compared the refreshed Pages homepage with [`design-references/01-home.png`](../../../design-references/01-home.png) and the prior public capture in `home-editorial-curation-2026-11-23/public/`.

## Change

The 1440 px layout had oversized editorial spacing and dish photography. Tightened only the desktop layout: smaller recommendation imagery and card padding, reduced chef/space story heading sizes and vertical padding, and a more compact booking band. The four public catalog items, hero scene controls, story order, and mobile styles remain as before. The PHP stylesheet and Pages stylesheet were updated together.

## Result and limits

The inspected 1440 px full-page capture fell from 3100 px to 2841 px (259 px / 8.4% shorter). The 390 px test and full-page screenshot remained within the existing mobile rules. The approved PNG is 1024×1536; when scaled to a 1440 px width it is about 2160 px tall, so the current capture remains roughly 681 px taller. That scaled comparison is a rough density indicator, not pixel matching. Page composition and lower-section proportions still differ from the reference.

## Verification

`tests/visual-fidelity-regression.cjs` passed for the static home route at the test's 1440 px desktop and 390 px mobile viewports. It checked featured images and catalog IDs, visible page content, horizontal overflow and browser errors. Captures:

- `home-static-desktop.jpg` — 1440×2841
- `home-static-mobile.jpg` — 390×4708

This is a focused density pass, not full homepage or all-page acceptance.
