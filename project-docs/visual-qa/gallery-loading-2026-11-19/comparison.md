# Gallery loading and recovery · 2026-11-19

## Reproduced defect and repair

On the unmodified local PHP gallery, changing the first tile's full-size URL to a missing image and opening it left the dialog open with `naturalWidth=0`, its regular caption, no status and no retry. Both `pages-extra.js` and `experience-ui.js` assigned the selected image URL independently.

The enhanced lightbox now owns photo loading. Its loading and failure messages are inside the modal accessibility tree, with a keyboard-accessible retry. A generation counter rejects late responses after a newer selection or closing the window. The previous image is hidden while the new caption/photo loads. Retry moves focus before its button is hidden; Escape returns focus to the original tile. The basic opener remains available if the enhancement script fails to load.

The modal now fits its content rather than stretching between fixed top/bottom edges. Its image height leaves room for the caption and controls. Existing filtered albums, room albums, arrow keys, horizontal swipes and room-to-booking links remain intact.

## Actual verification

- `test:gallery-loading`: PHP and separate static export passed. Simulated network failure → retry; exactly one image request per attempt; slow first photo → next photo → delayed first response; close before completion; six widths 320/390/768/1024/1440/1920; bounds of caption, error, retry, close and arrows; decoded image bounds; booking retains Main Hall; enhancement-script failure preserves the basic viewer. Results: [loading-browser.json](loading-browser.json).
- Existing `test:gallery-caption`: PHP and static passed, including room/filtered albums, Escape focus restoration and swipe direction.
- `test:tokyo20`: PHP + static passed, including 21 PHP / 19 public static dish routes, commerce features and responsive overflow. Captures are in `regression/`; these are regression artifacts, not a fresh visual acceptance of every page.
- `test:cart`: 14 passed. JS syntax and Git whitespace checks passed. Static export generated 37 pages. No server or database logic changed in this pass; the normal release workflow retains its PHP/SQLite checks.

## Visual comparison and limits

Opened approved `design-references/06-gallery.png` and inspected the full desktop gallery plus desktop failure and mobile photo/failure captures in this folder. The page retains the hero, four distinct room cards, eight-image grid, red section rules and footer. The error message and retry fit within the phone modal; photo captions and navigation remain visible. The empty vertical space from the previous full-height modal was reduced.

Compared with the reference, current photography, larger section typography, room descriptions and footer differ. There is no invented 360° tour: the approved concept's tour strip is intentionally absent because no panoramic source exists. This pass repairs the viewer and verifies existing page composition; it does not claim pixel parity with the reference or completion of all Tokyo 2.0 requirements.

Publication is pending at the time of this implementation commit. The follow-up release section records actual CI and public verification.
