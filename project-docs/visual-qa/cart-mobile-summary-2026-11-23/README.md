# Mobile cart checkout summary

The populated mobile cart now has a compact bottom summary that reads its total from the shared cart store and jumps to the existing checkout disclosure.

## Verified

- The focused `tests/cart-visual-regression.cjs` scenario passed against the freshly exported static Pages build.
- The summary matches the shared total, opens the checkout disclosure, moves focus to its heading, and hides while checkout is expanded.
- It yields to the open mobile navigation, fits a 320 px viewport, and stays hidden for an empty cart.
- The PHP cart route returned HTTP 200 and included the same summary markup.

## Captures

- `cart-empty-desktop.png` and `cart-empty-mobile.png`
- `cart-populated-desktop.png`, `cart-populated-mobile.png`, and `cart-populated-mobile-viewport.png`
- `cart-checkout-validation-desktop.png` and `cart-checkout-validation-mobile.png`
- `cart-pickup-desktop.png` and `cart-delivery-desktop.png`

This is a focused interaction and layout check, not final acceptance or a pixel-difference comparison against the cart reference.
