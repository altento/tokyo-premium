# Cart progressive checkout — 2026-11-17

Eight full-page browser captures of the PHP cart: empty and populated desktop/mobile states, checkout validation desktop/mobile, pickup, and delivery.

The populated states use three real catalogue dishes, including quantity 2, with their catalogue photos and calculated total. Checkout contact fields are collapsed until the guest chooses to continue; fulfillment choice and promotion estimate remain visible. This is a school/demo flow and does not take payment or transmit a real order.

Captured with `npm run test:cart-visual` against `http://127.0.0.1:8092`. The focused regression verifies shared cart totals, recommendation placement, and the collapsed checkout disclosure. Captures were visually inspected alongside `design-references/10-cart.png`.

Files:

- `cart-empty-desktop.png`
- `cart-empty-mobile.png`
- `cart-populated-desktop.png`
- `cart-populated-mobile.png`
- `cart-checkout-validation-desktop.png`
- `cart-checkout-validation-mobile.png`
- `cart-pickup-desktop.png`
- `cart-delivery-desktop.png`
