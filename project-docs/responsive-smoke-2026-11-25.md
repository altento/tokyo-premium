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


## Current SprintHost PHP routes — 2026-11-25

A fresh read-only HTTP smoke check used the routes linked from the deployed site: `/index.php`, `/menu.php`, `/pages.php?page=omakase`, `/offers.php`, `/about.php`, `/pages.php?page=gallery`, `/reviews.php`, `/pages.php?page=contacts`, `/pages.php?page=booking`, `/pages.php?page=cart`, `/account.php`, `/set-builder.php`, `/perfect-evening.php`, `/collection.php?slug=autumn-in-kyoto`, `/gift-certificates.php`, and `/admin.php`. All 16 returned HTTP 200 with no PHP fatal, warning, uncaught exception, or SQLSTATE in the response. The live menu returned 50 dish cards.

Security and release checks remain open: `/seed.sql` returns HTTP 200 (11,351 bytes), while `/.env` and `/schema.sql` return 403. The live PHP Omakase route still contains the old “6 подач” text; current GitHub Pages shows five. The last read-only database audit recorded SprintHost at schema migration 21, while the current application source expects migration 22. Do not infer the database version from the visible seasonal collection; check `PRAGMA user_version` after the code update. The technical domain is HTTP-only. No login, booking, payment, or write operation was performed.
