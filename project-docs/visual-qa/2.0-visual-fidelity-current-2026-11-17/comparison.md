# Tokyo Premium 2.0 — visual comparison, 2026-11-17

## Scope and evidence

- Source build: `7d888d1b24e70e6ec593a2083930522d9db08e58`.
- Mode: GitHub Pages static demo served locally at `http://127.0.0.1:8765/`; no PHP routes were visually captured in this pass.
- Browser viewport captures: desktop 1440 px and mobile 390 px, full page, for all 11 reference routes. The reproducible visual-fidelity check passed: `PASS: 11 visual-fidelity routes validated in static mode`.
- Contact sheets `compare-01.jpg` through `compare-04.jpg` pair the approved PNG and current page capture for visual inspection.
- Full-page captures are named `<route>-static-desktop.jpg` and `<route>-static-mobile.jpg`.
- The populated cart captures use actual catalog interactions: Philadelphia ×2, salmon nigiri ×1, matcha latte ×1; displayed subtotal is 12,700 ₸. Recommendations and checkout controls are visible. Images were decoded before capture; an earlier blank recommendation image was a screenshot timing artifact caused by lazy loading, not a missing asset.

## Route-by-route first-pass comparison

| # | Reference / current route | Observed match | Remaining visible difference |
|---|---|---|---|
| 01 | `01-home.png` / home | Cinematic food hero, primary actions, menu cards, chef/story, space and booking sections retain the dark luxury direction. | Current page uses a more editorial, spacious story layout; the reference has a denser first view, more ornamental Japanese framing, and a compact booking strip. Not pixel-matched. |
| 02 | `02-menu.png` / menu | Category navigation, search/filter controls, dish imagery, cards, and set-building CTA are present. | Current layout is more spacious and editorial, with a wider feature-led grid and fewer ornamental details; exact hero, sidebar density and card proportions differ. |
| 03 | `03-dish.png` / dish | Large dish photo, title, description, price/actions, chef story and real recommendations are present. | The reference has a more commerce-dense top block, multi-image thumbnail rail, nutrition strip, ingredient tiles and more compact recommendations. Current page uses a simpler photo/story composition; it is not a pixel match. |
| 04 | `04-omakase.png` / omakase | Premium hero, tasting progression, menu tiers and booking path are present. | Current page is a longer editorial narrative; it does not reproduce the reference's video treatment and compact horizontal tasting-card sequence. No non-working video or fake 360° control is presented. |
| 05 | `05-philosophy.png` / philosophy | Story, principles, sourcing, chef/product content and a closing quote are represented. | Current design is a spacious editorial treatment; the reference has stronger signature/chef portrait, a more visible dated timeline and more decorative framing. |
| 06 | `06-gallery.png` / space | Distinct room cards, filtered photo gallery and lightbox-oriented content follow the reference's spatial storytelling. | Current page intentionally presents a conventional photo gallery; it does not claim a real 360° tour. The reference is denser and gives the tour/floor plan more prominence. |
| 07 | `07-reservation.png` / booking | Reservation parameters, zone/table preference controls, guidance and FAQ are present. | Current form-led layout is more restrained than the reference's large floor-plan composition. Table selection is a preference in the demo, not a real-time availability check. |
| 08 | `08-reviews.png` / reviews | Rating summary, review cards, category controls and review submission are present. | Current design uses fewer, larger cards and an explicitly educational demo form; it does not reproduce the reference video-review carousel or its denser grid. Demo feedback is not presented as verified public reviews. |
| 09 | `09-contacts.png` / contacts | Contact methods, schematic location panel, message form, hours and booking CTA are present. | Current page is less ornamented and has a more restrained map/route composition. Contact/location values are educational demo data; the schematic is not represented as a live map. |
| 10 | `10-cart.png` / cart | Populated cart has product images, quantity controls, removal, subtotal, promo field, pickup/delivery choice, checkout CTA and recommendations. Desktop uses a two-column order/summary layout. | The reference includes add-ons, richer decorative banner/footer and different item density. The captured cart is a real app state, but not a pixel-level reproduction. Mobile evidence is captured separately. |
| 11 | `11-account.png` / account | Account view exposes profile/loyalty, orders, bookings, favourites and related actions with demo labeling. | Current account is more functional and tabular, while the reference is a compact dashboard of visual cards, addresses and profile settings. It remains structurally and visually different. |

## Acceptance status

This is a real visual inspection of all 11 current route captures against the matching references, not a pixel-diff claim. The overall luxury direction and section intent are present. There are still meaningful differences in density, decorative art direction, and the detailed layouts of dish, booking and account; the all-reference visual-fidelity objective is therefore **in progress**, not accepted as pixel-perfect.

A separate empty-cart capture remains available as `cart-static-*.jpg`; the two `cart-populated-current-*.png` files provide the populated state required for review.
