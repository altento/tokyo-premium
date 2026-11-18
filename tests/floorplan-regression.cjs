const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const base = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8090';
const staticBase = process.env.TOKYO_STATIC_BASE_URL || `${base}/docs`;
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(root, 'project-docs', 'visual-qa', 'booking-floorplan-42');
const expectedZones = new Map([
  ['Основной зал', 20],
  ['Суши-бар', 8],
  ['Приватная комната', 4],
  ['Летняя терраса', 10]
]);

async function run() {
  const { chromium } = require('playwright');
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const modes = [
    { name: 'PHP', url: `${base}/pages.php?page=booking` },
    { name: 'Pages', url: `${staticBase}/booking.html` }
  ];

  try {
    for (const mode of modes) {
      const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      const response = await page.goto(mode.url, { waitUntil: 'domcontentloaded' });
      assert.equal(response?.status(), 200, `${mode.name} booking route loads`);
      await page.waitForSelector('.tp-seat-plan [data-tp-table="42"]', { timeout: 10000 });

      const initialState = await page.evaluate(() => {
        const zone = document.querySelector('[name="zone"]')?.value;
        const svg = document.querySelector('.tp-seat-plan');
        const selectedZone = Array.from(svg?.querySelectorAll('[data-tp-zone]') || [])
          .find(item => !item.hasAttribute('data-tp-table') && item.classList.contains('is-selected'))?.dataset.tpZone;
        return {
          zone,
          selectedZone,
          photo: document.querySelector('[data-tp-zone-photo]')?.getAttribute('src'),
          status: document.querySelector('[data-tp-floorplan-selection]')?.textContent || ''
        };
      });
      assert.equal(initialState.selectedZone, initialState.zone, `${mode.name} highlights the form's default zone on initial render`);
      assert.match(initialState.photo || '', /room-main-hall\.webp$/, `${mode.name} shows the matching room image on initial render`);
      assert.match(initialState.status, /выберите предпочтительный стол/i, `${mode.name} explains the initial zone and table preference`);

      const layout = await page.locator('.tp-seat-plan').evaluate(svg => {
        const counts = {};
        svg.querySelectorAll('[data-tp-table]').forEach(table => {
          counts[table.dataset.tpZone] = (counts[table.dataset.tpZone] || 0) + 1;
        });
        return { counts, total: svg.querySelectorAll('[data-tp-table]').length, zones: svg.querySelectorAll('[data-tp-zone-hit]').length };
      });
      assert.deepEqual(layout.counts, Object.fromEntries(expectedZones), `${mode.name} SVG matches the four-zone table plan`);
      assert.equal(layout.total, 42, `${mode.name} SVG has 42 preference markers`);
      assert.equal(layout.zones, 4, `${mode.name} SVG exposes four selectable zones`);

      await page.locator('.tp-seat-plan [data-tp-table="42"]').click();
      assert.equal(await page.locator('[name="table"]').inputValue(), '42', `${mode.name} SVG table choice reaches the form`);
      assert.equal(await page.locator('[name="zone"]').inputValue(), 'Летняя терраса', `${mode.name} table choice also selects its zone`);
      assert.match(await page.locator('[data-tp-floorplan-selection]').innerText(), /предпочтение сохранено/i, `${mode.name} states that selection is only a preference`);
      assert.match(await page.locator('[data-tp-floorplan-selection]').innerText(), /доступность|подтвердит отдельно/i, `${mode.name} does not claim live availability`);
      if (mode.name === 'Pages') await page.screenshot({ path: path.join(evidence, '07-booking-floorplan-desktop.png'), fullPage: true });

      await page.setViewportSize({ width: 390, height: 844 });
      const zoneButton = page.locator('.tp-floorplan-mobile-zone[data-tp-zone-select="Суши-бар"]');
      assert.ok(await zoneButton.isVisible(), `${mode.name} exposes touch-sized zone controls on mobile`);
      const zoneBox = await zoneButton.boundingBox();
      assert.ok(zoneBox && zoneBox.height >= 44, `${mode.name} mobile zone control is at least 44 px tall`);
      await zoneButton.click();
      const sushiPanel = page.locator('.tp-floorplan-mobile-zone-options[data-tp-floorplan-zone-options="Суши-бар"]');
      assert.ok(await sushiPanel.isVisible(), `${mode.name} reveals the selected zone's mobile table list`);
      assert.equal(await sushiPanel.locator('[data-tp-seat-button]').count(), 8, `${mode.name} lists all sushi-bar preferences`);
      const mobileTable = sushiPanel.locator('[data-tp-seat-button]').first();
      const tableBox = await mobileTable.boundingBox();
      assert.ok(tableBox && tableBox.height >= 44, `${mode.name} mobile table control is at least 44 px tall`);
      await mobileTable.click();
      assert.equal(await page.locator('[name="table"]').inputValue(), '21', `${mode.name} mobile picker updates the booking form`);
      assert.equal(await page.locator('[name="zone"]').inputValue(), 'Суши-бар', `${mode.name} mobile picker preserves the chosen zone`);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${mode.name} mobile floorplan does not overflow horizontally`);
      if (mode.name === 'Pages') await page.screenshot({ path: path.join(evidence, '07-booking-floorplan-mobile.png'), fullPage: true });
      assert.deepEqual(errors, [], `${mode.name} booking flow has no browser errors`);
      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log(`PASS: 42-table booking plan, mobile picker, honest preference status and two delivery modes. Evidence: ${evidence}`);
}

run().catch(error => { console.error(error); process.exitCode = 1; });
