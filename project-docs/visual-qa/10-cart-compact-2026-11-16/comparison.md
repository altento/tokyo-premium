# Cart visual pass — 2026-11-16

Compared the populated static cart capture with `design-references/10-cart.png`.

- The reference puts populated lines and the order total/fulfillment/checkout side by side, then shows recommendations below. The cart markup now keeps the cart lines and checkout as the two primary columns and moves the existing recommendation section after that layout. This also makes the checkout appear before recommendations on mobile.
- The cart-specific hero and top spacing are reduced; the cart heading now carries the site's red section rule. The checkout keeps its quote/promo, gift selection and quantity, fulfillment, contact, address, comment, and demo-submit controls.
- Desktop keeps a dense multi-column recommendation row; mobile stacks the checkout before the recommendation cards. No cart-store or checkout JavaScript was changed.

## Evidence

`cart-visual-regression.cjs` passed for the static cart and saved the populated desktop/mobile plus empty, pickup, delivery, and validation states in this folder. I inspected the populated desktop and mobile captures. The static cart has three distinct dishes, one at quantity 2, matching visible total `12 700 ₸`, loaded dish photos, and three remaining recommendations.

The PHP template passes `php -l`. A fresh PHP browser capture could not be completed in this environment: the PHP runtime lacks the PDO SQLite driver (`could not find driver` while rendering `pages.php?page=cart`). The existing PHP route therefore needs verification in an environment with `pdo_sqlite` enabled.

## Remaining visual caveat

The desktop hero is shorter than before, but the captured hero remains taller than the compact reference hero because the title and disclosure copy are stacked. On mobile, the full checkout form still creates a long page; recommendations now follow it, so they no longer interrupt the order flow.
