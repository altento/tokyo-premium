const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const base = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8091';
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(root, 'project-docs', 'visual-qa');
const captureScreenshots = process.env.TOKYO_CAPTURE_ACCOUNT_SCREENSHOTS !== '0';

async function captureFullPage(page, filename) {
  const style = await page.addStyleTag({ content: '.site-header{position:static!important;top:auto!important}' });
  try {
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.screenshot({ path: path.join(evidence, filename), fullPage: true, type: 'jpeg', quality: 74 });
  } finally {
    await style.evaluate(node => node.remove());
  }
}

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
    assert.equal(await page.locator('#demo-addresses article').count(), 2, 'first visit includes two fictional addresses in the browser-local example');
    assert.match(await page.locator('#demo-addresses').innerText(), /Учебный пример.*Учебный пример/s, 'example addresses are visibly marked as fictional');
    assert.equal(await page.locator('#demo-addresses-preview article').count(), 2, 'dashboard overview surfaces the two saved addresses');
    assert.match(await page.locator('#demo-addresses-preview').innerText(), /Учебный пример.*Учебный пример/s, 'dashboard address preview keeps its fictional-data label');
    await page.locator('.account-addresses-preview [data-account-quick-target="addresses"]').click();
    assert.ok(await page.locator('[data-account-tab="addresses"]').evaluate(button => button.classList.contains('active')), 'address overview action opens the existing address manager');
    await page.locator('[data-account-tab="history"]').click();
    assert.deepEqual(await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-addresses') || '[]').length), 2, 'example addresses are stored only in this browser');
    assert.equal(await page.locator('#demo-notifications .account-notification-card').count(), 4, 'local order and booking events appear in the separate demo notification center');
    assert.match(await page.locator('#demo-notifications').innerText(), /ресторан его не получил, стол не подтверждён/i, 'demo booking notification does not claim a real confirmation');
    assert.equal(await page.locator('[data-account-stat="notifications"]').innerText(), '4', 'unread local demo notifications update the dashboard metric');
    assert.equal(await page.locator('#demo-favorites-preview .dish-card').count(), 3, 'first visit includes three catalog-backed favorites');
    assert.equal(await page.locator('[data-account-demo-seed]').count(), 1, 'account retains an explicit sample-data action for later use');
    assert.ok(!(await page.locator('.account-demo-seed').isVisible()), 'sample prompt is hidden after first-visit seeding');
    assert.equal(await page.locator('.account-overview h1').innerText(), 'Личный кабинет', 'account masthead keeps a stable page title');
    assert.equal(await page.locator('.account-overview-lead').innerText(), 'Ваше пространство в Tokyo Premium', 'account masthead follows the approved subtitle hierarchy');
    assert.match(await page.locator('.account-overview-privacy').innerText(), /не зашифровано/i, 'account explains that browser-local demo data is not encrypted');
    assert.equal(await page.locator('.account-overview-art img').getAttribute('src'), 'assets/generated/contact-exterior.webp', 'account uses an entrance photograph rather than a room photo');
    assert.ok(await page.locator('.account-overview-art img').evaluate(image => image.complete && image.naturalWidth > 0), 'account entrance photograph is decoded');
    assert.ok(await page.locator('.account-overview').evaluate(hero => hero.getBoundingClientRect().height <= 330), 'desktop account masthead stays compact beside the approved account reference');
    const orderGeometry = await page.locator('#demo-orders .account-demo-order').first().evaluate(row => {
      const title = row.querySelector('.account-demo-order-title').getBoundingClientRect();
      const total = row.querySelector('.account-demo-order-total').getBoundingClientRect();
      const panel = row.closest('[data-account-panel]').getBoundingClientRect();
      return { titleRight: title.right, totalLeft: total.left, rowWidth: row.getBoundingClientRect().width, panelWidth: panel.width };
    });
    assert.ok(orderGeometry.titleRight <= orderGeometry.totalLeft, 'order title and amount never overlap');
    assert.ok(orderGeometry.rowWidth >= orderGeometry.panelWidth * .85, 'order history uses the dashboard width instead of a narrow half column');
    const desktopOrderHeight = await page.locator('#demo-orders .account-demo-order').first().evaluate(row => row.getBoundingClientRect().height);
    assert.ok(desktopOrderHeight <= 116, `desktop order rows stay compact while retaining all actions (actual ${desktopOrderHeight}px)`);
    assert.equal(await page.locator('.account-quick-actions').evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length), 4, 'desktop quick actions form a compact row within the dashboard column');
    const desktopAccountColumns = await page.locator('.account-demo-layout').evaluate(node => {
      const nav = node.querySelector('.account-demo-nav').getBoundingClientRect();
      const summary = node.querySelector('.tp20-static-privilege').getBoundingClientRect();
      return { navLeft: nav.left, navTop: nav.top, navRight: nav.right, summaryLeft: summary.left, summaryTop: summary.top };
    });
    assert.ok(desktopAccountColumns.navRight < desktopAccountColumns.summaryLeft, 'account navigation occupies the dashboard sidebar');
    assert.ok(Math.abs(desktopAccountColumns.navTop - desktopAccountColumns.summaryTop) <= 2, 'account navigation aligns with the member summary from the first dashboard row');

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
    assert.deepEqual(tabBadgeLayout, [true, true, true, true], 'history, notification, favorites and address counts are separated from their labels');
    await page.locator('[data-account-quick-target="notifications"]').click();
    assert.ok(await page.locator('[data-account-tab="notifications"]').evaluate(button => button.classList.contains('active')), 'quick action opens the notifications tab');
    const unreadDemoNotification = page.locator('#demo-notifications .account-notification-card.is-unread').first();
    if (captureScreenshots) await captureFullPage(page, '11-account-notifications-unread-desktop.jpg');
    await unreadDemoNotification.locator('[data-demo-notification-read]').click();
    assert.equal(await page.locator('#demo-notifications .account-notification-card.is-unread').count(), 3, 'mark-read action updates the visible local notification state');
    assert.equal(await page.locator('[data-account-stat="notifications"]').innerText(), '3', 'mark-read action updates unread metric and tab count');
    assert.equal(await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-read-notifications') || '[]').length), 1, 'read state is persisted only in browser-local storage');
    await page.reload({ waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#demo-notifications .account-notification-card.is-unread').count(), 3, 'read state survives a page reload');
    assert.equal(await page.locator('#demo-notifications .account-notification-card.is-read').count(), 1, 'read notification remains visible after a reload');
    await page.locator('[data-account-tab="history"]').click();
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

    await page.locator('[data-account-tab="history"]').click();
    await page.locator('#demo-orders [data-repeat-order]').first().click();
    assert.match(await page.locator('#demo-repeat-status').innerText(), /состав добавлен в корзину/i, 'demo order restores its saved composition through the shared cart store');
    assert.ok(await page.evaluate(() => window.TokyoCart.get().length > 0), 'repeated demo order adds catalogue-backed products');
    const cartBeforeInvalidRepeat = await page.evaluate(() => JSON.stringify(window.TokyoCart.snapshotAll()));
    const atomicRepeatRejected = await page.evaluate(before => {
      const firstId = window.DISHES?.[0]?.id;
      return !window.TokyoCart.addOrder({ items: [{ id: firstId, qty: 1 }, { id: 999999, qty: 1 }], customSets: [] })
        && JSON.stringify(window.TokyoCart.snapshotAll()) === before;
    }, cartBeforeInvalidRepeat);
    assert.ok(atomicRepeatRejected, 'invalid demo order is rejected without partially changing the existing cart');
    await page.evaluate(() => window.TokyoCart.clear());

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
    if (captureScreenshots) await captureFullPage(page, '11-account-seeded-desktop.jpg');
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'seeded account has no mobile horizontal overflow');
    assert.ok(await page.evaluate(() => document.querySelector('.account-demo-nav').getBoundingClientRect().top < document.querySelector('.tp20-static-privilege').getBoundingClientRect().top), 'mobile visual order follows keyboard navigation and DOM order');
    assert.equal(await page.locator('.account-quick-actions').evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length), 2, 'mobile quick actions remain an easy two-column grid');
    const mobileTabBadges = await page.locator('.account-demo-nav [data-account-tab]').evaluateAll(buttons => buttons.filter(button => button.querySelector('.account-demo-tab-count')).map(button => {
      const labelNode = [...button.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
      const range = document.createRange();
      range.selectNodeContents(labelNode);
      const labelRight = Math.max(...[...range.getClientRects()].map(rect => rect.right));
      const badge = button.querySelector('.account-demo-tab-count').getBoundingClientRect();
      return badge.left - labelRight >= 6 && badge.right <= button.getBoundingClientRect().right;
    }));
    assert.deepEqual(mobileTabBadges, [true, true, true, true], 'mobile account tab counts remain legible and inside their buttons');
    await page.locator('[data-account-tab="notifications"]').click();
    if (captureScreenshots) await captureFullPage(page, '11-account-notifications-mobile.jpg');
    await page.locator('[data-account-tab="history"]').click();
    assert.ok(await page.evaluate(() => { const title=document.querySelector('.account-overview h1').getBoundingClientRect(); const hero=document.querySelector('.account-overview').getBoundingClientRect(); return title.left >= hero.left && title.right <= hero.right && title.width > 0; }), 'account title stays inside the mobile masthead');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    if (captureScreenshots) await captureFullPage(page, '11-account-seeded-mobile.jpg');
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
    assert.equal(await page.locator('[data-account-stat="notifications"]').innerText(), '0', 'clear-data action removes local notification and read state');
    assert.equal(await page.locator('#demo-addresses article').count(), 0, 'clear-data action removes example addresses');
    assert.equal(await page.locator('#demo-addresses-preview article').count(), 0, 'clearing browser data removes the dashboard address preview');
    await page.locator('[data-account-tab="history"]').click();
    assert.ok(await page.locator('#demo-addresses-preview-empty').isVisible(), 'empty dashboard address preview explains the local state');
    assert.equal(await page.evaluate(() => window.TokyoCart.getFavorites().length), 0, 'clear-data action removes seeded favorites');
    assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-demo-account-seed-dismissed')), '1', 'clear-data action persists the choice to keep the profile empty');
    assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-demo-read-notifications')), null, 'clear-data action also clears notification read state');
    await page.reload({ waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 0, 'cleared account stays empty after reload');
    assert.ok(await page.locator('.account-demo-seed').isVisible(), 'cleared account offers an explicit way to restore the sample');
    await page.locator('[data-account-demo-seed]').click();
    assert.equal(await page.locator('#demo-orders .demo-order').count(), 3, 'user can opt into the sample again after clearing');
    assert.equal(await page.locator('#demo-addresses article').count(), 2, 'restoring the sample brings back its example addresses');
    assert.equal(await page.locator('#demo-addresses-preview article').count(), 2, 'restoring the sample repopulates the dashboard address preview');
    assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-demo-account-seed-dismissed')), null, 'explicitly restoring the sample removes the dismissal marker');

    const preservedPage = await browser.newPage({ viewport: { width: 390, height: 844 } });
    const preservedErrors = [];
    preservedPage.on('pageerror', error => preservedErrors.push(error.message));
    await preservedPage.addInitScript(() => {
      localStorage.setItem('tokyo-demo-profile', JSON.stringify({ name: 'Сохранённый гость', phone: '' }));
      localStorage.setItem('tokyo-demo-orders', JSON.stringify([{ orderId: '<img src=x onerror=alert(1)>', date: 'Локальная запись', name: 'Сохранённый гость', count: 1, subtotal: 1200, total: 1200, fulfillment: 'pickup', items: [{ id: 3, qty: 1, unitPrice: 1200, lineTotal: 1200 }] }]));
      localStorage.setItem('tokyo-demo-addresses', JSON.stringify(['<img src=x onerror=alert(1)>']));
    });
    const preservedResponse = await preservedPage.goto(`${base}/account.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(preservedResponse?.status(), 200, 'account with existing local data loads');
    assert.equal(await preservedPage.locator('#demo-orders .demo-order').count(), 1, 'first visit does not replace existing local order history with samples');
    assert.equal(await preservedPage.locator('#demo-orders .demo-order small').first().innerText(), '<img src=x onerror=alert(1)>', 'existing order identifier remains intact as text');
    assert.equal(await preservedPage.locator('#demo-notifications img').count(), 0, 'browser-local notification content cannot inject HTML elements');
    assert.match(await preservedPage.locator('#demo-notifications').innerText(), /не оплачен и не отправлен ресторану/i, 'saved demo order notification keeps its local-only status');
    assert.equal(await preservedPage.locator('#demo-orders .account-demo-record-badge').count(), 0, 'an existing user order is not relabeled as a system sample');
    assert.equal(await preservedPage.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-profile') || '{}').name), 'Сохранённый гость', 'existing local profile data is preserved');
    assert.ok(await preservedPage.locator('.account-demo-seed').isVisible(), 'sample opt-in remains available when user data already exists');
    await preservedPage.locator('[data-account-demo-seed]').click();
    assert.deepEqual(await preservedPage.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-addresses') || '[]')), ['<img src=x onerror=alert(1)>'], 'restoring examples never overwrites an existing saved address');
    assert.equal(await preservedPage.locator('#demo-addresses-preview img').count(), 0, 'browser-local address text is escaped in the dashboard preview');
    assert.equal(preservedErrors.length, 0, `no errors with existing data: ${preservedErrors.join('; ')}`);
    await preservedPage.close();
    assert.equal(errors.length, 0, `no browser exceptions: ${errors.join('; ')}`);
    console.log(`PASS: browser-only account, local notifications, read-state persistence, XSS-safe rendering and clear-data flow. Screenshots: ${evidence}`);
  } finally {
    await browser.close();
  }
}

if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
