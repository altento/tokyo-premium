# Contacts page focused comparison — 2026-11-16

Compared the fresh PHP and static browser captures below with `design-references/09-contacts.png` at desktop (1440 CSS px) and mobile (390 CSS px). This is a focused visual review of the contacts route, not a pixel-difference audit of all eleven references.

## What changed

- The exterior hero is now compact and uses the reference's “Контакты / Всегда на связи” hierarchy.
- The first content row follows the reference composition: three contact rows at left and a wide, custom-drawn map panel at right. The map remains explicitly fictional and says that it does not provide directions.
- The message form, hours/reservation panel, and separate interior photograph now share one desktop row. At narrow widths they stack in reading order without clipping.
- The lower visit notes retain the educational disclosures and reservation caveat instead of inventing parking, transit, accessibility, or real navigation facts.
- The contact form's required name, email, and message fields and its existing `data-api="contact"` handler remain intact.

## Browser evidence

- `contacts-php-desktop.jpg`
- `contacts-php-mobile.jpg`
- `contacts-static-desktop.jpg`
- `contacts-static-mobile.jpg`

The focused `visual-fidelity-regression.cjs` run passed the contacts route in PHP and static modes: HTTP 200, compact hero, loaded exterior and interior photos, map disclaimer, required form fields, section order, mobile bounds, no horizontal overflow, and no console/page errors.

## Remaining differences

The approved image depicts an aerial street map, social-media buttons, and a scenic route banner. This implementation uses an original vector schematic because the address is fictional; it does not present a decorative map as navigation or invent social destinations. The page follows the reference's section hierarchy and three-column form/hours/photo composition, but it is not a pixel recreation.
