const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const evidence = path.resolve(process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '..', 'project-docs', 'visual-qa'));

async function screenshot(page, name) {
  const expectedWidth = /-desktop\.png$/.test(name) ? 1440 : 390;
  assert.equal(page.viewportSize()?.width, expectedWidth, `${name} is captured at the named viewport width`);
  assert.equal(await page.evaluate(() => innerWidth), expectedWidth, `${name} browser viewport matches its filename`);
  if (!page.__visualStyleAdded) {
    await page.addStyleTag({ content: 'html{scroll-behavior:auto!important}.site-header{position:relative!important;top:auto!important}.reveal,.reveal.visible{opacity:1!important;transform:none!important}' });
    page.__visualStyleAdded = true;
  }
  await page.evaluate(async () => Promise.all([...document.images].map(img => { img.loading = 'eager'; return img.decode().catch(() => null); })));
  await page.evaluate(() => { document.documentElement.scrollTop = 0; document.body.scrollTop = 0; });
  await page.waitForFunction(() => window.scrollY === 0);
  const header = await page.locator('.site-header').evaluate(element => ({
    position: getComputedStyle(element).position,
    top: element.getBoundingClientRect().top
  }));
  assert.equal(header.position, 'relative', `${name} full-page capture keeps the header in document flow`);
  assert.equal(header.top, 0, `${name} full-page capture starts at the top of the page`);
  await page.screenshot({ path: path.join(evidence, name), fullPage: true });
}

async function run(options = {}) {
  const { chromium } = options.chromium ? { chromium: options.chromium } : require('playwright');
  const base = options.base || process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8090';
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    page.setDefaultTimeout(7000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));

    await page.goto(`${base}/docs/menu.html`, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => { localStorage.clear(); window.TokyoCart?.clear(); });
    await page.reload();
    const add = page.locator('.dish-card .add-to-cart');
    assert.ok(await add.count() >= 3, 'menu exposes real product cards');
    await add.nth(0).click();
    await add.nth(1).click();
    await add.nth(2).click();
    await page.goto(`${base}/docs/cart.html`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => document.querySelectorAll('#cart-page-items .cart-page-item').length === 3);
    const seededItems = await page.evaluate(() => window.TokyoCart.snapshot().map(item => ({ id: Number(item.id), qty: Number(item.qty) })));
    assert.equal(new Set(seededItems.map(item => item.id)).size, 3, 'populated cart contains three different dishes');
    await page.locator('[data-cart-step="1"]').first().click();
    assert.equal(await page.evaluate(() => window.TokyoCart.snapshot()[0].qty), 2, 'one cart line has quantity two');
    assert.ok((await page.evaluate(() => window.TokyoCart.snapshot())).some(item => Number(item.qty) >= 2), 'populated cart includes a quantity of at least two');
    const computedTotal = await page.evaluate(() => window.TokyoCart.total());
    const displayedTotal = Number((await page.locator('#cart-page-total').innerText()).replace(/\D/g, ''));
    assert.equal(displayedTotal, computedTotal, 'visible total matches the shared cart store');
    assert.equal(await page.locator('.cart-recommendations .dish-card').count(), 3, 'real recommendations remain available');
    assert.equal(await page.locator('.cart-recommendations .add-to-cart').evaluateAll((buttons, ids) => buttons.every(button => !ids.includes(Number(button.dataset.id))), await page.evaluate(() => window.TokyoCart.get().map(item => Number(item.id)))), true, 'recommendations exclude dishes already in the cart');
    await screenshot(page, 'cart-populated-desktop.png');
    assert.equal(await page.locator('#cart-page-items .cart-page-item img').evaluateAll(images => images.length === 3 && images.every(image => image.complete && image.naturalWidth > 0)), true, 'each cart line has a loaded food photograph');
    assert.equal(await page.locator('.cart-recommendations .dish-card img').evaluateAll(images => images.length === 3 && images.every(image => image.complete && image.naturalWidth > 0)), true, 'each recommendation has a loaded food photograph');
    assert.equal(await page.locator('.cart-recommendations .dish-card').first().isVisible(), true, 'recommendations are visible in populated screenshot');
    await page.setViewportSize({ width: 390, height: 844 });
    await screenshot(page, 'cart-populated-mobile.png');

    await page.locator('#checkout-name').fill('Демо-гость');
    await page.locator('#checkout-phone').fill('+77010000000');
    await page.locator('#checkout-address').fill('ул. Сакура, 12');
    await page.locator('#fulfillment').selectOption('pickup');
    assert.equal(await page.locator('#checkout-address').isDisabled(), true);
    assert.equal(await page.locator('#checkout-address-field').isHidden(), true);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await screenshot(page, 'cart-pickup-desktop.png');
    await page.locator('#fulfillment').selectOption('delivery');
    assert.equal(await page.locator('#checkout-address').isEnabled(), true);
    assert.equal(await page.locator('#checkout-address').isVisible(), true);
    await page.locator('#checkout-address').fill('');
    await page.locator('#checkout-form button[type="submit"]').click();
    assert.equal(await page.locator('#checkout-address').evaluate(input => input.validity.valueMissing), true, 'delivery without address is blocked');
    await screenshot(page, 'cart-checkout-validation-desktop.png');
    await page.setViewportSize({ width: 390, height: 844 });
    await screenshot(page, 'cart-checkout-validation-mobile.png');
    await page.locator('#checkout-address').fill('ул. Сакура, 12');
    await page.setViewportSize({ width: 1440, height: 1000 });
    assert.equal(await page.evaluate(() => innerWidth), 1440, 'delivery evidence uses the desktop viewport');
    assert.equal(await page.locator('#fulfillment').inputValue(), 'delivery', 'delivery evidence shows the selected delivery method');
    assert.equal(await page.locator('#checkout-address').inputValue(), 'ул. Сакура, 12', 'delivery evidence shows the required address');
    await screenshot(page, 'cart-delivery-desktop.png');

    await page.locator('#cart-clear').click();
    assert.equal(await page.locator('#cart-page-empty').isVisible(), true);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await screenshot(page, 'cart-empty-desktop.png');
    await page.setViewportSize({ width: 390, height: 844 });
    await screenshot(page, 'cart-empty-mobile.png');
    assert.equal(errors.length, 0, errors.join('\n'));
    console.log('PASS: empty/populated/validation/pickup/delivery cart visual states saved as 8 full-page PNG screenshots.');
  } finally { await browser.close(); }
}

module.exports = run;
if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
