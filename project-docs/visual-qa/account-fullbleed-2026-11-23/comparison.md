# Account hero reference alignment

Date: 2026-11-23. Reference: [`design-references/11-account.png`](../../../design-references/11-account.png).

The approved account hero runs edge-to-edge beneath the global header. The previous Pages capture constrained it to the 1320 px dashboard container, leaving wide side gutters and making it read as a card. The desktop rule now gives the hero viewport width with zero side borders and no top gap; the body and dashboard content keep their readable centered width. At phone widths, the existing inset, stacked hero remains unchanged.

The focused `npm run test:account-demo` browser regression passed, including seeded local-demo data, notifications, persisted read state, XSS-safe rendering, and clear-data behavior. The fresh full-page captures are `11-account-seeded-desktop.jpg` and `11-account-seeded-mobile.jpg`. I inspected the desktop capture beside reference 11: the hero now has the same full-bleed width, while the account's richer local-demo dashboard intentionally remains a different detailed composition. This is a scoped visual alignment, not full-page pixel parity. The PHP route uses the same shared CSS source, but PHP browser rendering was not separately verified here.
