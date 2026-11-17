# Mobile booking title repair — 2026-11-17

The booking hero title used hyphens: auto in the shared editorial heading rule. At 320 px, the automated check rejected this even though the heading text fit within the visible hero. The shared rule now uses hyphens: none; overflow-wrap: anywhere remains as a safety fallback for unusually long headings.

A focused browser check measured the booking title at 320, 360, 375, 390, 430, 768, 1024, 1440 and 1920 CSS px. At each width the title stayed on one line, text bounds remained in the viewport, and scrollWidth matched the viewport. Full-page captures are 07-booking-mobile-320.png and 07-booking-mobile-390.png.

The screenshots in ../2.0-motion-2026-11-17/ are from the static motion regression after correcting its crop expectation to match the visually reviewed 82% salmon framing.
