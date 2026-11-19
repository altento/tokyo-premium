# Reviews and contacts · 2026-11-19

## Confirmed defects and changes

[Before-fix reproduction](reproduction.json) records the actual empty filter with no explanation, a new review draft erased by a delayed PHP response, and a one-character contact message accepted by the static demo. The empty-topic reproduction hid only the Omakase seed record in an isolated temporary database; it was restored before final captures/export. No production records were changed.

- PHP contact/review requests snapshot submitted values. Success resets an unchanged form; later edits remain with an explicit unsent notice. Error responses keep inputs and re-enable submission. Booking/order reset and idempotency logic were retained.
- Contact messages require 5–1000 trimmed code points in both modes; reviews require 20–1000. PHP no longer treats Cyrillic UTF-8 bytes as individual characters. Native HTML limits remain UTF-16 based, so these are not claims of grapheme-cluster equivalence for every emoji sequence.
- A saved local review links directly to its separate browser-only region. Filter buttons expose selection with `aria-pressed`; results announce counts or explain an empty topic. Local records still use text-only rendering and never affect the published aggregate.
- The static exporter replaces the review hero/form's database/moderation wording with explicit browser-only storage, including before submission. The PHP copy still accurately describes its database mode.
- The review form's privacy note spans both grid columns, so the name field no longer starts beside a note occupying the first field cell. Existing red heading rules remain.

## Actual verification

`npm run test:review-contact-forms` passed against a temporary PHP/SQLite database and separate static export:

- Real PHP responses held after processing: edit the input before releasing the response; both contact and review preserve the new draft. Unchanged success resets; a simulated 503 retains input and enables retry.
- Actual PHP POSTs validate Cyrillic lengths 19/20/1000/1001 for reviews and 4/5/1000/1001 for contacts.
- Static short and whitespace-only contact text rejected; valid text saved locally with honest disclosure and reset. Review link, HTML injection safety, persistence, clearing, unchanged aggregate, selected filter and ascending sort passed. The reproducible empty-topic test removes a topic's rendered seed card to simulate moderation.
- Both pages checked at 320, 390, 768, 1024, 1440 and 1920 px, including actual heading text fragments against clipping ancestors and input/select/textarea rectangles; zero page errors. `prefers-reduced-motion: reduce` was used.

[Machine-readable result](form-regression.json). Existing `test:reviews` also passed its four-seed layout, local persistence/XSS/clear checks. Consolidated PHP integration passed 229 assertions using SQLite-enabled child processes; PHP/JS syntax and `git diff --check` passed. Static export produced 37 pages. Full-site browser, motion, Lighthouse and live SMTP tests were not repeated in this focused pass.

## Inspected screenshots and approved concepts

Opened both originals, `design-references/08-reviews.png` and `09-contacts.png`, then inspected these full-page screenshots:

- [Reviews desktop](reviews-desktop.jpg) and [mobile](reviews-mobile.jpg).
- [Empty-topic state](reviews-empty-topic-desktop.jpg).
- [Contacts desktop](contacts-desktop.jpg) and [mobile](contacts-mobile.jpg).
- [Corrected desktop form disclosure](review-form-desktop.jpg).

Reviews retains the dark/gold/red palette, illustrated hero, score band, topic controls and three-column desktop cards. Local records are visually separated; mobile uses one readable column. The reference has more compact guest cards with avatars/media/engagement controls, whereas this educational implementation uses food illustrations and a photo interlude. It has no guest video files; fake video/like counts were not added. The current page remains taller and is not a pixel match.

Contacts retains the entrance hero, adjacent contacts/map composition, form/hours/interior panel, landscape CTA and gold separators. The map is explicitly decorative; fictional contact and transport/access information is disclosed rather than presented as real directions. Mobile stacks these sections without cropped headings or form controls. The typography and overall spacing still differ from the supplied concept; this pass fixes behavior and the misplaced form note, not a complete visual rebuild.

Capture-only normalization makes the sticky header relative and eagerly decodes images/fonts for coherent full-page screenshots. It does not change shipped navigation. Static submissions are local; PHP submissions are educational database records. No real restaurant communication is claimed.

## Publication

Implementation commits `d7b5326` and `378b8e5` were pushed to main. [Actions 44492876066](https://github.com/altento/tokyo-premium/actions/runs/44492876066) passed build and Pages deployment. The fast profile ran its configured cart/PHP/SQLite/payment/syntax/smoke/export checks; the optional full browser CI was not requested. Focused browser checks above ran locally.

Fresh Chromium verified the deployed review copy and status controls, local save/clear/persistence and safe text, unchanged published score, rejection of a short contact message, successful local contact storage/reset, and both routes at 390 px. [Public result](public-verification.json) records the deployed implementation SHA and no page errors or failed resources. Image requests cancelled by quick page transitions were classified as `net::ERR_ABORTED`; their URLs were explicitly refetched and returned HTTP 200. The public check is static only and does not claim a public PHP form/SMTP deployment.
