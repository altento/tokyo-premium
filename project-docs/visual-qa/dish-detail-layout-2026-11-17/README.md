# Dish detail layout follow-up — 2026-11-17

Updated captures from the current `tokyo20-dish-detail-layout` working branch, based on `c64b5fc`. These are full-page screenshots from the actual local PHP + SQLite runtime and freshly generated static Pages export; neither uses a design PNG as page content.

## Captures

- `03-dish-php-desktop.png` — PHP route, 1440 px.
- `03-dish-php-mobile.png` — PHP route, 390 px.
- `03-dish-static-desktop.png` — static export, 1440 px.
- `03-dish-static-mobile.png` — static export, 390 px.

## Changes visible here

- Price and quantity/add-to-cart controls have a clearer commerce hierarchy.
- Favorite action overlays the hero image and exposes its pressed state accessibly.
- A compact facts strip uses only catalog data that exists: category, portion, listed ingredient count, and actual gallery image count.
- Chef, ingredients, and recommendations have a tighter vertical rhythm.
- Salmon nigiri shows its three distinct available photos. Other dishes with one actual photo are not presented as multi-photo galleries.

## Verification

Focused Playwright regression passed against both local PHP + SQLite and the newly generated static export. It checked 21 PHP detail routes and 19 static routes (two locked demo items are hidden in the static version), including price, facts, gallery count, favorite toggle, quantity update, add-to-cart, and mobile fit. All four captures loaded without horizontal page overflow at 390 px; no console errors were observed.

Run: set `TOKYO20_MODES=php,static` and `TOKYO20_DISH_ONLY=1`, then run `npm run test:tokyo20`.

## Remaining difference from `design-references/03-dish.png`

The source reference contains a denser nutrition band, ingredient tiles, more compact recommendations, and a thumbnail rail with a video. The project does not have verified nutrition values or a real video asset, so these were not invented. The current composition is improved but is not a pixel match.
