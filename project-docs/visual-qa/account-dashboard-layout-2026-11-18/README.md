# Account dashboard layout follow-up — 2026-11-18

Compared the fresh browser-local account captures with `design-references/11-account.png`. The static account's member and activity summary now share the dashboard grid with the section navigation: at desktop width the left navigation begins beside the first content row instead of starting below a full-width loyalty/metrics block. Order cards are denser on desktop; the mobile layout follows DOM and keyboard order: section navigation, privilege summary, activity summary and the selected panel.

## Evidence

- `account-seeded-desktop.jpg` — 1440 px viewport, populated browser-local example.
- `account-seeded-mobile.jpg` — 390 px viewport, populated browser-local example with navigation before the account content.
- `account-notifications-mobile.jpg` and `account-notifications-unread-desktop.jpg` — read/unread notification states.
- `npm run test:account-demo` passed against the local Pages preview. The regression covers 320 px overflow, dashboard column alignment, mobile quick-action and tab layout, saved dish/cart interaction, XSS-safe local notifications, clear-data and explicit sample restore.
- `php -l account.php` passed. The available PHP CLI has no PDO SQLite driver, so this change's PHP-rendered layout was not captured locally; the server-side account functionality was not changed.

## Remaining visual difference

The approved reference is a denser profile dashboard with a portrait, profile/contact strip, a larger orders panel and side-by-side bonuses, addresses and settings. The current static demo keeps browser-local status disclosures and tabbed sections, and still does not reproduce those exact panels. This is a structural improvement toward reference 11, not full visual parity or acceptance of the whole site.
