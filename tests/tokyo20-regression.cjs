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

async function run() {
  const { chromium } = require('playwright');
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    for (const mode of [
      { name: 'php', base: phpBase, routes: { offers: '/offers.php', collection: '/collection.php?slug=autumn-in-kyoto', builder: '/set-builder.php', evening: '/perfect-evening.php', gift: '/gift-certificates.php', omakase: '/pages.php?page=omakase', dish: '/pages.php?page=dish&id=3', booking: '/pages.php?page=booking', menu: '/menu.php', cart: '/pages.php?page=cart', account: '/account.php' } },
      { name: 'static', base: staticBase, routes: { offers: '/offers.html', collection: '/collection-autumn-in-kyoto.html', builder: '/set-builder.html', evening: '/perfect-evening.html', gift: '/gift-certificates.html', omakase: '/omakase.html', dish: '/dish-3.html', booking: '/booking.html', menu: '/menu.html', cart: '/cart.html', account: '/account.html' } }
    ]) {
      const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
      const page = await context.newPage();
      page.setDefaultTimeout(7000);
      page.on('pageerror', error => failures.push(`${mode.name} pageerror ${page.url()}: ${error.message}`));
      page.on('console', message => { if (message.type() === 'error' && !/fonts\.googleapis|fonts\.gstatic/.test(message.text())) failures.push(`${mode.name} console ${page.url()}: ${message.text()}`); });

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
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-secret-menu-unlocked-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await prepareVisual(page);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-secret-menu-unlocked-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });
      check(await page.evaluate(() => localStorage.getItem('tokyo-secret-menu-code')) === secretCode, `${mode.name} successful Secret Menu access persists the re-check token for checkout`);
      await page.locator('[data-tp-secret-results] .add-to-cart').first().click();
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
          await input.fill(index === 2 ? '30000' : '2');
        }
        await step.locator('[data-tp-evening-next]').click();
      }
      check(await page.locator('[data-tp-evening-result]').isVisible(), `${mode.name} preference wizard renders a recommendation result`);
      check(await page.locator('.tp-recommendation-card').count() > 0, `${mode.name} recommendation uses real catalog items`);
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-perfect-evening-result.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-perfect-evening-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });

      await page.goto(mode.base + mode.routes.booking, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('[data-tp-table]', { timeout: 8000 });
      check(!(await page.locator('.booking-form .table-selection').innerText()).includes('Параметры изменились'), `${mode.name} booking form has no stale table warning on initial load`);
      await page.locator('[data-tp-table]').first().click();
      check(await page.locator('[name="table"]').inputValue() === '1', `${mode.name} floorplan stores a preferred table in the booking form`);
      check((await page.locator('[data-tp-floorplan-selection]').innerText()).includes('Предпочтение сохранено'), `${mode.name} floorplan states that a seat is only a preference`);
      check((await page.locator('.booking-form .table-selection').innerText()).includes('предпочтительный стол №1'), `${mode.name} selected table is reflected in the booking form`);
      const bookingDate = page.locator('.booking-form [name="date"]');
      await bookingDate.fill(await bookingDate.getAttribute('min'));
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
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-booking-table-selected.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 390, height: 844 });
      await page.screenshot({ path: path.join(evidence, `12-${mode.name}-booking-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
      await page.setViewportSize({ width: 1440, height: 1000 });

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
    console.log(`PASS: Tokyo 2.0 interactive regression (PHP + static): offer routes, Omakase, set builder, preference wizard, floorplan, gift preview, quick view, profile, mobile overflow. Evidence: ${evidence}`);
  } finally { await browser.close(); }
}

if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
