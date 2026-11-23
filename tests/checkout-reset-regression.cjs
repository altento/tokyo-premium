const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..', 'docs');
const contentTypes = {
  '.css': 'text/css; charset=utf-8', '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8', '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml', '.webp': 'image/webp', '.jpg': 'image/jpeg', '.png': 'image/png',
  '.woff2': 'font/woff2', '.ico': 'image/x-icon',
};

function startStaticServer() {
  const server = http.createServer((request, response) => {
    let pathname;
    try { pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname); }
    catch { response.writeHead(400).end(); return; }
    const candidate = path.resolve(root, `.${pathname === '/' ? '/index.html' : pathname}`);
    if (candidate !== root && !candidate.startsWith(root + path.sep)) {
      response.writeHead(404).end(); return;
    }
    fs.readFile(candidate, (error, data) => {
      if (error) { response.writeHead(404).end(); return; }
      response.writeHead(200, { 'Content-Type': contentTypes[path.extname(candidate)] || 'application/octet-stream', 'Cache-Control': 'no-store' });
      response.end(data);
    });
  });
  return new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => resolve({ server, base: `http://127.0.0.1:${server.address().port}` }));
  });
}

async function main() {
  const { server, base } = await startStaticServer();
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const response = await page.goto(`${base}/cart.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200);

    const addDish = async index => page.locator('.cart-recommendations .add-to-cart').nth(index).click();
    const submit = () => page.locator('#checkout-form button[type="submit"]').click();
    const success = () => page.waitForFunction(() => document.querySelector('#checkout-form .form-status')?.classList.contains('success'));
    const fillContact = async suffix => {
      await page.locator('#checkout-name').fill(`Tokyo QA ${suffix}`);
      await page.locator('#checkout-phone').fill(`+770100000${suffix}`);
    };
    const cartLines = () => page.locator('#cart-page-items .cart-page-item').count();

    await addDish(0);
    await page.locator('.cart-checkout-details > summary').click();
    await page.locator('#fulfillment').selectOption('pickup');
    assert.equal(await page.locator('#checkout-address').isDisabled(), true);
    await fillContact('01');
    await submit();
    await success();
    assert.equal(await page.locator('#cart-page-empty').isVisible(), true, 'pickup checkout clears the cart');
    assert.equal(await page.locator('#fulfillment').inputValue(), 'delivery', 'reset restores delivery');
    assert.equal(await page.locator('#checkout-address').isVisible(), true, 'delivery address is visible after reset');
    assert.equal(await page.locator('#checkout-address').isEnabled(), true, 'delivery address is enabled after reset');
    assert.equal(await page.locator('#checkout-address').evaluate(input => input.required), true, 'delivery address is required after reset');

    await addDish(1);
    await fillContact('02');
    await submit();
    assert.equal(await page.locator('#checkout-form').evaluate(form => form.checkValidity()), false, 'delivery without address is rejected');
    assert.equal(await cartLines(), 1, 'invalid delivery keeps the new cart line');
    await page.locator('#checkout-address').fill('Учебный адрес, дом 2');
    await submit();
    await success();
    assert.equal(await page.locator('#cart-page-empty').isVisible(), true, 'repeat delivery clears the cart');

    await addDish(2);
    await page.locator('#checkout-address').fill('Адрес для проверки переключения');
    await page.locator('#fulfillment').selectOption('pickup');
    assert.equal(await page.locator('#checkout-address-field').isHidden(), true);
    assert.equal(await page.locator('#checkout-address').inputValue(), '', 'pickup clears stale address');
    assert.equal(await page.locator('#checkout-address').evaluate(input => input.required), false);
    await page.locator('#fulfillment').selectOption('delivery');
    assert.equal(await page.locator('#checkout-address-field').isVisible(), true);
    assert.equal(await page.locator('#checkout-address').evaluate(input => input.required), true);

    await page.goto(`${base}/menu.html`, { waitUntil: 'domcontentloaded' });
    await page.goto(`${base}/cart.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(await cartLines(), 1, 'cart survives route changes');
    await page.reload({ waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#fulfillment').inputValue(), 'delivery', 'reload starts with delivery');
    assert.equal(await page.locator('#checkout-address').evaluate(input => input.required), true, 'reload restores address validation');
    assert.deepEqual(errors, [], 'no browser JavaScript errors');
    console.log('PASS: pickup reset, address validation, repeat delivery, route changes and reload (static demo)');
  } finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
