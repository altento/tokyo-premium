# Contacts reference follow-up — 2026-11-17

## Captures inspected

- Static Pages route: `/contacts.html`
- Desktop capture: `contacts-static-desktop.jpg` (1440 px viewport)
- Mobile capture: `contacts-static-mobile.jpg` (390 px viewport)
- Supplementary header captures from the same regression: `menu-static-desktop.jpg` and `menu-static-mobile.jpg`
- Approved reference: `design-references/09-contacts.png`

## Changes and observed result

The contact hero is now shorter and its title scale is more controlled at desktop widths. The contact summary and schematic map use a wider map column; the map height is capped so it no longer dominates the first contact section. The hero copy is offset on desktop so the vertical Japanese ornament does not crowd the heading. The contact form section uses tighter vertical spacing. Mobile retains a single-column flow; the heading, contact details, form, hours, and calls to action remain inside the viewport in the inspected capture.

The current page preserves the explicit educational-demo disclosures. Its map is labeled as a schematic and says that it is not a route or a real location. The contact details are likewise labeled fictional. The image in the later planning section is identified as a fictional space image, not a facade or a real address.

## Comparison with reference 09

The implementation follows the reference's dark/gold/red palette and the contact-first structure: hero, contact details beside a map panel, message form beside visit information, and a planning section before the footer. The current layout deliberately uses the project-wide Tokyo 2.0 header, educational labels, and schematic map treatment. It does not reproduce the reference's exact cherry-blossom hero crop, social links, transport strip, map artwork, or section proportions; those remain visible fidelity gaps. These screenshots establish a focused layout check, not completion of the full reference comparison.

## Verification scope

The focused static visual-fidelity Playwright run passed for `menu` and `contacts` at the tested desktop/tablet/mobile widths. It checked the contact hero and map geometry and the 1024/1023 px menu breakpoint. These are local static-demo results only. PHP/SQLite rendering, hosted CI for the current working-tree changes, and public Pages deployment are not verified by these captures.
