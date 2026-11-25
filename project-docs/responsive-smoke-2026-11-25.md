# Responsive smoke check — 2026-11-25

## Scope

Published GitHub Pages demo: https://altento.github.io/tokyo-premium/  
Static export commit: [d39368d](https://github.com/altento/tokyo-premium/commit/d39368da325690b3b2b03e66de25512f31dad3f2)  
Responsive pagination CSS source fix: [e95296c](https://github.com/altento/tokyo-premium/commit/e95296c159a504e070c1dad9b31de8a0c2465a16)  
Pages workflow: [45523840607 — success](https://github.com/altento/tokyo-premium/actions/runs/45523840607)

## Result

Browser smoke checks passed for 13 public page types at 320 px and 1280 px: home, menu, Omakase, offers, perfect evening, set builder, philosophy, gallery, reviews, contacts, account, booking, and cart. The document had no horizontal page overflow at either width. This is a responsive and route smoke check; it does not claim that every section received a final desktop visual redesign review.

The menu pagination previously kept 11 page buttons in a single row, making the document wider than a 320 px viewport. The mobile rule now wraps those buttons into multiple rows. On the 320 px menu, the pagination occupies 148 px, its buttons retain a 44 px minimum touch target, and the page no longer overflows horizontally. The menu still shows all 50 public dishes.

Additional focused checks:

- Menu list-view “Добавить” action stays inside the dish card at 320 px (button measured 108 × 42 px).
- Selecting “Закуски” keeps the scroll position stable (61 px before and after); six results are shown.
- Booking guest options use “1 гость”, “2–4 гостя”, “5–12 гостей”; the date, time, zone and contact controls are present.
- Omakase shows five course cards and the three format options; no “6 подач” label remains in the static export.
- No order, booking, email, or payment was submitted.

## Limits and outstanding work

These checks target GitHub Pages, which is a static demonstration and does not run PHP, persist to the server database, or exercise server-side admin/authentication. SprintHost remains on the older PHP release and still exposes `/seed.sql`; safe PHP synchronization must wait until the SQL download is blocked. A full paired visual review of all page sections and live PHP admin workflows is still open; see [site-finish-checklist.md](site-finish-checklist.md).
