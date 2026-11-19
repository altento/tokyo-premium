const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.resolve('project-docs/visual-qa/gallery-loading-2026-11-19');
const modes = [
  {name:'php', base:process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8176', route:'/pages.php?page=gallery'},
  {name:'static', base:process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8177/docs', route:'/gallery.html'}
].filter(mode => !process.env.TOKYO_GALLERY_LOADING_MODE || process.env.TOKYO_GALLERY_LOADING_MODE === mode.name);
const photo = fs.readFileSync(path.resolve('assets/generated/room-main-hall.webp'));
const waitState = (page, value) => page.waitForFunction(value => document.querySelector('.image-lightbox').dataset.tpGalleryState === value, value);
async function capture(page, name, fullPage = false) {
  await page.screenshot({path:path.join(evidence, name), fullPage, type:'jpeg', quality:78});
}
(async () => {
  fs.mkdirSync(evidence, {recursive:true});
  const browser = await chromium.launch(), results = [];
  try {
    for (const mode of modes) {
      const page = await browser.newPage({viewport:{width:1440,height:960}, reducedMotion:'reduce'}), errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.goto(mode.base + mode.route);
      await page.evaluate(async () => {
        await Promise.all([...document.images].filter(image => image.getAttribute('src')).map(image => {image.loading='eager'; return image.decode()}));
        await document.fonts.ready;
      });
      const dialog = page.locator('.image-lightbox'), first = page.locator('#gallery-grid .gallery-tile').first();
      await first.click(); await waitState(page, 'ready');
      assert.ok(await dialog.locator('img').evaluate(image => image.naturalWidth > 0 && !image.hidden));
      await capture(page, `${mode.name}-photo-desktop.jpg`);
      await page.keyboard.press('Escape');
      let calls = 0;
      await page.route('**/__gallery-fixture-retry.webp', route => ++calls === 1 ? route.abort('failed') : route.fulfill({contentType:'image/webp',body:photo}));
      await first.evaluate(el => el.dataset.lightbox='/__gallery-fixture-retry.webp');
      await first.click(); await waitState(page, 'error');
      assert.equal(calls, 1, 'one owner issues one photo request');
      assert.equal(await dialog.locator('img').isVisible(), false, 'previous photograph is never shown below a new caption');
      assert.match(await dialog.getByRole('status').innerText(), /Не удалось загрузить/);
      await capture(page, `${mode.name}-error-desktop.jpg`);
      const retry = dialog.locator('[data-tp-gallery-retry]');
      await retry.focus(); await page.keyboard.press('Enter'); await waitState(page, 'ready');
      assert.equal(calls, 2); assert.equal(await dialog.locator('.lightbox-close').evaluate(el => document.activeElement === el), true);
      await page.keyboard.press('Escape');
      let release, started;
      const received = new Promise(resolve => started=resolve), gate = new Promise(resolve => release=resolve);
      await page.route('**/__gallery-fixture-slow.webp', async route => {started(); await gate; await route.fulfill({contentType:'image/webp',body:photo})});
      await first.evaluate(el => el.dataset.lightbox='/__gallery-fixture-slow.webp');
      await first.click(); await received; await waitState(page, 'loading');
      assert.equal(await dialog.locator('.tp-gallery-stage').getAttribute('aria-busy'), 'true');
      await capture(page, `${mode.name}-loading-desktop.jpg`);
      await page.keyboard.press('ArrowRight'); await waitState(page, 'ready');
      const selected = await dialog.locator('img').getAttribute('src');
      release(); await page.waitForResponse(response => response.url().endsWith('/__gallery-fixture-slow.webp'));
      await page.waitForTimeout(60);
      assert.equal(await dialog.locator('img').getAttribute('src'), selected, 'late image cannot replace a newer selection');
      assert.equal((await dialog.locator('[data-tp-gallery-caption]').innerText()).trim(), 'Бар шефа');
      await page.keyboard.press('Escape');
      let releaseClosed, startedClosed;
      const closedReceived = new Promise(resolve => startedClosed=resolve), closedGate = new Promise(resolve => releaseClosed=resolve);
      await page.route('**/__gallery-fixture-closed.webp', async route => {startedClosed(); await closedGate; await route.fulfill({contentType:'image/webp',body:photo})});
      await first.evaluate(el => el.dataset.lightbox='/__gallery-fixture-closed.webp');
      await first.click(); await closedReceived; await page.keyboard.press('Escape');
      releaseClosed(); await page.waitForResponse(response => response.url().endsWith('/__gallery-fixture-closed.webp'));
      assert.equal(await dialog.evaluate(el => el.open), false, 'a late response cannot reopen the dialog');
      assert.equal(await first.evaluate(el => document.activeElement === el), true);
      await page.route('**/__gallery-fixture-error.webp', route => route.abort('failed'));
      for (const width of [320,390,768,1024,1440,1920]) {
        await page.setViewportSize({width,height:900});
        await first.evaluate(el => el.dataset.lightbox='/__gallery-fixture-error.webp');
        await first.click(); await waitState(page, 'error');
        for (const selector of ['[role=status]', '[data-tp-gallery-retry]', '.lightbox-close', '[data-tp-gallery-prev]', '[data-tp-gallery-next]', '[data-tp-gallery-caption]']) {
          assert.ok(await dialog.locator(selector).evaluate(el => {
            const r=el.getBoundingClientRect(), box=el.closest('dialog').getBoundingClientRect();
            return r.left>=Math.max(0,box.left)-1 && r.right<=Math.min(innerWidth,box.right)+1 && r.top>=Math.max(0,box.top)-1 && r.bottom<=Math.min(innerHeight,box.bottom)+1 && el.scrollWidth<=el.clientWidth+1;
          }), `${mode.name}: ${selector} is visible without clipping at ${width}`);
        }
        if (width===390) await capture(page, `${mode.name}-error-mobile.jpg`);
        await page.keyboard.press('ArrowRight'); await waitState(page, 'ready');
        assert.ok(await dialog.locator('img').evaluate(el => {const r=el.getBoundingClientRect();return r.width>0&&r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight}), 'recovery image fits');
        if (width===390) await capture(page, `${mode.name}-photo-mobile.jpg`);
        await page.keyboard.press('Escape');
      }
      const room = page.locator('#spaces .zone-card').nth(1);
      await room.locator('.lightbox-trigger').click(); await waitState(page,'ready');
      await page.keyboard.press('Escape'); await room.locator('.zone-booking-link').click();
      assert.equal(await page.locator('#full-booking-form [name=zone]').inputValue(), 'Основной зал', 'room booking keeps the selected zone');
      await page.goto(mode.base + mode.route);
      await page.evaluate(async()=>{await Promise.all([...document.images].filter(i=>i.getAttribute('src')).map(i=>{i.loading='eager';return i.decode()}));await document.fonts.ready});
      const style=await page.addStyleTag({content:'.site-header{position:relative!important;top:auto!important}'});
      await page.setViewportSize({width:1440,height:960}); await capture(page,`${mode.name}-gallery-desktop.jpg`,true);
      await page.setViewportSize({width:390,height:844}); await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
      await capture(page,`${mode.name}-gallery-mobile.jpg`,true); await style.evaluate(el=>el.remove());
      assert.deepEqual(errors, []);
      results.push({mode:mode.name,passed:true,widths:[320,390,768,1024,1440,1920],scenarios:['single request','failure and keyboard retry','hidden stale photograph','late navigation response','close during load','controls and text bounds','room booking zone','full-page captures']});
      await page.close();
      const fallback=await browser.newPage(); await fallback.route('**/experience-ui.js*',route=>route.abort());
      await fallback.goto(mode.base+mode.route); await fallback.locator('#gallery-grid .gallery-tile').first().click();
      assert.equal(await fallback.locator('.image-lightbox').evaluate(el=>el.open),true,'basic lightbox survives an unavailable enhancement script');
      await fallback.keyboard.press('Escape'); await fallback.close();
    }
    fs.writeFileSync(path.join(evidence,'loading-browser.json'),JSON.stringify(results,null,2));
    console.log('PASS: gallery loading, retry, stale-response protection, fallback and room links ('+modes.map(m=>m.name).join(', ')+').');
  } finally {await browser.close()}
})().catch(error=>{console.error(error);process.exitCode=1});
