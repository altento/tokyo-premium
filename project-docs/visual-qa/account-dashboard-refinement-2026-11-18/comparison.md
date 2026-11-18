# Account dashboard refinement — 2026-11-18

Compared the current static account page with `design-references/11-account.png` at 1440 px and 390 px.

- The dashboard now keeps orders and reservations together, with the club card and referral action separated into a clear overview.
- Desktop history uses compact purchase rows with a distinct total and readable order composition; favorites remain a separate visual section.
- On mobile, the overview, shortcuts, account tabs, orders, reservations and favorites stack in a single column without horizontal overflow.
- Static demo records remain explicitly browser-local; no server account or payment is implied.

Evidence: `account-desktop.jpg` and `account-mobile.jpg`. Both were captured from the local static export after loading the account stylesheet. Browser console/page errors: none. `document.documentElement.scrollWidth` matched the viewport at 1440 px and 390 px.

This is a focused layout comparison, not a pixel-difference audit of the full account reference.
