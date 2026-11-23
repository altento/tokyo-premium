# Booking preference state — 2026-11-23

Compared the current static booking page with approved reference `design-references/07-reservation.png` and the prior full-page captures.

## Confirmed issue and correction

- The PHP floorplan synchronized its default zone and cleared table selection after form reset. The checked-in Pages copy of `experience-ui.js` did neither, so it could show “Зона не выбрана” after the map and form had already selected the main hall, and a reset left the prior table highlighted.
- `pages.php` and `docs/booking.html` now keep the form's duplicate status node hidden for assistive and visual output. The map remains the single visible live status.
- The Pages floorplan now synchronizes the default zone and clears the table preference, highlights and message after reset, matching the PHP behavior.

## Focused verification

`TOKYO_FLOORPLAN_MODE=Pages node tests/floorplan-regression.cjs` passed against the local static export. It checked all 42 table preference markers in four zones, initial form/map/photo synchronization, one visible status, table selection, form reset, the mobile zone/table picker, no horizontal overflow and no browser page errors. It generated the desktop and mobile screenshots in this folder; both were visually inspected.

The comparison retains the reference's reservation hero, benefits, booking controls, plan, selection summary and FAQ hierarchy. Photography and the educational preference-only language intentionally differ from the concept artwork. This is a focused state and composition check, not pixel-perfect acceptance of the entire page. A PHP browser pass was unavailable locally because PDO SQLite is not installed.
