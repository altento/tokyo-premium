# Contacts reference follow-up — 2026-11-17

## Captures inspected

- Static Pages route: `/contacts.html`
- Desktop capture: `contacts-static-desktop.jpg` (1440 px viewport)
- Mobile capture: `contacts-static-mobile.jpg` (390 px viewport)
- Supplementary common-header captures from the same regression: `home-static-desktop.jpg`, `home-static-mobile.jpg`, `menu-static-desktop.jpg`, and `menu-static-mobile.jpg`
- Approved reference: `design-references/09-contacts.png`

## Changes and observed result

The contact hero is now shorter and its title scale is more controlled at desktop widths. The contact summary and schematic map use a wider map column; the map height is capped so it no longer dominates the first contact section. The hero copy is offset on desktop so the vertical Japanese ornament does not crowd the heading. The contact form section uses tighter vertical spacing. Mobile retains a single-column flow; the heading, contact details, form, hours, and calls to action remain inside the viewport in the inspected capture.

The current page preserves the explicit educational-demo disclosures. Its map is labeled as a schematic and says that it is not a route or a real location. The contact details are likewise labeled fictional. The image in the later planning section is identified as a fictional space image, not a facade or a real address.

## Comparison with reference 09

The implementation follows the reference's dark/gold/red palette and the contact-first structure: hero, contact details beside a map panel, message form beside visit information, and a planning section before the footer. The current layout deliberately uses the project-wide Tokyo 2.0 header, educational labels, and schematic map treatment. It does not reproduce the reference's exact cherry-blossom hero crop, social links, transport strip, map artwork, or section proportions; those remain visible fidelity gaps. These screenshots establish a focused layout check, not completion of the full reference comparison.

## Verification scope

The focused static visual-fidelity Playwright run passed for `menu` and `contacts` at the tested desktop/tablet/mobile widths. It checked the contact hero and map geometry and the 1024/1023 px menu breakpoint. Hosted run #181 verified the changes across PHP/SQLite and responsive jobs but exposed a timing race in the same test's immediate `inert` read after a viewport resize. A bounded wait for the documented `matchMedia` handler has been added and awaits hosted rerun. These are local static-demo results only; public Pages deployment has not occurred.

## Follow-up · 23 November 2026

The current static export was recaptured at 1440 px and 390 px in `../contacts-reference-followup-2026-11-23/`. The contact form section was still taller than the compact reference: its desktop height measured 694 px, with 88 px between the map note and form content. Contact-only spacing now reduces those values to 542 px and 55 px. The form keeps labeled fields and its clear demo-storage disclosure; mobile controls remain 46 px high. The visit-planning section also uses a smaller vertical inset.

The focused visual regression passed after the change. It checks the new gap/height bounds, image loading, demo disclosures, form fields, heading fit and mobile overflow. Both fresh full-page screenshots were visually inspected. Remaining differences are intentional or still visible: this is a local educational address with a schematic rather than real routing, and the reference's social row, transport strip and exact image crops are not reproduced. No claim of full-page pixel parity is made.

Commit `56c3bee` was published by workflow [45186061680](https://github.com/altento/tokyo-premium/actions/runs/45186061680). The same one-route Playwright check passed on the public Pages URL; deployed desktop and mobile captures are in `../contacts-reference-followup-2026-11-23/public/` and were visually inspected.
