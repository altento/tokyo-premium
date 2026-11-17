# Cart hero reference pass — 2026-11-17

Compared `design-references/10-cart.png` (1024 × 1536) with the existing populated-cart capture. The cart layout already covers the reference's product list, summary, fulfillment choice, checkout and recommendations. The most visible asset gap was the hero: it reused a close-up omakase-set image instead of an atmospheric table scene.

Added an original, text-free generated photograph at `assets/generated/cart-hero.webp` (1672 × 941, 127 KB). Both the PHP cart route and the static Pages export now use it. Its dark left side keeps the title readable; the lantern and plated sushi remain visible in the desktop and mobile crops.

Inspected the new populated desktop and mobile captures in `cart-hero-refinement-2026-11-17/`. The focused `npm run test:cart-visual` run passed all eight cart states and verified that the new hero image loads. PHP syntax, test JavaScript syntax and `git diff --check` passed.

This is a focused cart-hero improvement, not a full pixel-by-pixel acceptance of all 11 references. The current public GitHub Pages site was not changed by this local pass.
