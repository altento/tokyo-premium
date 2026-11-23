const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const base = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8098';
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '..', 'project-docs', 'visual-qa', 'philosophy-2026-11-19');
const route = process.env.TOKYO_PHILOSOPHY_ROUTE || '/about.php';
const widths = process.env.TOKYO_VIEWPORTS
  ? process.env.TOKYO_VIEWPORTS.split(',').map(value => Number(value.trim())).filter(Number.isFinite)
  : [320, 390, 768, 1024, 1440, 1920];
(async () => {
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const results = [];
  try {
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      const response = await page.goto(`${base}${route}`, { waitUntil: 'domcontentloaded' });
      assert.equal(response.status(), 200);
      await page.evaluate(async () => {
        for (const img of document.querySelectorAll('main img')) { img.loading = 'eager'; }
        await Promise.race([
          Promise.allSettled([...document.querySelectorAll('main img')].map(img => img.decode())),
          new Promise(resolve => setTimeout(resolve, 2500))
        ]);
        await Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 1500))]);
      });
      const metrics = await page.evaluate(() => {
        const headings = [...document.querySelectorAll('main h1,main h2')].map(el => {
          const range = document.createRange(); range.selectNodeContents(el);
          const box = el.getBoundingClientRect();
          return { text: el.textContent, clipped: [...range.getClientRects()].some(r => r.left < -1 || r.right > innerWidth + 1 || r.left < box.left - 1 || r.right > box.right + 1) };
        });
        const chapter = selector => {
          const element = document.querySelector(selector);
          const style = getComputedStyle(element);
          return { height: Math.round(element.getBoundingClientRect().height), paddingTop: parseFloat(style.paddingTop) };
        };
        const sectionRules = [...document.querySelectorAll('.about-story .about-copy h2,.about-chef .about-copy h2,.about-source .source-copy h2,.about-contact h2')].map(el => {
          const rule = getComputedStyle(el, '::after');
          return { heading: el.textContent.trim(), visible: rule.content !== 'none' && parseFloat(rule.width) >= 40 && parseFloat(rule.height) === 2 };
        });
        return { width: innerWidth, scrollWidth: document.documentElement.scrollWidth, height: document.documentElement.scrollHeight, chefHeight: document.querySelector('.about-chef').getBoundingClientRect().height, chapters: { story: chapter('.about-story'), chef: chapter('.about-chef'), principles: chapter('.about-principles'), source: chapter('.about-source'), quote: chapter('.about-quote') }, headings, sectionRules, images: [...document.querySelectorAll('main img')].every(img => img.complete && img.naturalWidth > 0) };
      });
      assert.equal(metrics.scrollWidth, width, `overflow at ${width}`);
      assert.ok(metrics.images, `images at ${width}`);
      assert.ok(!metrics.headings.some(h => h.clipped), `heading clipping at ${width}`);
      assert.equal(metrics.sectionRules.length, 4, `expected four editorial red rules at ${width}`);
      assert.ok(metrics.sectionRules.every(rule => rule.visible), `missing red section rule at ${width}`);
      assert.deepEqual(errors, []);
      if (width >= 1440) assert.ok(metrics.chefHeight < 560, 'compact chef chapter');
      if (width >= 1101) {
        assert.ok(metrics.chapters.story.paddingTop < 50, 'compact philosophy story spacing');
        assert.ok(metrics.chapters.chef.paddingTop < 50, 'compact chef spacing');
      }
      if ([390,1440].includes(width)) await page.screenshot({ path: path.join(evidence, `${width === 390 ? 'mobile' : 'desktop'}.jpg`), fullPage: true, type: 'jpeg', quality: 83 });
      results.push(metrics);
      await page.close();
    }
    fs.writeFileSync(path.join(evidence, 'metrics.json'), JSON.stringify(results, null, 2));
    console.log(`PASS: philosophy at ${results.map(r=>r.width).join(', ')}px; photographs, title bounds, red section rules and desktop spacing checked`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
