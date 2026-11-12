const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const evidence = path.join(root, 'project-docs', 'visual-qa');
const routes = [
  ['01-home', '/index.php'], ['02-menu', '/menu.php'], ['03-dish', '/pages.php?page=dish&id=3'],
  ['04-omakase', '/pages.php?page=omakase'], ['05-philosophy', '/about.php'],
  ['06-space', '/pages.php?page=gallery'], ['07-booking', '/pages.php?page=booking'],
  ['08-reviews', '/reviews.php'], ['09-contacts', '/pages.php?page=contacts'],
  ['10-cart', '/pages.php?page=cart'], ['11-account', '/account.php']
];
const widths = [320, 390, 768, 1024, 1440, 1920];
const failures = [];
const check = (condition, label) => { if (!condition) throw new Error(label); };
const isRequired = locator => locator.evaluate(element => element.required);
async function prepareVisual(page) {
  if (!page.__visualStyleAdded) {
    await page.addStyleTag({ content: '.reveal,.reveal.visible{opacity:1!important;transform:none!important}' });
    page.__visualStyleAdded = true;
  }
  await page.evaluate(async () => {
    const images = [...document.images];
    images.forEach(image => { image.loading = 'eager'; });
    await Promise.all(images.map(image => image.decode().catch(() => null)));
  });
  await page.evaluate(() => { document.documentElement.scrollTop = 0; document.body.scrollTop = 0; });
  await page.waitForFunction(() => window.scrollY === 0);
}

async function run(options = {}) {
  const { chromium } = options.chromium ? { chromium: options.chromium } : require('playwright');
  const base = options.base || process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8090';
  fs.mkdirSync(evidence, { recursive: true });
  const progress = stage => fs.appendFileSync(path.join(evidence, 'run.log'), `${new Date().toISOString()} ${stage}\n`);
  fs.writeFileSync(path.join(evidence, 'run.log'), '');
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    page.setDefaultTimeout(7000);
    page.on('pageerror', error => failures.push(`pageerror ${page.url()}: ${error.message}`));
    page.on('console', message => { if (message.type() === 'error') failures.push(`console ${page.url()}: ${message.text()}`); });
    page.on('requestfailed', request => {
      const error = request.failure()?.errorText || '';
      if (!/fonts\.googleapis|fonts\.gstatic/.test(request.url()) && error !== 'net::ERR_ABORTED') failures.push(`request ${request.url()}: ${error}`);
    });

    for (const [name, route] of routes) {
      progress(`route ${name} start`);
      console.log(`Visual QA: ${name}`);
      const response = await page.goto(base + route, { waitUntil: 'domcontentloaded', timeout: 15000 });
      check(response && response.status() === 200, `${name} HTTP response`);
      check(await page.locator('h1').count() > 0, `${name} has a page heading`);
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `${name}-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 70 });
      progress(`route ${name} screenshot done`);
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `${name}-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 70 });
      await page.setViewportSize({ width: 1440, height: 1000 });
      if (name === '03-dish') {
        const dishCount = await page.evaluate(() => window.DISHES?.length || 0);
        check(dishCount === 20, `catalog has 20 dishes (got ${dishCount})`);
        for (let id = 1; id <= 20; id++) {
          const detail = await page.goto(`${base}/pages.php?page=dish&id=${id}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
          check(detail?.status() === 200, `dish ${id} responds`);
          check(await page.locator('.detail-actions .add-to-cart').count() === 1, `dish ${id} add button`);
          await page.locator('#dish-main-image').evaluate(img => img.decode());
          check(await page.locator('#dish-main-image').evaluate(img => img.naturalWidth > 0), `dish ${id} image loads`);
        }
      }
      if (name === '01-home') {
        const featuredImages = await page.locator('.home-dish-card img').evaluateAll(images => images.map(image => image.currentSrc || image.src));
        check(featuredImages.length === 4 && new Set(featuredImages).size === 4, 'home featured dishes use four distinct food photographs');
      }
    }

    // Register a synthetic account so checkout can be verified in server-backed history.
    await page.goto(base + '/account.php');
    progress('registration start');
    const profileEmail = `qa-${Date.now()}@example.test`;
    await page.locator('form').nth(1).locator('[name="name"]').fill('Гость QA');
    await page.locator('form').nth(1).locator('[name="email"]').fill(profileEmail);
    await page.locator('form').nth(1).locator('[name="password"]').fill('Strong-test-password-123');
    await page.getByText('Регистрация', { exact: true }).click();
    await page.waitForFunction(() => document.body.innerText.includes('Здравствуйте, Гость QA'), null, { timeout: 20000 }).catch(async () => {
      throw new Error(`Synthetic account registration failed: ${(await page.locator('body').innerText()).slice(0, 500)}`);
    });
    progress('registration done');
    await page.screenshot({ path: path.join(evidence, '11-account-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 70 });

    // State survives routes and refresh, and header count does not depend on a drawer.
    await page.goto(base + '/');
    progress('cart flow start');
    await page.evaluate(() => localStorage.clear());
    await page.reload();
    await page.locator('.home-dish-card .add-to-cart, .dish-card .add-to-cart').first().click();
    await page.waitForFunction(() => Number(document.querySelector('.header-cart-count')?.textContent) === 1);
    progress('cart add done');
    await page.goto(base + '/about.php');
    check((await page.locator('.header-cart-count').innerText()).trim() === '1', 'badge on page without server-rendered drawer route');
    await page.reload();
    check((await page.locator('.header-cart-count').innerText()).trim() === '1', 'cart survives reload');

    // Multi-tab storage synchronization.
    const second = await context.newPage();
    await second.setViewportSize({ width: 1280, height: 900 });
    await second.goto(base + '/menu.php');
    await second.locator('.dish-card .add-to-cart').nth(1).click();
    await page.waitForFunction(() => Number(document.querySelector('.header-cart-count')?.textContent) === 2);
    await second.close();

    // Quantity, removal, clear, add-after-clear, fulfillment and demo order.
    await page.goto(base + '/pages.php?page=cart');
    progress('cart page opened');
    check(await page.locator('#cart-page-items .cart-page-item').count() === 2, 'two different dishes in cart');
    const before = await page.locator('#cart-page-total').innerText();
    await page.locator('[data-cart-step="1"]').first().click();
    check(await page.locator('#cart-page-items .cart-page-item').first().locator('.cart-page-controls span').innerText() === '2', 'increase quantity');
    check((await page.locator('#cart-page-total').innerText()) !== before, 'subtotal recalculates');
    await page.locator('[data-cart-remove]').first().click();
    check(await page.locator('#cart-page-items .cart-page-item').count() === 1, 'remove one line');
    await page.locator('#cart-clear').click();
    check(await page.locator('#cart-page-empty').isVisible(), 'clear empties cart');
    await page.locator('.cart-recommendations .add-to-cart').first().click();
    check(await page.locator('#cart-page-items .cart-page-item').count() === 1, 'add after clear does not restore old lines');
    await page.locator('#fulfillment').selectOption('pickup');
    check(await page.locator('#checkout-address').isDisabled(), 'pickup disables address');
    await page.locator('#checkout-name').fill('Гость QA');
    await page.locator('#checkout-phone').fill('+77010000000');
    await page.locator('#checkout-form button[type="submit"]').click();
    await page.waitForFunction(() => document.querySelector('#checkout-form .form-status')?.classList.contains('success'));
    check(await page.locator('#cart-page-empty').isVisible(), 'successful checkout clears canonical cart');
    check(await page.locator('#fulfillment').inputValue() === 'delivery', 'successful pickup checkout resets fulfillment to delivery');
    check(await page.locator('#checkout-address').isVisible(), 'address is visible after pickup checkout reset');
    check(await page.locator('#checkout-address').isEnabled() && await isRequired(page.locator('#checkout-address')), 'address is enabled and required after pickup checkout reset');

    // Scenario A: the next delivery cannot submit until a new address is provided.
    await page.locator('.cart-recommendations .add-to-cart').nth(1).click();
    await page.locator('#checkout-name').fill('Гость QA повтор');
    await page.locator('#checkout-phone').fill('+77010000002');
    await page.locator('#checkout-form button[type="submit"]').click();
    check(!(await page.locator('#checkout-form').evaluate(form => form.checkValidity())), 'delivery after pickup requires an address');
    check(await page.locator('#cart-page-items .cart-page-item').count() === 1, 'invalid delivery leaves cart unchanged');
    await page.locator('#checkout-address').fill('Демо-адрес, дом 2');
    await page.locator('#checkout-form button[type="submit"]').click();
    await page.waitForFunction(() => document.querySelector('#cart-page-empty')?.hidden === false && document.querySelector('#checkout-form .form-status')?.classList.contains('success'));
    check(await page.locator('#cart-page-empty').isVisible(), 'second delivery order clears cart');

    // Scenario B: pickup hides/disables/clears address; switching back restores delivery validation.
    await page.locator('.cart-recommendations .add-to-cart').nth(2).click();
    await page.locator('#checkout-address').fill('Адрес, который очистится при самовывозе');
    await page.locator('#fulfillment').selectOption('pickup');
    check(await page.locator('#checkout-address-field').isHidden() && await page.locator('#checkout-address').isDisabled(), 'pickup hides and disables address');
    check(!(await isRequired(page.locator('#checkout-address'))) && await page.locator('#checkout-address').inputValue() === '', 'pickup clears address and removes required state');
    await page.locator('#fulfillment').selectOption('delivery');
    check(await page.locator('#checkout-address-field').isVisible() && await isRequired(page.locator('#checkout-address')), 'switching back to delivery restores required address');

    // Scenario C: the cart persists through navigation and reload, while form defaults stay consistent.
    await page.goto(base + '/about.php');
    await page.goto(base + '/pages.php?page=cart');
    check(await page.locator('#cart-page-items .cart-page-item').count() === 1, 'cart survives route change before repeat checkout');
    await page.reload();
    check(await page.locator('#fulfillment').inputValue() === 'delivery' && await page.locator('#checkout-address').isVisible() && await isRequired(page.locator('#checkout-address')), 'delivery and address validation are consistent after reload');
    await page.locator('#cart-clear').click();
    await page.goto(base + '/account.php');
    check(await page.locator('body').innerText().then(text => text.includes('Гость QA') && text.includes('Состав заказа')), 'order shown in account history');

    // Favorites are event-delegated even after the profile cards are rendered.
    await page.goto(base + '/menu.php');
    await page.locator('[data-favorite]').first().click();
    await page.goto(base + '/docs/account.html');
    await page.locator('[data-account-tab="favorites"]').click();
    check(await page.locator('#demo-favorites .add-to-cart').count() > 0, 'favorites are rendered');
    await page.locator('#demo-favorites .add-to-cart').first().click();
    check((await page.locator('.header-cart-count').innerText()).trim() === '1', 'add from dynamic favorites updates cart');

    // The static export keeps all order data local and supports repeat-order from history.
    await page.goto(base + '/docs/cart.html');
    await page.locator('.cart-recommendations .add-to-cart').first().click();
    await page.locator('#fulfillment').selectOption('pickup');
    await page.locator('#checkout-name').fill('Демо-гость');
    await page.locator('#checkout-phone').fill('+77010000000');
    await page.locator('#checkout-form button[type="submit"]').click();
    await page.waitForFunction(() => document.querySelector('#checkout-form .form-status')?.classList.contains('success'));
    check(await page.locator('#cart-page-empty').isVisible(), 'static pickup checkout clears the cart');
    check(await page.locator('#fulfillment').inputValue() === 'delivery' && await page.locator('#checkout-address').isVisible(), 'static pickup reset restores visible delivery address');
    check(await page.locator('#checkout-address').isEnabled() && await isRequired(page.locator('#checkout-address')), 'static pickup reset restores required delivery address');

    // Repeat the same delivery validation and recovery flow in the static Pages demo.
    await page.locator('.cart-recommendations .add-to-cart').nth(1).click();
    await page.locator('#checkout-name').fill('Демо-гость повтор');
    await page.locator('#checkout-phone').fill('+77010000002');
    await page.locator('#checkout-form button[type="submit"]').click();
    check(!(await page.locator('#checkout-form').evaluate(form => form.checkValidity())), 'static delivery after pickup requires an address');
    check(await page.locator('#cart-page-items .cart-page-item').count() === 1, 'invalid static delivery keeps cart intact');
    await page.locator('#checkout-address').fill('Учебный адрес 2');
    await page.locator('#checkout-form button[type="submit"]').click();
    await page.waitForFunction(() => document.querySelector('#cart-page-empty')?.hidden === false && document.querySelector('#checkout-form .form-status')?.classList.contains('success'));
    check(await page.locator('#cart-page-empty').isVisible(), 'static repeat delivery clears cart');

    // Static pickup/delivery toggles and route/reload consistency.
    await page.locator('.cart-recommendations .add-to-cart').nth(2).click();
    await page.locator('#checkout-address').fill('Будет очищен');
    await page.locator('#fulfillment').selectOption('pickup');
    check(await page.locator('#checkout-address-field').isHidden() && await page.locator('#checkout-address').isDisabled(), 'static pickup hides and disables address');
    check(!(await isRequired(page.locator('#checkout-address'))) && await page.locator('#checkout-address').inputValue() === '', 'static pickup clears address and required state');
    await page.locator('#fulfillment').selectOption('delivery');
    check(await page.locator('#checkout-address-field').isVisible() && await isRequired(page.locator('#checkout-address')), 'static delivery restores address validation');
    await page.goto(base + '/docs/menu.html');
    await page.goto(base + '/docs/cart.html');
    check(await page.locator('#cart-page-items .cart-page-item').count() === 1, 'static cart survives route change');
    await page.reload();
    check(await page.locator('#fulfillment').inputValue() === 'delivery' && await page.locator('#checkout-address').isVisible() && await isRequired(page.locator('#checkout-address')), 'static delivery state is consistent after reload');
    await page.locator('#cart-clear').click();

    await page.goto(base + '/docs/account.html');
    await page.locator('[data-account-tab="history"]').click();
    check((await page.locator('#demo-orders').innerText()).includes('Составлен заказ снова') || await page.locator('#demo-orders .demo-order-items li').count() > 0, 'static profile shows order line snapshot');
    await prepareVisual(page);
    await page.screenshot({ path: path.join(evidence, '11-account-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 70 });
    await page.setViewportSize({ width: 390, height: 844 });
    await prepareVisual(page);
    await page.screenshot({ path: path.join(evidence, '11-account-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 70 });

    // Menu default sorting returns the source order; search and filter work together.
    await page.goto(base + '/menu.php');
    const sourceOrder = await page.locator('.dish-grid .dish-card h3').allTextContents();
    const sort = page.locator('#sort-menu');
    await sort.selectOption('price-asc');
    await sort.selectOption('default');
    const restored = await page.locator('.dish-grid .dish-card h3').allTextContents();
    check(restored.join('|') === sourceOrder.join('|'), 'default category sort restores original order');
    check(await page.locator('.dish-card:visible').count() === 6, 'menu paginates the catalog to six visible dishes');
    await page.locator('#menu-pagination button[aria-label="Страница 2"]').click();
    check(await page.locator('.dish-card:visible').count() === 6, 'menu pagination advances while preserving page size');
    check(await page.locator('#menu-pagination [aria-current="page"]').innerText() === '2', 'menu pagination exposes the active page accessibly');
    await page.locator('#menu-pagination button[aria-label="Страница 4"]').click();
    check(await page.locator('.dish-card:visible').count() === 2, 'last menu page shows the remaining catalog items');
    await page.locator('.menu-search').fill('нигири');
    check(await page.locator('.dish-card:visible').count() > 0, 'menu search matches dish names');
    check(await page.locator('.dish-card:visible').evaluateAll(cards => cards.every(card => (card.dataset.search || card.textContent).toLocaleLowerCase('ru').includes('нигири'))), 'menu search removes non-matching dishes, including cards left from the previous page');
    const matchingCategory = await page.locator('.dish-card:visible').first().getAttribute('data-category');
    await page.locator(`.menu-category-list [data-category="${matchingCategory}"]`).click();
    check(await page.locator('.dish-card:visible').evaluateAll((cards, category) => cards.every(card => card.dataset.category === category && card.textContent.toLocaleLowerCase('ru').includes('нигири')), matchingCategory), 'category and search filters combine');
    await page.locator('.menu-search').fill('');
    await page.locator('.menu-category-list [data-category="all"]').click();
    await page.goto(base + '/docs/menu.html');
    check(await page.locator('.dish-card:visible').count() === 6, 'static menu paginates catalog');
    await page.locator('#menu-pagination button[aria-label="Страница 2"]').click();
    check(await page.locator('.dish-card:visible').count() === 6, 'static menu pagination changes page');
    await page.locator('.menu-search').fill('матча');
    check(await page.locator('.dish-card:visible').count() > 0, 'static menu search matches demo catalog');
    check(await page.locator('.dish-card:visible').evaluateAll(cards => cards.every(card => (card.dataset.search || card.textContent).toLocaleLowerCase('ru').includes('матча'))), 'static menu search hides stale cards from the previous page');
    await page.locator('.menu-search').fill('');

    // Gallery filters only the gallery, and mobile menu works with Escape/focus return.
    await page.goto(base + '/pages.php?page=gallery');
    const zoneCount = await page.locator('.zone-card:visible').count();
    await page.locator('[data-gallery="details"]').click();
    check(await page.locator('.zone-card:visible').count() === zoneCount, 'gallery filter preserves room cards');
    await page.setViewportSize({ width: 390, height: 844 });
    await prepareVisual(page);
    await page.screenshot({ path: path.join(evidence, '06-space-filter-details-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 70 });
    await page.locator('[data-gallery="all"]').click();
    await prepareVisual(page);
    await page.screenshot({ path: path.join(evidence, '06-space-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 70 });
    await page.locator('.gallery-tile:visible').first().click();
    check(await page.locator('.image-lightbox').evaluate(dialog => dialog.open), 'gallery lightbox opens from a visible image');
    await page.keyboard.press('Escape');
    check(!(await page.locator('.image-lightbox').evaluate(dialog => dialog.open)), 'gallery lightbox closes with Escape');
    const menuButton = page.locator('.mobile-menu');
    await menuButton.focus(); await page.keyboard.press('Enter');
    check(await menuButton.getAttribute('aria-expanded') === 'true', 'mobile navigation opens by keyboard');
    await page.keyboard.press('Escape');
    check(await menuButton.getAttribute('aria-expanded') === 'false', 'Escape closes navigation');
    await page.goto(base + '/pages.php?page=booking');
    await page.locator('[data-table-select]').first().click();
    const tomorrow = await page.evaluate(() => { const d=new Date();d.setDate(d.getDate()+1);return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; });
    await page.locator('.booking-form [name="date"]').fill(tomorrow);
    check(await page.locator('[name="table"]').inputValue() === '' && await page.locator('[data-table-select="1"]').getAttribute('aria-pressed') === 'false', 'booking selection resets when visit date changes');
    await page.goto(base + '/pages.php?page=omakase');
    await page.locator('.tier-card-featured a').click();
    check(await page.locator('#booking-format').inputValue() === 'Сезонный', 'PHP booking keeps the selected Omakase set');
    await page.goto(base + '/docs/omakase.html');
    await page.locator('.tier-card-featured a').click();
    check(await page.locator('#booking-format').inputValue() === 'Сезонный', 'static booking keeps the selected Omakase set');
    for (const [name, route] of routes) {
      // Preserve the deliberate gallery-filter and paired static-account evidence above.
      if (name === '06-space' || name === '11-account') continue;
      await page.goto(base + route, { waitUntil: 'domcontentloaded' });
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `${name}-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 70 });
    }

    // Every page is checked at all six target widths.
    for (const [name, route] of routes) {
      for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(base + route, { waitUntil: 'domcontentloaded' });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
        check(!overflow, `${name} has no horizontal overflow at ${width}px`);
      }
    }
    // Final paired screenshots use the same static account route and browser-local sample history.
    await page.goto(base + '/docs/account.html', { waitUntil: 'domcontentloaded' });
    await page.locator('[data-account-tab="history"]').click();
    check(await page.locator('.account-demo-page').count() === 1, 'paired account evidence comes from the static demo');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await prepareVisual(page);
    await page.screenshot({ path: path.join(evidence, '11-account-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 70 });
    await page.setViewportSize({ width: 390, height: 844 });
    await prepareVisual(page);
    await page.screenshot({ path: path.join(evidence, '11-account-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 70 });
    check(failures.length === 0, failures.slice(0, 12).join('\n'));
    progress('browser regression pass');
    console.log(`PASS: browser regression; 11 routes, 20 details, cart/checkout, tabs, favorites, navigation, filters, 6 widths. Screenshots: ${evidence}`);
  } finally { await browser.close(); }
}

module.exports = run;
if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
