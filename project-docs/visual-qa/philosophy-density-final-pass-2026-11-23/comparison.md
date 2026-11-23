# Philosophy page — reference rhythm follow-up

Reference: [`design-references/05-philosophy.png`](../../../design-references/05-philosophy.png).

The desktop history image is now capped near 250 px at wide viewports, the timeline places each year beside its event as in the reference, and timeline copy remains 12 px. The principles band and booking call-to-action use less vertical padding. Changes are limited to desktop widths above 1100 px; the mobile composition is unchanged. The PHP and Pages stylesheets are identical.

Focused Playwright verification passed on the static route at 390 px and 1440 px. It checked loaded images, heading bounds, all four red section rules, page overflow and desktop section limits. The 390 px full-page height stayed 5821 CSS px. At 1440 px the page is 2543 CSS px, compared with 2548 px before this pass. The page remains 383 px taller than the proportionally scaled 2160 px reference; this change improves the story/timeline layout but does not resolve the overall density gap. Captures and metrics are in this directory.

PHP browser rendering and public Pages deployment have not been run for this change. No full visual-parity claim is made.
