# Marketing admin integrity · 2026-11-19

## Reproduced defects and repairs

[Before](marketing-before.json): real authenticated HTTP and disposable SQLite, eight failed checks out of 14. [After](marketing-after.json): 18 checks, zero failures.

- A campaign title save reactivated an explicitly disabled gift and could restore an older quantity after checkout consumed inventory. Existing-campaign metadata now leaves the inventory row unchanged; a newly selected gift item starts with zero stock. Initial campaign creation can still initialize stock. Readonly stock fields explain the separate inventory action, which now supports all gift-reward campaign types.
- A threshold campaign changed to a non-gift reward still exposed its old free gift. Quotes now require the gift reward type, while inventory history remains stored.
- The new-code form silently updated an existing promo. Creation now rejects duplicates; edit forms submit the existing immutable code identity. Missing/renamed edit targets are rejected; usage counters/history remain unchanged.
- Zero and overflow dish IDs could be silently dropped from collection membership, including clearing a collection with `0`. Validation now rejects every invalid member before changing metadata or links. An explicitly blank list still clears membership.
- Open promo editors previously stayed in a narrow sidebar with three tiny columns. They now span the row; desktop date fields were verified wider than 200 px. [Desktop](promo-edit-desktop.jpg) and [mobile](promo-edit-mobile.jpg) were actually opened and inspected.

## Checks

- `test:admin-marketing-integrity`: 18 real HTTP/SQLite checks for auth, CSRF, gifts, promo identity/history, collection create/edit/invalid IDs and deliberate clearing.
- `test:admin-marketing-browser`: real login, metadata save after an intervening stock change, code creation/duplicate rejection/edit/disable, collection creation/edit/composition, Enter keyboard toggling, and control/document bounds at 320/390/768/1024/1440/1920 px. [Result](marketing-browser.json). All databases/accounts are temporary; SMTP variables are removed from the browser server environment.
- Existing gift quantity 19, pricing 6 and loyalty-tier admin 8 assertions passed. Consolidated PHP/SQLite integration passed 229 assertions. The tier regression's old direct-POST rendering assumption was corrected to follow the existing PRG flow; application redirects were retained.
- PHP syntax, JS syntax and diff hygiene passed. New HTTP regression is included in the fast Pages workflow; the browser mutation test remains in optional full QA.

Screenshots temporarily normalize sticky header/navigation positioning only during capture. The actual UI was checked before normalization. These are focused marketing-admin states, not proof of every administrative function or public PHP hosting. Production merchant/SMTP configuration and full visual parity remain outside this repair.

Implementation `a0cba4e` was pushed to main. [Actions 44508111996](https://github.com/altento/tokyo-premium/actions/runs/44508111996) passed the configured build checks (including the new marketing integrity suite) and Pages deployment. Optional full browser CI was skipped; authenticated browser checks ran locally.

[Fresh public Chromium verification](public-verification.json) confirmed the deployed editor CSS, review text/photo order, functional topic filtering, local-review save/link/reload and 390 px bounds, with zero page errors. Public `admin-marketing.php` correctly returns 404: this release does not deploy PHP administration to Pages. SMTP/merchant credentials and a public PHP host remain unconfigured.
