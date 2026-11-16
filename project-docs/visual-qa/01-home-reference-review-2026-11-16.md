# Home reference comparison — 2026-11-16

Reference: `design-references/01-home.png`.

Compared the reference with the current PHP screenshots `2.0-motion/php-desktop.jpg` and `2.0-motion/php-mobile.jpg`. The current page preserves the reference's main sequence: cinematic food hero and booking/menu calls to action, a short value strip, recommended dishes, chef philosophy, restaurant space, and a reservation form. It adds the interactive three-scene hero, Omakase story and clearly fictional guest note. The product and interior photography are independent assets; the reference PNG is not used as page content.

The most recent rough full-page images in the untracked `reference-pass-2026-11-16` folder were captured without scrolling through the page and therefore left ScrollTrigger reveals hidden. They are not valid evidence of missing homepage sections. The existing motion regression deliberately scrolls all the way through before capturing and asserts that story targets become visible. Its saved desktop and mobile screenshots show the complete page. This comparison is visual and structural, not a pixel-diff acceptance pass. No code or broad test suite was changed for this review.