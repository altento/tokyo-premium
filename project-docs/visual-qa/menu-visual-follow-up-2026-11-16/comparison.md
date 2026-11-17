# Menu visual follow-up — 2026-11-16

Reference: `design-references/02-menu.png`. Captures: `menu-1440-loaded.png`, `menu-390-loaded.png`.

The desktop keeps the reference's category rail, search/sort controls, three-column dish grid, pagination, and authored-set story. The phone view retains a horizontally browsable category strip and a readable two-column catalog. The site uses its own original Japanese-garden-and-sushi hero photograph, so its image composition is an intentional adaptation rather than a copy of the reference. The menu catalog's bottom padding is now capped at 58 px on desktop and 32 px on mobile to bring the set-builder story closer to the catalog.

A focused local static check passed at 1440 px and 390 px: HTTP 200, all 22 page images decoded before full-page capture, document width matches viewport, and there were no browser errors or HTTP failures. Screenshots were visually inspected. The PHP route was not rendered because this runtime lacks the PDO SQLite driver; this was not a full menu-interaction test, broad suite, hosted CI run, or public deployment.

Follow-up: removed the empty “Сеты 0” filter and placed “Собрать сет” beside “Все блюда” in both PHP and static menu templates. At 1440 px and 390 px the shortcut was visible without horizontally scrolling the category strip; clicking it opened the static set builder. The focused run found no empty filters or browser errors. Cropped controls are saved as `category-controls-1440.png` and `category-controls-390.png`. The PHP route, full suite, CI, and public deployment were not checked in this follow-up.

The menu toolbar now includes an accessible grid/list toggle, with a real horizontal-card list layout on desktop and mobile. In list mode, a search for “Рамен с говядиной” must show only the matching card; the focused regression asserts this and restores grid mode for the standard captures. Visual crops are in `../menu-grid-list-2026-11-16/`.

The six reference-aligned ingredient filters are also available and combine with category and search. The browser check verifies a salmon Philadelphia roll appears under Rolls + Salmon + “Филадельфия”, while the same query returns no items under Sushi; the filters are then reset before captures.
