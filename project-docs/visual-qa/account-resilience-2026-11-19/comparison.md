# Account preservation and administrative settings · 2026-11-19

## Reproduction and changes

[Before](before.json) records a fresh browser reproduction: an unsaved name became empty after a favorites event; spaces were stored as an empty address; stored profile `null` raised `Cannot read properties of null`. Selecting Profile also left the History panel `hidden=true` but computed `display:grid`. These were real rendered states, not inferred only from source.

- Profile fields hydrate from valid string properties, preserving dirty inputs across account rerenders. Native storage events refresh saved summaries and clean forms in other tabs; a dirty form keeps its draft with an explicit conflict warning. Drafts are not automatically persisted across navigation/reload.
- Address input is trimmed and checked; duplicates and an eleventh address are rejected without silently evicting an older saved address. Removal finds the intended saved value in the latest list, so an intervening prepend cannot delete the wrong entry. Invalid non-string/blank stored address rows are omitted from display.
- Storage write failures retain input and show an error. Failed read-receipt/deletion writes and incomplete data clearing do not claim success. Successful clearing explicitly resets the profile draft/hydration state.
- A scoped hidden-panel rule overrides the historical grid layout, so inactive History is actually hidden. Red title rules and the existing dashboard composition are retained.
- [Admin before](settings-before.json) reproduced a fee-only request erasing ten omitted text settings. PHP now preserves missing keys, rejects non-string/invalid UTF-8/oversized values and invalid fees before any write, then saves all submitted settings transactionally. Explicit blank text is still an intentional clear operation. [After](settings-after.json): 26 assertions passed.

## Actual checks and screenshots

`test:account-resilience` passed with native same-origin browser tabs, real account forms and injected storage failures; it checks one visible panel, dirty/clean cross-tab behavior, errors, capacity and bounds at 320/390/768/1024/1440/1920 px. [Browser result](browser.json). Existing account-demo/XSS/seed/repeat/clear and durable-notification identity suites passed too. PHP integration passed 229 assertions and PHP/JS syntax/diff hygiene passed. The settings HTTP test covers authorization, CSRF, partial/full requests, input bounds and a deliberately failing database trigger to verify rollback. Its fixture database and sessions are temporary.

Tokyo 2.0 passed the combined PHP + static run, including both detail-route matrices and existing commerce/interactive flows. The exporter produced 37 pages; the account HTML address field now includes its native minimum length.

Opened the approved `design-references/11-account.png` and inspected [desktop unsaved/conflict state](profile-unsaved-desktop.jpg) and [mobile address-capacity state](addresses-mobile.jpg). The selected profile panel now follows the shared dashboard cards directly; History is no longer displayed above it. Mobile addresses remain single-column and the error explains the ten-address limit. These captures use fictional data; temporary relative-header normalization is capture-only. The two views are functional states, not a new claim of full pixel parity with the overview reference. The ten-address view is intentionally long.

Browser-local profile/address records remain an educational demonstration, not server authentication or encrypted storage. PHP saved-address ownership logic and live payment/mail configuration were not changed. Publication status will be appended after deployment.

## Publication

Implementation `d5d8ed6` was pushed to main. [Actions 44509845484](https://github.com/altento/tokyo-premium/actions/runs/44509845484) passed the configured build (including settings preservation/rollback) and Pages deployment; optional full browser CI was skipped because browser checks ran locally.

The same account resilience test then passed against the actual public Pages origin in a fresh Chromium context, including native two-tab synchronization, dirty draft/error preservation, actual selected-panel visibility, invalid/capacity/stale-row address cases, corrupted profile data, local clear behavior and six viewport widths. [Public verification](public-verification.json) records the origin, implementation and UTC check time, with no page errors. No live personal data was used. This verifies the static account; the PHP administrator still requires a PHP host, and real SMTP/payment credentials remain unconfigured.
