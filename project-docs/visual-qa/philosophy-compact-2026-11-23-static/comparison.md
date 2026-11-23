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
