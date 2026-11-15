const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const phpBase = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8100';
const staticBase = process.env.TOKYO_STATIC_BASE_URL || `${phpBase}/docs`;

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
  await page.reload({ waitUntil: 'domcontentloaded' });
  assert.equal(await cards.first().locator('[data-tp-set-qty]').innerText(), '2', `${mode.name}: quantity restores after refresh`);
  assert.match(await page.locator('[data-tp-status]').innerText(), /Черновик восстановлен/, `${mode.name}: restored state is explained to the guest`);

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
  await context.close();
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    await checkMode(browser, { name: 'PHP', base: phpBase, route: '/set-builder.php' });
    await checkMode(browser, { name: 'Pages', base: staticBase, route: '/set-builder.html' });
    console.log('Set-builder draft: 16 focused assertions passed across PHP and static modes.');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
