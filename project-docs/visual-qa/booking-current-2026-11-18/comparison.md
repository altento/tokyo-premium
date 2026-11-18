# Booking FAQ visual pass — 2026-11-18

Reference: `design-references/07-reservation.png`.

Evidence in this folder was captured from the local static Pages preview at 1440×900 CSS px and 390×844 CSS px. The full-page screenshots show the page as a user sees it, including the booking form, preference-only floorplan, new FAQ treatment, and footer.

## Change

The plain full-width FAQ rows now use a two-column editorial composition: a bespoke lightweight SVG landscape on the left and four accessible disclosure rows on the right. The first answer is open by default so the demo's confirmation boundary is immediately clear. One answer explains that email is sent only when server SMTP is configured; GitHub Pages does not send mail. The illustration is labeled as educational artwork, not a real venue.

## Comparison

The reference also places scenic artwork beside an accordion, so the new FAQ section follows its balance, dark-and-gold palette, and open first answer more closely. The page still differs materially above the FAQ: its hero, step strip, form, and preference floorplan use the current product design and do not reproduce the reference's live availability and virtual-tour claims. No full-page pixel-parity claim is made.

## Verification

- `tests/visual-fidelity-regression.cjs`, static booking route: passed at desktop and mobile sizes.
- Checked that the SVG loads, all four questions render, the first answer starts open, and the email answer can be opened/closed with Enter and Space.
- Checked that desktop content stays in its two-column bounds and mobile stacks the image before the questions without horizontal overflow.
- PHP rendering was not locally exercised because this workstation's PHP CLI lacks `pdo_sqlite`; CI is the server-rendering gate.
