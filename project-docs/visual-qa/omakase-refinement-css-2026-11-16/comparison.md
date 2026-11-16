# Omakase stylesheet delivery — 2026-11-16

The shared PHP page head did not include `luxury-refinements.css`, even though the static exporter copied it and the file contains the Omakase hero mirror and targeted responsive refinements. The stylesheet is now linked after `premium.css` in `bootstrap.php`; the current checked-in Pages route has the corresponding link so the static behavior can be inspected before a database-backed re-export.

The chef now faces into the hero from the right, leaving the title and actions clear on the left. At 390 px, the five course tabs form a visible two-column grid. The five catalog-backed course stages, image gallery, and set options remain available.

Evidence: `omakase-desktop.png` (1440 px) and `omakase-mobile.png` (390 px). The static route returned HTTP 200, the refinement stylesheet returned HTTP 200, all five tabs fit, and the page had no browser errors or horizontal overflow. The computed hero transform was `matrix(-1, 0, 0, 1, 0, 0)` on both widths. PHP syntax lint passed; a PHP browser render was not available because the active PHP runtime lacks PDO SQLite. The full generated Pages export, CI and public deployment were not run.
