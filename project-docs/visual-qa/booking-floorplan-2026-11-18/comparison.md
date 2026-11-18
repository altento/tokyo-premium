# Booking map visual check — 2026-11-18

Compared the current static booking page against [the approved reservation reference](../../../design-references/07-reservation.png).

- At 1440 px, the form and map retain a balanced two-column layout; the SVG floorplan now uses the full map card width (625 px) and the room photo follows beneath it.
- At 390 px, the page stays within the viewport and stacks the form, map, zone buttons, and room photo in a readable order.
- The browser returned HTTP 200, the floorplan rendered, and no JavaScript page errors or horizontal page overflow were observed.
- Captures: [desktop](booking-desktop.png) and [mobile](booking-mobile.png).

This is a focused check of the booking composition, not a complete pixel-by-pixel acceptance comparison of all 11 references.
