# Menu grid/list view — 2026-11-16

The catalog now matches the reference's grid/list choice. Grid remains the default. List mode turns each card into a photo-led horizontal row; on mobile, the row keeps a compact image column and stacks the card actions. The toggle uses labelled buttons with `aria-pressed` state and visible keyboard focus.

`menu-list-1440.jpg` and `menu-list-390.jpg` show the cards; `menu-toolbar-1440.png` and `menu-toolbar-390.png` show the controls. A route-focused run at both widths confirmed the active list state, correct card layout, page width, grid restoration, and search filtering for the unique “Рамен с говядиной” result while hiding nonmatches. The separate static route run also checked the set-builder shortcut and loaded menu imagery. PHP syntax passed; PHP browser rendering, CI, and deployment were not run for this follow-up.
