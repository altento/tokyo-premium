const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '../project-docs/visual-qa/ideal-evening-2026-11-19');
const modes = [
  { name: 'php', base: process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8128', route: '/perfect-evening.php' },
  { name: 'static', base: process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8129/docs', route: '/perfect-evening.html' }
];
async function choose(page, preference = 'fish', budget = '20000', guests = '2', atmosphere = 'main') {
  for (const [field, value] of [['occasion', 'romantic'], ['preference', preference]]) {
    await page.locator(`[data-field="${field}"][value="${value}"]`).click();
    await page.locator('[data-tp-evening-step]:visible [data-tp-evening-next]').click();
    assert.ok(await page.locator('[data-tp-evening-step]:visible h3').evaluate(h => document.activeElement === h), 'focus follows the newly visible step');
  }
  await page.locator('#evening-budget').fill(budget);
  await page.locator('[data-tp-evening-step]:visible [data-tp-evening-next]').click();
  await page.locator('#evening-guests').fill(guests);
  await page.locator('[data-tp-evening-step]:visible [data-tp-evening-next]').click();
  await page.locator(`[data-field="atmosphere"][value="${atmosphere}"]`).click();
  await page.locator('[data-tp-evening-step]:visible [data-tp-evening-next]').click();
}
async function capture(page, filename) {
  await page.evaluate(async () => { await Promise.all([...document.images].filter(i => i.getAttribute('src')).map(i => { i.loading = 'eager'; return i.decode(); })); await document.fonts.ready; });
  const style = await page.addStyleTag({ content: '.site-header{position:relative!important;top:auto!important}.toast{display:none!important}' });
  await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: path.join(evidence, filename), fullPage: true, quality: 78 });
  await style.evaluate(e => e.remove());
}
(async () => {
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch();
  try {
    for (const mode of modes) {
      const page = await browser.newPage({ reducedMotion: 'reduce', viewport: { width: 1440, height: 960 } });
      const errors = []; page.on('pageerror', e => errors.push(e.message));
      await page.goto(mode.base + mode.route);
      assert.equal(await page.locator('[data-tp-evening-booking]').isHidden(), true);
      for (const [field, value] of [['occasion', 'romantic'], ['preference', 'fish']]) {
        await page.locator(`[data-field="${field}"][value="${value}"]`).click();
        await page.locator('[data-tp-evening-step]:visible [data-tp-evening-next]').click();
      }
      await page.locator('[data-tp-evening-step]:visible [data-tp-evening-next]').click();
      for (const guests of ['', '0', '13', '1.5']) {
        await page.locator('#evening-guests').fill(guests);
        assert.equal(await page.locator('[data-tp-evening-step]:visible [data-tp-evening-next]').isDisabled(), true, 'invalid guest input blocks the wizard');
      }
      await page.locator('[data-tp-evening-restart]').click();
      await choose(page, 'plant');
      assert.equal(await page.locator('.tp-empty-state').count(), 1, 'current catalog has no suitable plant main including broth screening');
      assert.equal(await page.locator('[data-tp-evening-add-plan]').count(), 0);
      assert.equal(await page.locator('.tp-evening-estimate').count(), 0, 'empty state does not advertise a zero-price meal');
      assert.equal(await page.locator('[data-tp-evening-result]').evaluate(e => document.activeElement === e), true);
      if (mode.name === 'static') await capture(page, 'plant-empty-desktop.jpg');
      await page.locator('[data-tp-evening-edit]').click();
      assert.equal(await page.locator('[data-field="preference"][value="plant"]').getAttribute('aria-pressed'), 'true', 'edit preserves earlier answers');
      assert.equal(await page.locator('[data-tp-evening-booking]').isHidden(), true);
      assert.equal(await page.locator('[data-tp-evening-result]').isHidden(), true);
      await choose(page, 'fish', '10000', '3', 'private');
      const plan = await page.locator('[data-tp-evening-plan-line]').evaluateAll(nodes => nodes.map(n => ({ id: Number(n.dataset.id), qty: Number(n.dataset.qty) })));
      assert.ok(plan.length >= 2);
      await page.evaluate(() => TokyoCart.clear());
      await page.locator('[data-tp-evening-add-plan]').click();
      assert.deepEqual(await page.evaluate(() => TokyoCart.get()), plan);
      assert.equal(await page.locator('[data-tp-evening-add-plan]').isDisabled(), true);
      for (const width of [320, 390, 768, 1024, 1440, 1920]) {
        await page.setViewportSize({ width, height: 960 });
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const bounds = await page.evaluate(() => ({
          overflow: document.documentElement.scrollWidth > innerWidth,
          outside: [...document.querySelectorAll('body *')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.right > innerWidth + 1; }).slice(0,8).map(e => ({tag:e.tagName,cls:e.className,width:e.getBoundingClientRect().width,right:e.getBoundingClientRect().right})),
          clipped: [...document.querySelectorAll('main h1,main h2,main h3')].filter(e => {
            if (!e.getClientRects().length) return false;
            const range = document.createRange(); range.selectNodeContents(e); const box = e.getBoundingClientRect();
            return [...range.getClientRects()].some(r => r.left < Math.max(0, box.left) - 2 || r.right > Math.min(innerWidth, box.right) + 2);
          }).map(e => e.textContent)
        }));
        assert.equal(bounds.overflow, false, `${mode.name} overflow at ${width}: ${JSON.stringify(bounds.outside)}`); assert.deepEqual(bounds.clipped, [], `${mode.name} clipped headings at ${width}`);
        if (mode.name === 'static' && [390, 1440].includes(width)) await capture(page, `result-${width}.jpg`);
      }
      await page.locator('[data-tp-evening-booking]').click();
      await page.waitForLoadState('domcontentloaded');
      assert.equal(await page.locator('[name="guests"]').inputValue(), '3');
      assert.equal(await page.locator('#booking-zone').inputValue(), 'Приватная комната');
      assert.match(await page.locator('#booking-note').inputValue(), /Подбор меню:/);
      await page.goto(mode.base + mode.route);
      await choose(page, 'fish', '1000');
      assert.match(await page.locator('.tp-budget-note').innerText(), /выходит за ваш ориентир/, 'insufficient budget is disclosed instead of claiming the plan fits');
      // Synthetic catalog checks classification and pairing rules, not real menu claims.
      await page.goto(mode.base + mode.route);
      await page.evaluate(() => {
        window.DISHES = [
          { id: 101, name: 'Овощи с тофу', category_name: 'Горячие блюда', description: 'Грибы и овощи', price: 3000 },
          { id: 102, name: 'Чай', category_name: 'Напитки', price: 1000 },
          { id: 103, name: 'Матча-тирамису', category_name: 'Десерты', price: 500 },
          { id: 104, name: 'Ролл с авокадо и лососем', category_name: 'Роллы', price: 2000 },
          { id: 105, name: 'Мисо с тофу', category_name: 'Супы', description: 'Бульон даси', price: 1000 }
        ];
        window.TP_DISH_PAIRINGS = [{ dish_id: 101, paired_dish_id: 103, pairing_type: 'drink' }, { dish_id: 101, paired_dish_id: 104, pairing_type: 'dish' }];
      });
      await choose(page, 'plant');
      const roles = await page.locator('.tp-evening-plan-card').evaluateAll(nodes => nodes.map(n => ({ role: n.dataset.role, id: Number(n.dataset.id) })));
      assert.ok(roles.some(r => r.role === 'Напиток к блюду' && r.id === 102));
      assert.ok(roles.some(r => r.role === 'Десерт для компании' && r.id === 103));
      assert.ok(roles.every(r => ![104, 105].includes(r.id)), 'plant preference screens explicit animal ingredients in mains and pairings');
      await page.locator('[data-tp-evening-restart]').click();
      assert.equal(await page.locator('[data-tp-evening-booking]').isHidden(), true);
      assert.equal(await page.locator('[data-tp-evening-edit]').isHidden(), true);
      assert.deepEqual(errors, []); await page.close();
      console.log(`PASS: ${mode.name} dietary preference screening, guest validation, edit/reset/focus, pairing types, six widths, cart and booking`);
    }
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
