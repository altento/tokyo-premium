# Reviews page — compact hero comparison

Compared `design-references/08-reviews.png` with fresh PHP and static routes. The reference places its title, subtitle, short disclosure and CTA in a compact image-led hero, then moves directly into the rating summary. The prior current capture used the global 760px editorial minimum plus large padding and a wrapped display title, leaving roughly 800px before the score strip.

The reviews-only CSS override reduces the desktop hero to 370px at 1440px, keeps “Отзывы гостей” on one line, separates the Japanese vertical mark from the title, and tightens the rating-band height. At 390px, the hero remains 370px and the title fits within the viewport. The static export receives the same `premium.css` rules through `docs/premium.css`.

Existing review cards, filters, sort control, form, demo disclosures and storage scripts were not changed. The route check found four review cards in both PHP and static pages; the kitchen filter and sort controls remained operable. The review submission flow was not submitted in this visual pass.

## Evidence

- PHP desktop: `php-desktop.jpg`
- PHP mobile: `php-mobile.jpg`
- Static desktop: `static-desktop.jpg`
- Static mobile: `static-mobile.jpg`

The hero and rating rhythm now track the reference more closely; the rest of the page still differs in card density, video-review section and lower-page spacing, which this focused slice did not change.

## Focused verification

One page-only Playwright check visited `/reviews.php` and `/docs/reviews.html` at 1440×1000 and 390×844, exercised the cuisine filter and sort selector, checked title bounds and page width, and saved these four full-page captures. Both hero heights measured 370px, mobile width measured 390px, and no browser page errors were observed. `php -l reviews.php` and `git diff --check` passed.

## Mobile filter update

At 390px, the five review-category controls now use a two-column grid, so the full set is visible across three rows instead of requiring horizontal scrolling. This keeps all categories discoverable and preserves the existing button/filter handlers. PHP and static mobile captures above were refreshed after the change.

Focused mobile-only Playwright check: both routes expose five visible chips within a 342px toolbar; toolbar `scrollWidth` equals `clientWidth`; selecting Omakase displays one matching card and returns to the all-filter state. No browser page errors.
