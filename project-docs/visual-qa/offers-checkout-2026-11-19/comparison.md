# Offers and checkout quote — 2026-11-19

Baseline `aec9485`; restoration branch `backup/offers-20261119`.

## Confirmed defects

[Before-fix reproduction](reproduction.json) records two real PHP browser scenarios: editing DEMO10 to INVALID left its 240 ₸ discount visible; changing the quantity while a real quote response was held hid the old quote, but releasing that response restored its stale 2,160 ₸ total instead of leaving the edited basket unquoted. The server still checked prices during final order creation; these were misleading UI states.

Quote input/cart changes now advance a revision and cancel pending requests. Responses and finalizers only update the UI if their revision is current. Editing fulfillment, promo, points or gifts also hides the quote. Gift-only changes preserve valid choices; other eligibility changes clear them. Reset updates state after the native form reset finishes. Explicit promo removal clears the input default and invitation query, so reset and refresh cannot restore the removed code.

## Navigation and visual changes

Offer-code cards have separate copy and direct-to-cart actions; the latter pre-fills the code without relying on clipboard permissions. The static exporter maps that query to `cart.html` while retaining its PHP-link guard. Actions have 44 px targets and wrap on phones. The code vault, chef feature, Secret Menu and profile invitation headings retain red rules.

Complete [offers desktop](offers-1440.jpg), [offers mobile](offers-390.jpg), [discounted cart desktop](promo-1440.jpg) and [discounted cart mobile](promo-390.jpg) were opened and inspected. The approved cart concept was inspected alongside the current page in the [paired comparison](cart-comparison.jpg). This scoped cart capture intentionally has one dish/two portions to examine the promo state; it does not replace earlier three-dish cart acceptance evidence. Header positioning and transient toast visibility are overridden only during full-page capture.

Offers uses the existing dark/gold/red visual system, individual campaign imagery, restrained actions, seasonal collection and separate Secret Menu/Privilege bands. There is no approved Offers PNG. Cart still differs in photography, decoration, form composition and vertical proportions from reference 10. No full pixel-parity claim is made.

## Actual verification

- `test:offer-quote-state`: PHP and static passed code-to-cart, valid/invalid/edit/remove code, reset, refresh, fulfillment changes, six widths (320, 390, 768, 1024, 1440, 1920), actual heading bounds and document overflow. No page errors observed.
- The PHP delayed-response scenario uses a real API response held by Playwright. Gift rendering/selection/quantity uses a clearly isolated mock response, checking UI behavior rather than inventory persistence. Existing server inventory/discount logic was not modified.
- `test:tokyo20` passed current PHP/static routes: 21 PHP/19 static details, add actions, offers, Omakase, builder, preference wizard, floorplan, preview certificate, quick view, profile and cart. The final one-line removed-code form-default edge was then verified by the focused suite.
- Export: 37 pages. The first export correctly rejected the new unmapped PHP promo URL; the mapping was added and export then passed. PHP syntax, JavaScript syntax and whitespace checks passed.
- The focused test is included in optional full-QA CI. Fast default publication remains unchanged.

PHP still enforces price, promotion entitlement, gift inventory and order transactions. Pages estimates are browser-local and do not reserve promotions or gifts, send real orders, activate email or process payments. External SMTP/payment configuration remains outside this update.

## Publication

Implementation `5946e9c` was pushed to main. [Actions run 44471466815](https://github.com/altento/tokyo-premium/actions/runs/44471466815) passed build and Pages deployment. The fast profile ran its server/cart/syntax/smoke/export checks; optional full browser CI was skipped. Browser results above were obtained locally.

Fresh public Chromium verified offers-to-cart with DEMO10, visible local discount, invalidation on editing, code removal, native reset, refresh and a new zero-discount quote at 390/1440 px. No page errors occurred and no order was submitted. [Public verification](public-verification.json). The real delayed-response race was verified locally in PHP; GitHub Pages has no quote API.
