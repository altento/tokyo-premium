# Contacts page comparison — 2026-11-18

Compared the current static page with `design-references/09-contacts.png` at 1440 px desktop and 390 px mobile. Captures: `contacts-static-desktop.jpg` and `contacts-static-mobile.jpg`.

## Updated

- The compact photographic hero keeps the Russian title/subtitle hierarchy and now offers two useful actions: a `tel:` link built from the configured demo phone number and a jump to the message form.
- Contact facts sit beside the custom SVG area schematic. The schematic and nearby copy state clearly that the address is fictional and that the illustration does not provide navigation.
- The desktop form, opening-hours/booking card, and separate interior photograph remain grouped in one row; the mobile layout stacks these sections in reading order.
- A generated landscape banner is explicitly captioned as an educational illustration, not the restaurant's location. Visit notes avoid asserting unverified transit, parking, or accessibility information.

## Verification

- `php -l pages.php` and `node --check tests/visual-fidelity-regression.cjs` passed.
- `node tests/visual-fidelity-regression.cjs` passed for the static contacts route at desktop and mobile; the suite checked both hero actions, responsive heading/panels, loaded photographs, form requirements, fictional-map disclosure, page order, horizontal bounds, and browser errors.
- PHP browser rendering of the new hero actions remains subject to the hosted workflow; this local PHP runtime does not have `pdo_sqlite`.

## Remaining differences

The reference shows a real aerial map, social network destinations, and a scenic route banner. This page uses a clearly fictional diagram and avoids invented social URLs or directions. Its section proportions, content density, and image choices remain different from the reference; no pixel-perfect claim is made. The complete visual review of the other ten references remains open.
