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
const viewports = [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 }
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
            heroHeight: Math.round(hero?.getBoundingClientRect().height || 0),
            heroImageLoaded: Boolean(heroImage?.naturalWidth),
            breadcrumb: document.querySelector('.menu-breadcrumbs [aria-current="page"]')?.textContent.trim(),
            firstPageCards: [...document.querySelectorAll('.menu-page .dish-card')].filter(card => {
              const style = getComputedStyle(card);
              const rect = card.getBoundingClientRect();
              return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            }).length,
            emptyCategoryButtons: [...document.querySelectorAll('.menu-category-list button[data-category]')].filter(button => Number(button.querySelector('b')?.textContent.trim()) === 0).length,
            setBuilderShortcut: document.querySelector('.menu-category-list .menu-set-link')?.getAttribute('href'),
            menuViewButtons: document.querySelectorAll('[data-menu-view]').length,
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
        assert.ok(metrics.heroHeight > 0 && metrics.heroHeight <= 460, `${route.mode} ${viewport.name} has the compact menu hero`);
        assert.equal(metrics.heroImageLoaded, true, `${route.mode} ${viewport.name} hero image loads`);
        assert.equal(metrics.breadcrumb, 'Меню', `${route.mode} ${viewport.name} shows the current breadcrumb`);
        assert.equal(metrics.firstPageCards, 6, `${route.mode} ${viewport.name} renders the first six catalog cards`);
        assert.equal(metrics.emptyCategoryButtons, 0, `${route.mode} ${viewport.name} does not present an empty catalog filter`);
        assert.match(metrics.setBuilderShortcut || '', /set-builder/, `${route.mode} ${viewport.name} links directly to the set builder from categories`);
        assert.equal(metrics.menuViewButtons, 2, `${route.mode} ${viewport.name} exposes grid and list view controls`);
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
        const builderResponse = await page.request.get(new URL(metrics.setBuilderLink, page.url()).href);
        assert.equal(builderResponse.status(), 200, `${route.mode} ${viewport.name} set-builder route loads`);

        await page.screenshot({
          path: path.join(evidenceDir, `02-menu-current-${route.mode}-${viewport.name}-2026-11-16.jpg`),
          type: 'jpeg',
          quality: viewport.name === 'desktop' ? 74 : 72,
          fullPage: true
        });
        console.log(`${route.mode} ${viewport.name}: ${JSON.stringify(metrics)}`);
      }
    }
    assert.deepEqual(errors, [], 'menu has no browser JavaScript or console errors');
    console.log(`PASS: ${selectedRoutes.length * viewports.length} focused menu visual cases (${selectedRoutes.map(route => route.mode).join(', ')} × desktop/mobile)`);
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
