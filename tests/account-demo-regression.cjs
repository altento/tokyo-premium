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
    assert.equal(await page.locator('.account-overview h1').innerText(), 'Личный кабинет', 'account masthead keeps a stable page title');
    assert.equal(await page.locator('#demo-favorites-preview .dish-card').count(), 0, 'empty profile has no favorite previews');
    assert.ok(await page.locator('#demo-favorites-preview-empty').isVisible(), 'empty profile explains how to save a favorite');
    assert.equal(await page.locator('.account-overview-lead').innerText(), 'Ваше пространство в Tokyo Premium', 'account masthead follows the approved subtitle hierarchy');
    assert.match(await page.locator('.account-overview-privacy').innerText(), /не зашифровано/i, 'account explains that browser-local demo data is not encrypted');
    assert.equal(await page.locator('.account-overview-art img').getAttribute('src'), 'assets/generated/contact-exterior.webp', 'account uses an entrance photograph rather than a room photo');
    assert.ok(await page.locator('.account-overview-art img').evaluate(image => image.complete && image.naturalWidth > 0), 'account entrance photograph is decoded');
    assert.ok(await page.locator('.account-overview').evaluate(hero => hero.getBoundingClientRect().height <= 420), 'desktop account masthead remains a compact introduction');

    await page.locator('[data-account-demo-seed]').click();
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 3, 'sample action adds three orders');
    assert.equal(await page.locator('#demo-bookings article').count(), 1, 'sample action adds one booking request');
    const tabBadgeLayout = await page.locator('.account-demo-nav [data-account-tab]').evaluateAll(buttons => buttons.filter(button => button.querySelector('.account-demo-tab-count')).map(button => {
      const labelNode = [...button.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
      const range = document.createRange();
      range.selectNodeContents(labelNode);
      const labelRight = Math.max(...[...range.getClientRects()].map(rect => rect.right));
      const badge = button.querySelector('.account-demo-tab-count').getBoundingClientRect();
      return getComputedStyle(button).display === 'flex' && badge.left - labelRight >= 8 && badge.right <= button.getBoundingClientRect().right;
    }));
    assert.deepEqual(tabBadgeLayout, [true, true, true], 'history, favorites and address counts are separated from their labels');
    const historyHeadingTops = await page.locator('.account-demo-panel[data-account-panel="history"] .intro-grid h2').evaluateAll(headings => headings.map(heading => heading.getBoundingClientRect().top));
    assert.equal(historyHeadingTops.length, 2, 'history exposes order and booking headings');
    assert.ok(Math.abs(historyHeadingTops[0] - historyHeadingTops[1]) <= 20, `order and booking columns align at the top edge: ${historyHeadingTops.join(', ')}`);
    assert.equal(await page.locator('#demo-favorites .dish-card').count(), 3, 'sample favorites resolve to catalog dishes');
    assert.equal(await page.locator('#demo-favorites-preview .dish-card').count(), 3, 'dashboard surfaces the saved dishes without opening another tab');
    assert.ok(await page.locator('#demo-favorites-preview .dish-card img').first().evaluate(image => image.complete && image.naturalWidth > 0), 'dashboard favorite preview uses a loaded dish photograph');
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
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.screenshot({ path: path.join(evidence, '11-account-seeded-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 74 });
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'seeded account has no mobile horizontal overflow');
    const mobileTabBadges = await page.locator('.account-demo-nav [data-account-tab]').evaluateAll(buttons => buttons.filter(button => button.querySelector('.account-demo-tab-count')).map(button => {
      const labelNode = [...button.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
      const range = document.createRange();
      range.selectNodeContents(labelNode);
      const labelRight = Math.max(...[...range.getClientRects()].map(rect => rect.right));
      const badge = button.querySelector('.account-demo-tab-count').getBoundingClientRect();
      return badge.left - labelRight >= 6 && badge.right <= button.getBoundingClientRect().right;
    }));
    assert.deepEqual(mobileTabBadges, [true, true, true], 'mobile account tab counts remain legible and inside their buttons');
    assert.ok(await page.evaluate(() => { const title=document.querySelector('.account-overview h1').getBoundingClientRect(); const hero=document.querySelector('.account-overview').getBoundingClientRect(); return title.left >= hero.left && title.right <= hero.right && title.width > 0; }), 'account title stays inside the mobile masthead');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.screenshot({ path: path.join(evidence, '11-account-seeded-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 74 });
    await page.setViewportSize({ width: 320, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'favorites preview has no overflow at 320 px');
    assert.ok(await page.locator('#demo-favorites-preview .dish-card').evaluateAll(cards => { const box=document.querySelector('#demo-favorites-preview').getBoundingClientRect(); return cards.every(card => { const rect=card.getBoundingClientRect(); return rect.width>0 && rect.left>=box.left && rect.right<=box.right; }); }), 'favorite cards stay within their panel at 320 px');
    await page.setViewportSize({ width: 390, height: 844 });

    await page.locator('#demo-favorites-preview .add-to-cart').first().click();
    assert.equal(await page.evaluate(() => window.TokyoCart.get().length), 1, 'quick add works from the dashboard favorite preview');

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
