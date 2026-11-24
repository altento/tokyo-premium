const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const staticBase = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8765';

async function getRecommendationTitles(page, occasion, preference, atmosphere = 'main') {
  await page.goto(`${staticBase}/perfect-evening.html`, { waitUntil: 'domcontentloaded' });
  const choices = [
    ['occasion', occasion],
    ['preference', preference],
    null,
    null,
    ['atmosphere', atmosphere]
  ];
  for (let index = 0; index < choices.length; index++) {
    const step = page.locator('[data-tp-evening-step]').nth(index);
    const choice = choices[index];
    if (choice) await step.locator(`[data-tp-evening-option][data-field="${choice[0]}"][value="${choice[1]}"]`).click();
    else await step.locator('[data-tp-evening-input]').fill(index === 2 ? '10000' : '2');
    await step.locator('[data-tp-evening-next]').click();
  }
  return page.locator('.tp-recommendation-card h4').allInnerTexts();
}

async function main() {
  assert.equal(
    fs.readFileSync(path.join(root, 'experience-ui.js'), 'utf8'),
    fs.readFileSync(path.join(root, 'docs', 'experience-ui.js'), 'utf8'),
    'static export uses the same experience logic as PHP'
  );
  const evidenceDir = path.join(root, 'project-docs', 'visual-qa');
  fs.mkdirSync(evidenceDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const pageErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  try {
    const offersResponse = await page.goto(`${staticBase}/offers.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(offersResponse.status(), 200, 'static offers route loads');
    const offersHtml = await page.locator('body').innerText();
    assert.doesNotMatch(offersHtml, /Undefined array key|<b>Warning<\/b>/, 'static offers contain no leaked PHP warning');
    assert.ok(await page.locator('.tp20-offer-card').count() > 0, 'offers retain their rendered campaign card');
    assert.match(offersHtml, /локальную корзину и предварительный расчёт в браузере/, 'static offer copy describes local promo behavior');
    assert.match(offersHtml, /серверной проверки скидки и итоговой стоимости нет/, 'static offer copy does not promise server validation');

    const builderResponse = await page.goto(`${staticBase}/set-builder.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(builderResponse.status(), 200, 'static set-builder route loads');
    const builder = page.locator('[data-tp-set-builder]');
    assert.match(await page.locator('body').innerText(), /локальная оценка без серверной проверки/, 'static set builder explains price limitations');
    await builder.locator('[data-tp-set-dish]').first().locator('[data-tp-set-step="1"]').click();
    assert.match(await page.locator('[data-tp-status]').innerText(), /стоимость — локальная оценка без серверной проверки/, 'set-builder interaction keeps static pricing disclosure');

    const eveningResponse = await page.goto(`${staticBase}/perfect-evening.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(eveningResponse.status(), 200, 'static recommendation route loads');
    const choices = [
      ['occasion', 'romantic'],
      ['preference', 'fish'],
      null,
      null,
      ['atmosphere', 'main']
    ];
    for (let index = 0; index < choices.length; index++) {
      const step = page.locator('[data-tp-evening-step]').nth(index);
      const choice = choices[index];
      if (choice) {
        await step.locator(`[data-tp-evening-option][data-field="${choice[0]}"][value="${choice[1]}"]`).click();
      } else {
        await step.locator('[data-tp-evening-input]').fill(index === 2 ? '10000' : '3');
      }
      await step.locator('[data-tp-evening-next]').click();
    }
    assert.equal(await page.locator('[data-tp-evening-result]').isVisible(), true, 'recommendation result appears');
    assert.ok(await page.locator('.tp-recommendation-card').count() > 0, 'recommendations use current catalog items');
    assert.ok(await page.locator('.tp-evening-plan-card').count() >= 2, 'recommendation is a composed meal plan, not three unrelated dishes');
    assert.ok(await page.locator('.tp-evening-plan-card[data-role="Напиток к блюду"]').count() === 1, 'plan includes a real catalogue drink pairing');
    assert.equal(await page.locator('[data-tp-evening-add-plan]').count(), 1, 'one action adds the complete recommendation');
    assert.match(await page.locator('.tp-evening-estimate').innerText(), /не проверяется сервером/, 'static recommendation labels its total as a browser estimate');
    const summary = await page.locator('.tp-evening-budget-summary').innerText();
    assert.match(summary, /10[\s\u00a0\u202f]*000.+на гостя/, 'summary shows the per-guest budget');
    assert.match(summary, /30[\s\u00a0\u202f]*000.+на компанию/, 'summary shows the multiplied group allowance');
    assert.equal(await page.locator('.tp-budget-note').count(), 0, 'a recommendation under the group allowance is not falsely flagged');
    await page.evaluate(() => window.TokyoCart.clear());
    const expectedPlan = await page.locator('[data-tp-evening-plan-line]').evaluateAll(nodes => nodes.map(node => ({ id: Number(node.dataset.id), qty: Number(node.dataset.qty) })));
    const expectedTotal = await page.evaluate(lines => lines.reduce((sum, line) => sum + Number(window.DISHES.find(dish => Number(dish.id) === line.id)?.price || 0) * line.qty, 0), expectedPlan);
    await page.locator('[data-tp-evening-add-plan]').click();
    assert.deepEqual(await page.evaluate(() => window.TokyoCart.get()), expectedPlan, 'complete meal plan transfers all quantities to the shared cart');
    assert.equal(await page.evaluate(() => window.TokyoCart.total()), expectedTotal, 'the shared cart calculates the same plan estimate from catalogue prices');
    assert.equal(await page.locator('[data-tp-evening-add-plan]').isDisabled(), true, 'the batch-add button prevents accidental repeated submission');
    const bookingHref = await page.locator('[data-tp-evening-booking]').getAttribute('href');
    assert.equal(new URL(bookingHref, staticBase).searchParams.get('zone'), 'Основной зал', 'booking action keeps the selected zone');
    assert.equal(new URL(bookingHref, staticBase).searchParams.get('guests'), '3', 'booking action carries the selected guest count');
    assert.match(bookingHref, /experience=/, 'booking action carries a concise menu summary');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.waitForTimeout(80);
    await page.screenshot({ path: path.join(evidenceDir, 'ideal-evening-budget-desktop-2026-11-16.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: path.join(evidenceDir, 'ideal-evening-budget-mobile-2026-11-16.png'), fullPage: true });

    await page.setViewportSize({ width: 1280, height: 900 });
    await page.locator('[data-tp-evening-restart]').click();
    assert.equal(await page.locator('[data-tp-evening-result]').isVisible(), false, 'restart hides the old recommendation');
    assert.equal(await page.locator('[data-tp-evening-step]').nth(0).isVisible(), true, 'restart returns to the first step');
    assert.equal(await page.locator('[data-tp-evening-step]').nth(0).locator('[data-tp-evening-next]').isDisabled(), true, 'restart requires a new occasion choice');
    assert.equal(await page.locator('[data-tp-evening-step]').nth(1).locator('[data-tp-evening-next]').isDisabled(), true, 'restart requires a new taste choice');
    assert.equal(await page.locator('#evening-budget').inputValue(), '20000', 'restart restores the default per-guest budget');
    assert.equal(await page.locator('#evening-guests').inputValue(), '2', 'restart restores the default guest count');
    await page.locator('[data-tp-evening-step]').nth(0).locator('[data-tp-evening-option][value="romantic"]').click();
    assert.equal(await page.locator('[data-tp-evening-step]').nth(0).locator('[data-tp-evening-next]').isDisabled(), false, 'choosing an occasion re-enables its continue button');
    await page.locator('[data-tp-evening-step]').nth(0).locator('[data-tp-evening-next]').click();
    assert.equal(await page.locator('[data-tp-evening-step]').nth(1).locator('[data-tp-evening-next]').isDisabled(), true, 'the taste step remains gated after restart');

    const romanticRecommendations = await getRecommendationTitles(page, 'romantic', 'plant');
    const celebrationRecommendations = await getRecommendationTitles(page, 'celebration', 'meat');
    assert.notDeepEqual(romanticRecommendations, celebrationRecommendations, 'romantic and special-occasion answers change the same-preference recommendations');

    await getRecommendationTitles(page, 'discovery', 'sets', 'omakase');
    assert.equal(await page.locator('.tp-evening-plan-card[data-role="Сет для компании"]').count(), 1, 'Omakase atmosphere recommends a real catalog set');
    assert.equal(await page.locator('.tp-evening-set-card').getAttribute('data-variant'), 'omakase', 'recommended set uses the active SQLite Omakase variant');
    await page.evaluate(() => window.TokyoCart.clear());
    await page.locator('[data-tp-evening-add-plan]').click();
    const savedSet = await page.evaluate(() => ({ sets: window.TokyoCart.getCustomSets(), total: window.TokyoCart.total() }));
    assert.equal(savedSet.sets.length, 1, 'complete recommendation enters the shared cart as one custom set');
    assert.equal(savedSet.sets[0].setType, 'omakase', 'custom set preserves the server-defined variant slug');
    assert.ok(savedSet.sets[0].items.length >= 5 && savedSet.total > 0, 'Omakase composition includes the required distinct catalog dishes and has a computed estimate');
    const omakaseBookingHref = await page.locator('[data-tp-evening-booking]').getAttribute('href');
    assert.match(omakaseBookingHref, /format=/, 'selected Omakase set format is transferred to booking');
    await page.locator('[data-tp-evening-booking]').click();
    assert.match(new URL(page.url()).pathname, /booking\.html$/, 'booking opens after transferring an evening recommendation');
    await page.waitForFunction(() => document.querySelector('#booking-format')?.value);
    assert.ok(await page.locator('#booking-format').inputValue(), 'static booking form receives the selected Omakase format');
    assert.equal(await page.locator('#full-booking-form [name="guests"]').inputValue(), '2', 'static booking form receives the guest count from the recommendation');
    assert.match(await page.locator('#booking-note').inputValue(), /Подбор меню:/, 'static booking form receives the selected catalogue items as an editable note');
    await page.locator('#booking-form').scrollIntoViewIfNeeded();
    await page.waitForTimeout(900);
    await page.screenshot({ path: path.join(evidenceDir, 'ideal-evening-booking-prefilled-2026-11-18.png'), type: 'png' });

    await page.goto(`${staticBase}/booking.html?guests=99`, { waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('#full-booking-form [name="guests"]').inputValue(), '2', 'invalid guest counts are ignored by the booking prefill');

    assert.deepEqual(pageErrors, [], 'static routes produce no browser runtime errors');
    console.log('Focused Offers + Ideal Evening regression passed (static browser, per-guest budget and warning leakage).');
  } finally {
    await browser.close();
  }
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
