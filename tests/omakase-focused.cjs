const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '../project-docs/visual-qa/omakase-2026-11-19');
const availableModes = [
  { name: 'php', base: process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8098', route: '/pages.php?page=omakase' },
  { name: 'static', base: process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8099/docs', route: '/omakase.html' }
];
const modes = availableModes.filter(mode => !process.env.TOKYO_OMAKASE_MODE || mode.name === process.env.TOKYO_OMAKASE_MODE.toLowerCase());
const widths = (process.env.TOKYO_OMAKASE_WIDTHS || '320,390,768,1024,1440,1920').split(',').map(Number).filter(Number.isFinite);
async function capture(page, filename) {
  await page.evaluate(async () => {
    await Promise.all([...document.images].filter(i => i.getAttribute('src')).map(i => { i.loading = 'eager'; return i.decode(); }));
    await document.fonts.ready;
  });
  const style = await page.addStyleTag({ content: '.site-header{position:relative!important;top:auto!important}' });
  await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
  await page.screenshot({ path: path.join(evidence, filename), fullPage: true, type: 'jpeg', quality: 80 });
  await style.evaluate(e => e.remove());
}
(async () => {
  assert.ok(modes.length, 'TOKYO_OMAKASE_MODE must be php or static');
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch();
  try {
    for (const mode of modes) {
      const page = await browser.newPage({ reducedMotion: 'reduce' });
      const errors = [], badResponses = [];
      page.on('pageerror', e => errors.push(e.message));
      page.on('response', r => { if (r.status() >= 400) badResponses.push(r.status() + ' ' + r.url()); });
      for (const width of widths) {
        await page.setViewportSize({ width, height: 960 });
        assert.equal((await page.goto(mode.base + mode.route)).status(), 200);
        await page.waitForSelector('[data-tp-course][role="tab"]');
        const tabs = page.locator('[data-tp-course]'), previous = page.locator('[data-tp-course-prev]'), next = page.locator('[data-tp-course-next]');
        assert.equal(await tabs.count(), 5);
        const sources = await page.locator('.tp20-course-photo img').evaluateAll(images => images.map(i => i.getAttribute('src')));
        assert.equal(new Set(sources).size, 5, 'each course has a distinct catalog photograph');
        assert.equal(await previous.isDisabled(), true);
        for (let step = 2; step <= 5; step++) {
          await next.click();
          assert.equal(await page.locator('[data-tp-course-panel]:visible').getAttribute('data-step'), String(step));
          assert.equal(await page.locator('[data-tp-omakase-progress]').getAttribute('aria-valuenow'), String(step));
          assert.match(await page.locator('[data-tp-course-current]').innerText(), new RegExp(`${step}.*5`));
        }
        assert.equal(await next.isDisabled(), true);
        await previous.click();
        assert.equal(await page.locator('[data-tp-course-panel]:visible').getAttribute('data-step'), '4');
        await tabs.nth(3).focus(); await page.keyboard.press('Home');
        assert.equal(await tabs.first().getAttribute('aria-selected'), 'true');
        await page.keyboard.press('End');
        assert.equal(await tabs.last().getAttribute('aria-selected'), 'true');
        if (width <= 390) {
          assert.ok(await tabs.last().evaluate(e => { const a = e.getBoundingClientRect(), b = e.parentElement.getBoundingClientRect(); return a.left >= b.left - 2 && a.right <= b.right + 2; }), 'keyboard keeps selected thumbnail visible in the mobile strip');
        }
        await page.keyboard.press('Home'); await page.keyboard.press('ArrowRight');
        assert.equal(await page.locator('[data-tp-course-panel]:visible').getAttribute('data-step'), '2');
        const bounds = await page.evaluate(() => ({
          overflow: document.documentElement.scrollWidth > innerWidth,
          clipped: [...document.querySelectorAll('main h1,main h2,[data-tp-course-panel]:not([hidden]) h3')].filter(e => {
            const range = document.createRange(); range.selectNodeContents(e); const box = e.getBoundingClientRect();
            return [...range.getClientRects()].some(r => r.left < Math.max(0, box.left) - 2 || r.right > Math.min(innerWidth, box.right) + 2);
          }).map(e => e.textContent)
        }));
        assert.equal(bounds.overflow, false, `${mode.name} overflow at ${width}`);
        assert.deepEqual(bounds.clipped, [], `${mode.name} clipped text at ${width}`);
        assert.equal(await page.locator('[data-tp-course-panel]:visible img').evaluate(i => getComputedStyle(i).animationName), 'none', 'reduced motion removes course image animation');
        if (mode.name === 'static' && [390, 1440].includes(width)) await capture(page, `omakase-${width}.jpg`);
      }
      for (let index = 0; index < 3; index++) {
        await page.goto(mode.base + mode.route);
        const link = page.locator('[data-tp-omakase-select]').nth(index), format = await link.getAttribute('data-format');
        await link.click();
        await page.waitForFunction(expected => document.querySelector('#booking-format')?.value === expected, format);
        assert.equal(await page.locator('#booking-format').inputValue(), format, `${mode.name} tier selection reaches booking`);
        await page.reload();
        await page.waitForFunction(expected => document.querySelector('#booking-format')?.value === expected, format);
        assert.equal(await page.locator('#booking-format').inputValue(), format, `${mode.name} format survives refresh`);
      }
      await page.goto(mode.base + mode.route);
      await page.locator('.omakase-hero .button').first().click();
      assert.equal(new URL(page.url()).hash, '#omakase-tiers');
      await page.emulateMedia({ reducedMotion: 'no-preference' });
      await page.locator('[data-tp-course]').last().click();
      assert.equal(await page.locator('[data-tp-course-panel]:visible').getAttribute('data-step'), '5', 'normal motion preserves interaction');
      assert.deepEqual(errors, []); assert.deepEqual(badResponses, []);
      await page.close();
      console.log(`PASS: ${mode.name} five courses, sequential/keyboard controls, three booking formats, refresh, widths ${widths.join(', ')} and reduced motion`);
    }
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
