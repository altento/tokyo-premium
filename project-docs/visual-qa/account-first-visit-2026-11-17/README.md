# Static account first-visit preview — 2026-11-17

The captures show the GitHub Pages account after first visit in a clean browser at 1440 px desktop and 390 px mobile. The static demo creates three labeled sample orders, one sample booking request and three catalog favorites in that browser only.

The focused `npm run test:account-demo` regression verifies that existing local profile and order data is preserved, the sample action is idempotent across reloads, and clearing data leaves the page empty until the visitor explicitly restores the sample. Every order thumbnail is loaded before capture.

- `11-account-seeded-desktop.jpg`
- `11-account-seeded-mobile.jpg`

This improves the initial account preview against `design-references/11-account.png`; it does not claim pixel parity. PHP account behavior, public Pages deployment and the complete site were not retested in this focused pass.
