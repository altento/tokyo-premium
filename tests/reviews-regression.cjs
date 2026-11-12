const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const evidence = path.resolve(process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '..', 'project-docs', 'visual-qa'));

async function run(options = {}) {
  const { chromium } = options.chromium ? { chromium: options.chromium } : require('playwright');
  const base = options.base || process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8090';
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/docs/reviews.html`, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => localStorage.removeItem('tokyo-demo-reviews'));
    await page.reload();
    const publicAverage = await page.locator('.review-score-band strong').innerText();

    const hostileName = '<img src=x onerror=window.__reviewXss=1> QA';
    const hostileBody = 'Интерфейс понравился. <img src=x onerror=window.__reviewXss=2> Очень уютно.';
    await page.locator('#review-name').fill(hostileName);
    await page.locator('#review-theme').selectOption('Омакасе');
    await page.locator('#rating').selectOption('4');
    await page.locator('#review-body').fill(hostileBody);
    await page.locator('.review-form button[type="submit"]').click();
    await page.locator('#local-reviews').waitFor({ state: 'visible' });
    assert.match(await page.locator('.review-form .form-status').innerText(), /сохранён в этом браузере/i);
    assert.equal(await page.locator('#local-reviews .local-review-card').count(), 1);
    assert.equal(await page.locator('#local-reviews .local-review-meta span').innerText(), 'Омакасе', 'local review preserves its selected theme');
    assert.equal(await page.locator('#local-reviews img').count(), 0, 'local review content is text only, no injected image');
    assert.equal(await page.evaluate(() => window.__reviewXss || 0), 0, 'untrusted markup did not execute');
    assert.equal((await page.locator('#local-reviews .local-review-body').innerText()), hostileBody);
    assert.equal(await page.locator('.review-score-band strong').innerText(), publicAverage, 'local rating does not alter public average');
    await page.screenshot({ path: path.join(evidence, '08-reviews-local-desktop.jpg'), fullPage: true, type: 'jpeg', quality: 70 });

    await page.reload();
    assert.equal(await page.locator('#local-reviews .local-review-card').count(), 1, 'local review survives reload');
    await page.getByRole('button', { name: 'Очистить мои отзывы' }).click();
    assert.equal(await page.locator('#local-reviews').isHidden(), true, 'clear action removes local reviews');
    assert.equal(await page.locator('.review-score-band strong').innerText(), publicAverage, 'clear preserves public aggregate');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => {
      localStorage.setItem('tokyo-demo-reviews', JSON.stringify([{ name: 'Гость', text: 'Хороший демонстрационный ужин, хочу сохранить заметку.', rating: 5, theme: 'Кухня', date: '2026-11-12' }]));
    });
    await page.reload();
    assert.equal(await page.locator('#local-reviews .local-review-card').count(), 1, 'mobile local review is visible');
    await page.screenshot({ path: path.join(evidence, '08-reviews-local-mobile.jpg'), fullPage: true, type: 'jpeg', quality: 70 });
    assert.equal(errors.length, 0, errors.join('\n'));
    console.log('PASS: local review submit, persistence, XSS-safe rendering, clear action, and unchanged public score.');
  } finally {
    await browser.close();
  }
}

module.exports = run;
if (require.main === module) run().catch(error => { console.error(error); process.exitCode = 1; });
