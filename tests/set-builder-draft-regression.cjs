const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');

const phpBase = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8100';
const staticBase = process.env.TOKYO_STATIC_BASE_URL || `${phpBase}/docs`;
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '../project-docs/visual-qa/set-builder-2026-11-19');

async function checkMode(browser, mode) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  page.setDefaultTimeout(7000);
  await page.goto(`${mode.base}${mode.route}`, { waitUntil: 'domcontentloaded' });
  assert.equal(await page.locator('[data-tp-set-builder]').count(), 1, `${mode.name}: builder route loaded`);

  await page.evaluate(() => {
    localStorage.removeItem('tokyo-custom-set-draft-v1:signature');
    window.TokyoCart?.clear();
  });
  await page.reload({ waitUntil: 'domcontentloaded' });

  const cards = page.locator('[data-tp-set-dish]');
  assert.ok(await cards.count() >= 3, `${mode.name}: at least three selectable catalog dishes`);
  for (let index = 0; index < 3; index++) await cards.nth(index).locator('[data-tp-set-step="1"]').click();
  await cards.first().locator('[data-tp-set-step="1"]').click();

  const draftBeforeReload = await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-custom-set-draft-v1:signature') || '[]'));
  assert.equal(draftBeforeReload.length, 3, `${mode.name}: draft stores three dish IDs`);
  assert.equal(draftBeforeReload[0].qty, 2, `${mode.name}: draft stores selected quantities`);
  const expectedTotal = await page.evaluate(rows => rows.reduce((sum, row) => sum + Number(window.DISHES.find(d => Number(d.id) === row.id).price) * row.qty, 0), draftBeforeReload);
  const summaryTotal = () => page.locator('[data-tp-set-total]').innerText().then(text => Number(text.replace(/\D/g, '')));
  assert.equal(await summaryTotal(), expectedTotal, `${mode.name}: sidebar matches the selected catalogue prices`);
  assert.equal(await page.locator('[data-tp-set-progress]').getAttribute('aria-valuenow'), '4', `${mode.name}: progress counts selected portions`);
  assert.match(await page.locator('[data-tp-set-count]').innerText(), /3.*4/, `${mode.name}: summary counts distinct dishes and portions`);
  await page.reload({ waitUntil: 'domcontentloaded' });
  assert.equal(await cards.first().locator('[data-tp-set-qty]').innerText(), '2', `${mode.name}: quantity restores after refresh`);
  assert.match(await page.locator('[data-tp-status]').innerText(), /Черновик восстановлен/, `${mode.name}: restored state is explained to the guest`);
  assert.equal(await summaryTotal(), expectedTotal, `${mode.name}: summary restores with the saved draft`);

  await cards.first().locator('[data-tp-set-step="1"]').click();
  await cards.first().locator('[data-tp-set-step="1"]').click();
  assert.equal(await cards.first().locator('[data-tp-set-step="1"]').isDisabled(), true, 'per-dish limit disables increment');
  for (const index of [3, 4]) await cards.nth(index).locator('[data-tp-set-step="1"]').click();
  assert.equal(await page.locator('[data-tp-set-step="1"]:enabled').count(), 0, 'portion limit disables further additions');
  await cards.first().locator('[data-tp-set-step="-1"]').click();
  assert.equal(await cards.first().locator('[data-tp-set-step="1"]').isEnabled(), true, 'decrement reopens valid additions');
  assert.equal(await cards.nth(5).locator('[data-tp-set-step="1"]').isDisabled(), true, 'distinct-dish limit blocks a new ingredient');
  await page.locator('[data-tp-set-clear]').click();
  assert.equal(await summaryTotal(), 0);
  assert.equal(await page.locator('[data-tp-set-progress]').getAttribute('aria-valuenow'), '0');
  assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-custom-set-draft-v1:signature')), null);
  assert.equal(await page.locator('[data-tp-set-add]').isDisabled(), true);
  assert.equal(await page.locator('[data-tp-set-clear]').isDisabled(), true);
  for (let index = 0; index < 3; index++) await cards.nth(index).locator('[data-tp-set-step="1"]').click();
  await cards.first().locator('[data-tp-set-step="1"]').click();

  for (const width of [320, 390, 768, 1024, 1440, 1920]) {
    await page.setViewportSize({ width, height: 960 });
    assert.equal(await summaryTotal(), expectedTotal);
    assert.equal(await page.locator('[data-tp-set-add]').count(), 1, 'only one add-set action exists');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${mode.name}: no document overflow at ${width}`);
    assert.deepEqual(await page.evaluate(() => [...document.querySelectorAll('main h1,main h2')].filter(e => {
      const r = document.createRange(); r.selectNodeContents(e); const box = e.getBoundingClientRect();
      return [...r.getClientRects()].some(t => t.left < Math.max(0, box.left) - 2 || t.right > Math.min(innerWidth, box.right) + 2);
    }).map(e => e.textContent)), [], `${mode.name}: heading text bounds at ${width}`);
    if (width <= 900) {
      const jump = page.locator('.tp-set-mobile-summary');
      assert.ok(await jump.isVisible());
      assert.equal(Number((await jump.locator('strong').innerText()).replace(/\D/g, '')), expectedTotal);
      await cards.first().scrollIntoViewIfNeeded();
      if (mode.name === 'Pages' && width === 390) await page.screenshot({ path: path.join(evidence, 'builder-mobile-controls.jpg'), quality: 80 });
      await jump.click();
      await page.waitForFunction(() => { const r = document.querySelector('[data-tp-set-add]').getBoundingClientRect(); return r.top >= 0 && r.bottom <= innerHeight; });
      const nav = page.locator('.mobile-menu');
      await nav.click(); assert.equal(await jump.isHidden(), true, 'summary bar does not cover open navigation');
      if (mode.name === 'Pages' && width === 390) await page.screenshot({ path: path.join(evidence, 'builder-menu-open.jpg'), quality: 80 });
      await page.keyboard.press('Escape'); assert.equal(await jump.isVisible(), true);
    }
    if (mode.name === 'Pages' && [390, 1440].includes(width)) {
      await page.evaluate(async () => { await Promise.all([...document.images].filter(i => i.getAttribute('src')).map(i => { i.loading = 'eager'; return i.decode(); })); await document.fonts.ready; });
      const style = await page.addStyleTag({ content: '.site-header{position:relative!important;top:auto!important}.tp-set-mobile-summary{display:none!important}' });
      await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
      await page.screenshot({ path: path.join(evidence, `builder-${width}.jpg`), fullPage: true, quality: 78 });
      await style.evaluate(e => e.remove());
    }
  }

  await page.locator('[data-tp-set-add]').click();
  try {
    await page.waitForFunction(() => document.querySelector('[data-tp-status]')?.textContent.includes('Сет добавлен'));
  } catch (_) {
    const message = await page.locator('[data-tp-status]').innerText();
    throw new Error(`${mode.name}: set was not accepted; status was: ${message}`);
  }
  const result = await page.evaluate(() => ({
    cartSets: window.TokyoCart?.getCustomSets?.().length || 0,
    draft: localStorage.getItem('tokyo-custom-set-draft-v1:signature'),
    selected: [...document.querySelectorAll('[data-tp-set-qty]')].some(output => output.textContent !== '0')
  }));
  assert.equal(result.cartSets, 1, `${mode.name}: validated draft enters the shared cart once`);
  assert.equal(result.draft, null, `${mode.name}: successful transfer clears the draft`);
  assert.equal(result.selected, false, `${mode.name}: builder resets after successful transfer`);
  assert.equal(await summaryTotal(), 0, `${mode.name}: transferred draft leaves no stale total`);
  await cards.first().locator('[data-tp-set-step="1"]').click();
  await page.locator('[data-tp-set-clear]').click();
  assert.equal(await page.evaluate(() => TokyoCart.getCustomSets().length), 1, 'clearing a draft preserves the accepted cart set');
  await page.evaluate(() => localStorage.setItem('tokyo-custom-set-draft-v1:signature', JSON.stringify([{ id: -1, qty: 1 }])));
  await page.reload({ waitUntil: 'domcontentloaded' });
  assert.equal(await summaryTotal(), 0, 'invalid saved identifiers cannot populate the summary');
  await context.close();
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  fs.mkdirSync(evidence, { recursive: true });
  try {
    await checkMode(browser, { name: 'PHP', base: phpBase, route: '/set-builder.php' });
    await checkMode(browser, { name: 'Pages', base: staticBase, route: '/set-builder.html' });
    console.log('PASS: PHP/static set draft, live summary, price/progress, limits, clear, transfer and six-width layouts.');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
