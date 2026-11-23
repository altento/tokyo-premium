# Booking floorplan legibility — 2026-11-23

Compared the current static booking page with [`design-references/07-reservation.png`](../../../design-references/07-reservation.png). The selected-seat screenshots show the SVG plan at desktop and mobile widths.

## Finding and change

At desktop width the 960-unit SVG is reduced into the map panel. Its 11-unit zone notes and 10-unit small table numbers became difficult to read; the private-room title also sat too close to its note. Increased the SVG text sizes in the source and Pages styles, raised the note contrast slightly, and moved the private-room title up to restore separation. The 42 selectable markers, zone preference behavior, and no-live-availability disclaimer are unchanged.

## Verification

`TOKYO_FLOORPLAN_MODE=pages TOKYO_STATIC_BASE_URL=http://127.0.0.1:8098 node tests/floorplan-regression.cjs` passed. The focused browser run exercised all 42 markers, zone/photo synchronization, form reset, mobile zone/table controls, and checks for overlapping zone labels and notes. Fresh full-page captures were inspected at 1440 px and 390 px:

- `07-booking-floorplan-desktop.png`
- `07-booking-floorplan-mobile.png`

The typography is clearer and the private-room label no longer overlaps its note. The booking page still differs materially from the approved composition: this implementation uses a long preference form and a four-zone schematic, while the reference foregrounds compact booking controls and a photographic overhead plan. The SVG communicates preferences only; it does not claim real availability. This is a targeted map-legibility improvement, not full page visual acceptance. PHP browser rendering was not part of this run.
