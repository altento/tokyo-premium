const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const evidence = path.resolve(process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '../project-docs/visual-qa/reviews-contacts-2026-11-19'));
const phpBase = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8158';
const staticBase = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8159/docs';
const report = { php: {}, static: {}, widths: [320, 390, 768, 1024, 1440, 1920] };

async function fill(page, type, body) {
  const form = page.locator(`form[data-api="${type}"]`);
  await form.locator('[name="name"]').fill('Учебный QA');
  if (type === 'contact') await form.locator('[name="email"]').fill('qa@example.invalid');
  await form.locator(`[name="${type === 'contact' ? 'message' : 'body'}"]`).fill(body);
  return form;
}
async function sent(form, outcome = 'success') {
  await form.locator(`.form-status.${outcome}`).waitFor();
  await form.page().waitForFunction(type => document.querySelector(`form[data-api="${type}"]`).dataset.submitting !== 'true', await form.getAttribute('data-api'));
}
async function capture(page, name) {
  await page.evaluate(async () => {
    await Promise.all([...document.images].filter(i => i.getAttribute('src')).map(i => { i.loading = 'eager'; return i.decode(); }));
    await document.fonts.ready;
  });
  const style = await page.addStyleTag({ content: '.site-header{position:relative!important;top:auto!important}.toast{display:none!important}' });
  await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: path.join(evidence, name), fullPage: true, quality: 77 });
  await style.evaluate(e => e.remove());
}
async function preserveWhilePending(page, type) {
  const original = 'Учебное сообщение отправляется, новая версия должна сохраниться.';
  const draft = 'Новый черновик во время отправки: ещё не отправлен.';
  const form = await fill(page, type, original);
  let release;
  const gate = new Promise(resolve => { release = resolve; });
  let arrived;
  const fetched = new Promise(resolve => { arrived = resolve; });
  await page.route('**/api.php', async route => {
    const response = await route.fetch();
    assert.equal(response.status(), 201);
    arrived();
    await gate;
    await route.fulfill({ response });
  }, { times: 1 });
  await form.locator('button[type="submit"]').click();
  await fetched;
  assert.equal(await form.locator('button[type="submit"]').isDisabled(), true);
  const textarea = form.locator('textarea');
  await textarea.fill(draft);
  release();
  await sent(form);
  assert.equal(await textarea.inputValue(), draft, `${type}: the newer unsent draft survives a delayed real PHP response`);
  assert.match(await form.locator('.form-status').innerText(), /пока не отправлены/);
  assert.equal(await form.locator('button[type="submit"]').isEnabled(), true);
}

(async () => {
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch();
  const errors = [];
  try {
    const page = await browser.newPage({ reducedMotion: 'reduce', viewport: { width: 1440, height: 960 } });
    page.on('pageerror', e => errors.push(e.message));
    for (const type of ['review', 'contact']) {
      await page.goto(`${phpBase}/${type === 'review' ? 'reviews.php' : 'pages.php?page=contacts'}`);
      await preserveWhilePending(page, type);
      report.php[`${type}PendingDraft`] = 'preserved';
      const form = await fill(page, type, 'Неизменённый текст после успешного сохранения должен очиститься.');
      await form.locator('button[type="submit"]').click();
      await sent(form);
      assert.equal(await form.locator('textarea').inputValue(), '');
      report.php[`${type}UneditedSuccess`] = 'reset';

      await fill(page, type, 'Учебный текст при ошибке остаётся доступен для повторной отправки.');
      await page.route('**/api.php', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, message: 'Учебная ошибка сети' }) }), { times: 1 });
      await form.locator('button[type="submit"]').click();
      await sent(form, 'error');
      assert.match(await form.locator('textarea').inputValue(), /при ошибке/);
      assert.equal(await form.locator('button[type="submit"]').isEnabled(), true);

      const csrf = await form.locator('[name="csrf"]').inputValue();
      const field = type === 'review' ? 'body' : 'message';
      const minimum = type === 'review' ? 20 : 5;
      for (const length of [minimum - 1, minimum, 1000, 1001]) {
        const data = { type, csrf, name: 'Учебный QA', email: 'qa@example.invalid', subject: 'Общий вопрос', rating: '5', theme: 'Кухня', [field]: 'я'.repeat(length) };
        const response = await page.request.post(`${phpBase}/api.php`, { form: data, headers: { 'X-CSRF-Token': csrf } });
        assert.equal(response.status(), length < minimum || length > 1000 ? 422 : 201, `${type}: Cyrillic length ${length}`);
      }
      report.php[`${type}UnicodeBoundaries`] = [minimum - 1, minimum, 1000, 1001];
    }

    await page.goto(`${staticBase}/contacts.html`);
    let form = await fill(page, 'contact', 'x');
    await form.locator('button[type="submit"]').click();
    assert.equal(await form.locator('textarea').evaluate(e => e.validity.tooShort), true);
    assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-demo-contacts')), null);
    await form.locator('textarea').fill('      ');
    await form.locator('button[type="submit"]').click();
    await sent(form, 'error');
    assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-demo-contacts')), null);
    await fill(page, 'contact', 'Корректное учебное сообщение, сохраняется только в этом браузере.');
    await form.locator('button[type="submit"]').click();
    await sent(form);
    assert.match(await form.locator('.form-status').innerText(), /не отправлено ресторану/);
    assert.equal(await form.locator('textarea').inputValue(), '');
    assert.equal(await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-contacts')).length), 1);
    report.static.contact = 'short and whitespace rejected; valid local message stored; form reset';
    await capture(page, 'contacts-desktop.jpg');

    await page.goto(`${staticBase}/reviews.html`);
    const publicAverage = await page.locator('.review-score-band strong').innerText();
    form = await fill(page, 'review', 'Учебный отзыв: <img src=x onerror=window.reviewXss=1> Это только локальная запись.');
    await form.locator('button[type="submit"]').click();
    await sent(form);
    await form.locator('[data-local-review-link]').click();
    assert.equal(await page.locator('#local-reviews').isVisible(), true);
    assert.equal(await page.locator('#local-reviews img').count(), 0);
    assert.equal(await page.evaluate(() => window.reviewXss || 0), 0);
    assert.equal(await page.locator('.review-score-band strong').innerText(), publicAverage);
    await page.reload();
    assert.equal(await page.locator('.local-review-card').count(), 1);

    // Simulate a topic whose last published record has been moderated away.
    await page.locator('[data-review-card][data-theme="Омакасе"]').evaluateAll(cards => cards.forEach(card => card.remove()));
    await page.locator('[data-review-filter="Омакасе"]').click();
    assert.equal(await page.locator('[data-review-card]:visible').count(), 0);
    assert.match(await page.locator('.review-results-status').innerText(), /пока нет/);
    assert.equal(await page.locator('[data-review-filter="Омакасе"]').getAttribute('aria-pressed'), 'true');
    await capture(page, 'reviews-empty-topic-desktop.jpg');
    await page.locator('[data-review-filter="all"]').click();
    await page.locator('#review-sort').selectOption('lowest');
    const ratings = await page.locator('[data-review-card]:visible').evaluateAll(cards => cards.map(card => Number(card.dataset.rating)));
    assert.deepEqual(ratings, [...ratings].sort((a, b) => a - b));
    report.static.reviews = 'local link, persistence, XSS safety, unchanged score, simulated empty topic, ARIA and sorting passed';

    for (const route of ['reviews', 'contacts']) {
      await page.goto(`${staticBase}/${route}.html`);
      for (const width of report.widths) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        const layout = await page.evaluate(() => ({
          document: document.documentElement.scrollWidth,
          clipped: [...document.querySelectorAll('main h1, main h2, main form input:not([type="hidden"]), main form textarea, main form select')].filter(e => e.getClientRects().length).filter(e => {
            const r = e.getBoundingClientRect();
            if (r.left < -1 || r.right > innerWidth + 1) return true;
            if (!e.matches('h1,h2')) return false;
            const range = document.createRange(); range.selectNodeContents(e);
            const fragments = [...range.getClientRects()];
            if (fragments.some(b => b.left < -1 || b.right > innerWidth + 1)) return true;
            for (let ancestor = e; ancestor; ancestor = ancestor.parentElement) {
              const s = getComputedStyle(ancestor), a = ancestor.getBoundingClientRect();
              if (fragments.some(b => (['hidden', 'clip'].includes(s.overflowX) && (b.left < a.left - 2 || b.right > a.right + 2)) || (['hidden', 'clip'].includes(s.overflowY) && (b.top < a.top - 2 || b.bottom > a.bottom + 2)))) return true;
            }
            return false;
          }).map(e => e.id || e.tagName)
        }));
        assert.ok(layout.document <= width + 1, `${route} at ${width}: page overflow`);
        assert.deepEqual(layout.clipped, [], `${route} at ${width}: visible headings and form controls fit`);
      }
      await page.setViewportSize({ width: 390, height: 844 });
      await capture(page, `${route}-mobile.jpg`);
      if (route === 'reviews') {
        await page.setViewportSize({ width: 1440, height: 960 });
        await capture(page, 'reviews-desktop.jpg');
        await page.getByRole('button', { name: 'Очистить мои отзывы' }).click();
        assert.equal(await page.locator('#local-reviews').isHidden(), true);
        assert.equal(await page.locator('.review-score-band strong').innerText(), publicAverage);
      }
    }
    assert.deepEqual(errors, []);
    report.pageErrors = errors;
    report.result = 'passed';
    fs.writeFileSync(path.join(evidence, 'form-regression.json'), JSON.stringify(report, null, 2) + '\n');
    console.log('PASS: PHP delayed drafts, reset, errors and Unicode boundaries; static validation, local reviews, empty topics and six widths.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
