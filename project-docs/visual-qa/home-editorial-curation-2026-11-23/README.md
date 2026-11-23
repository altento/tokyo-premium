# Home editorial curation — 2026-11-23

Compared `design-references/01-home.png` with the current PHP and GitHub Pages home routes. The brand title and salmon hero already followed the reference, so the focused correction was limited to the lower editorial flow.

## Changes

- Homepage recommendations now use a deliberate public-catalog order: tuna nigiri, salmon nigiri, scallop nigiri, and matcha tiramisu. The order no longer depends on cross-category `sort_order`, and hidden Secret Menu items are not exposed.
- Removed the duplicate home Omakase and sample-review sections so restaurant space transitions directly into the booking form, matching the approved home composition. Omakase and reviews remain accessible from their dedicated routes and site navigation.
- The recommendation area remains four cards on desktop and a single-column sequence on mobile.

## Verification

- Focused `tests/visual-fidelity-regression.cjs` passed for the home route in static and PHP modes at desktop and mobile widths.
- The test asserts the exact four public item IDs and the direct space-to-booking section order.
- Both static and PHP screenshots were captured; static desktop/mobile screenshots were opened and compared with reference 01. This is a focused visual comparison, not a pixel-level match claim.

Captures: `home-static-desktop.jpg`, `home-static-mobile.jpg`, `home-php-desktop.jpg`, `home-php-mobile.jpg`.
