# Booking hero focused comparison — 2026-11-16

Reference: `design-references/07-reservation.png`.

The mobile capture showed that the original hero subtitle, “Выберите формат, дату и столик.”, ran too close to the viewport edge. The PHP route and static export now use the reference-aligned “Забронируйте свой столик.” The booking CTA, form, email behavior, SVG floorplan and preference-only availability wording are unchanged.

`booking-hero-desktop.jpg` and `booking-hero-mobile.jpg` record the updated hero. The focused static Playwright route returned HTTP 200, loaded both page photos, and reported no browser errors or failed resources. Text ranges, button bounds, the booking anchor and page width were checked at 320, 390 and 1440 px; all fit. `php -l pages.php` and `git diff --check` passed. The PHP route was not browser-rendered in this pass.

The shorter subtitle now follows the approved copy more closely. This was a focused hero correction; the complete page is not claimed to be a pixel-level reproduction of the booking reference.
