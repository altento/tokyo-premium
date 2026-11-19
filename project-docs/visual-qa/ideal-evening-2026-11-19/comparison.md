# Ideal Evening — 2026-11-19

Baseline `ad8c0a5`; restoration branch `backup/ideal-evening-20261119`.

## Reproduction and corrections

[Before-fix values](reproduction.json) show that an empty guest count enabled the next step and that “Растительные блюда” returned miso with dashi plus tuna nigiri. The [baseline result](before-result.jpg) records the rendered recommendation.

Guest count is now required, retaining integer/min/max checks. Plant preference excludes explicit meat/fish/seafood/gelatin signals and ambiguous dashi from all candidate dishes and pairings, then requires a plant signal for the main. The current seed catalog consequently gives an honest empty state, without an add-plan button or fictitious zero-price meal. Its booking action offers to discuss the menu. This is conservative keyword screening, not vegetarian/vegan certification or an allergen guarantee; ambiguous or negatively phrased descriptions can be excluded. Staff must verify actual ingredients/broth bases. No new restaurant dish or dietary claim was invented.

Product categories take priority over drink words such as “матча”, and linked pairings must still match the requested role. This prevents a matcha dessert from becoming a beverage when catalog categories or pairings change.

## Interaction and layout

“Изменить ответы” preserves selections and values while removing the previous result and booking query until recalculation. Restart clears choices and restores default fields. Focus follows step headings and the result region. Booking is offered after a result, and copy states that chosen parameters transfer to the form.

Inspected complete [populated desktop](result-1440.jpg), [populated mobile](result-390.jpg), and [plant empty desktop](plant-empty-desktop.jpg) captures. The populated example is three guests/private room, 10,000 ₸ each/30,000 ₸ group allowance, a 19,100 ₸ selection and matching cart quantities. Result cards use two columns on desktop and one on phones; long action labels wrap. The parchment note now has dark readable text. Full-page capture temporarily normalizes the sticky header and hides transient toasts.

There is no approved Ideal Evening PNG among the eleven originals. The route keeps its existing cinematic food image, serif headings, red rules, gold progress, dark wizard and parchment footer note. No claim of full visual parity or whole-project completion is made.

## Actual checks

- `test:ideal-evening-focused` passed in PHP and Pages: empty/zero/oversized/fractional guests; real catalog plant empty state; edit preservation and reset; heading/result focus; cart batch quantities and disabled repeat button; guest/zone/menu booking prefill; insufficient-budget warning.
- Synthetic catalog-only cases verify an available plant meal, exclusion of salmon/dashi and correct tea/dessert pairing. Those cases are not photographed or presented as real catalog entries.
- Six widths: 320, 390, 768, 1024, 1440, 1920 px. Actual heading bounds and document overflow passed. An initial measurement immediately after resizing observed transitional viewport styles; the test now waits two animation frames before measuring. This was not accepted as a production overflow defect.
- `test:tokyo20` passed current PHP/static routes, 21 PHP/19 static dish details, cart and interactive flows. Subsequent CSS refinements and the budget assertion passed the focused suite.
- Export: 37 pages. PHP/JavaScript syntax and whitespace checks passed. The new focused test is in optional full-QA CI; default fast publication is unchanged.

PHP remains authoritative for final prices and order validation. The helper stores no guest identity; Pages cart/booking scenarios remain browser-local. Real SMTP, merchant setup and live seating availability are not activated by this change.
