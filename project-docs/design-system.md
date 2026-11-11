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

## Photography
Pages currently use remote Unsplash images with responsive source sets and lazy loading outside the hero. Captions and alt text identify editorial imagery as illustrative. The assets are not evidence of a real restaurant location, chef, guest, or menu item. Verify licensing and replace/host approved images before any commercial use.
