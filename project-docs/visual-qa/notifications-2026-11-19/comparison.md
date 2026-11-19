# Local notifications — 2026-11-19

Baseline `de30c8d`; restoration branch `backup/notifications-20261119`.

[Reproduction](reproduction.json) records a real public-browser scenario: marking an old booking read yielded one read item; prepending another booking and reloading changed this to zero read/two unread. The old notification ID included its array index.

New local bookings receive durable `demoId` and `createdAt` values when saved. Local account history assigns IDs to older records without replacing their valid fields. Legacy read IDs migrate using the previous key; a uniquely identifiable booking can recover its receipt even when its old position changed. Historically indistinguishable duplicate records cannot have past identity reconstructed reliably. Stable IDs prevent future index-based shifts. Legacy orders already carrying an order ID or creation identity keep it.

Read storage and history readers accept arrays only; malformed JSON shapes no longer throw on `.filter` or history rendering. Notifications sort by known creation timestamp, with source order retained for older unknown timestamps, rather than sorting random IDs. No server authorization, confirmation status, email or database behavior changed.

## Checks and inspected evidence

- `test:notification-identity` passed migration, prepend/reorder/reload preservation, malformed read state, malformed history shapes and new local-booking metadata. It also checks desktop/mobile document bounds.
- Existing `test:account-demo` passed local notifications, read persistence, HTML escaping, preservation of local profile/address/history and data clearing. Its duplicate historical screenshots were disabled for this scoped run.
- `test:tokyo20` passed PHP/static interaction flows, cart and 21 PHP/19 static dish routes. Export produced 37 pages; JS syntax and diff hygiene passed.
- Inspected [desktop notifications](notifications-1440.jpg) and [mobile notifications](notifications-390.jpg). This is a state-persistence repair using the existing account design, not a new full visual-parity pass. Full-page capture temporarily puts the header in document flow.

The cards explicitly state that a local booking was not received or confirmed by a restaurant. PHP's existing confirmed-booking badge and server notification protections remain separate. No real email, push delivery or payment is activated.

## Publication

Implementation `b23de6a` was pushed to main. [Actions 44487482961](https://github.com/altento/tokyo-premium/actions/runs/44487482961) passed build and Pages deployment using the fast profile. The optional full browser CI suite was skipped; local results above cover the changed behavior.

The complete focused notification regression then passed against public Pages: legacy migration, prepend/reorder/reload, malformed storage, a new browser-local booking with creation metadata, and desktop/mobile account checks. No page errors occurred; no restaurant booking/email was sent. [Public verification](public-verification.json).
