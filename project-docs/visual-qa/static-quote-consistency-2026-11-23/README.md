# Static checkout quote consistency

**Result:** the local Pages checkout saved the same promotion discount and total as the offer quote shown immediately before submission. The order remains explicitly browser-local demo data.

- `offers-390.jpg` — offer invitation view at 390 px.
- `promo-390.jpg` — populated cart and visible quote at 390 px before opening the checkout details.
- Automated path — `node tests/offer-quote-state.cjs` with `TOKYO_OFFER_QUOTE_MODES=static` and `TOKYO_OFFER_QUOTE_WIDTHS=390`.

This check covers the static demo only. It does not reserve a campaign, validate current server inventory, or establish live payment/order processing.
