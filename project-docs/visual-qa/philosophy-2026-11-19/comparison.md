# Philosophy — reference follow-up, 2026-11-19

Opened approved `design-references/05-philosophy.png` and inspected the new full desktop/mobile PHP captures. The paired `reference-comparison.jpg` uses the final static capture at the same 600-pixel display width as the reference; it was also inspected.

The chef chapter now has a smaller heading, shorter spacing, a visibly loaded portrait and a balanced three-column photo/text/landscape composition. The ingredient chapter, principle grid, quote and booking CTA have a tighter rhythm. Copy now describes the restaurant concept instead of repeating implementation disclaimers throughout every paragraph; the fictional chef caption, fictional history notice and global educational disclosure remain.

`tests/philosophy-visual.cjs` passed against PHP and the static export at 320, 390, 768, 1024, 1440 and 1920 px. It waits for image decoding and fonts; checks heading text rectangles, document overflow, loaded images and JavaScript page errors; captures complete pages at 390 and 1440 px with reduced motion. Final JSON metrics are in `metrics.json` and `static/metrics.json`. Export completed with 37 pages.

The new page is closer in chapter scale but remains taller than the approved reference. Photo selection, decorative density, global footer and extra booking CTA differ. This is not pixel-perfect acceptance, a complete 11-page review, or confirmation of a live restaurant service. The older density screenshots had lazy images absent during capture; these captures explicitly wait for every main image.

Desktop: `desktop.jpg`, `static/desktop.jpg`. Mobile: `mobile.jpg`, `static/mobile.jpg`. No existing screenshots in other evidence folders were changed.
