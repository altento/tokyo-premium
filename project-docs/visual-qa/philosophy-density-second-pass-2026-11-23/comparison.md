# Philosophy page — desktop density follow-up

Date: 2026-11-23. Reference: [`design-references/05-philosophy.png`](../../../design-references/05-philosophy.png).

## Change

Reduced excess desktop height in the chef, principles, sourcing, quote and booking call-to-action chapters. The Japanese medallions and red section rules remain; small descriptive copy stays at 12 CSS px or larger in the compacted chapters. Mobile styles were left unchanged. The same rules are present in the PHP source and Pages stylesheet.

## Browser verification

The focused Playwright check `tests/philosophy-visual.cjs` passed on the checked-in Pages route at 390 px, 1440 px and 1920 px. It checks viewport overflow, loaded photographs, unclipped headings, all four red section rules, and chapter-height limits at desktop widths. At 1440 px the full page is 2548 CSS px tall, down from 3065 px in the previous checked capture (517 px, 16.9%); at 1920 px it is 2554 px. The 1440 px chapter heights are: story 408 px, chef 375 px, principles 332 px, sourcing 254 px, quote 174 px, and booking call to action 130 px. At 390 px the document stays within the viewport width.

Fresh full-page captures and measurements are in this directory: [`desktop.jpg`](desktop.jpg), [`mobile.jpg`](mobile.jpg), and [`metrics.json`](metrics.json).

## Comparison limits

The approved PNG is 1024 × 1536 px (2160 px high when proportionally scaled to 1440 px width). The current desktop page is still about 18% taller, and differs in typography, image treatment, editorial copy and its additional booking call to action. This is a measured density improvement, not a pixel-match claim. PHP-rendered visual verification is not available in the local environment because the Windows PHP CLI has no PDO SQLite driver.
