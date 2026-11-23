# Account overview navigation — 2026-11-23

The default account panel contains the dashboard summary, saved orders and bookings, favorites preview, addresses, and settings preview. Its selected navigation label incorrectly said “История”. It now says “Обзор”; the existing internal `history` key is unchanged, preserving browser storage and interactions.

Before-change public captures: `../account-final-pass-2026-11-23/account-static-desktop.jpg` and `account-static-mobile.jpg`. After-change local static regression captures: `11-account-seeded-desktop.jpg`, `11-account-seeded-mobile.jpg`, and the notification/profile mobile states in this directory. The desktop capture was inspected after the change.

`npm run test:account-demo` passed against the local static export. This is a focused navigation-label correction; it does not claim full dashboard visual parity.
