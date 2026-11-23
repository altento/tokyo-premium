# Home section hierarchy follow-up — 2026-11-23

Reference: `design-references/01-home.png`.

The section labels and headings for recommendations, philosophy, space, and booking now follow the reference's concise editorial hierarchy. The original narrative, photos, functional calls to action, and booking form remain in place. The PHP and GitHub Pages source files were synchronized through the static exporter.

Visual review: `home-php-desktop.jpg` (1440 px) and `home-php-mobile.jpg` (390 px) were opened and inspected. Long booking title wraps cleanly on mobile. The recommendation cards and section transitions remain visible. This change improves heading alignment; it does not claim full-page parity with the reference.

Browser check: `TOKYO_VISUAL_ROUTES=home`, `TOKYO_VISUAL_MODE=both`, `npm run test:visual-fidelity` passed for PHP and static routes. The test captured PHP desktop/mobile evidence; the static route was validated without a separate screenshot.
