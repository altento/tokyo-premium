# Omakase — 2026-11-19

Baseline `4258d14`, restoration branch `backup/omakase-20261119`. Opened and inspected approved reference `04-omakase.png`, the [baseline capture](before.jpg), and the complete final [desktop](omakase-1440.jpg) / [mobile](omakase-390.jpg) captures. The [paired comparison](comparison.jpg) displays both pages at an equal 520 px width. Full-page screenshot capture temporarily puts the header in document flow; normal sticky positioning remains unchanged.

## Changes

- Added previous/next course buttons, endpoint disabled states and a visible current-course label. Progress exposes the course name and number to assistive technology. Reaching a disabled endpoint moves focus to the available direction, preserving keyboard access.
- Existing tab/arrow/Home/End behavior remains. On phones, a horizontal photo strip replaces three rows of thumbnails; keyboard and sequential navigation keep the selected thumbnail visible without scrolling the document.
- The hero's format action now leads to the three-format comparison. Each tier still carries its own explicit booking query, including after refresh. Removed unreachable session-storage selection code; no hidden previous selection overrides the URL.
- Added red section rules to the philosophy, stages, reservation and backstage headings. Seasonal-format purchase preference uses a red action. Backstage copy now describes the presentation rather than the lack of a video player; the block remains a photograph and retains educational disclosure elsewhere on the page.

## Verification

- `test:omakase-focused` passed against PHP and the generated static export at 320, 390, 768, 1024, 1440 and 1920 px. It verifies five distinct course photos, sequential and keyboard controls, accessible progress, mobile selected-thumbnail visibility, three booking formats and refresh, heading text bounds, document overflow and reduced/normal-motion behavior. No page errors or HTTP responses >=400 were observed.
- `test:tokyo20` passed against the current PHP and static app: 21 PHP/19 static product details and add actions, offers, Omakase, builder, preference wizard, booking scheme, gift preview, quick view, profile and mobile overflow.
- An old local server initially served a previous static artifact; its results were not accepted for the new feature. The final focused and Tokyo 2.0 checks used the current export on port 8101.
- Export: 37 pages; `php -l pages.php`, `node --check experience-ui.js` and `git diff --check` passed. The new focused test is available in the optional full-QA workflow; the fast default deployment profile remains unchanged.

## Remaining visual differences

The page preserves the approved hero/philosophy/five-course/three-tier/reservation/backstage/parchment structure. It remains taller because of the interactive course-detail panel and uses different first-party photos, ornaments and footer. Prices and course counts are the existing fictional KZT catalog values, not the PNG's ruble values. No video or live restaurant availability is implied. This is not a claim of complete pixel parity or full Tokyo Premium 2.0 acceptance.
