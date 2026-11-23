# Birthday preference in the static account

Reference: [`design-references/11-account.png`](../../../design-references/11-account.png).

The optional month/day preference is added inside the existing profile panel, with the site's dark lacquer surface, antique-gold border, and red primary action. The year is not requested. Consent is a separate checkbox, and the disclosure says the GitHub Pages demo stores data only in this browser and does not issue a coupon, award points, or send a message.

Browser captures show the populated leap-day state at desktop and 390 px mobile widths: [desktop](birthday-profile-desktop.png), [mobile](birthday-profile-mobile.png). The mobile checkbox and its text remain together inside the form fieldset. This is a focused review of the new account setting, not a claim of full-page parity with the account reference.

`npm run test:account-birthday-demo` passed local save, reload, leap-day, April day limits, separate consent withdrawal, and clear-data behavior. The test runs against the static export. The PHP profile field remains governed by its existing server-side consent flow; local PDO SQLite is unavailable for database-backed verification here.
