const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createRequire } = require('node:module');
const projectRequire = createRequire(path.resolve(__dirname, '../package.json'));
const { chromium } = projectRequire('playwright');

const phpBase = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8085';
const staticBase = process.env.TOKYO_STATIC_BASE_URL || `${phpBase}/docs`;
const evidenceDir = path.resolve(process.env.TOKYO_VISUAL_FIDELITY_DIR || path.join(__dirname, '..', 'project-docs', 'visual-qa', '2.0-visual-fidelity'));
fs.mkdirSync(evidenceDir, { recursive: true });

const routes = [
  { key: 'home', php: '/', static: '/index.html', width: 1440 },
  { key: 'menu', php: '/menu.php', static: '/menu.html', width: 1440 },
  { key: 'philosophy', php: '/about.php', static: '/about.html', width: 1440 },
  { key: 'space', php: '/pages.php?page=gallery', static: '/gallery.html', width: 1440 },
  { key: 'booking', php: '/pages.php?page=booking', static: '/booking.html', width: 1440 },
  { key: 'contacts', php: '/pages.php?page=contacts', static: '/contacts.html', width: 1440 },
  { key: 'omakase', php: '/pages.php?page=omakase', static: '/omakase.html', width: 1440 }
];

async function visit(browser, base, route, mode, capture = false) {
  const page = await browser.newPage({ viewport: { width: route.width, height: 1000 }, deviceScaleFactor: 1 });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  const response = await page.goto(`${base}${route[mode]}`, { waitUntil: 'networkidle' });
  assert.equal(response?.status(), 200, `${mode} ${route.key} returns 200`);
  await page.addStyleTag({ content: 'html{scroll-behavior:auto!important}' });
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(async () => {
    for (const image of document.images) {
      image.loading = 'eager';
      await image.decode().catch(() => {});
    }
  });
  const revealAll = async () => {
    await page.evaluate(async () => {
      document.documentElement.style.scrollBehavior = 'auto';
      const step = Math.max(320, innerHeight * 0.72);
      for (let top = 0; top < document.documentElement.scrollHeight; top += step) {
        window.scrollTo({ top, behavior: 'instant' });
        await new Promise(resolve => setTimeout(resolve, 90));
      }
      await Promise.all([...document.images].map(image => image.decode().catch(() => {})));
      window.scrollTo({ top: 0, behavior: 'instant' });
    });
    await page.waitForTimeout(850);
    const hiddenReveals = await page.evaluate(() => [...document.querySelectorAll('.reveal,[data-tp-reveal]')].filter(node => {
      const rect = node.getBoundingClientRect();
      const style = getComputedStyle(node);
      return rect.width > 0 && rect.height > 0 && style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity) < 0.95;
    }).length);
    assert.equal(hiddenReveals, 0, `${mode} ${route.key} has no visible content left hidden after scrolling`);
  };
  await revealAll();

  if (route.key === 'home') {
    const image = page.locator('.home-hero-photo--active');
    assert.match(await image.getAttribute('src'), /home-hero-salmon-nigiri-v3\.webp$/);
    assert.equal(await image.evaluate(node => node.naturalWidth > 0), true, `${mode} homepage hero image loads`);
    assert.equal(await page.locator('.home-dish-grid .home-dish-card.visible').count(), 4, `${mode} featured dishes appear after a natural scroll through the page`);
    assert.equal(await page.locator('.home-dish-grid img').evaluateAll(images => images.filter(image => image.naturalWidth > 0).length), 4, `${mode} all featured dish photos load`);
  } else if (route.key === 'menu') {
    const image = page.locator('.menu-hero > img');
    assert.match(await image.getAttribute('src'), /menu-hero-sushi-assortment-v1\.webp$/);
    assert.equal(await image.evaluate(node => node.naturalWidth > 0), true, `${mode} menu hero image loads`);
    assert.notEqual(await image.getAttribute('src'), 'assets/generated/home-hero-salmon-nigiri-v3.webp', 'menu hero is a distinct photograph');
  } else if (route.key === 'philosophy') {
    const background = await page.locator('.about-hero').evaluate(node => getComputedStyle(node).backgroundImage);
    assert.match(background, /room-garden-terrace\.webp/, `${mode} philosophy hero uses its garden photograph`);
  } else if (route.key === 'space') {
    const geometry = await page.locator('[data-tp-gallery] .gallery-grid').evaluate(grid => {
      const section = grid.closest('[data-tp-gallery]');
      const bounds = grid.getBoundingClientRect();
      const sectionBounds = section.getBoundingClientRect();
      const tiles = [...grid.querySelectorAll('.gallery-tile')];
      return {
        height: bounds.height,
        scrollHeight: grid.scrollHeight,
        clientHeight: grid.clientHeight,
        tileCount: tiles.length,
        allTilesWithinSection: tiles.every(tile => tile.getBoundingClientRect().bottom <= sectionBounds.bottom + 1),
        imageCount: tiles.filter(tile => tile.querySelector('img')?.naturalWidth > 0).length
      };
    });
    assert.equal(geometry.tileCount, 8, `${mode} gallery renders all eight tiles`);
    assert.ok(geometry.clientHeight > 390, `${mode} editorial gallery grows to fit all rows`);
    assert.ok(geometry.scrollHeight <= geometry.clientHeight + 1, `${mode} gallery grid does not internally clip overflow`);
    assert.equal(geometry.allTilesWithinSection, true, `${mode} every gallery tile stays inside its section`);
    assert.equal(geometry.imageCount, 8, `${mode} all gallery images load`);
  } else if (route.key === 'booking') {
    assert.equal(await page.locator('#full-booking-form').count(), 1, `${mode} booking form is present`);
    assert.equal(await page.locator('#full-guest-email').getAttribute('required'), '', `${mode} booking requests an email for status updates`);
    assert.equal(await page.locator('[data-tp-floorplan] svg').count(), 1, `${mode} preference floorplan renders as SVG`);
    assert.equal(await page.locator('[data-tp-floorplan-selection]').count(), 1, `${mode} floorplan clearly labels selection as a preference`);
  } else if (route.key === 'contacts') {
    assert.equal(await page.locator('.fictional-map[role="img"]').count(), 1, `${mode} contact map is labelled as a decorative schematic`);
    assert.equal(await page.locator('form.contact-form').count(), 1, `${mode} contact form is present`);
    assert.equal(await page.locator('.editorial-hero img').evaluate(image => image.naturalWidth > 0), true, `${mode} contact hero image loads`);
  } else if (route.key === 'omakase') {
    const counts = await page.evaluate(() => ({
      selectedStages: document.querySelectorAll('[data-tp-course]').length,
      overviewCards: document.querySelectorAll('.omakase-course-grid .omakase-course-card').length,
      loadedOverviewPhotos: [...document.querySelectorAll('.omakase-course-grid img')].filter(image => image.naturalWidth > 0).length
    }));
    assert.equal(counts.overviewCards, counts.selectedStages, `${mode} overview and interactive course stages agree`);
    assert.equal(counts.overviewCards, 5, `${mode} overview presents the five catalog-backed stages`);
    assert.equal(counts.loadedOverviewPhotos, 5, `${mode} all overview photographs load`);
  }

  assert.deepEqual(errors, [], `${mode} ${route.key} has no JavaScript or console errors`);
  if (capture) await page.screenshot({ path: path.join(evidenceDir, `${route.key}-${mode}-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 76 });

  await page.setViewportSize({ width: 390, height: 844 });
  await revealAll();
  const mobile = await page.evaluate(() => ({
    width: innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    heroTitleVisible: [...document.querySelectorAll('h1')].every(title => {
      const r = title.getBoundingClientRect();
      return r.left >= -1 && r.right <= innerWidth + 1 && r.bottom > 0;
    })
  }));
  assert.ok(mobile.scrollWidth <= mobile.width, `${mode} ${route.key} has no mobile horizontal overflow`);
  assert.equal(mobile.heroTitleVisible, true, `${mode} ${route.key} mobile heading remains in the viewport`);
  if (capture) await page.screenshot({ path: path.join(evidenceDir, `${route.key}-${mode}-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
  await page.close();
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const route of routes) await visit(browser, phpBase, route, 'php', true);
    for (const route of routes) await visit(browser, staticBase, route, 'static', false);
    console.log(`PASS: seven visual-fidelity routes validated in PHP and static mode; PHP desktop/mobile evidence saved to ${evidenceDir}`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
