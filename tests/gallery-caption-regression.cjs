const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const staticBase = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8765';
const targets = [{ label: 'Pages demo', url: `${staticBase}/gallery.html`, screenshot: true }];
if (process.env.TOKYO_BASE_URL) {
  targets.unshift({ label: 'PHP', url: `${process.env.TOKYO_BASE_URL}/pages.php?page=gallery` });
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    for (const target of targets) {
      const response = await page.goto(target.url, { waitUntil: 'domcontentloaded' });
      assert.equal(response?.status(), 200, `${target.label} gallery loads`);
      const tiles = page.locator('#gallery-grid .gallery-tile');
      assert.ok(await tiles.count() >= 2, `${target.label} gallery has enough images to navigate`);
      await tiles.first().click();
      const dialog = page.locator('.image-lightbox');
      assert.equal(await dialog.evaluate(element => element.open), true, `${target.label} lightbox opens`);
      const caption = dialog.locator('[data-tp-gallery-caption][data-lightbox-caption]');
      assert.equal((await caption.innerText()).trim(), 'Интерьер у садового окна', `${target.label} shows the selected image caption`);
      await dialog.locator('[data-tp-gallery-next]').click();
      assert.equal((await caption.innerText()).trim(), 'Бар шефа', `${target.label} updates the caption with the next image`);
      assert.ok(await caption.isVisible(), `${target.label} caption remains visible in the lightbox`);
      await dialog.locator('[data-tp-gallery-prev]').click();
      assert.equal((await caption.innerText()).trim(), 'Интерьер у садового окна', `${target.label} updates the caption with the previous image`);
      if (target.screenshot) {
        const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(root, 'project-docs', 'visual-qa', 'gallery-caption-followup-2026-11-16');
        fs.mkdirSync(evidence, { recursive: true });
        await page.screenshot({ path: path.join(evidence, 'gallery-caption-desktop.png') });
        await page.setViewportSize({ width: 390, height: 844 });
        const captionBounds = await caption.boundingBox();
        assert.ok(captionBounds && captionBounds.x >= 0 && captionBounds.x + captionBounds.width <= 390, 'mobile caption remains inside the viewport');
        await page.screenshot({ path: path.join(evidence, 'gallery-caption-mobile.png') });
      }
      await page.keyboard.press('Escape');
      assert.equal(await dialog.evaluate(element => element.open), false, `${target.label} lightbox still closes with Escape`);
      const firstRoom = page.locator('#spaces .lightbox-trigger').first();
      await firstRoom.click();
      assert.equal((await caption.innerText()).trim(), 'Суши-бар', `${target.label} room preview replaces the previous gallery caption`);
      await page.keyboard.press('ArrowRight');
      assert.equal((await caption.innerText()).trim(), 'Основной зал', `${target.label} room arrows stay in the room album`);
      await page.keyboard.press('Escape');
      assert.equal(await firstRoom.evaluate(element => document.activeElement === element), true, `${target.label} closing room preview restores its trigger focus`);
      await page.locator('[data-gallery="details"]').click();
      assert.equal(await page.locator('[data-gallery="details"]').getAttribute('aria-pressed'), 'true', `${target.label} filter exposes its selected state`);
      assert.equal(await page.locator('#spaces .zone-card:visible').count(), 4, `${target.label} gallery filter preserves all room cards`);
      await page.locator('#gallery-grid .gallery-tile:not([hidden])').first().click();
      assert.equal((await caption.innerText()).trim(), 'Нигири крупным планом', `${target.label} filtered gallery opens its first matching image`);
      await page.keyboard.press('ArrowLeft');
      assert.equal((await caption.innerText()).trim(), 'Чайная пауза', `${target.label} filtered gallery wraps within its own album`);
      const swipe = async (dx, dy) => dialog.evaluate((node, delta) => {
        const dispatch = (type, x, y) => {
          const event = new Event(type, {bubbles:true});
          Object.defineProperty(event, 'changedTouches', {value:[{clientX:x,clientY:y}]});
          node.dispatchEvent(event);
        };
        dispatch('touchstart', 160, 160); dispatch('touchend', 160 + delta.dx, 160 + delta.dy);
      }, {dx,dy});
      await swipe(10, 90);
      assert.equal((await caption.innerText()).trim(), 'Чайная пауза', `${target.label} vertical gestures do not change the photo`);
      await swipe(-90, 5);
      assert.equal((await caption.innerText()).trim(), 'Нигири крупным планом', `${target.label} horizontal swipe advances the filtered album`);
      await page.keyboard.press('Escape');
    }
    assert.deepEqual(errors, [], 'gallery has no browser errors');
    console.log(`PASS: gallery lightbox captions in ${targets.map(target => target.label).join(' and ')} modes.`);
  } finally {
    await browser.close();
  }
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
