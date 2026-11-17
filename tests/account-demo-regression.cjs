const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const base = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8091';
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(root, 'project-docs', 'visual-qa');
const captureScreenshots = process.env.TOKYO_CAPTURE_ACCOUNT_SCREENSHOTS !== '0';

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
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 3, 'first visit opens with three clearly labeled local demo orders');
    assert.equal(await page.locator('#demo-bookings article').count(), 1, 'first visit includes one labeled demo booking request');
    assert.equal(await page.locator('#demo-favorites-preview .dish-card').count(), 3, 'first visit includes three catalog-backed favorites');
    assert.equal(await page.locator('[data-account-demo-seed]').count(), 1, 'account retains an explicit sample-data action for later use');
    assert.ok(!(await page.locator('.account-demo-seed').isVisible()), 'sample prompt is hidden after first-visit seeding');
    assert.equal(await page.locator('.account-overview h1').innerText(), 'Личный кабинет', 'account masthead keeps a stable page title');
    assert.equal(await page.locator('.account-overview-lead').innerText(), 'Ваше пространство в Tokyo Premium', 'account masthead follows the approved subtitle hierarchy');
    assert.match(await page.locator('.account-overview-privacy').innerText(), /не зашифровано/i, 'account explains that browser-local demo data is not encrypted');
    assert.equal(await page.locator('.account-overview-art img').getAttribute('src'), 'assets/generated/contact-exterior.webp', 'account uses an entrance photograph rather than a room photo');
    assert.ok(await page.locator('.account-overview-art img').evaluate(image => image.complete && image.naturalWidth > 0), 'account entrance photograph is decoded');
    assert.ok(await page.locator('.account-overview').evaluate(hero => hero.getBoundingClientRect().height <= 330), 'desktop account masthead stays compact beside the approved account reference');
    assert.equal(await page.locator('.account-quick-actions').evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length), 4, 'desktop account quick actions form four balanced tiles');

    assert.match(await page.locator('[data-account-demo-seed-status]').evaluate(node => node.textContent), /только в этом браузере/i, 'first-visit status clearly identifies browser-local demo data');
    const privilege = page.locator('[data-tp-demo-loyalty]');
    assert.ok(await privilege.isVisible(), 'local demo loyalty card is visible');
    assert.match(await privilege.innerText(), /TOKYO PRIVILEGE · ЛОКАЛЬНАЯ ДЕМО/, 'loyalty card identifies its browser-only demo mode');
    assert.match(await privilege.locator('.tp20-referral-card .kicker').innerText(), /ДЕМО-ПРИГЛАШЕНИЕ · ТОЛЬКО ЭТОТ БРАУЗЕР/i, 'referral card keeps its browser-only demo label in Russian');
    assert.equal(await privilege.locator('[data-tp-loyalty-points]').innerText(), await page.evaluate(() => Math.floor(JSON.parse(localStorage.getItem('tokyo-demo-orders') || '[]').reduce((sum, order) => sum + Math.max(0, Number(order.total) || 0), 0) / 100).toLocaleString('ru-RU')), 'loyalty points refresh from sample orders created after page load');
    assert.ok(await page.evaluate(() => document.querySelector('[data-tp-demo-loyalty]').getBoundingClientRect().top < document.querySelector('#demo-orders').getBoundingClientRect().top), 'loyalty summary appears before order history');
    const tabBadgeLayout = await page.locator('.account-demo-nav [data-account-tab]').evaluateAll(buttons => buttons.filter(button => button.querySelector('.account-demo-tab-count')).map(button => {
      const labelNode = [...button.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
      const range = document.createRange();
      range.selectNodeContents(labelNode);
      const labelRight = Math.max(...[...range.getClientRects()].map(rect => rect.right));
      const badge = button.querySelector('.account-demo-tab-count').getBoundingClientRect();
      return getComputedStyle(button).display === 'flex' && badge.left - labelRight >= 8 && badge.right <= button.getBoundingClientRect().right;
    }));
    assert.deepEqual(tabBadgeLayout, [true, true, true], 'history, favorites and address counts are separated from their labels');
    const historyLayout = await page.locator('.account-demo-panel[data-account-panel="history"] .intro-grid').evaluate(grid => {
      const orders = grid.children[0].getBoundingClientRect();
      const bookings = grid.children[1].getBoundingClientRect();
      const area = grid.getBoundingClientRect();
      return { ordersWidth: orders.width, areaWidth: area.width, ordersBottom: orders.bottom, bookingsTop: bookings.top, ordersLeft: orders.left, bookingsLeft: bookings.left };
    });
    assert.ok(historyLayout.ordersWidth >= historyLayout.areaWidth * .94, `desktop order history uses the dashboard width: ${historyLayout.ordersWidth}/${historyLayout.areaWidth}`);
    assert.ok(historyLayout.bookingsTop >= historyLayout.ordersBottom, `booking requests follow the full-width order history: ${historyLayout.ordersBottom} -> ${historyLayout.bookingsTop}`);
    assert.ok(Math.abs(historyLayout.ordersLeft - historyLayout.bookingsLeft) <= 2, 'order history and booking requests share the same left edge');
    assert.equal(await page.locator('#demo-favorites .dish-card').count(), 3, 'sample favorites resolve to catalog dishes');
    assert.equal(await page.locator('#demo-favorites-preview .dish-card').count(), 3, 'dashboard surfaces the saved dishes without opening another tab');
    assert.ok(await page.locator('#demo-favorites-preview .dish-card img').first().evaluate(image => image.complete && image.naturalWidth > 0), 'dashboard favorite preview uses a loaded dish photograph');
    assert.equal(await page.locator('#demo-orders .account-demo-record-badge').count(), 3, 'every sample order is explicitly labeled');
    assert.equal(await page.locator('#demo-orders .account-order-preview').count(), 3, 'sample order history uses catalogue thumbnails');
    const orderImageStates = await page.locator('#demo-orders .account-order-preview').evaluateAll(async images => {
      images.forEach(image => { image.loading = 'eager'; });
      return Promise.all(images.map(async image => { try { await image.decode(); return image.complete && image.naturalWidth > 0; } catch { return false; } }));
    });
    assert.deepEqual(orderImageStates, [true, true, true], 'all catalogue order photographs are decoded before the full-page capture');
    assert.equal(await page.locator('#demo-bookings .account-demo-record-badge').count(), 1, 'sample booking is explicitly labeled');
    assert.ok(await page.locator('#demo-orders .demo-order-items li').count() >= 5, 'order history retains item snapshots');
    const orderDetails = page.locator('#demo-orders .demo-order-details');
    assert.equal(await orderDetails.count(), 3, 'each sample order has an expandable composition');
    assert.ok(await orderDetails.first().evaluate(node => !node.open), 'order composition starts collapsed to keep the dashboard compact');
    await orderDetails.first().locator('summary').click();
    assert.ok(await orderDetails.first().evaluate(node => node.open), 'order composition opens from its keyboard-accessible summary');
    assert.ok(await orderDetails.first().locator('li').first().isVisible(), 'expanded order exposes its saved item snapshot');
    await orderDetails.first().locator('summary').click();
    assert.ok(await orderDetails.first().evaluate(node => !node.open), 'order composition can be collapsed again');
    assert.ok(await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-orders') || '[]').every(order => order.subtotal === order.items.reduce((sum, item) => sum + item.lineTotal, 0))), 'sample totals agree with saved catalog item prices');
    assert.equal(await page.locator('[data-account-stat="orders"]').innerText(), '3', 'dashboard metrics update');
    assert.equal(await page.locator('[data-account-stat="bookings"]').innerText(), '1', 'booking metric updates');

    await page.reload({ waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 3, 'reload preserves first-visit sample orders without duplicating them');
    assert.equal(await page.locator('#demo-bookings article').count(), 1, 'reload preserves a single sample booking');
    assert.ok(!(await page.locator('.account-demo-seed').isVisible()), 'seed prompt stays hidden while the sample profile is populated');
    const reloadedOrderImageStates = await page.locator('#demo-orders .account-order-preview').evaluateAll(async images => {
      images.forEach(image => { image.loading = 'eager'; });
      return Promise.all(images.map(async image => { try { await image.decode(); return image.complete && image.naturalWidth > 0; } catch { return false; } }));
    });
    assert.deepEqual(reloadedOrderImageStates, [true, true, true], 'all lazy order photographs are decoded again after reload before the desktop capture');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    if (captureScreenshots) await page.screenshot({ path: path.join(evidence, '11-account-seeded-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 74 });
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'seeded account has no mobile horizontal overflow');
    assert.equal(await page.locator('.account-quick-actions').evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length), 2, 'mobile quick actions remain an easy two-column grid');
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
    if (captureScreenshots) await page.screenshot({ path: path.join(evidence, '11-account-seeded-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 74 });
    await page.setViewportSize({ width: 320, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'favorites preview has no overflow at 320 px');
    assert.ok(await page.locator('#demo-favorites-preview .dish-card').evaluateAll(cards => { const box=document.querySelector('#demo-favorites-preview').getBoundingClientRect(); return cards.every(card => { const rect=card.getBoundingClientRect(); return rect.width>0 && rect.left>=box.left && rect.right<=box.right; }); }), 'favorite cards stay within their panel at 320 px');
    await page.setViewportSize({ width: 390, height: 844 });

    await page.locator('#demo-favorites-preview .add-to-cart').first().click();
    assert.equal(await page.evaluate(() => window.TokyoCart.get().length), 1, 'quick add works from the dashboard favorite preview');

    await page.locator('[data-account-tab="settings"]').click();
    await page.locator('#demo-clear-data').click();
    assert.equal(await page.locator('[data-account-stat="orders"]').innerText(), '0', 'clear-data action removes seeded orders');
    assert.equal(await page.locator('[data-tp-loyalty-points]').innerText(), '0', 'clearing local orders refreshes the demo loyalty balance');
    assert.equal(await page.locator('[data-account-stat="bookings"]').innerText(), '0', 'clear-data action removes seeded booking');
    assert.equal(await page.evaluate(() => window.TokyoCart.getFavorites().length), 0, 'clear-data action removes seeded favorites');
    assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-demo-account-seed-dismissed')), '1', 'clear-data action persists the choice to keep the profile empty');
    await page.reload({ waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 0, 'cleared account stays empty after reload');
    assert.ok(await page.locator('.account-demo-seed').isVisible(), 'cleared account offers an explicit way to restore the sample');
    await page.locator('[data-account-demo-seed]').click();
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 3, 'user can opt into the sample again after clearing');
    assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-demo-account-seed-dismissed')), null, 'explicitly restoring the sample removes the dismissal marker');

    const preservedPage = await browser.newPage({ viewport: { width: 390, height: 844 } });
    const preservedErrors = [];
    preservedPage.on('pageerror', error => preservedErrors.push(error.message));
    await preservedPage.addInitScript(() => {
      localStorage.setItem('tokyo-demo-profile', JSON.stringify({ name: 'Сохранённый гость', phone: '' }));
      localStorage.setItem('tokyo-demo-orders', JSON.stringify([{ orderId: 'MY-DEMO-ORDER', date: 'Локальная запись', name: 'Сохранённый гость', count: 1, subtotal: 1200, total: 1200, fulfillment: 'pickup', items: [{ id: 3, qty: 1, unitPrice: 1200, lineTotal: 1200 }] }]));
    });
    const preservedResponse = await preservedPage.goto(`${base}/account.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(preservedResponse?.status(), 200, 'account with existing local data loads');
    assert.equal(await preservedPage.locator('#demo-orders .demo-order').count(), 1, 'first visit does not replace existing local order history with samples');
    assert.equal(await preservedPage.locator('#demo-orders .demo-order small').first().innerText(), 'MY-DEMO-ORDER', 'existing order identifier remains intact');
    assert.equal(await preservedPage.locator('#demo-orders .account-demo-record-badge').count(), 0, 'an existing user order is not relabeled as a system sample');
    assert.equal(await preservedPage.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-profile') || '{}').name), 'Сохранённый гость', 'existing local profile data is preserved');
    assert.ok(await preservedPage.locator('.account-demo-seed').isVisible(), 'sample opt-in remains available when user data already exists');
    assert.equal(preservedErrors.length, 0, `no errors with existing data: ${preservedErrors.join('; ')}`);
    await preservedPage.close();
    assert.equal(errors.length, 0, `no browser exceptions: ${errors.join('; ')}`);
    console.log(`PASS: browser-only account sample, idempotency, local labels and clear-data flow. Screenshots: ${evidence}`);
  } finally {
    await browser.close();
  }
}

if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
