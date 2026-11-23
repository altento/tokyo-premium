# Philosophy page — compact desktop pass

Date: 2026-11-23. Reference: [`design-references/05-philosophy.png`](../../../design-references/05-philosophy.png)

## Change

Tightened the desktop-only chapter spacing, image heights and timeline typography on the philosophy page. Shortened the four fictional timeline descriptions while retaining their explicit educational-project disclosure. Tablet and mobile spacing rules remain in effect; the static export markup and PHP template use the same revised copy.

## Browser check

Served the checked-in Pages export locally and ran `tests/philosophy-visual.cjs` at 390 px and 1440 px. Both passed: page width matched the viewport, all main images loaded, heading text bounds stayed visible, and no JavaScript page errors were recorded. The 1440 px document height is 3039 CSS px; the 390 px document height is 5769 CSS px. Screenshots: [`desktop.jpg`](desktop.jpg) and [`mobile.jpg`](mobile.jpg). Metrics: [`metrics.json`](metrics.json).

The test was updated to support a short viewport list and to cap image/font readiness waits, so a stalled remote font cannot turn this focused check into a long wait.

## Reference comparison and limits

I opened the approved PNG and inspected it alongside the new full-page desktop capture. The section rhythm is tighter and the story timeline is more compact. The page is still materially longer: the reference is 1024 × 1536 px, or 2160 px high when scaled proportionally to 1440 px width; the current 1440 px capture is 3039 px high (about 41% taller). Its hero image, added chef/source/contact chapters, and footer also differ. This is a focused visual improvement, not pixel-perfect parity or completion of the remaining page reviews.

The local PHP CLI is PHP 8.5.11 but lacks PDO SQLite. A PHP-rendered capture failed in the footer because the database driver is unavailable, so this comparison uses the static Pages export only and does not claim PHP browser verification.

## Red section dividers follow-up

The reference's recurring red section accent was missing under the philosophy story, chef, sourcing and contact headings. Added the same 42 × 2 px crimson rule beneath those four headings in the source and Pages CSS. The focused Playwright check now verifies all four pseudo-elements are visible, in addition to the existing image, title-bound and overflow checks. At 390 px and 1440 px the local Pages export passed; refreshed full-page captures and metrics are saved here. This corrects the missing accent only and does not close the documented full-page composition gap.

Commit [`e0e1e41`](https://github.com/altento/tokyo-premium/commit/e0e1e413172305afed847763fff5ebbef9c1a02f) deployed successfully in [workflow 45157250560](https://github.com/altento/tokyo-premium/actions/runs/45157250560). The same focused browser check passed against the cache-busted public page at 390 px and 1440 px; refreshed public screenshots and metrics are in [`public/`](public/). PHP-rendered browser verification remains unavailable locally because PDO SQLite is missing.

## Public verification

After commit [`038413a`](https://github.com/altento/tokyo-premium/commit/038413a04ca8ef5a4227fca3333b78aa79bffa87) deployed successfully in [workflow 45155137552](https://github.com/altento/tokyo-premium/actions/runs/45155137552), the same focused Playwright check passed against the public GitHub Pages route at 390 and 1440 px. Public screenshots and metrics are in [`public/`](public/). The live 1440 px document is 3039 CSS px tall and uses the compact desktop story spacing. CI's optional full browser suite was skipped by the fast profile; this public visual check ran separately with the repository Playwright test.
