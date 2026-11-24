const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(root, 'project-docs', 'visual-qa');
const phpBase = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8090';
const staticBase = process.env.TOKYO_STATIC_BASE_URL || `${phpBase}/docs`;
const widths = [320, 390, 768, 1024, 1440, 1920];
const failures = [];
const check = (value, message) => assert.ok(value, message);

async function prepareVisual(page) {
  await page.evaluate(async () => {
    const images = [...document.images];
    images.forEach(image => { image.loading = 'eager'; });
    await Promise.all(images.map(image => image.decode().catch(() => null)));
    if (document.fonts?.ready) await document.fonts.ready.catch(() => null);
    window.scrollTo({ top: 0, behavior: 'instant' });
  });
}

async function verifyDishDetailRoutes(page, mode, catalog, secretIds) {
  await page.setViewportSize({ width: 390, height: 844 });
  const secret = new Set(secretIds.map(Number));
  const accessibleDishes = catalog.filter(dish => mode.name !== 'static' || !secret.has(Number(dish.id)));
  check(accessibleDishes.length === (mode.name === 'static' ? catalog.length - secret.size : catalog.length), `${mode.name} has a route for every dish available in this mode`);

  for (const dish of accessibleDishes) {
    const id = Number(dish.id);
    const url = mode.name === 'php' ? `/pages.php?page=dish&id=${id}` : `/dish-${id}.html`;
    const response = await page.goto(mode.base + url, { waitUntil: 'domcontentloaded' });
    check(response?.status() === 200, `${mode.name} dish ${id} detail route returns 200`);
    check(await page.locator('.detail-feature--product').count() === 1, `${mode.name} dish ${id} uses the product detail layout`);
    check(await page.locator('h1').count() === 1, `${mode.name} dish ${id} has one heading`);

    const heading = page.locator('.detail-product-heading h1');
    const titleState = await heading.evaluate(node => {
      const rect = node.getBoundingClientRect();
      const clippingAncestorsFit = (() => {
        for (let parent = node.parentElement; parent; parent = parent.parentElement) {
          const style = getComputedStyle(parent);
          if (!['hidden', 'clip'].includes(style.overflowX)) continue;
          const box = parent.getBoundingClientRect();
          if (rect.left < box.left - 1 || rect.right > box.right + 1) return false;
        }
        return true;
      })();
      return {
        text: node.innerText.trim(),
        insideViewport: rect.left >= -1 && rect.right <= innerWidth + 1,
        noTextOverflow: node.scrollWidth <= node.clientWidth + 1,
        clippingAncestorsFit
      };
    });
    check(titleState.text === String(dish.name).trim(), `${mode.name} dish ${id} heading matches its catalog title`);
    check(titleState.insideViewport && titleState.noTextOverflow && titleState.clippingAncestorsFit, `${mode.name} dish ${id} title is not clipped at 390px`);
    check(await page.locator('[data-dish-facts]').count() === 1, mode.name + ' dish ' + id + ' shows its factual detail strip');
    const detailFacts = await page.locator('[data-dish-facts]').evaluate(node => ({
      fields: Object.fromEntries([...node.querySelectorAll('[data-dish-fact]')].map(item => [item.dataset.dishFact, {
        value: item.querySelector('strong')?.textContent.trim() || '',
        count: Number(item.dataset.count || 0)
      }])),
      itemsFit: [...node.querySelectorAll('article')].every(item => {
        const box = item.getBoundingClientRect();
        return box.left >= node.getBoundingClientRect().left - 1 && box.right <= node.getBoundingClientRect().right + 1
          && [...item.querySelectorAll('span,strong')].every(text => text.scrollWidth <= text.clientWidth + 2);
      })
    }));
    check(detailFacts.fields.category?.value === dish.category_name && detailFacts.fields.portion?.value === dish.weight, mode.name + ' dish ' + id + ' facts match catalog category and portion');
    check(detailFacts.fields.photos?.count === (id === 3 ? 3 : 1), mode.name + ' dish ' + id + ' photo count matches its actual gallery');
    check(detailFacts.itemsFit, mode.name + ' dish ' + id + ' fact strip fits its mobile cards');
    check(Number(await page.locator('[data-dish-price]').getAttribute('data-price-value')) === Number(dish.price), mode.name + ' dish ' + id + ' primary price matches catalog');
    check(await page.locator('.detail-purchase-group .quantity-control').count() === 1, mode.name + ' dish ' + id + ' keeps quantity control in purchase group');
    const favoriteButton = page.locator('.detail-visual .detail-favorite-btn');
    check(await favoriteButton.count() === 1, mode.name + ' dish ' + id + ' exposes image-level favorite action');
    const favoriteBeforeToggle = await favoriteButton.getAttribute('aria-pressed') === 'true';
    await favoriteButton.click();
    check((await favoriteButton.getAttribute('aria-pressed') === 'true') !== favoriteBeforeToggle, mode.name + ' dish ' + id + ' toggles favorite state from the image');
    await favoriteButton.click();
    check((await favoriteButton.getAttribute('aria-pressed') === 'true') === favoriteBeforeToggle, mode.name + ' dish ' + id + ' restores its initial favorite state');
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${mode.name} dish ${id} has no mobile horizontal overflow`);

    const image = page.locator('#dish-main-image');
    await image.evaluate(node => node.decode());
    check(await image.evaluate(node => node.naturalWidth > 0), `${mode.name} dish ${id} main photograph loads`);
    const galleryButtons = page.locator('[data-detail-gallery-image]');
    if (id === 3) {
      check(await galleryButtons.count() === 3, `${mode.name} salmon nigiri exposes exactly three original gallery photos`);
      const gallerySources = await galleryButtons.evaluateAll(buttons => buttons.map(button => button.dataset.detailGalleryImage));
      check(new Set(gallerySources).size === 3, `${mode.name} salmon nigiri gallery uses three distinct photo files`);
      const secondPhoto = galleryButtons.nth(1);
      await secondPhoto.click();
      await image.evaluate(node => node.decode());
      check(await image.getAttribute('src') === await secondPhoto.getAttribute('data-detail-gallery-image'), `${mode.name} selecting a thumbnail updates the main dish photo`);
      check(await secondPhoto.getAttribute('aria-pressed') === 'true' && await galleryButtons.nth(0).getAttribute('aria-pressed') === 'false', `${mode.name} gallery exposes its selected photo accessibly`);
      check((await page.locator('[data-detail-gallery-caption]').innerText()).includes('Фото 2 из 3'), `${mode.name} gallery caption follows the selected photo`);
      const thirdPhoto = galleryButtons.nth(2);
      await thirdPhoto.click();
      await image.evaluate(node => node.decode());
      check(await image.getAttribute('src') === await thirdPhoto.getAttribute('data-detail-gallery-image'), `${mode.name} third product photo is selectable`);
      await galleryButtons.first().click();
      await image.evaluate(node => node.decode());
    } else {
      check(await galleryButtons.count() === 0, `${mode.name} dish ${id} does not claim unavailable gallery photos`);
      check((await page.locator('[data-detail-gallery-caption]').innerText()).includes('Одна фотография'), `${mode.name} dish ${id} honestly describes its single photo`);
    }
    const addButton = page.locator('.detail-actions .add-to-cart');
    check(await addButton.count() === 1 && Number(await addButton.getAttribute('data-id')) === id, `${mode.name} dish ${id} add button targets the current product`);
    await page.locator('[data-qty-step="1"]').click();
    check(await page.locator('#dish-qty').innerText() === '2' && await addButton.getAttribute('data-qty') === '2', `${mode.name} dish ${id} quantity control updates the add action`);
    await addButton.click();
    const cartState = await page.evaluate(targetId => ({
      line: window.TokyoCart?.get().find(item => item.id === targetId),
      total: window.TokyoCart?.total()
    }), id);
    check(cartState.line?.qty === 2, `${mode.name} dish ${id} can add the selected quantity from its detail page`);
    check(Number(cartState.total) === Number(dish.price) * 2, `${mode.name} dish ${id} detail add uses its catalog price and quantity`);
    await page.evaluate(() => window.TokyoCart.clear());
    check(await page.evaluate(() => window.TokyoCart.get().length === 0), `${mode.name} dish ${id} can be cleared after adding`);
  }

  for (const id of secret) {
    if (mode.name === 'php') continue;
    const response = await page.request.get(`${mode.base}/dish-${id}.html`);
    const body = await response.text();
    const dish = catalog.find(item => Number(item.id) === id);
    check(Boolean(dish) && (response.status() === 404 || !body.includes(String(dish.name))), `static Secret Menu dish ${id} has no public detail route`);
  }
  return accessibleDishes.length;
}

async function run() {
  const { chromium } = require('playwright');
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    const modes = [
      { name: 'php', base: phpBase, routes: { offers: '/offers.php', collection: '/collection.php?slug=autumn-in-kyoto', builder: '/set-builder.php', evening: '/perfect-evening.php', gift: '/gift-certificates.php', omakase: '/pages.php?page=omakase', dish: '/pages.php?page=dish&id=3', booking: '/pages.php?page=booking', menu: '/menu.php', cart: '/pages.php?page=cart', account: '/account.php' } },
      { name: 'static', base: staticBase, routes: { offers: '/offers.html', collection: '/collection-autumn-in-kyoto.html', builder: '/set-builder.html', evening: '/perfect-evening.html', gift: '/gift-certificates.html', omakase: '/omakase.html', dish: '/dish-3.html', booking: '/booking.html', menu: '/menu.html', cart: '/cart.html', account: '/account.html' } }
    ];
    const requestedModes = String(process.env.TOKYO20_MODES || '').split(',').map(value => value.trim()).filter(Boolean);
    const selectedModes = requestedModes.length ? modes.filter(mode => requestedModes.includes(mode.name)) : modes;
    check(selectedModes.length === (requestedModes.length ? requestedModes.length : modes.length), `requested Tokyo 2.0 modes exist: ${requestedModes.join(', ')}`);
    for (const mode of selectedModes) {
      const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
      const page = await context.newPage();
      page.setDefaultTimeout(7000);
      page.on('pageerror', error => failures.push(`${mode.name} pageerror ${page.url()}: ${error.message}`));
      page.on('console', message => {
        if (message.type() === 'error' && !/fonts\.googleapis|fonts\.gstatic/.test(message.location().url)) failures.push(`${mode.name} console ${page.url()}: ${message.text()}`);
      });

      for (const name of ['offers', 'collection', 'builder', 'evening', 'gift', 'omakase', 'booking', 'menu', 'cart', 'account']) {
        const response = await page.goto(mode.base + mode.routes[name], { waitUntil: 'domcontentloaded' });
        check(response?.status() === 200, `${mode.name} ${name} route returns 200`);
        check(await page.locator('h1').count() > 0, `${mode.name} ${name} route has a page heading`);
      }

      await page.goto(mode.base + mode.routes.offers, { waitUntil: 'domcontentloaded' });
      if (mode.name === 'static') {
        const demoSecretIds = await page.evaluate(() => (window.TOKYO_SECRET_DEMO_ITEMS || []).map(dish => Number(dish.id)).sort((a, b) => a - b));
        check(demoSecretIds.includes(17) && demoSecretIds.includes(18), 'static export keeps Secret Menu items in a clearly client-only preview payload');
        const hiddenStaticResponse = await fetch(`${mode.base}/dish-17.html`);
        const hiddenStaticHtml = await hiddenStaticResponse.text();
        check(hiddenStaticResponse.status === 404 || !hiddenStaticHtml.includes('Омакасе · 18 кусочков'), `static exporter does not serve a direct Secret Menu detail page (HTTP ${hiddenStaticResponse.status})`);
      }
      const secretForm = page.locator('[data-tp-secret-unlock]');
      check(await secretForm.count() === 1, `${mode.name} Secret Menu exposes its honest access form`);
      const secretCode = mode.name === 'php' ? 'SECRET' : 'TOKYO-DEMO';
      await secretForm.locator('[name="code"]').fill(secretCode);
      await secretForm.locator('button[type="submit"]').click();
      await page.waitForFunction(() => {
        const root = document.querySelector('[data-tp-secret-results]');
        return root && !root.hidden && root.querySelector('.dish-card');
      }, null, { timeout: 10000 });
      const exposedDishes = await page.evaluate(() => (Array.isArray(window.DISHES) ? window.DISHES : []).map(dish => ({
        id: Number(dish.id),
        name: String(dish.name),
        category_name: String(dish.category_name || ''),
        weight: String(dish.weight || ''),
        price: Number(dish.price)
      })));
      const unlockedSecretIds = await page.locator('[data-tp-secret-results] .add-to-cart').evaluateAll(buttons => buttons.map(button => Number(button.dataset.id)));
      check(exposedDishes.length >= 19, `${mode.name} exposes the public catalog after Secret Menu unlock`);
      check(unlockedSecretIds.length >= 2, `${mode.name} Secret Menu unlock exposes at least two additional dishes`);
      const detailRouteCount = await verifyDishDetailRoutes(page, mode, exposedDishes, unlockedSecretIds);
      console.log(`PASS: ${mode.name} product detail routes (${detailRouteCount}) and add-to-cart actions`);
      if (process.env.TOKYO20_DISH_ONLY === '1') {
        await context.close();
        continue;
      }
      await page.goto(mode.base + mode.routes.offers, { waitUntil: 'domcontentloaded' });
      if (mode.name === 'static') {
        // Pages has no server session, so a fresh navigation starts at the
        // honest local-demo gate and the visitor must unlock the preview again.
        const repeatSecretForm = page.locator('[data-tp-secret-unlock]');
        await repeatSecretForm.locator('[name="code"]').fill(secretCode);
        await repeatSecretForm.locator('button[type="submit"]').click();
        await page.waitForFunction(() => {
          const root = document.querySelector('[data-tp-secret-results]');
          return root && !root.hidden && root.querySelector('.dish-card');
        }, null, { timeout: 10000 });
      } else {
        // PHP stores the unlock in the session and renders the secret grid on
        // return; its form is intentionally absent after access is granted.
        const serverUnlockedItems = page.locator('.tp20-secret-grid .add-to-cart');
        check(await serverUnlockedItems.count() >= 2, 'php Secret Menu access persists after returning to offers');
      }
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-secret-menu-unlocked-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-secret-menu-unlocked-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });
      check(await page.evaluate(() => localStorage.getItem('tokyo-secret-menu-code')) === secretCode, `${mode.name} successful Secret Menu access persists the re-check token for checkout`);
      const secretMenuAddButton = mode.name === 'static'
        ? page.locator('[data-tp-secret-results] .add-to-cart').first()
        : page.locator('.tp20-secret-grid .add-to-cart').first();
      await secretMenuAddButton.click();
      await page.goto(mode.base + mode.routes.cart, { waitUntil: 'domcontentloaded' });
      check(await page.locator('#checkout-form [name="secret_code"]').inputValue() === secretCode, `${mode.name} checkout carries the Secret Menu access code separately from discount codes`);
      if (mode.name === 'php') {
        await page.locator('[data-tp-quote]').click();
        await page.locator('[data-tp-quote-total]').waitFor({ state: 'visible', timeout: 10000 });
        const quoteStatusText = await page.locator('[data-tp-quote-status]').innerText();
        check(/сервер пересчитал/i.test(quoteStatusText), `php checkout server revalidates Secret Menu access in the quote: ${quoteStatusText}`);
      }
      await page.evaluate(() => window.TokyoCart.clear());

      await page.goto(mode.base + mode.routes.cart, { waitUntil: 'domcontentloaded' });
      check(await page.locator('#cart-page-empty').isVisible(), `${mode.name} cart begins in the empty state`);
      const emptyPrefix = mode.name === 'php' ? 'cart-empty' : 'static-cart-empty';
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `${emptyPrefix}-desktop.png`), fullPage: true, type: 'png' });
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `${emptyPrefix}-mobile.png`), fullPage: true, type: 'png' });
      await page.setViewportSize({ width: 1440, height: 1000 });

      await page.goto(mode.base + mode.routes.offers, { waitUntil: 'domcontentloaded' });
      check((await page.locator('h1').innerText()).includes('Особый вечер'), `${mode.name} offers page is editorial and branded`);
      check(await page.locator('a[href*="set-builder"]').count() > 0, `${mode.name} offers links to the set builder`);
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-offers-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-offers-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });

      await page.goto(mode.base + mode.routes.collection, { waitUntil: 'domcontentloaded' });
      check((await page.locator('h1').innerText()).includes('Autumn in Kyoto'), `${mode.name} seasonal collection route is active`);
      check(await page.locator('#collection-dishes .dish-card').count() > 0, `${mode.name} seasonal collection uses linked catalog dishes`);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-collection-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-collection-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });

      await page.goto(mode.base + mode.routes.omakase, { waitUntil: 'domcontentloaded' });
      check(await page.locator('[data-tp-course]').count() === 5, `${mode.name} Omakase has five interactive course stages`);
      const firstCourse = page.locator('[data-tp-course-panel][data-step="1"]');
      const firstCourseImage = firstCourse.locator('.tp20-course-photo img');
      await firstCourseImage.evaluate(image => image.decode());
      check(await firstCourseImage.evaluate(image => image.naturalWidth > 0), `${mode.name} Omakase first stage shows its catalog photo`);
      check(await firstCourse.evaluate(panel => panel.getBoundingClientRect().height <= 250), `${mode.name} Omakase selected-stage detail stays compact beneath the tasting cards`);
      const quoteStrip = page.locator('.omakase-quote');
      check((await quoteStrip.evaluate(node => getComputedStyle(node).backgroundImage)).includes('omakase-quote-landscape.svg'), `${mode.name} Omakase quote uses the original landscape illustration`);
      check((await quoteStrip.locator('p').evaluate(node => getComputedStyle(node).color)) === 'rgb(56, 44, 34)', `${mode.name} Omakase quote text remains dark and legible over parchment artwork`);
      const quoteArtwork = await page.request.get(`${mode.base}/assets/generated/omakase-quote-landscape.svg`);
      check(quoteArtwork.ok() && (await quoteArtwork.text()).includes('<svg'), `${mode.name} Omakase quote illustration is served as an SVG asset`);
      check(!(await firstCourse.evaluate(panel => panel.classList.contains('reveal'))), `${mode.name} Omakase stage does not depend on scrolling to become visible`);
      await page.locator('[data-tp-course][data-step="2"]').click();
      const secondCourse = page.locator('[data-tp-course-panel][data-step="2"]');
      check(await secondCourse.isVisible(), `${mode.name} Omakase stage selector changes the story panel`);
      check(await secondCourse.locator('h3').innerText() === 'Нигири с тунцом', `${mode.name} Omakase stage uses the matching active catalog item`);
      await secondCourse.locator('.tp20-course-photo img').evaluate(image => image.decode());
      check(await secondCourse.locator('.tp20-course-photo img').evaluate(image => image.naturalWidth > 0), `${mode.name} selected Omakase stage loads its catalog photo`);
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-omakase-stage-2.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      check(await page.locator('[data-tp-course-panel][data-step="2"]').evaluate(panel => panel.getBoundingClientRect().height < 420), `${mode.name} Omakase selected-stage panel remains compact on mobile`);
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${mode.name} Omakase mobile layout has no horizontal overflow`);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-omakase-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });

      await page.goto(mode.base + mode.routes.dish, { waitUntil: 'domcontentloaded' });
      const relatedCards = page.locator('.dish-grid-related .dish-card');
      check(await relatedCards.count() > 0, `${mode.name} dish detail has catalog-backed related cards`);
      check(await relatedCards.first().evaluate(card => !card.classList.contains('reveal') && Number(getComputedStyle(card).opacity) >= .99), `${mode.name} related dish is visible without scroll-trigger state`);
      await relatedCards.first().locator('img').evaluate(image => image.decode());
      check(await relatedCards.first().locator('img').evaluate(image => image.naturalWidth > 0), `${mode.name} related dish photo loads`);

      await page.goto(mode.base + mode.routes.builder, { waitUntil: 'domcontentloaded' });
      check(await page.locator('[data-tp-set-dish]').count() >= 6, `${mode.name} set builder contains catalog dishes`);
      for (let index = 0; index < 3; index++) await page.locator('[data-tp-set-dish]').nth(index).locator('[data-tp-set-step="1"]').click();
      check(await page.locator('[data-tp-set-add]').isEnabled(), `${mode.name} set builder enables a valid three-dish composition`);
      await page.locator('[data-tp-set-add]').click();
      check(await page.evaluate(() => window.TokyoCart?.getCustomSets?.().length === 1), `${mode.name} custom set enters the shared cart store`);
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-set-builder-filled.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-set-builder-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.goto(mode.base + mode.routes.cart, { waitUntil: 'domcontentloaded' });
      check(await page.locator('.cart-page-custom-set').count() === 1, `${mode.name} cart displays the custom set line`);
      const added = await page.evaluate(() => [window.TokyoCart.add(4, 2), window.TokyoCart.add(5, 1), window.TokyoCart.add(6, 1)].every(Boolean));
      check(added, `${mode.name} cart accepts three real catalog dishes, with a repeated quantity`);
      check(await page.locator('#cart-page-items .cart-page-item:not(.cart-page-custom-set)').count() === 3, `${mode.name} populated cart shows three separate dishes`);
      check((await page.locator('#cart-page-items .cart-page-item:not(.cart-page-custom-set)').first().locator('.cart-page-controls span').innerText()) === '2', `${mode.name} populated cart includes a quantity of two`);
      const cartPrefix = mode.name === 'php' ? 'cart' : 'static-cart';
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `${cartPrefix}-populated-desktop.png`), fullPage: true, type: 'png' });
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `${cartPrefix}-populated-mobile.png`), fullPage: true, type: 'png' });
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.locator('[name="promo_code"]').fill('DEMO10');
      await page.locator('[data-tp-quote]').click();
      await page.waitForFunction(() => document.querySelector('[data-tp-quote-total]')?.hidden === false, null, { timeout: 10000 });
      const quoteText = await page.locator('[data-tp-quote-total]').innerText();
      check(quoteText.includes(mode.name === 'php' ? 'серверный расчёт' : 'локальная оценка'), `${mode.name} promo quote clearly identifies its authority`);
      check(quoteText.includes('скидка') && quoteText.includes('итог'), `${mode.name} promo quote shows recalculated subtotal, discount, and total`);
      await page.screenshot({ path: path.join(evidence, `${cartPrefix}-promo-quote-desktop.png`), fullPage: true, type: 'png' });
      await page.locator('.cart-checkout-details > summary').click();
      await page.locator('#fulfillment').selectOption('delivery');
      check(await page.locator('#checkout-address').isVisible() && await page.locator('#checkout-address').isEnabled() && await page.locator('#checkout-address').evaluate(input => input.required), `${mode.name} delivery requires the address`);
      check(!(await page.locator('#checkout-form').evaluate(form => form.checkValidity())), `${mode.name} empty checkout blocks submission`);
      await page.screenshot({ path: path.join(evidence, `${cartPrefix}-checkout-validation-desktop.png`), fullPage: true, type: 'png' });
      await page.setViewportSize({ width: 390, height: 844 });
      await page.screenshot({ path: path.join(evidence, `${cartPrefix}-checkout-validation-mobile.png`), fullPage: true, type: 'png' });
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.locator('#fulfillment').selectOption('pickup');
      check(await page.locator('#checkout-address').isHidden() && await page.locator('#checkout-address').isDisabled() && !(await page.locator('#checkout-address').evaluate(input => input.required)), `${mode.name} pickup hides and disables address validation`);
      if (mode.name === 'php') await page.screenshot({ path: path.join(evidence, 'cart-pickup-desktop.png'), fullPage: true, type: 'png' });
      await page.locator('#fulfillment').selectOption('delivery');
      check(await page.locator('#checkout-address').isVisible() && await page.locator('#checkout-address').isEnabled() && await page.locator('#checkout-address').evaluate(input => input.required), `${mode.name} switching back to delivery restores the address requirement`);
      if (mode.name === 'php') await page.screenshot({ path: path.join(evidence, 'cart-delivery-desktop.png'), fullPage: true, type: 'png' });
      if (mode.name === 'static') {
        await page.locator('#fulfillment').selectOption('pickup');
        await page.locator('#checkout-name').fill('Демо заказа QA');
        await page.locator('#checkout-phone').fill('+77010000001');
        await page.locator('#checkout-form button[type="submit"]').click();
        await page.waitForFunction(() => document.querySelector('#checkout-form > .form-status')?.classList.contains('success'), null, { timeout: 10000 });
        const savedOrder = await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-orders') || '[]')[0]);
        check(savedOrder?.demo === true && savedOrder?.name === 'Демо заказа QA', 'static checkout prepends the submitted local demo order to the account history');
      }

      await page.goto(mode.base + mode.routes.evening, { waitUntil: 'domcontentloaded' });
      const choices = [
        ['occasion', 'romantic'], ['preference', 'fish'], null, null, ['atmosphere', 'main']
      ];
      for (let index = 0; index < choices.length; index++) {
        const step = page.locator('[data-tp-evening-step]').nth(index);
        const choice = choices[index];
        if (choice) await step.locator(`[data-tp-evening-option][data-field="${choice[0]}"][value="${choice[1]}"]`).click();
        else {
          const input = step.locator('[data-tp-evening-input]');
          await input.fill(index === 2 ? '10000' : '3');
        }
        await step.locator('[data-tp-evening-next]').click();
      }
      check(await page.locator('[data-tp-evening-result]').isVisible(), `${mode.name} preference wizard renders a recommendation result`);
      check(await page.locator('.tp-recommendation-card').count() > 0, `${mode.name} recommendation uses real catalog items`);
      check(await page.locator('.tp-evening-plan-card').count() >= 2, `${mode.name} evening recommendation includes a composed main and drink pairing`);
      check(await page.locator('.tp-evening-plan-card[data-role="Напиток к блюду"]').count() === 1, `${mode.name} drink pairing is a real catalogue position`);
      const budgetSummary = await page.locator('.tp-evening-budget-summary').innerText();
      check(/10[\s\u00a0\u202f]*000.+на гостя/.test(budgetSummary) && /30[\s\u00a0\u202f]*000.+на компанию/.test(budgetSummary), `${mode.name} budget distinguishes the per-guest amount from the group allowance`);
      check(await page.locator('.tp-budget-note').count() === 0, `${mode.name} recommendation below the three-person budget is not incorrectly flagged`);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-perfect-evening-result.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-perfect-evening-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.evaluate(() => window.TokyoCart.clear());
      const planLines = await page.locator('[data-tp-evening-plan-line]').evaluateAll(nodes => nodes.map(node => ({ id: Number(node.dataset.id), qty: Number(node.dataset.qty) })));
      await page.locator('[data-tp-evening-add-plan]').click();
      check(JSON.stringify(await page.evaluate(() => window.TokyoCart.get())) === JSON.stringify(planLines), `${mode.name} recommendation adds every plan line and quantity to the shared cart`);
      const bookingHref = await page.locator('[data-tp-evening-booking]').getAttribute('href');
      check(bookingHref.includes('experience='), `${mode.name} recommendation carries its menu summary to booking`);
      await page.locator('[data-tp-evening-booking]').click();
      check(await page.locator('#booking-zone').inputValue() === 'Основной зал', `${mode.name} booking receives the selected dining zone`);
      await page.waitForFunction(() => document.querySelector('#booking-note')?.value.startsWith('Подбор меню:'));
      check((await page.locator('#booking-note').inputValue()).startsWith('Подбор меню:'), `${mode.name} booking receives an editable recommendation summary`);
      await page.goto(mode.base + mode.routes.evening, { waitUntil: 'domcontentloaded' });
      const omakaseChoices = [
        ['occasion', 'discovery'], ['preference', 'sets'], null, null, ['atmosphere', 'omakase']
      ];
      for (let index = 0; index < omakaseChoices.length; index++) {
        const step = page.locator('[data-tp-evening-step]').nth(index);
        const choice = omakaseChoices[index];
        if (choice) await step.locator(`[data-tp-evening-option][data-field="${choice[0]}"][value="${choice[1]}"]`).click();
        else await step.locator('[data-tp-evening-input]').fill(index === 2 ? '100000' : '3');
        await step.locator('[data-tp-evening-next]').click();
      }
      check(await page.locator('.tp-evening-plan-card[data-role="Сет для компании"]').count() === 1, `${mode.name} Omakase choice recommends a real set product`);
      check(await page.locator('.tp-evening-set-card').getAttribute('data-variant') === 'omakase', `${mode.name} Omakase set matches the active server variant`);
      await page.evaluate(() => window.TokyoCart.clear());
      await page.locator('[data-tp-evening-add-plan]').click();
      const savedOmakase = await page.evaluate(() => ({ sets: window.TokyoCart.getCustomSets(), total: window.TokyoCart.total() }));
      check(savedOmakase.sets.length === 1 && savedOmakase.sets[0].setType === 'omakase', `${mode.name} full recommendation is transferred as a custom Omakase set`);
      check(savedOmakase.sets[0].items.length >= 5 && savedOmakase.total > 0, `${mode.name} set includes distinct catalog dishes and a computed estimate`);
      const omakaseHref = await page.locator('[data-tp-evening-booking]').getAttribute('href');
      check(omakaseHref.includes('format='), `${mode.name} selected Omakase format is carried to booking`);
      await page.locator('[data-tp-evening-booking]').click();
      await page.waitForFunction(() => document.querySelector('#booking-format')?.value);
      check(['Начало','Сезонный','Премиальный'].includes(await page.locator('#booking-format').inputValue()), `${mode.name} booking selects one of the configured Omakase formats`);

      await page.goto(mode.base + mode.routes.booking, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-tp-table]', { timeout: 8000 });
      const bookingEmail = page.locator('#full-guest-email');
      check(await bookingEmail.getAttribute('type') === 'email' && await bookingEmail.getAttribute('required') !== null, mode.name + ' booking requires a valid guest response email');
      check(!(await page.locator('.booking-form .table-selection').innerText()).includes('Параметры изменились'), `${mode.name} booking form has no stale table warning on initial load`);
      await page.locator('[data-tp-table]').first().click();
      check(await page.locator('[name="table"]').inputValue() === '1', `${mode.name} floorplan stores a preferred table in the booking form`);
      check((await page.locator('[data-tp-floorplan-selection]').innerText()).includes('Предпочтение сохранено'), `${mode.name} floorplan states that a seat is only a preference`);
      check((await page.locator('.booking-form .table-selection').innerText()).includes('предпочтительный стол №1'), `${mode.name} selected table is reflected in the booking form`);
      const bookingDate = page.locator('.booking-form [name="date"]');
      await bookingDate.fill(await bookingDate.evaluate(input => { const day = new Date(input.min+'T00:00:00Z'); day.setUTCDate(day.getUTCDate()+1); return day.toISOString().slice(0,10); }));
      check(await page.locator('.booking-form [name="table"]').inputValue() === '', `${mode.name} changing visit date clears the preferred table`);
      check((await page.locator('.booking-form .table-selection').innerText()).includes('Параметры изменились'), `${mode.name} date change explains that table preference must be selected again`);
      await page.locator('[data-tp-table]').first().click();
      check((await page.locator('.booking-form .table-selection').innerText()).includes('предпочтительный стол №1'), `${mode.name} table selection clears the stale date-change warning`);
      for (const fieldName of ['time', 'guests', 'zone']) {
        await page.locator('[data-tp-table]').first().click();
        const field = page.locator(`.booking-form [name="${fieldName}"]`);
        const currentValue = await field.inputValue();
        const nextValue = await field.locator('option').evaluateAll((options, selected) => options.map(option => option.value).find(value => value !== selected), currentValue);
        await field.selectOption(nextValue);
        check(await page.locator('.booking-form [name="table"]').inputValue() === '', `${mode.name} changing ${fieldName} clears the preferred table`);
      }
      await page.locator('[data-tp-table]').first().click();
      check((await page.locator('.booking-form .table-selection').innerText()).includes('предпочтительный стол №1'), `${mode.name} table preference can be reselected after visit parameter changes`);
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-booking-table-selected.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      const mobileBookingOrder = await page.evaluate(() => {
        const form = document.querySelector('#full-booking-form');
        const map = document.querySelector('.table-map-wrap');
        const caption = map?.querySelector('[data-tp-seat-caption]');
        const submit = map?.querySelector('button[form="full-booking-form"][type="submit"]');
        if (!form || !map || !caption || !submit) return null;
        const formRect = form.getBoundingClientRect();
        const mapRect = map.getBoundingClientRect();
        const captionRect = caption.getBoundingClientRect();
        const submitRect = submit.getBoundingClientRect();
        return { formBeforeMap: formRect.bottom <= mapRect.top, submitAfterMapCopy: submitRect.top >= captionRect.bottom, submitVisible: getComputedStyle(submit).display !== 'none' && submitRect.width > 0 && submitRect.height > 0 };
      });
      check(Boolean(mobileBookingOrder?.formBeforeMap && mobileBookingOrder.submitAfterMapCopy && mobileBookingOrder.submitVisible), `${mode.name} mobile booking puts the preference map before its final submit action`);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-booking-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });
      if (mode.name === 'static') {
        await page.locator('#full-guest-name').fill('Гость QA');
        await page.locator('#full-guest-phone').fill('+77010000000');
        await bookingEmail.fill('guest@example.test');
        await page.locator('#full-booking-form').evaluate(form => form.requestSubmit());
        await page.waitForFunction(() => document.querySelector('.booking-form > .form-status')?.textContent.includes('Email не отправляется'));
        const savedBooking = await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-bookings') || '[]')[0]);
        check(savedBooking && !Object.hasOwn(savedBooking, 'email'), 'static Pages booking confirms no email is sent and does not retain the guest email locally');
        check(savedBooking?.demo === true, 'static booking submitted through the form is tagged as a local demo record');
      }

      await page.goto(mode.base + mode.routes.gift, { waitUntil: 'domcontentloaded' });
      await page.locator('[name="recipient_name"]').fill('Ая QA');
      await page.locator('[name="greeting"]').fill('Тестовый эскиз, без отправки и оплаты.');
      await page.locator('[data-tp-gift-form] button[type="submit"]').click();
      await page.waitForFunction(() => /preview_only|localStorage|не является сертификатом/i.test(document.querySelector('[data-tp-gift-status]')?.textContent || ''), null, { timeout: 10000 });
      check((await page.locator('[data-tp-gift-recipient]').innerText()).includes('Ая QA'), `${mode.name} certificate preview shows entered recipient safely`);
      check(await page.locator('[data-tp-gift-print]').isEnabled(), `${mode.name} print becomes available after saving a preview`);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-gift-preview.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-gift-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });

      await page.goto(mode.base + mode.routes.menu, { waitUntil: 'domcontentloaded' });
      const quickView = page.locator('[data-tp-quick-view]').first();
      check(await quickView.count() === 1, `${mode.name} menu exposes quick view`);
      await quickView.click();
      check(await page.locator('#tp-quick-view-dialog').evaluate(dialog => dialog.open), `${mode.name} quick view opens as a dialog`);
      await page.keyboard.press('Escape');
      check(!(await page.locator('#tp-quick-view-dialog').evaluate(dialog => dialog.open)), `${mode.name} quick view closes with Escape`);

      if (mode.name === 'static') {
        await page.goto(mode.base + mode.routes.account, { waitUntil: 'domcontentloaded' });
        check(await page.locator('[data-tp-demo-loyalty]').count() === 1, 'static profile discloses browser-only loyalty demo');
        check(/^TP-DEMO-[A-Z0-9]{6}$/.test(await page.locator('[data-tp-demo-referral]').innerText()), 'static referral code is browser-generated demo data');
        check(await page.locator('#demo-orders .account-demo-record-badge').count() === 4, 'static account keeps the three first-visit examples and the submitted order clearly labeled');
        check(await page.locator('#demo-orders .demo-order').evaluateAll(rows => rows.some(row => row.textContent.includes('Демо заказа QA'))), 'static account history includes the order submitted through checkout');
        check(await page.locator('#demo-bookings .account-demo-record-badge').count() === 2, 'static account keeps the first-visit booking example and the submitted booking clearly labeled');
        await page.screenshot({ path: path.join(evidence, '12-static-account-privilege-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 72 });
      }

      for (const routeName of ['offers', 'collection', 'builder', 'evening', 'gift', 'omakase', 'booking', 'menu', 'cart', 'account']) {
        await page.goto(mode.base + mode.routes[routeName], { waitUntil: 'domcontentloaded' });
        for (const width of widths) {
          await page.setViewportSize({ width, height: 900 });
          check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${mode.name} ${routeName} has no horizontal overflow at ${width}px`);
        }
      }

      await page.goto(mode.base + mode.routes.cart, { waitUntil: 'domcontentloaded' });
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-cart-custom-set-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-cart-custom-set-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await context.close();
    }
    check(failures.length === 0, failures.slice(0, 12).join('\n'));
    console.log(`PASS: Tokyo 2.0 interactive regression (${selectedModes.map(mode => mode.name).join(' + ')}): product detail routes, offers, Omakase, set builder, preference wizard, floorplan, gift preview, quick view, profile, mobile overflow. Evidence: ${evidence}`);
  } finally { await browser.close(); }
}

if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });

