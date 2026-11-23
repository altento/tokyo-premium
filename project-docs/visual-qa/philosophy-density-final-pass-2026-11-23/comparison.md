# Philosophy page — reference rhythm follow-up

Reference: [`design-references/05-philosophy.png`](../../../design-references/05-philosophy.png).

The desktop history image is now capped near 250 px at wide viewports, the timeline places each year beside its event as in the reference, and timeline copy remains 12 px. The principles band and booking call-to-action use less vertical padding. Changes are limited to desktop widths above 1100 px; the mobile composition is unchanged. The PHP and Pages stylesheets are identical.

Focused Playwright verification passed on the local static route at 390 px and 1440 px. It checked loaded images, heading bounds, all four red section rules, page overflow and desktop section limits. The 390 px full-page height stayed 5821 CSS px. Local desktop height was 2543 CSS px, compared with 2548 px before this pass. Pages workflow [45191130338](https://github.com/altento/tokyo-premium/actions/runs/45191130338) passed build and deploy; the public static route then passed the same focused browser check at 390 px and 1440 px. Its measured heights were 5821 and 2526 CSS px respectively. Public captures and metrics are in `public/`.

The 1440 px public page remains 366 px taller than the proportionally scaled 2160 px reference. This change improves the story/timeline layout but does not resolve the overall density gap. Capture and metrics files are saved locally and from the published site.

PHP browser rendering was not run locally. No full visual-parity claim is made.
