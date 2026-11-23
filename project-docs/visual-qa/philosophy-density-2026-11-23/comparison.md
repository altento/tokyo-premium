# Philosophy desktop density — 2026-11-23

Compared the current Pages export with [`05-philosophy.png`](../../../design-references/05-philosophy.png). The wide layout now uses shorter image frames and tighter spacing across the story, chef, principles, sourcing, quote and closing action chapters. Content, section dividers and the stacked mobile presentation remain intact.

## Focused browser result

`tests/philosophy-visual.cjs` passed at 390 px and 1440 px against the checked-in Pages export. All main images loaded, headings remained within their visible bounds, all four crimson section rules were present and visible, and document width matched the viewport. The fresh desktop page is 2864 px tall, down from 3065 px in the previous stored metrics. The 390 px mobile page remains 5821 px tall; this patch only changes the desktop media query.

| View | Screenshot | Result |
|---|---|---|
| Desktop, 1440 px | [`desktop.jpg`](desktop.jpg) | Inspected after the change; section gaps and images are more compact. |
| Mobile, 390 px | [`mobile.jpg`](mobile.jpg) | Inspected; previous stacked layout is unchanged. |
| Public desktop, 1440 px | [`public/desktop.jpg`](public/desktop.jpg) | Inspected after deployment; matches the released spacing. |
| Public mobile, 390 px | [`public/mobile.jpg`](public/mobile.jpg) | Public browser assertions passed. |

The same focused browser script passed on the cache-busted public Pages route at 390 px and 1440 px after commit [`0d6988e`](https://github.com/altento/tokyo-premium/commit/0d6988ea35d26579518ad618d2987222e6a11064) deployed successfully in [Actions run 45171370418](https://github.com/altento/tokyo-premium/actions/runs/45171370418). The scaled 1440 px reference height is approximately 2160 px. The current page remains materially taller because it includes longer editorial copy, extra figures, a booking CTA and a fuller footer; this pass does not claim full visual parity. PHP rendering was not checked.
