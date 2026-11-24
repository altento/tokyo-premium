# Philosophy timeline spacing — 2026-11-25

Fixed the desktop timeline so each year has a dedicated column and the heading/description use the nested markup that actually exists in `about.php`. This removes year/title collisions while preserving the stacked tablet/mobile layout.

Both PHP and exported static pages passed `npm run test:philosophy-visual` at 320, 390, 768, 1024, 1440, and 1920 CSS px. The updated evidence was also included in the full `npm run test:visual-fidelity` run across all 11 routes in both modes.

- `philosophy-php-desktop.jpg`, `philosophy-php-mobile.jpg`
- `philosophy-static-desktop.jpg`, `philosophy-static-mobile.jpg`
- `php-metrics.json`, `static-metrics.json`

All visible restaurant content is fictional educational data.
