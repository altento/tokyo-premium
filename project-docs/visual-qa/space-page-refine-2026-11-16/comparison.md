# Space gallery hero — 2026-11-16

Compared with `design-references/06-gallery.png`, the hero now uses the reference's direct title “Пространство”, subtitle “Атмосфера современной Японии”, concise architectural copy, and a single gallery CTA. A route-specific layout reduces the oversized opening on desktop and keeps the Russian title on one line there. On 320 px mobile, the title and CTA remain within the hero; genuine gallery content and the explicit note that this is not a 360° tour are preserved.

The current page keeps four illustrative zone cards, the filterable photo gallery, and an honest demo-data note. Its lower section order and visual density remain an adaptation rather than a pixel match to the reference; no fake panorama or restaurant availability was added.

Evidence: `space-gallery-desktop.png` (1440 px) and `space-gallery-mobile.png` (320 px). The static route returned HTTP 200 at both widths with no JavaScript errors, failed requests, or horizontal overflow. The heading fit its copy area, and the gallery CTA resolves to the `#gallery` anchor. `php -l pages.php` and `git diff --check` passed. The PHP-rendered browser route was not tested because the active PHP runtime lacks PDO SQLite.

## Zone-card content follow-up

The four cards now use the reference's zone names—Sushi Bar, Main Hall, Private Room, Summer Terrace—with distinct descriptions and illustrative capacities instead of repeating one description or labeling the garden terrace as a tasting room. Captures `space-zones-desktop.png` (1440 px) and `space-zones-mobile.png` (390 px) show all four cards and their individual copy. A single static Playwright route check confirmed the four labels/descriptions, HTTP 200, no console errors, and no horizontal overflow. PHP browser rendering remains limited by the unavailable PDO SQLite driver.
