# Philosophy page focused comparison — 2026-11-16

Reference: `design-references/05-philosophy.png`.

## Hero updates

The PHP and static templates now use the reference's concise “Философия” title, gold italic subtitle, Japanese mark, image-led garden setting, and two clear actions. A red rule sits beneath the section title. “Наша история” and “Принципы кухни” link to the existing `#story` and `#principles` sections. The desktop mark sits beside the title; on mobile it moves into the upper-right image area and is hidden at widths up to 360 px to keep the heading clear.

The existing garden photograph and all lower page sections remain in place. The full page is still an editorial adaptation rather than a pixel-level reproduction: the reference groups the story and timeline more tightly and includes additional Fuji and ingredient compositions that the current page does not have.

## Evidence and checks

- `philosophy-hero-desktop.jpg` and `philosophy-hero-mobile.jpg` are focused hero captures.
- `philosophy-static-desktop.jpg` and `philosophy-static-mobile.jpg` are full-page static captures; all four content images were decoded before capture.
- A route-focused Playwright check returned HTTP 200, found no browser errors or failed resources, and checked title bounds, mark overlap, anchor targets, and page width at 320, 390, 768, and 1440 px.
- `php -l about.php` passed. The PHP route was not browser-rendered in this pass.
- `premium.css` and `docs/premium.css` have matching hashes.
