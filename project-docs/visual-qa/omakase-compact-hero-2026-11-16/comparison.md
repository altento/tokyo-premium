# Omakase compact hero — 2026-11-16

Reference: `design-references/04-omakase.png`. Captures: `hero-1440.png`, `hero-320.png`, `omakase-desktop.png`, `omakase-mobile-320.png`.

The first screen follows the reference composition: chef image on the right, clear title and copy on the left, Japanese side mark, and two direct actions. The mobile layout stacks the same content over the photograph without horizontal overflow. The full page retains the five-stage interactive tasting sequence, set choices, and explicit educational-concept disclosure. Lower sections are an adaptation and are not a pixel match to the reference.

Focused local static browser check: HTTP 200 at 1440 px and 320 px; hero heights 460 px and 449 px; title and copy bounding boxes are within the viewport; five course tabs are present (desktop flex, mobile grid); the mirrored photograph style is active; document width equals viewport; no console/page errors or failed requests. Screenshots were visually inspected. The route is static HTML; PHP rendering was not exercised because this environment's PHP has no PDO SQLite driver. No broad suite, CI run, full exporter run, or public deployment was performed.
