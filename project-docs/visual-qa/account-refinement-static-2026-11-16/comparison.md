# Static account stylesheet delivery — 2026-11-16

Reference: `design-references/11-account.png`. Captures: `account-1440.png`, `account-390.png`, `overview-1440.png`, `overview-390.png`.

The custom GitHub Pages account template was missing `luxury-refinements.css`, although the PHP account route uses the shared head. The exported profile intro therefore lost its two-column editorial treatment on desktop and its intended stacked treatment on mobile. The exporter template now includes the shared refinement stylesheet, the current checked-in `docs/account.html` mirrors the change, and Pages CI asserts the link exists after export.

Focused local static browser check: HTTP 200 at 1440 px and 390 px; stylesheet loaded with 163 rules; profile overview computed as a two-column grid on desktop and one column on mobile; document width equals viewport; no console/page errors or failed requests. The profile overview screenshots were visually inspected. The broader account remains a local demo and differs from the approved reference in its hero/dashboard density. This did not test the PHP session route, full database-backed exporter, hosted CI, or public Pages.
