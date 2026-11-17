# Account dashboard polish — 2026-11-17

Compared against `design-references/11-account.png`.

## What changed

- Replaced the full-height room-photo strip behind the PHP section navigation with a compact navigation card.
- Added six small inline line icons and a current-section state exposed with `aria-current="location"` as the visitor moves or follows an anchor.
- Styled each order state as a pill whose color follows the server-provided order status.
- Kept the red heading rule and the full-width order/booking history hierarchy.

## Browser evidence

- `account-php-desktop.jpg` and `account-php-mobile.jpg`: authenticated PHP dashboard at 1440 px and 390 px.
- `11-account-populated-desktop.jpg` and `11-account-populated-mobile.jpg`: populated PHP account with two synthetic test orders, a booking and a browser-local favorite. The status pill and section navigation are visible.
- Captures were taken through Playwright against a temporary PHP 8.5 server with SQLite enabled only for that process. The database is isolated under the system temporary directory; the captured account and data are test fixtures, not guest data.

## Verification

- `npm run test:visual-fidelity` with `TOKYO_VISUAL_ROUTES=account` and `TOKYO_VISUAL_MODE=php` passed for desktop and mobile.
- `npm run test:browser` passed: 11 route types, 21 dish details, cart/checkout, account history, navigation, favorites and filters across six widths.
- `npm run test:tokyo20` passed in PHP and static modes, including 21 PHP and 19 static detail routes plus the interactive 2.0 modules.
- `php tools/export-pages.php` regenerated 37 static pages. The first Tokyo 2.0 run had caught an out-of-date checked-in export; the regenerated export passed the rerun.
- PHP and JavaScript syntax checks and `git diff --check` passed.

## Remaining difference

The PHP account now has clearer hierarchy and state cues, but it remains a longer, data-rich dashboard. Reference 11 is denser and gives more visual space to favorites, gifts, addresses and settings. These captures document the improvement; they do not claim pixel-perfect parity.
