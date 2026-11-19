const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const base = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8098';
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '..', 'project-docs', 'visual-qa', 'philosophy-2026-11-19');
const route = process.env.TOKYO_PHILOSOPHY_ROUTE || '/about.php';
(async () => {
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const results = [];
  try {
    for (const width of [320, 390, 768, 1024, 1440, 1920]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      const response = await page.goto(`${base}${route}`, { waitUntil: 'domcontentloaded' });
      assert.equal(response.status(), 200);
      await page.evaluate(async () => {
        for (const img of document.querySelectorAll('main img')) { img.loading = 'eager'; }
        await Promise.all([...document.querySelectorAll('main img')].map(img => img.decode()));
        await document.fonts.ready;
      });
      const metrics = await page.evaluate(() => {
        const headings = [...document.querySelectorAll('main h1,main h2')].map(el => {
          const range = document.createRange(); range.selectNodeContents(el);
          const box = el.getBoundingClientRect();
          return { text: el.textContent, clipped: [...range.getClientRects()].some(r => r.left < -1 || r.right > innerWidth + 1 || r.left < box.left - 1 || r.right > box.right + 1) };
        });
        return { width: innerWidth, scrollWidth: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight, chefHeight: document.querySelector('.about-chef').getBoundingClientRect().height, headings, images: [...document.querySelectorAll('main img')].every(img => img.complete && img.naturalWidth > 0) };
      });
      assert.equal(metrics.scrollWidth, width, `overflow at ${width}`);
      assert.ok(metrics.images, `images at ${width}`);
      assert.ok(!metrics.headings.some(h => h.clipped), `heading clipping at ${width}`);
      assert.deepEqual(errors, []);
      if (width >= 1440) assert.ok(metrics.chefHeight < 560, 'compact chef chapter');
      if ([390,1440].includes(width)) await page.screenshot({ path: path.join(evidence, `${width === 390 ? 'mobile' : 'desktop'}.jpg`), fullPage: true, type: 'jpeg', quality: 83 });
      results.push(metrics);
      await page.close();
    }
    fs.writeFileSync(path.join(evidence, 'metrics.json'), JSON.stringify(results, null, 2));
    console.log(`PASS: philosophy at ${results.map(r=>r.width).join(', ')}px; photographs and text bounds checked`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
