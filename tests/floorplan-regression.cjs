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
  const availableModes = [
    { name: 'PHP', url: `${base}/pages.php?page=booking` },
    { name: 'Pages', url: `${staticBase}/booking.html` }
  ];
  const modeFilter = process.env.TOKYO_FLOORPLAN_MODE?.toLowerCase();
  const modes = modeFilter ? availableModes.filter(mode => mode.name.toLowerCase() === modeFilter) : availableModes;
  assert.ok(modes.length, `Unknown TOKYO_FLOORPLAN_MODE: ${modeFilter}`);
  const capturePage = async (page, filename) => {
    await page.evaluate(() => window.scrollTo(0, 0));
    const freezeStickyHeader = await page.addStyleTag({ content: '.site-header{position:relative!important;top:auto!important}' });
    try { await page.screenshot({ path: filename, fullPage: true }); }
    finally { await freezeStickyHeader.evaluate(style => style.remove()); }
  };

  try {
    for (const mode of modes) {
      const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      const response = await page.goto(mode.url, { waitUntil: 'domcontentloaded' });
      assert.equal(response?.status(), 200, `${mode.name} booking route loads`);
      const heroPresentation = await page.locator('.booking-hero .editorial-hero-photo').evaluate(image => ({ source: image.getAttribute('src') || '', position: getComputedStyle(image).objectPosition }));
      assert.match(heroPresentation.source, /room-main-hall\.webp$/, `${mode.name} booking hero shows the prepared main-hall table`);
      assert.equal(heroPresentation.position, '58% 62%', `${mode.name} desktop booking hero keeps the table in frame`);
      await page.waitForSelector('.tp-seat-plan [data-tp-table="42"]', { timeout: 10000 });
      await page.waitForFunction(() => /выберите предпочтительный стол/i.test(document.querySelector('[data-tp-floorplan-selection]')?.textContent || ''), null, { timeout: 5000 });

      const bookingFields = await page.locator('#full-booking-form .field').evaluateAll(fields => fields.map(field => ({
        name: field.querySelector('input,select,textarea')?.name || '',
        full: field.classList.contains('full')
      })));
      assert.deepEqual(bookingFields.map(field => field.name), ['name', 'phone', 'date', 'time', 'guests', 'zone', 'email', 'format', 'message'], `${mode.name} groups booking fields in guest/date/time/party order`);
      assert.equal(bookingFields.find(field => field.name === 'email')?.full, true, `${mode.name} gives the reply email its own full row`);

      const initialState = await page.evaluate(() => {
        const zone = document.querySelector('[name="zone"]')?.value;
        const svg = document.querySelector('.tp-seat-plan');
        const selectedZone = Array.from(svg?.querySelectorAll('[data-tp-zone]') || [])
          .find(item => !item.hasAttribute('data-tp-table') && item.classList.contains('is-selected'))?.dataset.tpZone;
        return {
          zone,
          selectedZone,
          photo: document.querySelector('[data-tp-zone-photo]')?.getAttribute('src'),
          status: document.querySelector('[data-tp-floorplan-selection]')?.textContent || '',
          hiddenFormStatus: document.querySelector('#full-booking-form .booking-table-state')?.hidden,
          visibleSelectionStatuses: Array.from(document.querySelectorAll('.table-selection')).filter(item => !item.hidden).length
        };
      });
      assert.equal(initialState.selectedZone, initialState.zone, `${mode.name} highlights the form's default zone on initial render`);
      assert.equal(initialState.hiddenFormStatus, true, `${mode.name} keeps the duplicate form copy out of the visual and accessibility trees`);
      assert.equal(initialState.visibleSelectionStatuses, 1, `${mode.name} exposes one visible table-selection status`);
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
      assert.equal(await page.locator('#full-booking-form .booking-table-state').evaluate(item => item.hidden), true, `${mode.name} keeps the form status non-visual`);
      assert.equal(await page.locator('.table-selection:visible').count(), 1, `${mode.name} shows one current selection status`);
      assert.match(await page.locator('[data-tp-floorplan-selection]').innerText(), /предпочтение сохранено/i, `${mode.name} states that selection is only a preference`);
      assert.match(await page.locator('[data-tp-floorplan-selection]').innerText(), /доступность|подтвердит отдельно/i, `${mode.name} does not claim live availability`);
      if (mode.name === 'Pages') await capturePage(page, path.join(evidence, '07-booking-floorplan-desktop.png'));

      await page.locator('#full-booking-form').evaluate(form => form.reset());
      await page.waitForFunction(() => document.querySelector('[name="table"]').value === '' && !document.querySelector('[data-tp-table].is-selected'));
      const resetState = await page.evaluate(() => ({
        zone: document.querySelector('[name="zone"]').value,
        photo: document.querySelector('[data-tp-zone-photo]').getAttribute('src'),
        selectedZone: document.querySelector('.tp-floorplan-mobile-zone.is-selected')?.dataset.tpZoneSelect,
        selectedTables: document.querySelectorAll('[data-tp-table].is-selected').length,
        formStatus: document.querySelector('#full-booking-form .table-selection').textContent
      }));
      assert.equal(resetState.zone, initialState.zone, `${mode.name} reset restores the default form zone`);
      assert.equal(resetState.selectedZone, resetState.zone, `${mode.name} reset restores the same picker zone`);
      assert.equal(resetState.photo, initialState.photo, `${mode.name} reset restores the default room photograph`);
      assert.equal(resetState.selectedTables, 0, `${mode.name} reset clears SVG and mobile selection`);
      assert.match(resetState.formStatus, /не выбран/i, `${mode.name} reset clears the selected-table message`);

      await page.locator('.tp-seat-plan [data-tp-table="42"]').click();
      await page.locator('[name="guests"]').selectOption('3');
      assert.equal(await page.locator('[name="table"]').inputValue(), '', `${mode.name} changing guests clears the previous table`);

      await page.setViewportSize({ width: 390, height: 844 });
      assert.equal(await page.locator('.booking-hero .editorial-hero-photo').evaluate(image => getComputedStyle(image).objectPosition), '62% 58%', 'mobile booking hero keeps the table visible');
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
      if (mode.name === 'Pages') await capturePage(page, path.join(evidence, '07-booking-floorplan-mobile.png'));
      assert.deepEqual(errors, [], `${mode.name} booking flow has no browser errors`);
      await page.close();
    }
  } finally {
    await browser.close();
  }

  console.log(`PASS: ${modes.map(({ name }) => name).join('/')} booking plan, mobile picker, preference status and reset. Evidence: ${evidence}`);
}

run().catch(error => { console.error(error); process.exitCode = 1; });
