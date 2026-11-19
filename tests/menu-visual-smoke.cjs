const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createRequire } = require('node:module');
const projectRequire = createRequire(path.resolve(__dirname, '../package.json'));
const { chromium } = projectRequire('playwright');

const base = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8085';
const evidenceDir = path.resolve(process.env.TOKYO_MENU_EVIDENCE_DIR || path.join(__dirname, '..', 'project-docs', 'visual-qa'));
fs.mkdirSync(evidenceDir, { recursive: true });

const routes = [
  { mode: 'php', path: '/menu.php' },
  { mode: 'static', path: '/docs/menu.html' }
];
const selectedMode = process.env.TOKYO_MENU_MODE;
if (selectedMode && !routes.some(route => route.mode === selectedMode)) throw new Error(`Unknown TOKYO_MENU_MODE: ${selectedMode}`);
const selectedRoutes = selectedMode ? routes.filter(route => route.mode === selectedMode) : routes;
async function verifyMenuInteractions(page) {
  const ids = () => page.locator('.dish-card[data-category]').evaluateAll(cards => cards.map(card => card.querySelector('.add-to-cart').dataset.id));
  const original = await ids();
  await page.locator('#sort-menu').selectOption('price-desc');
  await page.locator('#sort-menu').selectOption('default');
  assert.deepEqual(await ids(), original, 'default sorting restores the original catalogue order');
  await page.locator('.favorite-btn[data-favorite="3"]').click();
  await page.locator('#favorites-only').click();
  await page.locator('.menu-search').fill('лосос');
  const category = await page.evaluate(() => window.DISHES.find(dish => Number(dish.id) === 3).category);
  await page.locator(`button.filter-btn[data-category="${category}"]`).click();
  await page.locator('#sort-menu').selectOption('price-asc');
  const matches = await page.locator('.dish-card[data-category]').evaluateAll(cards => cards.filter(card => !card.hidden && getComputedStyle(card).display !== 'none').map(card => Number(card.querySelector('.add-to-cart').dataset.id)));
  assert.deepEqual(matches, [3], 'search, category, favorites and sorting compose correctly');
  await page.locator('.menu-search').fill('');
  await page.locator('#favorites-only').click();
  await page.locator('button.filter-btn[data-category="all"]').click();
  await page.locator('#sort-menu').selectOption('default');
  await page.locator('.favorite-btn[data-favorite="3"]').click();
  await page.locator('.dish-card:not([hidden]) [data-tp-quick-view]').first().click();
  await page.locator('#tp-quick-view-dialog').waitFor({state:'visible'});
  await page.locator('#tp-quick-view-dialog [data-tp-quick-view-add]').click();
  assert.ok(await page.evaluate(() => window.TokyoCart.get().length > 0), 'quick view adds a catalogue item to the shared cart');
  await page.keyboard.press('Escape');
  await page.evaluate(() => window.TokyoCart.clear());
}
const viewports = [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'tablet-landscape', width: 1024, height: 900 },
  { name: 'tablet-boundary', width: 1023, height: 900 },
  { name: 'tablet-portrait', width: 900, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
  { name: 'small-mobile', width: 320, height: 844 },
  { name: 'wide-desktop', width: 1920, height: 1080 }
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error') errors.push(message.text());
  });

  try {
    for (const route of selectedRoutes) {
      for (const viewport of viewports) {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        const response = await page.goto(`${base}${route.path}`, { waitUntil: 'networkidle' });
        assert.equal(response?.status(), 200, `${route.mode} menu returns HTTP 200`);
        if (viewport.name === 'desktop') {
          const dishPath = route.mode === 'php' ? '/pages.php?page=dish&id=21' : '/docs/dish-21.html';
          const detailResponse = await page.goto(`${base}${dishPath}`, { waitUntil: 'networkidle' });
          assert.equal(detailResponse?.status(), 200, `${route.mode} scallop detail route returns HTTP 200`);
          await page.locator('#dish-main-image').evaluate(image => image.decode());
          assert.match(await page.locator('h1').innerText(), /Нигири с морским гребешком/u, `${route.mode} detail page shows the scallop nigiri`);
          assert.ok(await page.locator('#dish-main-image').evaluate(image => image.naturalWidth > 0), `${route.mode} scallop product photograph loads`);
          await page.goto(`${base}${route.path}`, { waitUntil: 'networkidle' });
          await verifyMenuInteractions(page);
        }
        await page.evaluate(async () => {
          for (const image of document.images) image.loading = 'eager';
          await Promise.all([...document.images].map(image => image.decode().catch(() => {})));
          await document.fonts.ready;
        });

        const metrics = await page.evaluate(() => {
          const hero = document.querySelector('.menu-hero');
          const heroImage = hero?.querySelector(':scope > img');
          const storyImages = [...document.querySelectorAll('.menu-set-story-photo img')];
          return {
            width: innerWidth,
            scrollWidth: document.documentElement.scrollWidth,
            navigation: (() => {
              const nav = document.querySelector('#site-navigation');
              const toggle = document.querySelector('.site-header .mobile-menu');
              const wordmark = document.querySelector('.site-header .wordmark');
              const profile = document.querySelector('.site-header .header-actions .header-icon:not(.header-cart-link)');
              const cart = document.querySelector('.site-header .header-cart-link');
              const booking = document.querySelector('.site-header .header-cta');
              const header = document.querySelector('.site-header');
              const visible = element => Boolean(element && getComputedStyle(element).display !== 'none' && element.getBoundingClientRect().width > 0);
              const rect = element => {
                const box = element.getBoundingClientRect();
                return { left: box.left, right: box.right };
              };
              const desktopItems = [wordmark, nav, profile, cart, booking].filter(visible);
              const boxes = desktopItems.map(rect);
              const headerBox = header.getBoundingClientRect();
              const headerStyle = getComputedStyle(header);
              const contentLeft = headerBox.left + parseFloat(headerStyle.paddingLeft);
              const contentRight = headerBox.right - parseFloat(headerStyle.paddingRight);
              return {
                navVisible: visible(nav),
                navInert: nav.inert,
                mobileToggleVisible: visible(toggle),
                profileVisible: visible(profile),
                cartVisible: visible(cart),
                bookingVisible: visible(booking),
                desktopItemsDoNotOverlap: boxes.every((box, index) => index === 0 || boxes[index - 1].right <= box.left + 1),
                desktopItemsInsideContent: boxes.every(box => box.left >= contentLeft - 1 && box.right <= contentRight + 1)
              };
            })(),
            menuGridColumns: getComputedStyle(document.querySelector('.menu-page .dish-grid')).gridTemplateColumns.split(' ').length,
            heroHeight: Math.round(hero?.getBoundingClientRect().height || 0),
            heroImageLoaded: Boolean(heroImage?.naturalWidth),
            breadcrumb: document.querySelector('.menu-breadcrumbs [aria-current="page"]')?.textContent.trim(),
            catalogHeadingDivider: (() => {
              const title = document.querySelector('.menu-catalog-heading h2');
              if (!title) return null;
              const divider = getComputedStyle(title, '::after');
              return { content: divider.content, width: parseFloat(divider.width), height: parseFloat(divider.height) };
            })(),
            firstPageCards: [...document.querySelectorAll('.menu-page .dish-card')].filter(card => {
              const style = getComputedStyle(card);
              const rect = card.getBoundingClientRect();
              return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            }).length,
            emptyCategoryButtons: [...document.querySelectorAll('.menu-category-list button[data-category]')].filter(button => Number(button.querySelector('b')?.textContent.trim()) === 0).length,
            setBuilderShortcut: document.querySelector('.menu-category-list .menu-set-link')?.getAttribute('href'),
            menuViewButtons: document.querySelectorAll('[data-menu-view]').length,
            ingredientFilterButtons: [...document.querySelectorAll('.menu-subfilter[data-menu-ingredient]')].filter(button => !button.hidden).length,
            scallopFilterVisible: !document.querySelector('.menu-subfilter[data-menu-ingredient="scallop"]')?.hidden,
            ingredientFilterCountsMatch: (() => {
              const matchers = {
                tuna: /тун(ец|ца|цу|цом|ц)/u,
                salmon: /лосос/u,
                eel: /угор/u,
                shrimp: /кревет/u,
                scallop: /гребеш/u,
                roe: /икр/u
              };
              const cards = [...document.querySelectorAll('.dish-card[data-category]')];
              return [...document.querySelectorAll('.menu-subfilter[data-menu-ingredient]')].every(button => {
                const ingredient = button.dataset.menuIngredient;
                const count = ingredient === 'all' ? cards.length : cards.filter(card => matchers[ingredient]?.test((card.dataset.search || card.textContent).toLocaleLowerCase('ru'))).length;
                return Number(button.dataset.matchCount) === count && button.hidden === (ingredient !== 'all' && count === 0);
              });
            })(),
            setBuilderShortcutInViewport: (() => {
              const link = document.querySelector('.menu-category-list .menu-set-link');
              if (!link) return false;
              const rect = link.getBoundingClientRect();
              return rect.left >= 0 && rect.right <= innerWidth && rect.width > 0 && rect.height > 0;
            })(),
            storyPhotoCount: storyImages.length,
            loadedStoryPhotos: storyImages.filter(image => image.naturalWidth > 0).length,
            setBuilderLink: document.querySelector('.menu-set-story a[href*="set-builder"]')?.getAttribute('href')
          };
        });

        assert.ok(metrics.scrollWidth <= viewport.width, `${route.mode} ${viewport.name} has no horizontal page overflow`);
        const desktopNavigation = viewport.width > 1023;
        assert.equal(metrics.navigation.navVisible, desktopNavigation, `${route.mode} ${viewport.name} shows only the matching navigation mode`);
        assert.equal(metrics.navigation.navInert, !desktopNavigation, `${route.mode} ${viewport.name} keeps hidden navigation out of keyboard focus`);
        assert.equal(metrics.navigation.mobileToggleVisible, !desktopNavigation, `${route.mode} ${viewport.name} exposes the mobile menu only at mobile widths`);
        assert.equal(metrics.navigation.profileVisible, desktopNavigation, `${route.mode} ${viewport.name} keeps the profile action available`);
        assert.equal(metrics.navigation.cartVisible, true, `${route.mode} ${viewport.name} keeps the cart visible`);
        assert.equal(metrics.navigation.bookingVisible, desktopNavigation, `${route.mode} ${viewport.name} keeps booking available in the active header mode`);
        if (desktopNavigation) {
          assert.equal(metrics.navigation.desktopItemsDoNotOverlap, true, `${route.mode} ${viewport.name} header controls do not overlap`);
          assert.equal(metrics.navigation.desktopItemsInsideContent, true, `${route.mode} ${viewport.name} header controls stay inside the header padding`);
        }
        assert.ok(metrics.heroHeight > 0 && metrics.heroHeight <= (viewport.width > 700 ? 350 : 370), `${route.mode} ${viewport.name} keeps the compact responsive menu hero`);
        assert.equal(metrics.heroImageLoaded, true, `${route.mode} ${viewport.name} hero image loads`);
        assert.equal(metrics.breadcrumb, 'Меню', `${route.mode} ${viewport.name} shows the current breadcrumb`);
        assert.equal(metrics.menuGridColumns, viewport.width > 900 ? 3 : (viewport.width <= 360 ? 1 : 2), `${route.mode} ${viewport.name} keeps the catalog at its intended responsive column count`);
        assert.ok(metrics.catalogHeadingDivider && metrics.catalogHeadingDivider.content !== 'none' && metrics.catalogHeadingDivider.width >= 40 && metrics.catalogHeadingDivider.height === 2, `${route.mode} ${viewport.name} preserves the Japanese-red rule under the catalog section title`);
        assert.equal(metrics.firstPageCards, 6, `${route.mode} ${viewport.name} renders the first six catalog cards`);
        assert.equal(metrics.emptyCategoryButtons, 0, `${route.mode} ${viewport.name} does not present an empty catalog filter`);
        assert.match(metrics.setBuilderShortcut || '', /set-builder/, `${route.mode} ${viewport.name} links directly to the set builder from categories`);
        assert.equal(metrics.menuViewButtons, 2, `${route.mode} ${viewport.name} exposes grid and list view controls`);
        assert.ok(metrics.ingredientFilterButtons > 1, `${route.mode} ${viewport.name} exposes filters for ingredients available in the menu`);
        assert.equal(metrics.scallopFilterVisible, true, `${route.mode} ${viewport.name} exposes the scallop filter for the photographed scallop nigiri`);
        assert.equal(metrics.ingredientFilterCountsMatch, true, `${route.mode} ${viewport.name} shows only ingredient filters with matching dishes and correct counts`);
        assert.equal(metrics.setBuilderShortcutInViewport, true, `${route.mode} ${viewport.name} keeps the set-builder shortcut visible in the category controls`);
        assert.equal(metrics.storyPhotoCount, 2, `${route.mode} ${viewport.name} has two authored-set photographs`);
        assert.equal(metrics.loadedStoryPhotos, 2, `${route.mode} ${viewport.name} loads both authored-set photographs`);
        assert.match(metrics.setBuilderLink || '', /set-builder/, `${route.mode} ${viewport.name} links to the set builder`);
        await page.locator('[data-menu-view="list"]').click();
        const listState = await page.locator('.menu-page .dish-grid').evaluate(grid => ({
          view: grid.dataset.view,
          pressed: grid.closest('.menu-catalog')?.querySelector('[data-menu-view="list"]')?.getAttribute('aria-pressed'),
          cardDisplay: getComputedStyle(grid.querySelector('.dish-card:not([hidden])')).display
        }));
        assert.deepEqual(listState, { view: 'list', pressed: 'true', cardDisplay: 'grid' }, `${route.mode} ${viewport.name} switches to a real card list`);
        await page.locator('[data-menu-ingredient="scallop"]').click();
        assert.equal(await page.locator('.menu-page .dish-card:not([hidden])').count(), 1, `${route.mode} ${viewport.name} scallop filter returns its catalog dish`);
        assert.match(await page.locator('.menu-page .dish-card:not([hidden])').innerText(), /Нигири с морским гребешком/u, `${route.mode} ${viewport.name} filter shows the correct dish`);
        await page.locator('[data-menu-ingredient="all"]').click();
        await page.locator('.menu-search').fill('Рамен с говядиной');
        const filteredList = await page.locator('.menu-page .dish-grid').evaluate(grid => ({
          visible: [...grid.querySelectorAll('.dish-card')].filter(card => !card.hidden && getComputedStyle(card).display !== 'none').length,
          hidden: [...grid.querySelectorAll('.dish-card')].filter(card => card.hidden).length
        }));
        assert.equal(filteredList.visible, 1, `${route.mode} ${viewport.name} filters list cards without revealing hidden items`);
        assert.ok(filteredList.hidden > 0, `${route.mode} ${viewport.name} keeps nonmatching list cards hidden`);
        await page.locator('.menu-search').fill('');
        await page.locator('[data-menu-view="grid"]').click();
        assert.equal(await page.locator('.menu-page .dish-grid').getAttribute('data-view'), 'grid', `${route.mode} ${viewport.name} returns to the default grid`);
        await page.locator('.menu-category-list [data-category="rolls"]').click();
        await page.locator('[data-menu-ingredient="salmon"]').click();
        await page.locator('.menu-search').fill('Филадельфия');
        assert.equal(await page.locator('.menu-page .dish-card:not([hidden])').count(), 1, `${route.mode} ${viewport.name} combines category, ingredient, and search filters`);
        await page.locator('.menu-category-list [data-category="sushi"]').click();
        assert.equal(await page.locator('.menu-page .dish-card:not([hidden])').count(), 0, `${route.mode} ${viewport.name} applies category and ingredient filters together`);
        await page.locator('.menu-category-list [data-category="all"]').click();
        await page.locator('[data-menu-ingredient="all"]').click();
        await page.locator('.menu-search').fill('');
        const builderResponse = await page.request.get(new URL(metrics.setBuilderLink, page.url()).href);
        assert.equal(builderResponse.status(), 200, `${route.mode} ${viewport.name} set-builder route loads`);

        await page.waitForFunction(() => !document.querySelector('.toast.show'));
        const captureStyle = await page.addStyleTag({ content: '.site-header{position:relative!important;top:auto!important}' });
        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
        await page.screenshot({
          path: path.join(evidenceDir, `02-menu-current-${route.mode}-${viewport.name}.jpg`),
          type: 'jpeg',
          quality: viewport.name === 'desktop' ? 74 : 72,
          fullPage: true
        });
        await captureStyle.evaluate(node => node.remove());
        if (viewport.name === 'mobile') {
          const toggle = page.locator('.site-header .mobile-menu');
          await toggle.click();
          assert.equal(await toggle.getAttribute('aria-expanded'), 'true', `${route.mode} mobile menu opens`);
          const bookingHref = route.mode === 'php' ? 'pages.php?page=booking' : 'booking.html';
          assert.equal(await page.locator(`#site-navigation .mobile-nav-utilities a[href="${bookingHref}"]`).isVisible(), true, `${route.mode} mobile menu exposes booking`);
          await page.keyboard.press('Escape');
          assert.equal(await toggle.getAttribute('aria-expanded'), 'false', `${route.mode} Escape closes the mobile menu`);
          assert.equal(await toggle.evaluate(element => document.activeElement === element), true, `${route.mode} closing the mobile menu restores focus`);
        }
        console.log(`${route.mode} ${viewport.name}: ${JSON.stringify(metrics)}`);
      }
    }
    assert.deepEqual(errors, [], 'menu has no browser JavaScript or console errors');
    console.log(`PASS: ${selectedRoutes.length * viewports.length} focused menu visual cases (${selectedRoutes.map(route => route.mode).join(', ')} × ${viewports.map(viewport => viewport.name).join(', ')})`);
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
