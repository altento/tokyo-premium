const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const phpBase = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8085';
const staticBase = process.env.TOKYO_STATIC_BASE_URL || `${phpBase}/docs`;
const evidenceDir = path.resolve(process.env.TOKYO_MOTION_EVIDENCE_DIR || path.join(__dirname, '..', 'project-docs', 'visual-qa', '2.0-motion'));
const waitForScene = async (page, title) => page.waitForFunction(expected => document.querySelector('#home-title')?.textContent.includes(expected), title, { timeout: 5000 });
async function revealAll(page) {
  await page.evaluate(async () => {
    const bottom = document.documentElement.scrollHeight;
    const step = Math.max(320, innerHeight * 0.72);
    for (let top = 0; top < bottom; top += step) {
      window.scrollTo(0, top);
      await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    }
    window.scrollTo(0, 0);
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  });
}

async function runMode(browser, name, base, route, capture) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error' && !/fonts\.googleapis|fonts\.gstatic/.test(message.text())) errors.push(message.text());
  });
  const response = await page.goto(`${base}${route}`, { waitUntil: 'networkidle' });
  assert.equal(response?.status(), 200, `${name} homepage loads`);
  await page.evaluate(() => document.fonts.ready);

  const hero = page.locator('[data-home-hero]');
  const dots = page.locator('[data-home-hero-index]');
  assert.equal(await dots.count(), 3, `${name} renders three curated scenes`);
  await page.waitForFunction(() => !document.querySelector('[data-home-hero-controls]')?.hidden);
  assert.match(await page.locator('#home-hero-photo').getAttribute('src'), /home-hero-salmon-nigiri-v3\.webp$/);
  assert.equal(await page.locator('#home-hero-photo').evaluate(image => image.naturalWidth > 0), true, `${name} first image is loaded`);

  await page.locator('[data-home-hero-step="1"]').click();
  await waitForScene(page, 'Позвольте вечеру');
  assert.equal(await dots.nth(1).getAttribute('aria-pressed'), 'true', `${name} next scene sets the current dot`);
  await page.waitForFunction(() => /сезонная композиция/i.test(document.querySelector('#home-hero-photo')?.alt || ''));
  assert.match(await page.locator('#home-hero-photo').getAttribute('alt'), /сезонная композиция/i, `${name} updates image alternative text`);
  const omakaseHref = await page.locator('[data-home-hero-primary]').getAttribute('href');
  assert.match(omakaseHref, name === 'static' ? /omakase\.html$/ : /pages\.php\?page=omakase$/, `${name} primary action follows the selected story and static route map`);
  assert.equal(await page.locator('#home-hero-photo').evaluate(image => image.naturalWidth > 0), true, `${name} second image is loaded`);
  const builderHref = await page.locator('[data-home-hero-secondary]').getAttribute('href');
  assert.match(builderHref, name === 'static' ? /set-builder\.html$/ : /set-builder\.php$/, `${name} Omakase scene links to the live set-builder route`);

  await page.locator('[data-home-hero-step="-1"]').click();
  await waitForScene(page, 'Вкус, которому');
  assert.equal(await dots.nth(0).getAttribute('aria-pressed'), 'true', `${name} previous arrow returns to the first scene`);
  await dots.nth(1).focus();
  await page.keyboard.press('ArrowRight');
  await waitForScene(page, 'Точность в каждом');
  assert.equal(await dots.nth(2).getAttribute('aria-pressed'), 'true', `${name} keyboard arrows navigate the stories`);
  const galleryHref = await page.locator('[data-home-hero-secondary]').getAttribute('href');
  assert.match(galleryHref, name === 'static' ? /gallery\.html$/ : /page=gallery$/, `${name} secondary action follows the selected story and static route map`);
  await page.waitForTimeout(1400);
  assert.equal(await dots.nth(2).getAttribute('aria-pressed'), 'true', `${name} carousel does not autoplay`);

  for (const width of [320, 360, 390, 430, 768, 1024, 1440, 1920]) {
    await page.setViewportSize({ width, height: width < 600 ? 844 : 1000 });
    const geometry = await hero.evaluate(node => {
      const bounds = node.getBoundingClientRect();
      const titleBounds = node.querySelector('h1').getBoundingClientRect();
      const controlsBounds = node.querySelector('[data-home-hero-controls]').getBoundingClientRect();
      return {
        viewportWidth: innerWidth,
        scrollWidth: document.documentElement.scrollWidth,
        titleInsideHero: titleBounds.left >= bounds.left - 1 && titleBounds.right <= bounds.right + 1,
        controlsInsideHero: controlsBounds.left >= bounds.left - 1 && controlsBounds.right <= bounds.right + 1,
        titleVisible: titleBounds.width > 0 && titleBounds.height > 0
      };
    });
    assert.ok(geometry.scrollWidth <= geometry.viewportWidth, `${name} homepage has no horizontal overflow at ${width}px`);
    assert.equal(geometry.titleInsideHero, true, `${name} heading fits its hero at ${width}px`);
    assert.equal(geometry.controlsInsideHero, true, `${name} controls fit their hero at ${width}px`);
    assert.equal(geometry.titleVisible, true, `${name} heading remains visible at ${width}px`);
  }

  if (capture) {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto(`${base}${route}`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.locator('[data-home-hero]').screenshot({ path: path.join(evidenceDir, `${name}-hero-desktop.jpg`), type: 'jpeg', quality: 88 });
    await revealAll(page);
    await page.screenshot({ path: path.join(evidenceDir, `${name}-desktop.jpg`), fullPage: true, type: 'jpeg', quality: 76 });
    await page.locator('[data-home-hero-index="1"]').click();
    await waitForScene(page, 'Позвольте вечеру');
    await page.waitForFunction(() => /сезонная композиция/i.test(document.querySelector('#home-hero-photo')?.alt || ''));
    await page.locator('[data-home-hero]').screenshot({ path: path.join(evidenceDir, `${name}-omakase-scene-desktop.jpg`), type: 'jpeg', quality: 88 });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${base}${route}`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.locator('[data-home-hero]').screenshot({ path: path.join(evidenceDir, `${name}-hero-mobile.jpg`), type: 'jpeg', quality: 84 });
    await revealAll(page);
    await page.screenshot({ path: path.join(evidenceDir, `${name}-mobile.jpg`), fullPage: true, type: 'jpeg', quality: 72 });
  }
  assert.deepEqual(errors, [], `${name} motion has no browser errors`);
  await page.close();
}

async function runReducedMotion(browser, name, base, route) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
  const page = await context.newPage();
  await page.goto(`${base}${route}`, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !document.querySelector('[data-home-hero-controls]')?.hidden);
  const duration = await page.locator('#home-hero-photo').evaluate(image => getComputedStyle(image).transitionDuration);
  assert.ok(duration.split(',').every(value => Number.parseFloat(value) <= 0.01), `${name} respects prefers-reduced-motion`);
  await page.locator('[data-home-hero-step="1"]').click();
  await waitForScene(page, 'Позвольте вечеру');
  assert.equal(await page.locator('#home-hero-photo').evaluate(image => image.naturalWidth > 0), true, `${name} reduced-motion scene still works`);
  await context.close();
}

(async () => {
  fs.mkdirSync(evidenceDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    await runMode(browser, 'php', phpBase, '/', true);
    await runReducedMotion(browser, 'php', phpBase, '/');
    await runMode(browser, 'static', staticBase, '/index.html', true);
    await runReducedMotion(browser, 'static', staticBase, '/index.html');
    console.log(`PASS: cinematic homepage scene controls, keyboard, reduced motion, no autoplay, and eight responsive widths in PHP and static modes; screenshots saved to ${evidenceDir}`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
