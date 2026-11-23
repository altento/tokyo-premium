# Account mobile favorites preview — 2026-11-23

Compared the seeded mobile account capture with `design-references/11-account.png` and inspected `11-account-seeded-mobile.jpg`. At 390 px the final odd favorite is now a full-width compact row with its photo, title, price and 44 px add target; secondary description is omitted only from this overview preview. The full Favorites tab keeps its full details. The two-column preview above it is unchanged.

Focused check: `node tests/account-demo-regression.cjs` against the local static export. Passed, including the final-card width, hidden preview-copy and quick-add target assertions, plus the existing 320 px account layout checks. Captures from this run: `11-account-seeded-desktop.jpg`, `11-account-seeded-mobile.jpg`, and the two notification-state captures.
