# Static export parity — 2026-11-23

Freshly exported the PHP-rendered Omakase and set-builder routes into `docs/` with a disposable SQLite database, then inspected the full-page mobile and desktop captures. The static export now includes the current Omakase course controls and current set-builder composition, clear action, and mobile summary.

The first Omakase desktop capture in this folder was taken before the offscreen lazy-loaded backstage photograph entered the viewport. This was a screenshot-capture omission, not a missing production asset. The focused Omakase test now eagerly decodes that image and asserts it is visible before capture. The corrected desktop/mobile screenshots below were re-captured from the current static export and inspected; the previous desktop artifact is retained as `omakase-desktop-before-lazy-decode.jpg` for traceability. No page CSS or image was changed.

Focused Playwright checks passed against both the isolated PHP server and the exported Pages routes at 390 px and 1440 px:

- Omakase next/previous and keyboard navigation, reduced-motion state, all three tier-to-booking selections, and selection after refresh.
- Set-builder local draft restore, totals/progress, quantity and distinct-item limits, clearing, transfer into the shared cart, and no horizontal overflow.
- Six full-page screenshots (390 px and 1440 px for both modules, plus mobile builder controls and the open navigation state) were reviewed. The corrected Omakase captures include the backstage chef photograph.

Reproduce from the repository root with `TOKYO_BASE_URL` and `TOKYO_STATIC_BASE_URL` pointing at PHP and `/docs`, `TOKYO_OMAKASE_WIDTHS=390,1440`, and `TOKYO_SET_BUILDER_WIDTHS=390,1440`; then run `node tests/omakase-focused.cjs` and `node tests/set-builder-draft-regression.cjs`.

This is a focused export-parity check. It does not claim full visual-reference parity or final Tokyo Premium 2.0 acceptance.
