# Booking and gallery — 2026-11-19

Baseline: `be6ed61`; restoration branch `backup/booking-gallery-20261119`. Approved PNGs 06 and 07 were opened and inspected. Baseline and final full-page screenshots were inspected, then compared at an equal 520 px display width in the paired images below. Runtime header positioning was preserved; only full-page capture temporarily places the sticky header in normal flow.

## Reproduced and corrected

- Booking: selecting terrace table 42 then resetting restored the main-hall select but retained hidden table 42, terrace photo and two highlighted markers. The reset handler now recalculates the zone after the native reset, clears the hidden default/value and selection, updates photo and messages. The extended floorplan test verifies all states in PHP and Pages.
- Gallery: after opening a gallery image and then a room image, the photo changed but its caption remained from the previous gallery image. Album scope now includes room previews while keeping room navigation and filtered gallery navigation separate. Tests cover captions, arrow keys, Escape/focus restoration, filters and horizontal versus vertical gestures.
- Filters now expose `aria-pressed`; filtering leaves all four room cards visible.
- Booking notice now spans a full form row. Room/gallery headings, spacing and touch targets were refined. Inspection found the mobile caption “Дегустационная подача” clipped; responsive sizing and word wrapping correct it. Text-range checks cover gallery captions as well as headings.

## Evidence

- [Baseline state values](reproduction.json), [booking before](booking-before.jpg), [gallery before](gallery-before.jpg).
- [Booking desktop](after/booking-1440.jpg), [booking mobile](after/booking-390.jpg), [selected-table desktop](after/07-booking-floorplan-desktop.png), [selected-table mobile](after/07-booking-floorplan-mobile.png).
- [Gallery desktop](after/gallery-1440.jpg), [gallery mobile](after/gallery-390.jpg), [lightbox desktop](after/gallery-caption-desktop.png), [lightbox mobile](after/gallery-caption-mobile.png).
- [Booking reference comparison](booking-comparison.jpg), [gallery reference comparison](gallery-comparison.jpg).

## Actual verification

- `test:floorplan` and `test:gallery-caption`: passed in PHP and Pages modes.
- `test:booking-gallery-layout`: passed for both routes in both modes at 320, 390, 768, 1024, 1440 and 1920 px; real heading/caption bounds, horizontal overflow, loaded main photographs and full-row booking notice checked. No page errors observed.
- `test:tokyo20`: passed, including 21 PHP/19 static product detail routes/add actions, offers, Omakase, set builder, preference wizard, floorplan, gift preview, quick view, profile and mobile overflow.
- Static export: 37 pages. No PHP source/schema/database is copied into the Pages artifact.

## Remaining reference differences

Booking keeps the reference's dark photographic hero, red actions, zone/table choice, room image and scenic FAQ, but remains taller, with a combined form/map rather than the PNG's two-step composition. Its SVG shows 42 preferences instead of the reference's illustrated floorplan; it does not claim live availability. Gallery keeps the hero, four individual room cards and eight-image grid; photographs, ornaments and footer differ. The reference's unsupported 360-degree tour is intentionally absent. Full pixel parity is not claimed.

Real email requires configured SMTP and a PHP host; real payment requires configured merchant credentials and HTTPS. Neither is activated by this update. GitHub Pages booking remains browser-local.

## Publication

Implementation commit `35bbec6` was pushed to main. [GitHub Actions run 44467862326](https://github.com/altento/tokyo-premium/actions/runs/44467862326) completed build and Pages deployment successfully. The fast CI profile ran its PHP/SQLite/cart/syntax/smoke/export checks; its optional full browser suite was skipped. The browser checks described above ran locally against both modes.

After deployment, fresh Chromium opened cache-busted public booking/gallery routes at 390 and 1440 px. Native form reset, cleared table/highlights, default zone, room captions/arrows, gallery filter accessibility/state and persistent room cards all passed without page errors. No public booking was submitted. [Public verification record](public-verification.json).
