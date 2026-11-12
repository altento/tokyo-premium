## Visual QA evidence

Browser captures come from the locally served PHP preview and its generated static Pages export. `npm run test:browser` checks the 11 page types, all 20 dish details, and cart, booking, account, navigation, and menu flows. `npm run test:responsive` checks 320, 360, 375, 390, 430, 768, 1024, 1440, and 1920 px and checks text range rectangles for clipping.

- `01-home-…` through `11-account-…` are the route screenshots. The account route screenshots include the PHP account implementation; use the paired `11-account-static-desktop.jpg` and `11-account-static-mobile.jpg` when comparing the static Pages profile to `design-references/11-account.png`.
- Use `06-space-default-desktop.jpg` and `06-space-default-mobile.jpg` for the unfiltered gallery comparison. `06-space-filter-details-mobile.jpg` records the separate filter state.
- The cart state suite creates eight full-page screenshots at fixed viewport widths: desktop is 1440 CSS px and mobile is 390 CSS px (device scale factor 1). It asserts the viewport before writing each file. States are empty desktop/mobile, populated desktop/mobile, checkout validation desktop/mobile, pickup desktop, and delivery desktop. The populated view has three distinct dishes, one quantity of two, loaded food photos, the recalculated 12,700 ₸ subtotal, and three suggestions that do not duplicate cart items.
- Full-page capture helpers disable smooth scrolling, reset to scroll position zero, and render the header in normal document flow so a sticky header cannot be stitched into the middle of the evidence image. This is a capture-only override; responsive runtime tests still exercise the sticky header.
- `cart-delivery-desktop.png` is captured at 1440×2307 px with delivery selected and a populated required address. The regression checks the viewport, form state, and that the sticky header is placed in document flow for full-page screenshots.
- `07-booking-heading-320.jpg`, `07-booking-heading-390.jpg`, menu-open captures, and header captures document short-screen navigation and heading geometry.

GitHub Actions uploads the JPEG and PNG evidence as `tokyo-premium-visual-qa`. The page-by-page comparison and known visual gaps are in `project-docs/final-acceptance-report.md`.
