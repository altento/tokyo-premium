# Reviews · compact reference composition · 2026-11-19

Opened the approved `design-references/08-reviews.png` and inspected final full-page [static desktop](static-desktop.jpg), [static mobile](static-mobile.jpg), [PHP desktop](php-desktop.jpg) and [PHP mobile](php-mobile.jpg). The supplied concept leads with guest text, compact cards and a relatively short form; the previous page was dominated by large photos and oversized headings.

The scoped CSS now places guest text before a shallow photo strip, adds the reference-like card border radius, reduces section-heading scale and spacing, and compacts the form/illustration chapter. All four seeded records remain present. The red heading rule remains. No guest videos, fabricated popularity counts or fake uploads were added.

[Measured before](before.json) and [after](after.json), at 1440 px with the same four published seed records:

- Main content: 3433 → 2669 px (22% shorter).
- Review-list section: 1480 → 1102 px.
- Submission band: 934 → 583 px.

[Before screenshot](desktop-before.jpg). Mobile final captures use a new 390 px browser viewport and eagerly decoded images/fonts; the screenshot and document widths are both 390 px. An initial resize-only diagnostic capture had stale full-page dimensions and was replaced rather than accepted as visual evidence.

`test:review-contact-forms` checks heading text fragments/clipping ancestors, form-control bounds and document width at six widths, as well as the text-first card order, compact heading and red rule. It caught a CSS-specificity issue where `display:flex` overrode `[hidden]`; the stronger scoped hidden rule restored filtering and the rerun passed. Existing local-review/XSS/persistence/clear and three-column/four-seed checks passed too.

This closes a visible density gap rather than claiming a pixel-identical implementation: the reference contains six richer guest/media cards and a video strip; this educational dataset has four illustrated records and a still-photo interlude. No new photography or image behavior was introduced. Header positioning was normalized only for coherent full-page captures.

Tokyo 2.0 passed separate PHP and static mode runs, covering their detail-route matrices and interactive scenarios; cart-store checks passed 14/14. One initial combined local run reached both detail-route matrices but reported a transient connection-refused console resource; that run was not counted as a pass. Publication results are recorded with the release follow-up.
