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
  { key: 'dish', php: '/pages.php?page=dish&id=3', static: '/dish-3.html', width: 1440 },
  { key: 'philosophy', php: '/about.php', static: '/about.html', width: 1440 },
  { key: 'space', php: '/pages.php?page=gallery', static: '/gallery.html', width: 1440 },
  { key: 'booking', php: '/pages.php?page=booking', static: '/booking.html', width: 1440 },
  { key: 'contacts', php: '/pages.php?page=contacts', static: '/contacts.html', width: 1440 },
  { key: 'omakase', php: '/pages.php?page=omakase', static: '/omakase.html', width: 1440 }
];

const requestedRoutes = (process.env.TOKYO_VISUAL_ROUTES || '').split(',').map(value => value.trim()).filter(Boolean);
const routesToRun = requestedRoutes.length ? routes.filter(route => requestedRoutes.includes(route.key)) : routes;
if (requestedRoutes.length && routesToRun.length !== requestedRoutes.length) {
  throw new Error(`Unknown visual route filter: ${requestedRoutes.join(', ')}`);
}

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
    assert.match(await image.getAttribute('src'), /menu-hero-japanese-garden-v2-1600\.webp$/);
    assert.equal(await image.evaluate(node => node.naturalWidth > 0), true, `${mode} menu hero image loads`);
    assert.notEqual(await image.getAttribute('src'), 'assets/generated/home-hero-salmon-nigiri-v3.webp', 'menu hero is a distinct photograph');
    assert.equal(await page.locator('.menu-breadcrumbs [aria-current="page"]').innerText(), 'Меню', `${mode} menu includes the current breadcrumb`);
    assert.ok(await page.locator('.menu-hero').evaluate(node => node.getBoundingClientRect().height <= 460), `${mode} menu hero stays within the compact editorial height`);
    assert.equal(await page.locator('.menu-set-story-photo img').evaluateAll(images => images.length === 2 && images.every(image => image.naturalWidth > 0)), true, `${mode} menu's authored-set story has two loaded photographs`);
    assert.equal(await page.locator('.menu-set-story a[href*="set-builder"]').count(), 1, `${mode} menu links its authored-set story to the set builder`);
  } else if (route.key === 'dish') {
    assert.equal(await page.locator('.detail-feature--product').count(), 1, `${mode} dish uses the product detail layout`);
    assert.equal(await page.locator('.detail-product-heading h1').count(), 1, `${mode} dish has one product heading`);
    assert.equal(await page.locator('h1').count(), 1, `${mode} dish does not duplicate its title`);
    assert.equal(await page.locator('#dish-main-image').evaluate(image => image.naturalWidth > 0), true, `${mode} dish photo loads`);
    assert.equal(await page.locator('.dish-chef-story img').evaluate(image => image.naturalWidth > 0), true, `${mode} chef editorial photo loads`);
    assert.equal(await page.locator('.detail-actions .add-to-cart').count(), 1, `${mode} dish retains its add-to-cart action`);
    assert.equal(await page.locator('.dish-grid-related .dish-card').count(), 4, `${mode} dish shows four complementary items`);
    assert.match(await page.locator('.dish-grid-related').innerText(), /Юдзу-лимонад|Нигири с тунцом/, `${mode} dish recommendations use the configured pairing records`);
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
    assert.equal(await page.locator('.fictional-map svg.contact-map-art').count(), 1, `${mode} contact map has its own vector illustration`);
    assert.match(await page.locator('.fictional-map').getAttribute('aria-label'), /не является реальной картой/i, `${mode} map clearly disclaims real navigation`);
    assert.equal(await page.locator('form.contact-form').count(), 1, `${mode} contact form is present`);
    assert.equal(await page.locator('.editorial-hero.contact-hero img').evaluate(image => image.naturalWidth > 0), true, `${mode} contact exterior photo loads`);
    assert.ok(await page.locator('.editorial-hero.contact-hero').evaluate(node => node.getBoundingClientRect().height <= 500), `${mode} contact hero stays compact`);
    assert.equal(await page.locator('.contact-facts li').count(), 3, `${mode} contact summary has phone, email and address`);
    assert.equal(await page.locator('.contact-hours-panel').count(), 1, `${mode} hours and booking action have a dedicated panel`);
    assert.equal(await page.locator('.contact-interior img').evaluate(image => image.naturalWidth > 0), true, `${mode} contact page loads its separate interior photograph`);
    assert.equal(await page.locator('#contact-name').getAttribute('required'), '', `${mode} contact form keeps required name validation`);
    assert.equal(await page.locator('#contact-email').getAttribute('required'), '', `${mode} contact form keeps required email validation`);
    assert.equal(await page.locator('#contact-message').getAttribute('required'), '', `${mode} contact form keeps required message validation`);
    const contactOrder = await page.evaluate(() => {
      const nodes = ['.contact-overview', '#contact-form', '.contact-footnotes'].map(selector => document.querySelector(selector));
      return Boolean(nodes.every(Boolean) && (nodes[0].compareDocumentPosition(nodes[1]) & Node.DOCUMENT_POSITION_FOLLOWING)
        && (nodes[1].compareDocumentPosition(nodes[2]) & Node.DOCUMENT_POSITION_FOLLOWING));
    });
    assert.equal(contactOrder, true, `${mode} contact summary, message panel and visit notes follow the reference order`);
  } else if (route.key === 'omakase') {
    const counts = await page.evaluate(() => ({
      selectedStages: document.querySelectorAll('[data-tp-course]').length,
      overviewCards: document.querySelectorAll('.omakase-course-grid .omakase-course-card').length,
      loadedOverviewPhotos: [...document.querySelectorAll('.omakase-course-grid img')].filter(image => image.naturalWidth > 0).length
    }));
    assert.equal(counts.overviewCards, counts.selectedStages, `${mode} overview and interactive course stages agree`);
    assert.equal(counts.overviewCards, 5, `${mode} overview presents the five catalog-backed stages`);
    const hero = await page.locator('.omakase-hero .editorial-hero-photo').evaluate(image => ({
      transform: getComputedStyle(image).transform,
      source: image.currentSrc,
      mobileObjectPosition: getComputedStyle(image).objectPosition
    }));
    assert.equal(hero.transform, 'none', `${mode} Omakase panorama is not mirrored`);
    assert.match(hero.source, /omakase-chef-panorama-/);
    await page.setViewportSize({ width: 390, height: 844 });
    const mobileObjectPosition = await page.locator('.omakase-hero .editorial-hero-photo').evaluate(image => getComputedStyle(image).objectPosition);
    assert.equal(mobileObjectPosition, '100% 50%', `${mode} mobile Omakase crop keeps the chef in frame`);
    assert.equal(counts.loadedOverviewPhotos, 5, `${mode} all overview photographs load`);
  }

  assert.deepEqual(errors, [], `${mode} ${route.key} has no JavaScript or console errors`);
  if (capture) await page.screenshot({ path: path.join(evidenceDir, `${route.key}-${mode}-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 76 });

  await page.setViewportSize({ width: 390, height: 844 });
  await revealAll();
  const mobile = await page.evaluate(() => ({
    width: innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    omakaseTabsVisible: (() => {
      const tabs = document.querySelector('.tp20-omakase-experience .tp20-course-tabs');
      if (!tabs) return true;
      const bounds = tabs.getBoundingClientRect();
      const buttons = [...tabs.querySelectorAll('button')];
      return buttons.length === 5
        && tabs.scrollWidth <= tabs.clientWidth + 1
        && buttons.every(button => {
          const r = button.getBoundingClientRect();
          return r.left >= bounds.left - 1 && r.right <= bounds.right + 1;
        });
    })(),
    heroTitleVisible: [...document.querySelectorAll('h1')].every(title => {
      const r = title.getBoundingClientRect();
      return r.left >= -1 && r.right <= innerWidth + 1 && r.bottom > 0;
    }),
    contactPanelsVisible: (() => {
      const grid = document.querySelector('.contact-response-grid');
      if (!grid) return true;
      const bounds = grid.getBoundingClientRect();
      return bounds.left >= -1 && bounds.right <= innerWidth + 1
        && [...grid.children].every(child => { const r = child.getBoundingClientRect(); return r.left >= -1 && r.right <= innerWidth + 1; });
    })()
  }));
  assert.ok(mobile.scrollWidth <= mobile.width, `${mode} ${route.key} has no mobile horizontal overflow`);
  assert.equal(mobile.omakaseTabsVisible, true, "Omakase keeps all stages visible without horizontal scrolling");
  assert.equal(mobile.heroTitleVisible, true, `${mode} ${route.key} mobile heading remains in the viewport`);
  assert.equal(mobile.contactPanelsVisible, true, `${mode} ${route.key} contact panels fit the mobile viewport`);
  if (capture) await page.screenshot({ path: path.join(evidenceDir, `${route.key}-${mode}-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
  await page.close();
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const route of routesToRun) await visit(browser, phpBase, route, 'php', true);
    const captureStatic = process.env.TOKYO_CAPTURE_STATIC === '1';
    for (const route of routesToRun) await visit(browser, staticBase, route, 'static', captureStatic);
    console.log(`PASS: ${routesToRun.length} visual-fidelity route${routesToRun.length === 1 ? '' : 's'} validated in PHP and static mode; screenshots saved to ${evidenceDir}`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
