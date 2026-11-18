# 18 November 2026 — saved-address overview

The browser-local account dashboard now shows up to two saved delivery addresses directly under the order/favorites overview. The shortcut opens the existing address editor. Text is HTML-escaped, data stays in `localStorage`, clearing demo data removes the overview, and choosing the sample again restores fictional addresses without overwriting an existing user's value.

Evidence: `11-account-seeded-desktop.jpg`, `11-account-seeded-mobile.jpg`, `11-account-notifications-unread-desktop.jpg`, and `11-account-notifications-mobile.jpg` in this directory. The focused `npm run test:account-demo` browser check passed, including first-visit seeding, tab navigation, clear/restore behavior, preserved user data, and XSS-safe preview rendering.

The admin Marketing & Loyalty jump navigation now links to every management section (campaigns, promo codes, personal offers, collections, set variants, tiers, Secret Menu/gifts, loyalty settings, and ledger). PHP/SQLite interaction testing was not run locally in this pass because the local PHP build lacks `pdo_sqlite`; existing hosted integration coverage remains the server-side verification source.
