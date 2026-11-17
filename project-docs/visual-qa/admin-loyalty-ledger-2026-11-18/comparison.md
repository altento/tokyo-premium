# Tokyo Privilege admin ledger — focused visual QA

No approved PNG reference exists for this new administrator feature. The review checks consistency with the existing dark/gold/red admin art direction and tests actual responsive geometry rather than claiming a pixel comparison against the public-facing 11-page restaurant references.

## Captures

- `ledger-1440.png` — section entry, desktop filters and result summary.
- `ledger-1440-table.png` — desktop table at its natural 1236 px content width.
- `ledger-390.png` — section entry and filters on a 390 px viewport.
- `ledger-390-table.png` — mobile scroll hint and left side of the ledger.
- `ledger-mobile-table-details.png` — same mobile table after horizontal scrolling to the linked-event and note columns.

## Observations

- The heading, gold filter labels, red action, dark bordered panel, and data table follow the existing admin theme. The wide filter becomes a single-column form on mobile.
- At 390 px the document width remains 390 px. The 900 px ledger is clipped only by its own 340 px scroll container, preserving all columns without page-level horizontal overflow. The mobile hint appears above the table; the region can also receive keyboard focus and has that hint as its accessible description.
- At 1440 px, the table fits within its 1236 px container and the mobile-only hint is hidden.
- Browser run rendered 25 rows in each first-page capture, with no console or page errors. Search/filter state behavior and HTML escaping are covered separately by the focused SQLite regression.

These captures are feature-level desktop/mobile evidence. They do not establish full visual acceptance of Tokyo Premium or pixel-level parity across the 11 restaurant page references.
