# Omakase hero review — 2026-11-16

Scope: replace the cropped Omakase hero photograph and tune the mobile crop. This is a focused check of one page, not acceptance of all 11 pages.

Reference reviewed: `design-references/04-omakase.png`.

## Changes

- Added an original, wide chef-at-counter image in three optimized WebP sizes (640, 1024 and 1920 px).
- The PHP hero now emits responsive `srcset` candidates and the correct intrinsic dimensions.
- The GitHub Pages export uses the same image variants.
- Removed the previous horizontal mirror; the desktop composition now keeps the title on the left and chef on the right. On small screens the crop is anchored right so the chef remains visible.

## Browser evidence

Checked the static export at 1440×1000 and 390×844 in Chromium. Full-page screenshots are in this directory.

- Hero image loaded at both widths; mobile selected the 640 px WebP candidate.
- `scrollWidth` matched viewport width at both sizes (1440 and 390 px).
- Omakase heading stayed inside the mobile viewport; all five course tabs were visible.
- No page errors, console errors, or failed requests.

## Limits

This was a visual and runtime check of the Omakase page only. It does not claim full pixel-level fidelity, PHP runtime coverage, or verification of the published GitHub Pages site. The broader Tokyo Premium 2.0 work remains in progress.
