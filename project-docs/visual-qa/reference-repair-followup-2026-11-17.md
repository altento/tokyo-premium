# Visual repair follow-up — 2026-11-17

## Dish detail — reference 03

The PHP desktop capture showed the heading at the top of the photo while its description and purchase controls were pushed far below it. The grid's two-row spanning layout created the blank area. The title, intro, description, educational price, quantity and add-to-order action now form one flex column beside the photo. At mobile widths, the page preserves the reading order: title, image, purchase details.

The detail page continues to use catalog-backed ingredients and photographs. It does not invent ratings, nutrition values, allergy validation or extra photo angles for dishes that do not have those assets.

Evidence: `2.0-visual-fidelity/dish-php-desktop.jpg`, `dish-php-mobile.jpg`, `dish-static-desktop.jpg`, and `dish-static-mobile.jpg`.

## Omakase — reference 04

The selected course story previously occupied a tall photo-and-text panel after the five image cards. The interactive panel remains available, but its padding, image height and typography are now compact enough to keep the next set-selection section in view sooner. The reservation band uses a copy-side shade so the room photograph remains visible across the open side.

The section still differs intentionally from reference 04: selecting a stage opens a detail panel because the project requires an interactive tasting story, and the page does not claim to have video or a live restaurant experience.

Evidence: `2.0-visual-fidelity/omakase-php-desktop.jpg`, `omakase-php-mobile.jpg`, `omakase-static-desktop.jpg`, and `omakase-static-mobile.jpg`.

## Verification

- `php -l pages.php` passed.
- Focused `npm run test:visual-fidelity` passed the dish route in PHP and static modes. The test checks the purchase-column spacing, mobile order, loaded image, catalog-backed composition and existing cart/favorites controls.
- Focused `npm run test:visual-fidelity` passed Omakase in PHP and static modes. The test exercises five distinct stage photos, click and keyboard navigation, matching detail panels, mobile containment, and an active desktop panel height of at most 360 px.
- Updated desktop and mobile screenshots were opened and inspected.

This note covers two of the eleven approved reference pages. It is not a claim of full visual parity, full-site regression, PHP/SQLite suite completion, hosted CI, or current public deployment.

## Account dashboard — reference 11 follow-up

- Reduced the PHP dashboard's outer whitespace and hero height, retained the profile/loyalty pairing, and tightened empty order, booking, and notification states. The four summary metrics remain a balanced two-by-two group beside the loyalty card.
- Kept all account sections and server-backed status semantics visible; this CSS pass did not fabricate account history or change account data behavior.
- `npm run test:visual-fidelity` passed for the account route in PHP and static modes. Browser assertions cover the compact hero, summary geometry, concise empty states, seven in-page navigation targets, and mobile overflow. Fresh PHP desktop/mobile captures were inspected.
- This improves density but does not establish full parity with reference 11. Comprehensive account flows, full-site QA, hosted CI, and public deployment remain pending.
## Пространство / галерея — фото без повторов

В исходном блоке галереи первые четыре кадра повторяли изображения карточек залов. Заменил эти четыре позиции на отдельные интерьерный, chef-counter, блюдо и садовый кадры из уже имеющихся ресурсов; все восемь изображений теперь уникальны и не совпадают с фото карточек залов. Порядок секций и галерейный lightbox сохранены. Это функционально-честная галерея фотографий, не 360°-тур.

Сверка с `design-references/06-gallery.png`: сохранена последовательность hero → принципы пространства → карточки зон → фильтруемая галерея → footer. Фотографии и композиция галереи теперь разнообразнее; структура вёрстки остаётся компактнее и отличается от макета, поэтому полный визуальный паритет не заявляется.

Проверено: `npm run test:visual-fidelity` на PHP и Pages для маршрута `space`, включая уникальность 8 изображений, отсутствие повторов с карточками залов и загрузку изображений; `npm run test:gallery-caption`; `php -l pages.php`; `node --check tests/visual-fidelity-regression.cjs`; `git diff --check`. Захвачены и просмотрены PHP и статические скриншоты desktop/mobile: `2.0-visual-fidelity/space-{php,static}-{desktop,mobile}.jpg`.