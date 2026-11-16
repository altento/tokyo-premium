const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const base = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8091';
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(root, 'project-docs', 'visual-qa');

async function run() {
  const { chromium } = require('playwright');
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    const response = await page.goto(`${base}/account.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(response?.status(), 200, 'static account loads');
    await page.evaluate(() => localStorage.clear());
    await page.reload({ waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 0, 'new browser starts with an empty history');
    assert.equal(await page.locator('[data-account-demo-seed]').count(), 1, 'account offers an explicit sample-data action');

    await page.locator('[data-account-demo-seed]').click();
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 3, 'sample action adds three orders');
    assert.equal(await page.locator('#demo-bookings article').count(), 1, 'sample action adds one booking request');
    assert.equal(await page.locator('#demo-favorites .dish-card').count(), 3, 'sample favorites resolve to catalog dishes');
    assert.equal(await page.locator('#demo-orders .account-demo-record-badge').count(), 3, 'every sample order is explicitly labeled');
    assert.equal(await page.locator('#demo-bookings .account-demo-record-badge').count(), 1, 'sample booking is explicitly labeled');
    assert.ok(await page.locator('#demo-orders .demo-order-items li').count() >= 5, 'order history shows item snapshots');
    assert.ok(await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-orders') || '[]').every(order => order.subtotal === order.items.reduce((sum, item) => sum + item.lineTotal, 0))), 'sample totals agree with saved catalog item prices');
    assert.equal(await page.locator('[data-account-stat="orders"]').innerText(), '3', 'dashboard metrics update');
    assert.equal(await page.locator('[data-account-stat="bookings"]').innerText(), '1', 'booking metric updates');
    assert.match(await page.locator('[data-account-demo-seed-status]').innerText(), /только в этом браузере/i, 'status explains the local-only demo');

    await page.locator('[data-account-demo-seed]').click();
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 3, 'repeating the action does not duplicate orders');
    assert.equal(await page.locator('#demo-bookings article').count(), 1, 'repeating the action does not duplicate the booking');
    await page.screenshot({ path: path.join(evidence, '11-account-seeded-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 74 });
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'seeded account has no mobile horizontal overflow');
    await page.screenshot({ path: path.join(evidence, '11-account-seeded-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 74 });

    await page.locator('[data-account-tab="settings"]').click();
    await page.locator('#demo-clear-data').click();
    assert.equal(await page.locator('[data-account-stat="orders"]').innerText(), '0', 'clear-data action removes seeded orders');
    assert.equal(await page.locator('[data-account-stat="bookings"]').innerText(), '0', 'clear-data action removes seeded booking');
    assert.equal(await page.evaluate(() => window.TokyoCart.getFavorites().length), 0, 'clear-data action removes seeded favorites');
    assert.equal(errors.length, 0, `no browser exceptions: ${errors.join('; ')}`);
    console.log(`PASS: browser-only account sample, idempotency, local labels and clear-data flow. Screenshots: ${evidence}`);
  } finally {
    await browser.close();
  }
}

if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
