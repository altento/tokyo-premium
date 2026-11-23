# Booking layout pass — 2026-11-23

The approved booking concept uses a compact sequence of benefits, visit parameters, a preferred-seat map, and FAQ. This pass tightened desktop spacing and typography in the benefits strip, booking form, and FAQ. The form and map remain side by side above 1200 px and stack from 1200 px down; the mobile layout is unchanged. Table selection is still a preference, not live availability or a confirmed reservation.

## Evidence

- `07-booking-floorplan-desktop.png` — Pages at 1440 px; table 42 selected in the terrace, with the preference notice visible.
- `07-booking-floorplan-mobile.png` — Pages at 390 px; mobile zone/table preference selection works and the selected-state notice remains visible.
## Checks

- `TOKYO_FLOORPLAN_MODE=pages node tests/floorplan-regression.cjs` passed: all 42 SVG table preferences, zone synchronization, reset behavior, mobile controls, and selected-table state.
- A focused Chromium geometry check passed at 1440, 1200, 1024, and 390 px: no horizontal overflow or clipped page title; desktop columns remain separate above 1200 px, and tablet/mobile form and map remain vertically stacked.
- Reduced-motion mode was used for the geometry check. This pass checked the static Pages artifact only; PHP rendering and post-deploy verification are not claimed here.

This is a focused composition improvement, not full pixel parity with `design-references/07-reservation.png`.
