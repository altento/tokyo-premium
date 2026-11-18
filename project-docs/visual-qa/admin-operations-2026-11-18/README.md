# Admin operations smoke check — 2026-11-18

This is one focused browser check of the PHP admin, not the full Tokyo Premium regression matrix. It used an isolated SQLite file under the Windows temp directory (`RESTAURANT_DB`), a throwaway administrator, and four synthetic booking records; no repository or user database was opened or modified.

## Verified in the browser

- Admin login and operational dashboard render.
- Upcoming booking order: today 23:59, tomorrow 19:00, then today's cancelled record and yesterday's cancelled record.
- Today's booking KPI is `1`; the cancelled booking is excluded.
- Confirmed booking shows the `Бронь подтверждена` badge.
- Phone opens with a safe `tel:+77005550102` URI; valid guest email opens with `mailto:guest@example.test`.
- New machine-readable timestamp includes the restaurant offset (`+05:00`) and visible text labels the restaurant timezone.
- At 390 px, the document width remains 390 px and the booking card is readable with the 44 px mobile action targets.
- No page-level JavaScript errors were reported.

## Evidence

- `admin-desktop-top.jpg` — dashboard, navigation, notifications and global search.
- `admin-bookings-desktop.jpg` — operational queue, confirmed badge, contact links and local timestamp.
- `admin-bookings-mobile-390.jpg` — mobile booking list.
- `admin-browser-check.json` — measured assertions.

This check does not exercise email delivery, every admin mutation, the production database, or all responsive widths. The temporary database and login are not included in this folder or repository.