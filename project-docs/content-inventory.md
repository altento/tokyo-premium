# Content inventory
Updated 2026-11-13

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

## Tokyo Premium 2.0 content

- `assets/generated/tokyo-seasonal-omakase-v2.webp` is original AI-generated editorial photography, optimized from a 1600×900 image to WebP. It contains no text or interface.
- `assets/generated/home-hero-salmon-nigiri-v3.webp` and `assets/generated/menu-hero-sushi-assortment-v1.webp` are new original AI-generated, text-free hero photographs for separate home and menu scenes. Both are optimized WebP assets; the static exporter copies them from `assets/generated/`.
- Offers, campaign defaults and seasonal collections are educational data and can be edited by an authenticated PHP administrator.
- `perfect-evening.php` uses transparent catalog rules, not a claim of AI assistance.
- The static loyalty/referral views are browser-local examples. Certificate creation produces a preview record only, not a payment receipt or redeemable stored value.
