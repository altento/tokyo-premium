## Visual QA evidence

Browser captures come from the locally served PHP preview and its generated static Pages export. `npm run test:browser` checks the 11 page types, all 20 dish details, and cart, booking, account, navigation, and menu flows. `npm run test:responsive` checks 320, 360, 375, 390, 430, 768, 1024, 1440, and 1920 px and checks text range rectangles for clipping.

- `01-home-…` through `11-account-…` are the route screenshots. The account route screenshots include the PHP account implementation; use the paired `11-account-static-desktop.jpg` and `11-account-static-mobile.jpg` when comparing the static Pages profile to `design-references/11-account.png`.
- Use `06-space-default-desktop.jpg` and `06-space-default-mobile.jpg` for the unfiltered gallery comparison. `06-space-filter-details-mobile.jpg` records the separate filter state.
- The cart state suite creates eight full-page screenshots: empty desktop/mobile, populated desktop/mobile, checkout validation desktop/mobile, pickup desktop, and delivery desktop. The populated view has three distinct dishes, one quantity of two, the recalculated 12,700 ₸ subtotal, and three suggestions that do not duplicate cart items.
- `07-booking-heading-320.jpg`, `07-booking-heading-390.jpg`, menu-open captures, and header captures document short-screen navigation and heading geometry.

GitHub Actions uploads the JPEG and PNG evidence as `tokyo-premium-visual-qa`. The page-by-page comparison and known visual gaps are in `project-docs/final-acceptance-report.md`.
