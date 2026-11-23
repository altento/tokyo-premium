# Booking form field order — 2026-11-23

Compared the current reservation page with [`07-reservation.png`](../../../design-references/07-reservation.png) and inspected fresh full-page browser captures at 1440 px and 390 px.

The desktop form previously placed the reply email between the phone and date fields, leaving date and time on different rows and an unused grid cell. Reordered the form into name/phone, date/time, and guests/zone pairs; email, Omakase format and wishes each use a full row. On mobile, the same order stacks without reducing field width. The map still marks only a preferred table and does not claim live availability.

`node tests/floorplan-regression.cjs` passed against both the local PHP route and the regenerated Pages export. It checks the field sequence and email row, all 42 table preferences, zone/photo synchronization, selection reset, mobile controls, horizontal fit, and browser errors. Desktop and mobile selected-table captures are `07-booking-floorplan-desktop.png` and `07-booking-floorplan-mobile.png`.

This is a focused form usability correction; full pixel parity with the approved reference is not claimed.
