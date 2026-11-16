# Cart visual pass — 2026-11-16

Compared the populated static cart capture with `design-references/10-cart.png`.

- The reference puts populated lines and the order total/fulfillment/checkout side by side, then shows recommendations below. The cart markup now keeps the cart lines and checkout as the two primary columns and moves the existing recommendation section after that layout. This also makes the checkout appear before recommendations on mobile.
- The cart-specific hero and top spacing are reduced; the cart heading now carries the site's red section rule. The checkout keeps its quote/promo, gift selection and quantity, fulfillment, contact, address, comment, and demo-submit controls.
- Desktop keeps a dense multi-column recommendation row; mobile stacks the checkout before the recommendation cards. No cart-store or checkout JavaScript was changed.
- Rechecked the PHP and Pages CSS cascades after an older screenshot set showed the 430 px base hero. Both `pages.php?page=cart` and `docs/cart.html` load the compact rule from `experience-ui.css` last; measured hero heights are 232 px at 1440 px wide and 245 px at 390 px wide. The duplicate hero CTA and school note are hidden in both modes, so cart content appears sooner.

## Evidence

`cart-visual-regression.cjs` passed against the current static export at `http://127.0.0.1:8100/docs`. It refreshed the populated desktop/mobile plus empty, pickup, delivery, and validation states in this folder. I inspected the refreshed populated desktop and mobile captures against `design-references/10-cart.png`. The static cart has three distinct dishes, one at quantity 2, matching visible total `12 700 ₸`, loaded dish photos, and three remaining recommendations. I also opened the PHP route at both viewport widths and confirmed it uses the same 232 px / 245 px hero heights while keeping checkout intact.

The PHP route responded successfully at both viewport widths in the SQLite-enabled local environment. No checkout or cart behavior was changed.

## Remaining visual caveat

The earlier screenshot caveat came from captures that did not reflect the current PHP/Pages stylesheet cascade. Refreshed captures show the compact hero treatment in both modes; no cart behavior or checkout content needed changing. The mobile checkout remains a long form by nature, with recommendations after the order flow.
