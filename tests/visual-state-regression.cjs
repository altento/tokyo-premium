const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const evidence = path.resolve(process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '..', 'project-docs', 'visual-qa'));

async function capture(page, name) {
  await page.addStyleTag({ content: 'html{scroll-behavior:auto!important}.site-header{position:relative!important;top:auto!important}' });
  await page.evaluate(async () => {
    const images = [...document.images];
    images.forEach(image => { image.loading = 'eager'; });
    await Promise.all(images.map(image => image.decode().catch(() => null)));
  });
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'auto' }));
  await page.waitForFunction(() => window.scrollY === 0 && (!document.querySelector('.site-header') || Math.abs(document.querySelector('.site-header').getBoundingClientRect().top) < 1));
  await page.screenshot({ path: path.join(evidence, name), fullPage: true, type: 'jpeg', quality: 78 });
}

async function run(options = {}) {
  const { chromium } = options.chromium ? { chromium: options.chromium } : require('playwright');
  const base = options.base || process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8090';
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));

    await page.goto(`${base}/docs/gallery.html`, { waitUntil: 'domcontentloaded' });
    await page.locator('[data-gallery="all"]').click();
    await capture(page, '06-space-default-desktop.jpg');
    await page.setViewportSize({ width: 390, height: 844 });
    await capture(page, '06-space-default-mobile.jpg');
    assert.equal(await page.locator('[data-gallery="all"]').evaluate(button => button.classList.contains('active')), true, 'paired gallery screenshots use the default filter');
    assert.equal(await page.locator('.zone-card:visible').count(), 4, 'all distinct room cards remain visible on mobile');

    await page.goto(`${base}/docs/account.html`, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      localStorage.setItem('tokyo-demo-profile', JSON.stringify({ name: 'Гость QA', phone: '+77010000000' }));
      localStorage.setItem('tokyo-demo-orders', JSON.stringify([{
        orderId: 'DEMO-VISUAL-001', date: '12 ноября 2026, 19:30', name: 'Гость QA', count: 3,
        subtotal: 12700, fulfillment: 'pickup', items: [
          { id: 1, name: 'Ролл Филадельфия', qty: 2, unitPrice: 4500, lineTotal: 9000 },
          { id: 2, name: 'Нигири с лососем', qty: 1, unitPrice: 1200, lineTotal: 1200 },
          { id: 3, name: 'Матча латте', qty: 1, unitPrice: 2500, lineTotal: 2500 }
        ]
      }]));
      localStorage.setItem('tokyo-demo-bookings', JSON.stringify([{ name: 'Гость QA', date: '2026-11-20', time: '19:00', guests: 2, zone: 'Камерная комната', format: 'Сезонный' }]));
    });
    await page.evaluate(() => window.TokyoCart?.setFavorites([1, 2, 3]));
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.locator('[data-account-tab="history"]').click();
    assert.equal(await page.locator('.account-demo-page').count(), 1, 'account visual state is the static Pages implementation');
    assert.match(await page.locator('#demo-orders').innerText(), /DEMO-VISUAL-001/);
    assert.equal(await page.locator('[data-account-stat="orders"]').innerText(), '1');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await capture(page, '11-account-static-desktop.jpg');
    await page.setViewportSize({ width: 390, height: 844 });
    await capture(page, '11-account-static-mobile.jpg');
    assert.equal(await page.locator('.account-demo-page').count(), 1, 'paired account screenshots use the same static implementation');
    assert.equal(errors.length, 0, errors.join('\n'));
    console.log('PASS: paired default gallery and static account desktop/mobile evidence saved.');
  } finally {
    await browser.close();
  }
}

module.exports = run;
if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
