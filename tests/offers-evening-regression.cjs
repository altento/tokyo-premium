const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const staticBase = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8765';

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
    const summary = await page.locator('.tp-evening-budget-summary').innerText();
    assert.match(summary, /10[\s\u00a0\u202f]*000.+на гостя/, 'summary shows the per-guest budget');
    assert.match(summary, /30[\s\u00a0\u202f]*000.+на компанию/, 'summary shows the multiplied group allowance');
    assert.equal(await page.locator('.tp-budget-note').count(), 0, 'a recommendation under the group allowance is not falsely flagged');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.waitForTimeout(80);
    await page.screenshot({ path: path.join(evidenceDir, 'ideal-evening-budget-desktop-2026-11-16.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: path.join(evidenceDir, 'ideal-evening-budget-mobile-2026-11-16.png'), fullPage: true });
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
