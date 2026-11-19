# Booking clock and table identity · 2026-11-19

## Confirmed problems

- [Client before](client-before.json): browser demo saved a same-day 12:00 visit while the restaurant clock was 15:00. After a tab crossed midnight, selecting a valid future date left the old custom error active. Initial fresh date bounds already worked; this was a stale validation-state defect, not a claim that all date handling was broken.
- [API before](api-before.json): isolated PHP accepted same-day 12:00 at restaurant time 18:29 with HTTP 201.
- [Table before](table-before.json): a real table42/terrace preference returned HTTP 422 because the endpoint only allowed 1–13, while the current SVG exposes 42 tables.
- The new browser test exposed a home-form reset race: a zero-delay callback removed the new success class after the submitting handler had written it. Reset now clears only the previous result synchronously and resets its progressive step in a microtask.

## Repairs

One shared browser clock handles the existing restaurant date bounds and per-date time options. Input/change, visibility/page return, focus, reset and submission refresh dependent state; open booking pages also refresh once a minute. Correcting a date clears its previous custom error. Elapsed options are disabled; if all scheduled hours are elapsed, the hint asks for another date. The progressive home handler and static saving handler explicitly validate again because they can intercept submission before later handlers.

PHP checks a complete visit timestamp in `Asia/Qyzylorda`, independently of the browser clock and process timezone. Equality/past timestamps are rejected; allowed hour slots and the 365-day horizon remain. There is no invented two-hour lead policy.

`lib/booking_tables.php` reads only the checked-in SVG's table metadata, without requiring XML/DOM extensions. All 42 IDs are accepted in their correct zone; wrong explicit zones and unknown IDs are rejected. A missing zone can be inferred from a valid table. Existing zone aliases remain. Drawn capacity is informational because the table is a preference, not a guaranteed seating arrangement.

If the SVG request fails, the interface offers only real zone names with an empty table preference. The obsolete hardcoded 13-table layout was removed, and the unrelated default room image is hidden. Fallback captions consistently explain zone-only selection.

## Verification

[API after](api-after.json) and [table after](table-after.json): 64 passing helper/HTTP assertions, zero skipped. Tests cover exact equality and one-second future boundaries, restaurant/UTC midnight, leap/calendar/365/366-day cases, guests, actual table boundaries, aliases/inference, table42 acceptance/persistence, mismatch/unknown rejection, CSRF, no writes/outbox/rate-limit effects on invalid requests and valid retry deduplication. Databases/sessions are isolated and SMTP is disabled.

[Browser result](browser.json): both home and full forms in PHP/static modes, a browser clock two months after export and in another timezone, a midnight rollover followed by correcting the date, leap-day limits, elapsed/fully elapsed hours, reset, final-time revalidation and successful local saves. Mobile bounds passed at 320/390/768/1024/1440/1920 px. Failed-map testing injects an intentional SVG HTTP 503; it is not a broken deployed asset.

Existing `test:home-booking`, `test:notification-identity`, `test:floorplan` and 14 cart checks passed. Tokyo 2.0 passed PHP + static, including 21 PHP/19 static detail-route checks; after the table repair, PHP mode and 229 PHP/SQLite integration assertions passed again. Test fixtures for unrelated booking flows now use tomorrow so they do not depend on an already elapsed default hour. PHP/JS syntax, diff hygiene and a 37-page export passed. No real SMTP or merchant transaction ran.

## Inspected visual evidence

Opened `design-references/07-reservation.png`, then inspected full [desktop elapsed-hours state](no-future-times-desktop.jpg), [mobile elapsed-hours state](no-future-times-mobile.jpg) and [mobile unavailable-map state](map-unavailable-mobile.jpg). Time hints remain within their fields; red heading rules and the existing map/submit ordering remain. The fallback note spans both columns, zone controls are usable and no incorrect room image remains. Relative header positioning and eager image decoding are capture-only normalization.

The current page retains a different hero, a longer contact form and an illustrated SVG rather than the approved concept's photorealistic map. This pass fixes real booking logic/states; it does not claim pixel parity, a 360° tour or live table availability. Publication will be recorded after deployment.
