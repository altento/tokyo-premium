# Current visual comparison — 2026-11-16

The current static Pages routes were captured at 1440 px desktop and 390 px mobile in `reference-pass-current-2026-11-16/`. The following comparison is a human visual review of those full-page captures beside the approved PNGs. It is not pixel-difference analysis and does not claim exact parity. Filled cart evidence and a seeded account state are in the linked route-specific folders below.

## Route comparisons

### 01 — Home

The current home keeps the dark, cinematic food direction and uses the coordinated hero scenes with a strong food image. Its editorial section rhythm and first-screen text/CTA placement differ from the much denser reference composition. The visual notes and hero review remain in `01-home-reference-review-2026-11-16.md` and `hero-reference-review-2026-11-16/`.

### 02 — Menu

The current catalog has a distinct sushi-led hero, category/search/filter controls, and catalog-backed cards. The reference uses a busier hero with stronger sakura/Fuji decoration and a denser category sidebar. Current screenshots: `reference-pass-current-2026-11-16/menu-static-{desktop,mobile}.jpg`; focused menu evidence is indexed in `README.md`.

### 03 — Dish detail

The page has one real dish image, a clear product summary, quantity/cart controls, chef story and related items. The reference shows multiple product angles and ingredient/nutrition panels. The implementation intentionally does not repeat one image as a fake gallery or invent nutrition facts. Comparison: `03-dish-reference-review-2026-11-16.md`.

### 04 — Omakase

The current page has five distinct course images, a selectable course detail, three set tiers and booking handoff. It reads as a complete editorial experience, but the mock's red/black collage, course-card proportions and closing image bands are more decorative. No video is presented because no real video asset is available. Current screenshot: `reference-pass-current-2026-11-16/omakase-static-desktop.jpg`; focused review: `omakase-latest-review-2026-11-16/`.

### 05 — Philosophy

The revised page now has the sakura-lit entrance, a dark editorial story, chef/product sections, four principles and a warm quote band. It follows the reference's color and section hierarchy; exact image selection, Fuji ornamentation and page proportions still differ. Current screenshots: `reference-pass-current-2026-11-16/philosophy-static-{desktop,mobile}.jpg`.

### 06 — Space / gallery

Four individually photographed room cards and a distinct-image gallery provide a clear route through the space. The mock has a more elaborate garden hero and a wide illustrated 360° tour section. The implementation omits a pretend tour because no true panoramic asset exists. Current screenshots: `reference-pass-current-2026-11-16/space-static-{desktop,mobile}.jpg`.

### 07 — Booking

The form, room preference and schematic table selector are visible, and the page clearly states that the map does not confirm live availability. The current map is a restrained preference diagram rather than the mock's detailed illustrated dining-room plan; the decorative supporting sections are also less dense. Current screenshots: `reference-pass-current-2026-11-16/booking-static-{desktop,mobile}.jpg`.

### 08 — Reviews

The current route uses four explicitly fictional sample stories, a demo average label, filters/sort and a local submission form. It is less dense than the reference, which depicts a much larger review collection, photo/video testimonials and reviewer portraits. The implementation does not imply those sample reviews are real. Current screenshots: `reference-pass-current-2026-11-16/reviews-static-{desktop,mobile}.jpg`.

### 09 — Contacts

The entrance-led hero, contact facts, fictional schematic map, message form and room photo support the same warm Japanese atmosphere. The reference has more compact contact blocks, social controls and a richer map illustration. The map is labeled as a layout illustration and does not claim to provide navigation. Current screenshots: `reference-pass-current-2026-11-16/contacts-static-{desktop,mobile}.jpg`.

### 10 — Cart

The cart supports catalog items, quantity controls, recommendations, delivery/pickup selection and checkout fields. The populated comparison captures three different dishes, one at quantity two, with the total matching the shared cart store. The reference adds compact side-by-side item rows, optional extras and a separate delivery-benefits band; the current mobile cart is necessarily a longer stacked flow. Evidence: `cart-state-final-2026-11-16/cart-populated-{desktop,mobile}.png`, the other six cart states in that folder, and the PHP route capture in `php-cart-final-2026-11-16/`.

### 11 — Account

The account demo has an explicit seed action and shows three itemized sample orders, one booking request, favorites, local-only loyalty and referral cards. Compared with the reference, the dashboard is simpler and its order cards are more text-heavy. Empty-by-default behavior is intentional; no fictitious history is preloaded. The fresh seeded comparison is in `account-state-final-2026-11-16/`.

## Verification boundary

- Fresh full-page static screenshots exist for all 11 route families at desktop and mobile.
- The PHP cart route was separately opened and checked; the full page/11-route PHP comparison was not repeated here.
- The populated cart's eight-state browser regression passed and its screenshots were inspected beside `10-cart.png`.
- The account demo regression passed; its seeded screenshots were inspected beside `11-account.png`.
- This pass records visible gaps rather than marking all 11 layouts as pixel-matched. PHP/SQLite integration, public Pages, deployment and hosted CI status are recorded separately in `testing-report.md` and `final-modernization-report.md`.
