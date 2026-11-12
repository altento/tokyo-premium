# Tokyo Premium design system
Updated 2026-11-12

## Art direction
Luxury Japanese dining through restrained contrast: lacquer charcoal, warm ivory, aged gold, and selective vermilion. Editorial Playfair Display headings pair with DM Sans/Manrope interface text. Japanese characters function as small cultural accents, not decoration on every surface.

## Layout
- Cinematic hero with dark photo overlay and a clear first action.
- Long editorial bands alternate charcoal and warm light to pace storytelling.
- Dish cards prioritize image, name, portion, description, price, favorite, and cart action.
- Editorial routes receive distinct layouts: dish detail, tasting ritual, gallery, booking, contact, and checkout.
- Responsive grids collapse to a single-column reading flow on narrow screens.
- Shared header/footer, spacing, border, color, and typography tokens live in `luxury.css` and `editorial.css`.

## Motion and access
- Use short opacity/transform reveals, hover feedback, filter changes and a dialog lightbox.
- Prefer CSS transforms and opacity; no autoplay video or background audio.
- Respect `prefers-reduced-motion`. Keep controls semantic, labels visible, focus indicators intact, and modal dismissal available by Escape/clicking close.
- Avoid animated effects that obscure content or delay the main task.

## Photography and motion assets

Food and room photography is served from local WebP assets under `assets/food/` and `assets/generated/`. The original `tokyo-seasonal-omakase-v2.webp` image is a 1600×900 author-created editorial composition optimized to 148 KB. Offers and evening discovery use first-party imagery without requests to missing fallback URLs. Images describe the fictional school concept; they do not document a real restaurant, chef or served dish.

Motion uses deferred JavaScript and short opacity/transform transitions. The SVG floor plan selects preferences only. No autoplay sound or video is used. `prefers-reduced-motion` disables reveal transforms and transition-heavy behavior.
