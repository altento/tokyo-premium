const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '../project-docs/visual-qa/offers-checkout-2026-11-19');
const widths = String(process.env.TOKYO_OFFER_QUOTE_WIDTHS || '320,390,768,1024,1440,1920').split(',').map(Number).filter(width => Number.isInteger(width) && width >= 320);
const modes = [
  { name: 'php', base: process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8108', offers: '/offers.php', cart: '/pages.php?page=cart' },
  { name: 'static', base: process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8109/docs', offers: '/offers.html', cart: '/cart.html' }
];
const requestedModes = String(process.env.TOKYO_OFFER_QUOTE_MODES || '').split(',').map(value => value.trim()).filter(Boolean);
const selectedModes = requestedModes.length ? modes.filter(mode => requestedModes.includes(mode.name)) : modes;
assert.equal(selectedModes.length, requestedModes.length || modes.length, 'requested quote test modes exist');
async function capture(page, file) {
  await page.evaluate(async () => { await Promise.all([...document.images].filter(i => i.getAttribute('src')).map(i => { i.loading = 'eager'; return i.decode(); })); await document.fonts.ready; });
  const style = await page.addStyleTag({ content: '.site-header{position:relative!important;top:auto!important}.toast{display:none!important}' });
  await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: path.join(evidence, file), fullPage: true, type: 'jpeg', quality: 78 });
  await style.evaluate(e => e.remove());
}
async function checkLayout(page, label) {
  const state = await page.evaluate(() => ({
    overflow: document.documentElement.scrollWidth > innerWidth,
    clipped: [...document.querySelectorAll('main h1,main h2')].filter(e => {
      if (!e.getClientRects().length) return false;
      const box = e.getBoundingClientRect(), range = document.createRange(); range.selectNodeContents(e);
      return [...range.getClientRects()].some(r => r.left < Math.max(0, box.left) - 2 || r.right > Math.min(innerWidth, box.right) + 2);
    }).map(e => e.textContent)
  }));
  assert.equal(state.overflow, false, label); assert.deepEqual(state.clipped, [], label);
}
(async () => {
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch();
  try {
    for (const mode of selectedModes) {
      const page = await browser.newPage({ reducedMotion: 'reduce' });
      const errors = []; page.on('pageerror', e => errors.push(e.message));
      const total = page.locator('[data-tp-quote-total]'), calculate = page.locator('[data-tp-quote]'), promo = page.locator('#checkout-promo-code');
      for (const width of widths) {
        await page.setViewportSize({ width, height: 960 });
        assert.equal((await page.goto(mode.base + mode.offers)).status(), 200);
        await checkLayout(page, `${mode.name} offers at ${width}`);
        const codeCard = page.locator('.tp20-code-list article').filter({ has: page.locator('[data-copy-code="DEMO10"]') });
        const apply = codeCard.locator('a');
        assert.equal(new URL(await apply.getAttribute('href'), page.url()).searchParams.get('promo'), 'DEMO10');
        assert.equal(await apply.evaluate(a => a.getBoundingClientRect().height >= 44), true);
        if (mode.name === 'static' && [390, 1440].includes(width)) await capture(page, `offers-${width}.jpg`);
        await apply.click();
        await page.waitForURL(url => url.pathname.endsWith(mode.name === 'php' ? 'pages.php' : 'cart.html'));
        await page.waitForLoadState('domcontentloaded');
        assert.equal(await promo.inputValue(), 'DEMO10');
        await page.evaluate(() => { TokyoCart.clear(); TokyoCart.add(3, 2); });
        await calculate.click(); await total.waitFor({ state: 'visible' });
        assert.match(await total.innerText(), /240/);
        if (mode.name === 'static' && [390, 1440].includes(width)) await capture(page, `promo-${width}.jpg`);
        await promo.fill('INVALID'); assert.equal(await total.isHidden(), true, 'typing invalidates old discount immediately');
        await calculate.click(); await page.waitForFunction(() => !document.querySelector('[data-tp-quote]').disabled);
        assert.equal(await total.isHidden(), true, 'invalid promo does not leave a usable quote');
        await promo.fill('DEMO10'); await calculate.click(); await total.waitFor({ state: 'visible' });
        await page.locator('#fulfillment').selectOption('pickup');
        assert.equal(await total.isHidden(), true, 'fulfillment change invalidates quote');
        await calculate.click(); await total.waitFor({ state: 'visible' });
        await page.locator('[data-tp-promo-clear]').click();
        assert.equal(await promo.inputValue(), ''); assert.equal(await total.isHidden(), true);
        assert.equal(new URL(page.url()).searchParams.has('promo'), false, 'removal clears the invitation query');
        await page.locator('#checkout-form').evaluate(form => form.reset());
        assert.equal(await promo.inputValue(), '', 'form reset cannot restore the removed invitation code');
        await page.reload(); assert.equal(await promo.inputValue(), '', 'removed code stays removed after refresh');
        await calculate.click(); await total.waitFor({ state: 'visible' });
        assert.match(await total.innerText(), /скидка 0/);
        await checkLayout(page, `${mode.name} cart at ${width}`);
      }
      if (mode.name === 'php') {
        let release, reached, finished;
        const gate = new Promise(resolve => release = resolve), seen = new Promise(resolve => reached = resolve), completed = new Promise(resolve => finished = resolve);
        await page.route('**/api.php', async route => {
          if (!route.request().postData()?.includes('offer_quote')) return route.continue();
          const response = await route.fetch(); reached(); await gate;
          await route.fulfill({ response }).catch(() => {}); // An edited quote intentionally aborts this request.
          finished();
        });
        await promo.fill('DEMO10'); await calculate.click(); await seen;
        await page.evaluate(() => TokyoCart.setQty(3, 4));
        assert.equal(await total.isHidden(), true); assert.equal(await calculate.isEnabled(), true);
        release();
        await completed;
        // The aborted fetch must stay invalidated, even after its old response is released.
        await page.unrouteAll({ behavior: 'wait' });
        assert.equal(await total.isHidden(), true);
        await calculate.click(); await total.waitFor({ state: 'visible' });
        assert.match(await total.innerText(), /480/, 'new quote uses the updated four-item basket');
        await page.route('**/api.php', route => route.fulfill({ json: {
          ok: true, subtotal: 4800, discount_total: 480, delivery_fee: 0, total: 4320,
          gift_options: [{ id: 3, name: 'Gift A', max_per_order: 2, remaining_quantity: 4 }, { id: 6, name: 'Gift B', max_per_order: 2, remaining_quantity: 4 }]
        } }));
        await calculate.click(); await page.locator('[data-tp-gift-choice]').waitFor({ state: 'visible' });
        await page.locator('#checkout-gift-dish').selectOption('3');
        assert.equal(await total.isHidden(), true);
        assert.equal(await page.locator('#checkout-gift-dish option').count(), 3, 'gift changes preserve valid options');
        await calculate.click(); await total.waitFor({ state: 'visible' });
        await page.locator('#checkout-gift-quantity').fill('2'); assert.equal(await total.isHidden(), true);
        await page.unrouteAll({ behavior: 'wait' });
      }
      await page.locator('#checkout-form').evaluate(form => form.reset());
      await page.waitForFunction(() => document.querySelector('[data-tp-quote-total]').hidden && document.querySelector('[data-tp-gift-choice]').hidden);
      assert.equal(await page.locator('[data-tp-checkout-quote]').getAttribute('aria-busy'), 'false');
      if (mode.name === 'static') {
        await page.evaluate(() => { localStorage.removeItem('tokyo-demo-orders'); TokyoCart.clear(); TokyoCart.add(3, 2); });
        await page.locator('#checkout-promo-code').fill('DEMO10');
        await calculate.click(); await total.waitFor({ state: 'visible' });
        const quoted = await page.evaluate(() => TokyoDemoCheckoutQuote.calculate(TokyoCart.total(), new FormData(document.querySelector('#checkout-form'))));
        await page.locator('.cart-checkout-details').evaluate(node => { node.open = true; });
        await page.locator('#checkout-name').fill('Локальный гость');
        await page.locator('#checkout-phone').fill('+77010000000');
        await page.locator('#checkout-address').fill('Учебный адрес, 10');
        await page.locator('#checkout-form button[type="submit"]').click();
        await page.locator('#checkout-form .form-status.success').waitFor({ state: 'visible' });
        const saved = await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-orders') || '[]')[0]);
        assert.equal(saved?.total, quoted.total, 'static demo order persists exactly the discount from the displayed shared quote');
        assert.equal(saved?.discountTotal, quoted.discount_total, 'static demo order and checkout quote share one promotion calculation');
        assert.equal(saved?.promoCode, 'DEMO10');
        assert.equal(saved?.demo, true, 'saved order remains explicitly browser-local demo data');
      }
      assert.deepEqual(errors, []); await page.close();
      console.log(`PASS: ${mode.name} offer-to-cart, promo edits/removal, refresh, fulfillment, reset and widths ${widths.join(', ')}${mode.name === 'php' ? ', delayed-response race and gift choices' : ''}`);
    }
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
