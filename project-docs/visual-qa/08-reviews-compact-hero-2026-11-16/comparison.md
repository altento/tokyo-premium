# Reviews page — visual comparison

## Reference and current layout

`design-references/08-reviews.png` shows six review cards in three desktop columns, followed by a video-review strip and a guest review form. The current educational demo has four seeded review records in `seed.sql`; it labels each one fictional and does not invent extra testimonials or imply real guest footage. The page uses three columns on desktop, two through tablet widths, and one on phones. Since the demo has fewer than six records, a photo-backed editorial moment about the restaurant space fills the break before the form.

The current page keeps the reference’s image-led hero, Japanese display mark, score summary, category filters, review cards, and form. Its card count, card details, and lack of video testimonials remain intentional differences from the reference because the available data is limited and the project must preserve honest demo labeling.

## Corrected grid and refreshed captures — 2026-11-16

The previous `php-desktop.jpg` / `static-desktop.jpg` captures in this folder were stale. The PHP preview process on port 8090 served a reviews document whose stylesheet list contained only `luxury.css`, `editorial.css`, and base `premium.css`. It therefore showed the base two-column grid and omitted the current photo interlude. The current source cascade already had the intended responsive layout; the mismatch came from previewing an older document, not from a later CSS override.

I served the current worktree explicitly on port 8098 and checked the exported Pages route at 1440×1000 and 390×844. `premium.css` and `docs/premium.css` match: three columns at desktop, two at widths up to 980px, and one at 640px and below. Measurements were 3 columns / 4 cards at 1440px and 1 column / 4 cards at 390px, with no horizontal overflow. All four cards have the fictional-demo disclosure, and all page images—including the room interlude—decoded successfully. I visually inspected the refreshed captures against the reference.

Evidence:

- Desktop: `../reviews-density-follow-up-2026-11-16/refreshed-desktop.jpg`
- Mobile: `../reviews-density-follow-up-2026-11-16/refreshed-mobile.jpg`

## Focused browser check

`npm run test:reviews` passed against the current static export. The regression asserts all four seeded demo cards, the three-column desktop grid, one-column mobile grid, and the photo interlude; it also verifies local-review persistence, XSS-safe rendering, clearing, and that local reviews do not change the public demo score. The separate screenshot check reported no browser errors or broken images.

PHP rendering was not re-run in this slice because the available PHP CLI does not have `pdo_sqlite`. The inspected `reviews.php` source contains the same photo-backed interlude, and the Pages export at `docs/reviews.html` displays it.
