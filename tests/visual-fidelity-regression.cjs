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
  { key: 'home', php: '/', static: '/index.html', width: 1440, ogImage: 'assets/generated/home-hero-salmon-nigiri-v3.webp' },
  { key: 'menu', php: '/menu.php', static: '/menu.html', width: 1440, ogImage: 'assets/generated/menu-hero-sushi-assortment-v1.webp' },
  { key: 'dish', php: '/pages.php?page=dish&id=3', static: '/dish-3.html', width: 1440, ogImage: 'assets/food/03-nigiri-salmon.webp' },
  { key: 'philosophy', php: '/about.php', static: '/about.html', width: 1440, ogImage: 'assets/generated/contact-exterior.webp' },
  { key: 'space', php: '/pages.php?page=gallery', static: '/gallery.html', width: 1440, ogImage: 'assets/generated/room-main-hall.webp' },
  { key: 'booking', php: '/pages.php?page=booking', static: '/booking.html', width: 1440, ogImage: 'assets/generated/restaurant-interior.webp' },
  { key: 'contacts', php: '/pages.php?page=contacts', static: '/contacts.html', width: 1440, ogImage: 'assets/generated/contact-exterior.webp' },
  { key: 'omakase', php: '/pages.php?page=omakase', static: '/omakase.html', width: 1440, ogImage: 'assets/generated/omakase-chef-panorama-1920.webp' },
  { key: 'reviews', php: '/reviews.php', static: '/reviews.html', width: 1440, ogImage: 'assets/generated/restaurant-interior.webp' },
  { key: 'cart', php: '/pages.php?page=cart', static: '/cart.html', width: 1440, ogImage: 'assets/generated/cart-hero.webp' },
  { key: 'account', php: '/account.php', static: '/account.html', width: 1440, ogImage: 'assets/generated/contact-exterior.webp' }
];

const requestedRoutes = (process.env.TOKYO_VISUAL_ROUTES || '').split(',').map(value => value.trim()).filter(Boolean);
const routesToRun = requestedRoutes.length ? routes.filter(route => requestedRoutes.includes(route.key)) : routes;
const requestedMode = process.env.TOKYO_VISUAL_MODE || 'both';
if (!['php', 'static', 'both'].includes(requestedMode)) throw new Error(`Unknown visual mode: ${requestedMode}`);
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
  assert.equal(await page.locator('meta[property="og:image"]').getAttribute('content'), route.ogImage, `${mode} ${route.key} exposes its own social image`);
  assert.equal(await page.locator('meta[name="twitter:image"]').getAttribute('content'), route.ogImage, `${mode} ${route.key} keeps Twitter image in sync`);
  await page.addStyleTag({ content: 'html{scroll-behavior:auto!important}' });
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(async () => {
    for (const image of document.images) {
      image.loading = 'eager';
      await image.decode().catch(() => {});
    }
  });
  if (route.key === 'account' && mode === 'php' && !(await page.locator('#account-dashboard-title').count())) {
    // The PHP account is private and has a different dashboard from the static
    // browser demo. Register a synthetic QA user so the capture verifies the real
    // authenticated dashboard instead of comparing the login screen to demo markup.
    const registration = page.locator('.account-auth-grid form').nth(1);
    await registration.locator('[name="name"]').fill('Visual QA Guest');
    await registration.locator('[name="email"]').fill(`visual-qa-${Date.now()}@example.test`);
    await registration.locator('[name="password"]').fill('Visual-QA-password-2026!');
    await registration.getByRole('button', { name: 'Регистрация', exact: true }).click();
    await page.locator('#account-dashboard-title').waitFor({ state: 'visible' });
  }
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
      const hiddenReveals = await page.evaluate(() => [...document.querySelectorAll('.reveal,[data-tp-reveal],[data-story-reveal]')].filter(node => {
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
    assert.equal(await page.locator('.menu-breadcrumbs [aria-current="page"]').innerText(), 'Меню', `${mode} menu includes the current breadcrumb`);
    assert.ok(await page.locator('.menu-hero').evaluate(node => node.getBoundingClientRect().height <= 460), `${mode} menu hero stays within the compact editorial height`);
    assert.equal(await page.locator('.menu-set-story-photo img').evaluateAll(images => images.length === 2 && images.every(image => image.naturalWidth > 0)), true, `${mode} menu's authored-set story has two loaded photographs`);
    assert.equal(await page.locator('.menu-set-story a[href*="set-builder"]').count(), 1, `${mode} menu links its authored-set story to the set builder`);
    await page.setViewportSize({ width: 1024, height: 900 });
    const tabletHeader = await page.evaluate(() => {
      const header = document.querySelector('.site-header');
      const style = getComputedStyle(header);
      const nodes = [
        header.querySelector('.wordmark'),
        header.querySelector('#site-navigation'),
        header.querySelector('.header-actions .header-icon:not(.header-cart-link)'),
        header.querySelector('.header-cart-link'),
        header.querySelector('.header-cta')
      ];
      const boxes = nodes.map(node => node.getBoundingClientRect());
      const left = header.getBoundingClientRect().left + parseFloat(style.paddingLeft);
      const right = header.getBoundingClientRect().right - parseFloat(style.paddingRight);
      return {
        visible: nodes.every(node => getComputedStyle(node).display !== 'none' && node.getBoundingClientRect().width > 0),
        noOverlap: boxes.every((box,index) => index === 0 || boxes[index - 1].right <= box.left + 1),
        insideHeader: boxes.every(box => box.left >= left - 1 && box.right <= right + 1),
        navAvailable: !header.querySelector('#site-navigation').inert,
        bookingAccessibleName: nodes[4].getAttribute('aria-label')
      };
    });
    assert.deepEqual(tabletHeader, { visible: true, noOverlap: true, insideHeader: true, navAvailable: true, bookingAccessibleName: 'Бронировать столик' }, `${mode} 1024px desktop header follows the approved reference without clipping`);
    await page.setViewportSize({ width: 1023, height: 900 });
    const mobileToggle = page.locator('.site-header .mobile-menu');
    const mobileNav = page.locator('#site-navigation');
    assert.equal(await mobileToggle.isVisible(), true, `${mode} 1023px uses the mobile navigation`);
    // Wait for the matchMedia change handler to apply inert after resizing from desktop.
    await page.waitForFunction(() => document.querySelector('#site-navigation')?.inert === true, null, { timeout: 2000 });
    assert.equal(await mobileNav.evaluate(node => node.inert), true, `${mode} closed mobile navigation is inert`);
    await mobileToggle.click();
    assert.equal(await page.locator('#site-navigation .mobile-nav-utilities a[href*="booking"]').isVisible(), true, `${mode} mobile navigation exposes booking`);
    await page.keyboard.press('Escape');
    assert.equal(await mobileToggle.getAttribute('aria-expanded'), 'false', `${mode} mobile navigation closes with Escape`);
    assert.equal(await mobileToggle.evaluate(node => document.activeElement === node), true, `${mode} mobile navigation restores focus`);
    await page.setViewportSize({ width: route.width, height: 1000 });
    await revealAll();
  } else if (route.key === 'dish') {
    assert.equal(await page.locator('.detail-feature--product').count(), 1, `${mode} dish uses the product detail layout`);
    assert.equal(await page.locator('.detail-product-heading h1').count(), 1, `${mode} dish has one product heading`);
    assert.equal(await page.locator('h1').count(), 1, `${mode} dish does not duplicate its title`);
    assert.equal(await page.locator('#dish-main-image').evaluate(image => image.naturalWidth > 0), true, `${mode} dish photo loads`);
    assert.equal(await page.locator('.dish-chef-story img').evaluate(image => image.naturalWidth > 0), true, `${mode} chef editorial photo loads`);
    assert.equal(await page.locator('.detail-actions .add-to-cart').count(), 1, `${mode} dish retains its add-to-cart action`);
    const composition = await page.locator('[data-dish-composition]').innerText();
    assert.match(composition, /Лосось/, `${mode} salmon nigiri shows catalog-backed salmon composition`);
    assert.match(composition, /Рис для суши/, `${mode} salmon nigiri shows catalog-backed sushi-rice composition`);
    assert.match(await page.locator('.detail-accuracy-note').innerText(), /Аллергенные сведения не установлены/, `${mode} dish detail does not imply allergy verification`);
    assert.equal(await page.locator('.dish-grid-related .dish-card').count(), 4, `${mode} dish shows four complementary items`);
    assert.match(await page.locator('.dish-grid-related').innerText(), /Юдзу-лимонад|Нигири с тунцом/, `${mode} dish recommendations use the configured pairing records`);
  } else if (route.key === 'philosophy') {
    const background = await page.locator('.about-hero').evaluate(node => getComputedStyle(node).backgroundImage);
    assert.match(background, /contact-exterior\.webp/, `${mode} philosophy hero uses the sakura entrance photograph`);
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
    const heroHeight = await page.locator('.editorial-hero.booking-hero').evaluate(node => node.getBoundingClientRect().height);
    assert.ok(heroHeight <= 420, `${mode} booking hero stays compact and brings the booking content closer`);
    assert.equal(await page.locator('#full-booking-form').count(), 1, `${mode} booking form is present`);
    assert.equal(await page.locator('#full-guest-email').getAttribute('required'), '', `${mode} booking requests an email for status updates`);
    assert.equal(await page.locator('[data-tp-floorplan] svg').count(), 1, `${mode} preference floorplan renders as SVG`);
    assert.equal(await page.locator('[data-tp-floorplan-selection]').count(), 1, `${mode} floorplan clearly labels selection as a preference`);
  } else if (route.key === 'contacts') {
    const contactGeometry = await page.evaluate(() => ({
      hero: document.querySelector('.contact-hero').getBoundingClientRect().height,
      map: document.querySelector('.fictional-map').getBoundingClientRect().height,
      summaryWidth: document.querySelector('.contact-summary').getBoundingClientRect().width,
      mapWidth: document.querySelector('.fictional-map').getBoundingClientRect().width
    }));
    assert.ok(contactGeometry.hero <= 380, `${mode} contact hero stays close to reference proportions (${contactGeometry.hero}px)`);
    assert.ok(contactGeometry.map <= 350, `${mode} fictional map stays in the reference-led overview (${contactGeometry.map}px)`);
    assert.ok(contactGeometry.mapWidth >= contactGeometry.summaryWidth * 1.8, `${mode} map receives the dominant overview width from reference 09 (${contactGeometry.mapWidth}px vs ${contactGeometry.summaryWidth}px)`);
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
      const nodes = ['.contact-overview', '#contact-form', '.contact-visit-section'].map(selector => document.querySelector(selector));
      return Boolean(nodes.every(Boolean) && (nodes[0].compareDocumentPosition(nodes[1]) & Node.DOCUMENT_POSITION_FOLLOWING)
        && (nodes[1].compareDocumentPosition(nodes[2]) & Node.DOCUMENT_POSITION_FOLLOWING));
    });
    assert.equal(contactOrder, true, `${mode} contact summary, message panel and visit notes follow the reference order`);
  } else if (route.key === 'omakase') {
    const counts = await page.evaluate(() => ({
      interactiveStages: document.querySelectorAll('[data-tp-course]').length,
      stagePanels: document.querySelectorAll('[data-tp-course-panel]').length,
      duplicateCourseGrid: Boolean(document.querySelector('.omakase-course-grid')),
      heroHeight: document.querySelector('.omakase-hero')?.getBoundingClientRect().height ?? 0,
      orderIsEditorial: (() => {
        const philosophy = document.querySelector('.omakase-philosophy-strip');
        const story = document.querySelector('.tp20-omakase-experience');
        const tiers = document.querySelector('.experience-tiers')?.closest('.section');
        const booking = document.querySelector('.omakase-reservation-band');
        return Boolean(philosophy && story && tiers && booking
          && philosophy.compareDocumentPosition(story) & Node.DOCUMENT_POSITION_FOLLOWING
          && story.compareDocumentPosition(tiers) & Node.DOCUMENT_POSITION_FOLLOWING
          && tiers.compareDocumentPosition(booking) & Node.DOCUMENT_POSITION_FOLLOWING);
      })()
    }));
    assert.equal(counts.interactiveStages, 5, `${mode} Omakase presents five interactive catalog-backed stages`);
    assert.equal(counts.stagePanels, 5, `${mode} each Omakase stage has a matching story panel`);
    const coursePreviews = await page.locator('.tp20-course-tabs [data-tp-course] img').evaluateAll(images => images.map(image => image.currentSrc || image.src));
    assert.equal(coursePreviews.length, 5, `${mode} Omakase shows a photo preview for every stage`);
    assert.equal(new Set(coursePreviews).size, 5, `${mode} Omakase previews five distinct course photographs`);
    assert.equal(await page.locator('.tp20-course-tabs [data-tp-course] img').evaluateAll(images => images.every(image => image.naturalWidth > 0)), true, `${mode} all course previews load`);
    const secondCourse = page.locator('.tp20-course-tabs [data-tp-course][data-step="2"]');
    await secondCourse.click();
    assert.equal(await secondCourse.getAttribute('aria-selected'), 'true', `${mode} selecting a course updates its tab state`);
    assert.equal(await page.locator('[data-tp-course-panel][data-step="2"]').isVisible(), true, `${mode} selecting a course reveals its matching story`);
    await secondCourse.press('ArrowRight');
    const thirdCourse = page.locator('.tp20-course-tabs [data-tp-course][data-step="3"]');
    assert.equal(await thirdCourse.getAttribute('aria-selected'), 'true', `${mode} ArrowRight selects the next course tab`);
    assert.equal(await thirdCourse.evaluate(node => document.activeElement === node), true, `${mode} ArrowRight moves keyboard focus with the selected course`);
    assert.equal(await page.locator('[data-tp-course-panel][data-step="3"]').isVisible(), true, `${mode} keyboard selection reveals the matching course story`);
    await page.locator('.tp20-course-tabs [data-tp-course][data-step="1"]').click();
    assert.equal(counts.duplicateCourseGrid, false, `${mode} Omakase does not repeat the same course photos in a second grid`);
    assert.equal(counts.orderIsEditorial, true, `${mode} Omakase follows philosophy, interactive journey, tiers and booking order`);
    assert.ok(counts.heroHeight <= 370, `${mode} Omakase desktop hero stays close to the reference height (${counts.heroHeight}px)`);
    const hero = await page.locator('.omakase-hero .editorial-hero-photo').evaluate(image => ({
      transform: getComputedStyle(image).transform,
      source: image.currentSrc,
      mobileObjectPosition: getComputedStyle(image).objectPosition
    }));
    assert.equal(hero.transform, 'none', `${mode} Omakase panorama is not mirrored`);
    assert.match(hero.source, /omakase-chef-panorama-/);
  } else if (route.key === 'reviews') {
    assert.equal(await page.locator('.reviews-hero h1').innerText(), 'Отзывы гостей', `${mode} reviews page has its own editorial heading`);
    assert.equal(await page.locator('#review-grid [data-review-card]').count(), 4, `${mode} reviews page renders its disclosed demo stories`);
    assert.equal(await page.locator('#review-form').count(), 1, `${mode} reviews page includes the submission form`);
    assert.equal(await page.locator('#review-sort').count(), 1, `${mode} reviews page exposes sorting`);
  } else if (route.key === 'cart') {
    assert.equal(await page.locator('.cart-hero h1').count(), 1, `${mode} cart has a dedicated page heading`);
    assert.equal(await page.locator('#checkout-form').count(), 1, `${mode} cart includes checkout`);
    const cartHeroHeight = await page.locator('.cart-hero').evaluate(hero => hero.getBoundingClientRect().height);
    assert.ok(cartHeroHeight <= 270, `${mode} cart hero remains compact against the approved reference (${cartHeroHeight}px)`);
    assert.ok(await page.locator('#cart-page-empty').isVisible(), `${mode} fresh browser starts with an honest empty-cart state`);
    assert.equal(await page.locator('.cart-recommendations .dish-card').count(), 4, `${mode} empty cart offers four catalog-backed recommendations`);
  } else if (route.key === 'account') {
    const heading = mode === 'php' ? '#account-dashboard-title' : '.account-overview h1';
    const accountNavigation = mode === 'php' ? '.account-dashboard-nav' : '.account-demo-nav';
    const loyaltyCard = mode === 'php' ? '.account-dashboard-loyalty' : '[data-tp-loyalty-card]';
    assert.equal(await page.locator(heading).count(), 1, `${mode} account dashboard has a profile heading`);
    assert.ok(await page.locator(accountNavigation).isVisible(), `${mode} account dashboard exposes its navigation`);
    assert.ok(await page.locator(loyaltyCard).isVisible(), `${mode} account dashboard shows its correctly labelled loyalty card`);
  }

  assert.deepEqual(errors, [], `${mode} ${route.key} has no JavaScript or console errors`);
  assert.equal(await page.evaluate(() => innerWidth), route.width, `${mode} ${route.key} desktop capture uses the configured ${route.width}px viewport`);
  if (capture) await page.screenshot({ path: path.join(evidenceDir, `${route.key}-${mode}-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 76 });

  await page.setViewportSize({ width: 390, height: 844 });
  await revealAll();
  if (route.key === 'omakase') {
    const mobileObjectPosition = await page.locator('.omakase-hero .editorial-hero-photo').evaluate(image => getComputedStyle(image).objectPosition);
    assert.equal(mobileObjectPosition, '100% 50%', `${mode} mobile Omakase crop keeps the chef in frame`);
    assert.equal(await page.locator('.tp20-course-tabs [data-tp-course] img').evaluateAll(images => images.every(image => image.naturalWidth > 0)), true, `${mode} mobile course preview photos load`);
  }
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
    omakaseLastStageCentered: (() => {
      const tabs = document.querySelector('.tp20-omakase-experience .tp20-course-tabs');
      if (!tabs) return true;
      const buttons = [...tabs.querySelectorAll('button')];
      if (buttons.length !== 5) return false;
      const bounds = tabs.getBoundingClientRect();
      const last = buttons[4].getBoundingClientRect();
      return Math.abs((last.left + last.right) / 2 - (bounds.left + bounds.right) / 2) <= 2;
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
  if (route.key === 'booking') {
    const mobileBookingHero = await page.locator('.editorial-hero.booking-hero').evaluate(node => node.getBoundingClientRect().height);
    assert.ok(mobileBookingHero <= 460, `${mode} booking hero remains compact on mobile`);
  }
  assert.equal(mobile.omakaseTabsVisible, true, "Omakase keeps all stages visible without horizontal scrolling");
  if (route.key === 'omakase') assert.equal(mobile.omakaseLastStageCentered, true, `${mode} Omakase centers the fifth mobile course preview`);
  assert.equal(mobile.heroTitleVisible, true, `${mode} ${route.key} mobile heading remains in the viewport`);
  assert.equal(mobile.contactPanelsVisible, true, `${mode} ${route.key} contact panels fit the mobile viewport`);
  if (capture) await page.screenshot({ path: path.join(evidenceDir, `${route.key}-${mode}-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
  await page.close();
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const captureStatic = process.env.TOKYO_CAPTURE_STATIC === '1';
    if (requestedMode !== 'static') for (const route of routesToRun) await visit(browser, phpBase, route, 'php', true);
    if (requestedMode !== 'php') for (const route of routesToRun) await visit(browser, staticBase, route, 'static', captureStatic);
    console.log(`PASS: ${routesToRun.length} visual-fidelity route${routesToRun.length === 1 ? '' : 's'} validated in ${requestedMode} mode; screenshots saved to ${evidenceDir}`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
