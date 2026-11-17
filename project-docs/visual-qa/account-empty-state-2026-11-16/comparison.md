# Account empty states — 2026-11-16

Reference: `design-references/11-account.png`. Captures: `desktop.png` and `mobile.png`.

The reference shows a populated profile with example identity, several past orders, favorites, addresses and a Gold progress bar. GitHub Pages is a browser-local demonstration, so this account starts with the visitor's actual local data and does not seed fictitious orders or bookings. The previously empty history areas now explain how records appear and link directly to the menu or booking flow; an empty favorites section links back to the menu. The cards use the shared dark/gold account styling.

One focused Chromium run checked the static page at 1440 px and 390 px. All three empty-state destinations resolved to their static routes, mobile document width matched the viewport, and no page errors occurred. These captures document the empty state only; they do not claim full visual parity with the populated reference, PHP profile rendering, hosted CI, or public deployment.
