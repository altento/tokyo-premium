const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const evidence = path.join(root, 'project-docs', 'visual-qa');
const routes = [
  ['01-home', '/'], ['02-menu', '/menu.php'], ['03-dish', '/pages.php?page=dish&id=3'],
  ['04-omakase', '/pages.php?page=omakase'], ['05-philosophy', '/about.php'],
  ['06-space', '/pages.php?page=gallery'], ['07-booking', '/pages.php?page=booking'],
  ['08-reviews', '/reviews.php'], ['09-contacts', '/pages.php?page=contacts'],
  ['10-cart', '/pages.php?page=cart'], ['11-account', '/account.php']
];
const widths = [320, 390, 768, 1024, 1440, 1920];
const failures = [];
const check = (condition, label) => { if (!condition) throw new Error(label); };
async function prepareVisual(page) {
  await page.addStyleTag({ content: '.reveal,.reveal.visible{opacity:1!important;transform:none!important}' });
  await page.evaluate(async () => {
    const images = [...document.images];
    images.forEach(image => { image.loading = 'eager'; });
    await Promise.all(images.map(image => image.decode().catch(() => null)));
  });
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
    await page.locator('#fulfillment').selectOption('pickup');
    await page.locator('#checkout-name').fill('Демо-гость');
    await page.locator('#checkout-phone').fill('+77010000000');
    await page.locator('#checkout-form button[type="submit"]').click();
    await page.waitForFunction(() => document.querySelector('#checkout-form .form-status')?.classList.contains('success'));
    check(await page.locator('#cart-page-empty').isVisible(), 'static checkout clears the cart');
    await page.goto(base + '/docs/account.html');
    await page.locator('[data-account-tab="history"]').click();
    check((await page.locator('#demo-orders').innerText()).includes('Составлен заказ снова') || await page.locator('#demo-orders .demo-order-items li').count() > 0, 'static profile shows order line snapshot');
    await prepareVisual(page);
    await page.screenshot({ path: path.join(evidence, '11-account-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 70 });

    // Menu default sorting returns the source order; search and filter work together.
    await page.goto(base + '/menu.php');
    const sourceOrder = await page.locator('.dish-grid .dish-card h3').allTextContents();
    const sort = page.locator('#sort-menu');
    await sort.selectOption('price-asc');
    await sort.selectOption('default');
    const restored = await page.locator('.dish-grid .dish-card h3').allTextContents();
    check(restored.join('|') === sourceOrder.join('|'), 'default category sort restores original order');

    // Gallery filters only the gallery, and mobile menu works with Escape/focus return.
    await page.goto(base + '/pages.php?page=gallery');
    const zoneCount = await page.locator('.zone-card:visible').count();
    await page.locator('[data-gallery="details"]').click();
    check(await page.locator('.zone-card:visible').count() === zoneCount, 'gallery filter preserves room cards');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: path.join(evidence, '06-space-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 70 });
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
    for (const [name, route] of routes) {
      if (name === '06-space') continue;
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
    check(failures.length === 0, failures.slice(0, 12).join('\n'));
    progress('browser regression pass');
    console.log(`PASS: browser regression; 11 routes, 20 details, cart/checkout, tabs, favorites, navigation, filters, 6 widths. Screenshots: ${evidence}`);
  } finally { await browser.close(); }
}

module.exports = run;
if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
