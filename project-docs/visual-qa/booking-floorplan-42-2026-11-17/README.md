# Booking floorplan preference picker

This focused iteration expands the existing booking diagram to 42 table preferences across four restaurant zones and adds a mobile zone/table picker.

## Evidence

- `07-booking-floorplan-desktop.png` — full-page desktop capture with a selected preference.
- `07-booking-floorplan-mobile.png` — full-page mobile capture with a selected preference and the touch picker.
- The picker uses the same table IDs as the SVG and updates the existing booking form state.

## Verification

`npm run test:floorplan` passed against the local PHP app and static Pages export. It verifies all four zone counts, selection propagation, the disclosure that the map does not check availability, mobile control size, and no horizontal overflow. JavaScript syntax, PHP syntax for `pages.php`, and `git diff --check` passed.

The markers are illustrative preferences. They do not represent live availability, and this evidence does not establish full visual parity with `design-references/07-reservation.png` or check the public deployment.
