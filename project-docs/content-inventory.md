# Content inventory
Updated 2026-11-24

| Content | Source | Status |
|---|---|---|
| Restaurant brand and editorial story | Existing seed and page copy | Fictional school concept |
| Dish names, category, weights and KZT prices | SQLite seed data | Fictional menu |
| Omakase tiers and interactive course sequence | `pages.php`, `experience-ui.js` | Five educational scenes; menu and prices are fictional |
| Address, email, phone and opening hours | SQLite settings / contact page | Fictional; not for navigation or real contact |
| Chef voice and service descriptions | Editorial copy | Fictional writing, not a real person's quote |
| Guest reviews | Seed data and review page | Fictional demonstrations; no real rating claim |
| Food and interior photography | Local WebP assets in `assets/food/` and `assets/generated/` | Illustrative concept photography, not proof of a location or served dishes |
| Homepage hero stories | `index.php`, `motion-system.js`, and the existing home, seasonal Omakase, and chef WebP assets | Three manually selected, fictional editorial scenes; no autoplay and no video simulation |
| Customer profile, bookings, orders and messages on Pages | Browser localStorage | Test-only records on the current browser |
| PHP orders, bookings, messages, reviews and admin users | SQLite | Local educational data; not connected to a real restaurant |

No actual address or restaurant availability is claimed. The decorative map is explicitly a fictional diagram. Prices are examples in Kazakhstani tenge and are not a live offer.

The additive catalogue migrations v19–20 bring the current public menu to 50 entries. Newly added menu items use existing local food photos chosen as the closest available visual match; the 29 added dishes do not yet have unique product photography.

## Tokyo Premium 2.0 content

- `assets/generated/tokyo-seasonal-omakase-v2.webp` is original AI-generated editorial photography, optimized from a 1600×900 image to WebP. It contains no text or interface.
- `assets/generated/home-hero-salmon-nigiri-v3.webp` and `assets/generated/menu-hero-sushi-assortment-v1.webp` are new original AI-generated, text-free hero photographs for separate home and menu scenes. Both are optimized WebP assets; the static exporter copies them from `assets/generated/`.
- `assets/generated/menu-hero-japanese-garden-v2-{640,960,1280,1600}.webp` are original AI-generated, text-free menu hero variants. They are optimized responsive WebP images; the 1600 px file is the fallback and `srcset` serves smaller widths where appropriate.
- Offers, campaign defaults and seasonal collections are educational data and can be edited by an authenticated PHP administrator.
- `perfect-evening.php` uses transparent catalog rules, not a claim of AI assistance.
- The static loyalty/referral views are browser-local examples. Certificate creation produces a preview record only, not a payment receipt or redeemable stored value.

## 2026-11-16 selected visual assets

- The menu hero now selects `assets/generated/menu-hero-sushi-assortment-v1.webp`, an original AI-generated text-free food composition distinct from the homepage hero. Its dimensions are declared in markup to preserve layout space.
- The Omakase stage selector displays five different catalog photos linked to the same five educational stages shown in the interactive detail panel. These are menu photographs reused as truthful previews of those catalog items, not fabricated additional camera angles.

## 2026-11-17 catalog addition

- `assets/food/21-nigiri-scallop.webp` is original AI-generated, text-free product photography, exported as a 1200×1200 WebP (about 122 KiB). It illustrates the fictional “Нигири с морским гребешком” menu item; the entry and example price are educational content, not a real restaurant offer.

## 2026-11-18 campaign imagery

- `assets/generated/campaign-exclusive-v1.webp`, `campaign-golden-hour-v1.webp`, `campaign-chef-drop-v1.webp`, and `campaign-secret-menu-v1.webp` are original AI-generated, text-free food photographs, optimized as WebP for the four campaign concepts. They are illustrative school-project content, not evidence of a real promotion or current stock.
- The images are copied by the static exporter to `docs/assets/generated/` and assigned to the corresponding editable seed campaigns through additive SQLite migration 13. The Chef's Secret Drop points to the catalog scallop nigiri item and makes no claim about remaining portions.
