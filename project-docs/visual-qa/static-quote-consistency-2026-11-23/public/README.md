# Published checkout verification

The public GitHub Pages version at `https://altento.github.io/tokyo-premium/` passed the focused offer-to-cart and local checkout scenario at 390 px after commit `31be795` deployed in workflow run `45130366636`.

- `offers-390.jpg` — public offer invitation view.
- `promo-390.jpg` — public populated cart and local quote before checkout.
- The test verified that the browser-local saved order's subtotal, discount, and total match the displayed demo quote. It did not create a server order or payment.
