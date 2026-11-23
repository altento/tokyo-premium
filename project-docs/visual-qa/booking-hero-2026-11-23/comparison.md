# Booking hero visual refinement — 2026-11-23

Reference 07 leads with a warmly lit, prepared dining table framed by the Japanese garden. The previous booking hero image emphasized the circular garden window, leaving the table secondary. The hero now uses the existing `room-main-hall.webp` photo, which shows a set table, lanterns and garden; the desktop and mobile crops are tuned separately. The same source is used for the page's social preview.

Inspected full-page Pages captures at 1440 px and 390 px. The screenshot shows the new prepared-table composition and the existing floorplan/form states. `floorplan-regression.cjs` passed in Pages mode with explicit assertions for the image source and both crop positions, plus the existing zone, table-preference, reset, mobile-picker and no-error checks.

This improves the hero's subject and framing relative to reference 07. The page still uses its own editorial layout and does not claim pixel-perfect reference parity. PHP runtime rendering could not be run locally because this Windows PHP CLI has no PDO SQLite module; the PHP template and static export are synchronized and CI regenerates Pages from PHP.
