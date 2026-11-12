const { createRequire } = require('node:module');
const path = require('node:path');
const os = require('node:os');
const fs = require('node:fs');
const projectRequire = createRequire(path.resolve(__dirname, '../package.json'));
const { chromium } = projectRequire('playwright');

const baseUrl = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8080';
const evidenceDir = process.env.TOKYO_MENU_EVIDENCE_DIR || os.tmpdir();
fs.mkdirSync(evidenceDir, { recursive: true });
const failures = [];
function check(condition, message) {
  if (condition) console.log(`PASS ${message}`);
  else { failures.push(message); console.error(`FAIL ${message}`); }
}

async function inspect(page, width, height, screenshotName) {
  await page.setViewportSize({ width, height });
  const response = await page.goto(`${baseUrl}/menu.php?menu-visual=${width}`, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  const state = await page.evaluate(() => {
    const all = [...document.querySelectorAll('.dish-card')];
    const cards = all.filter(card => !card.hidden);
    const tops = cards.map(card => Math.round(card.getBoundingClientRect().top));
    const rows = [...new Set(tops)];
    const grid = document.querySelector('.dish-grid');
    const gridStyle = grid ? getComputedStyle(grid) : null;
    const links = cards.map(card => {
      const items = [...card.querySelectorAll('.menu-card-links > *')].map(node => {
        const rect = node.getBoundingClientRect();
        return { text: node.innerText.trim(), left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, width: rect.width, height: rect.height };
      });
      const overlap = items.some((a, index) => items.slice(index + 1).some(b =>
        Math.min(a.right, b.right) - Math.max(a.left, b.left) > 1 &&
        Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top) > 1
      ));
      return { items, overlap };
    });
    return {
      total: all.length,
      pageCards: cards.length,
      renderedCount: all.filter(card => getComputedStyle(card).display !== 'none').length,
      rows: rows.length,
      countsPerRow: rows.map(top => tops.filter(value => value === top).length),
      hiddenByReveal: cards.filter(card => Number(getComputedStyle(card).opacity) < 0.99).length,
      hiddenRows: all.filter(card => !card.hidden && Number(getComputedStyle(card).opacity) < 0.99).length,
      columns: gridStyle ? gridStyle.gridTemplateColumns.split(' ').length : 0,
      scrollWidth: document.documentElement.scrollWidth,
      viewportWidth: innerWidth,
      brokenActionOverlap: links.filter(card => card.overlap).length,
      missingActionPair: cards.filter(card => card.querySelectorAll('.menu-card-links > *').length !== 2).length
    };
  });
  // Exercise the native lazy loader before a full-page capture. Chromium's
  // fullPage screenshot does not itself scroll the viewport to trigger images.
  const loadedImages = await page.evaluate(async () => {
    const images = [...document.querySelectorAll('.dish-card:not([hidden]) .dish-image-wrap img')];
    for (const image of images) {
      image.scrollIntoView({ block: 'center' });
      await new Promise(resolve => requestAnimationFrame(resolve));
      await image.decode().catch(() => {});
    }
    window.scrollTo(0, 0);
    await new Promise(resolve => requestAnimationFrame(resolve));
    return images.filter(image => image.complete && image.naturalWidth > 0).length;
  });
  if (screenshotName) await page.screenshot({ path: path.join(evidenceDir, screenshotName), fullPage: true });
  return { status: response?.status(), ...state, loadedImages };
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  const errors = [];
  page.on('pageerror', error => errors.push(String(error)));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });

  const desktop = await inspect(page, 1440, 1000, 'tokyo-menu-1440-initial.png');
  console.log('Desktop:', JSON.stringify(desktop));
  check(desktop.status === 200, 'menu PHP route responds successfully at 1440 px');
  check(desktop.total >= 6 && desktop.pageCards === 6, 'page one contains six real catalog cards');
  check(desktop.renderedCount === 6, 'pagination hides all catalog cards outside the current page');
  check(desktop.columns === 3 && desktop.rows === 2 && desktop.countsPerRow.every(count => count === 3), 'desktop catalog forms a balanced three-column, two-row page');
  check(desktop.hiddenRows === 0, 'second catalog row is visible before any scroll or capture override');
  check(desktop.loadedImages === desktop.pageCards, 'all visible-page dish photos load during realistic scrolling before full-page capture');
  check(desktop.brokenActionOverlap === 0 && desktop.missingActionPair === 0, 'detail and quick-view actions have separate non-overlapping hit areas');

  const mobile = await inspect(page, 390, 844, 'tokyo-menu-390-initial.png');
  console.log('Mobile:', JSON.stringify(mobile));
  check(mobile.status === 200 && mobile.pageCards === 6, 'mobile route retains real catalog cards');
  check(mobile.renderedCount === 6, 'mobile pagination still hides cards outside the current page');
  check(mobile.scrollWidth <= mobile.viewportWidth, '390 px menu has no horizontal overflow');
  check(mobile.hiddenRows === 0 && mobile.brokenActionOverlap === 0, 'mobile cards remain visible and their actions do not overlap');
  check(mobile.loadedImages === mobile.pageCards, 'mobile dish photos load through normal lazy loading');

  const narrow = await inspect(page, 320, 740, 'tokyo-menu-320-initial.png');
  console.log('Narrow mobile:', JSON.stringify(narrow));
  check(narrow.status === 200 && narrow.scrollWidth <= narrow.viewportWidth, '320 px menu has no horizontal overflow');
  check(narrow.hiddenRows === 0 && narrow.brokenActionOverlap === 0, 'narrow cards remain visible with distinct actions');
  check(errors.length === 0, `menu browser console and page errors are clear (${errors.length})`);

  await browser.close();
  if (failures.length) process.exitCode = 1;
  else console.log(`All menu visual regression checks passed. Evidence: ${evidenceDir}`);
})().catch(error => { console.error(error); process.exit(1); });
