const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');

const root = path.resolve(__dirname, '..', 'docs');
const evidence = path.resolve(__dirname, '..', 'project-docs', 'visual-qa', 'account-birthday-2026-11-23');

async function run() {
  const { chromium } = require('playwright');
  const mime = { '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.svg': 'image/svg+xml', '.webp': 'image/webp', '.woff2': 'font/woff2' };
  const server = http.createServer((request, response) => {
    const pathname = decodeURIComponent(new URL(request.url, 'http://127.0.0.1').pathname);
    const file = path.resolve(root, `.${pathname === '/' ? '/index.html' : pathname}`);
    if (!file.startsWith(`${root}${path.sep}`)) { response.writeHead(403).end(); return; }
    fs.readFile(file, (error, data) => {
      if (error) { response.writeHead(404).end(); return; }
      response.writeHead(200, { 'content-type': mime[path.extname(file)] || 'application/octet-stream' }).end(data);
    });
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  fs.mkdirSync(evidence, { recursive: true });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const capture = async filename => {
    const style = await page.addStyleTag({ content: '.site-header{position:static!important;top:auto!important}' });
    try {
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
      await page.screenshot({ path: path.join(evidence, filename), fullPage: true });
    } finally {
      await style.evaluate(node => node.remove());
    }
  };
  try {
    await page.goto(`http://127.0.0.1:${server.address().port}/account.html`, { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => localStorage.clear());
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.locator('[data-account-tab="profile"]').click();

    const month = page.locator('#demo-birth-month');
    const day = page.locator('#demo-birth-day');
    await month.selectOption('2');
    assert.equal(await day.locator('option').count(), 30, 'February allows days 1–29 without asking for birth year');
    await day.selectOption('29');
    await page.locator('[name="birthday_offer_consent"]').check();
    await page.locator('[data-local-form="profile"] button[type="submit"], [data-local-form="profile"] button:not([type])').click();
    const saved = await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-profile')));
    assert.deepEqual({ month: saved.birthMonth, day: saved.birthDay, consent: saved.birthdayConsent }, { month: '2', day: '29', consent: true }, 'month/day and separate opt-in are saved locally');
    assert.match(await page.locator('[data-local-form="profile"] .form-status').innerText(), /не создаёт купон и не начисляет бонусы/i, 'saved state explains that Pages does not issue a real reward');
    await capture('birthday-profile-mobile.png');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await capture('birthday-profile-desktop.png');
    await page.setViewportSize({ width: 390, height: 844 });

    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.locator('[data-account-tab="profile"]').click();
    assert.equal(await month.inputValue(), '2', 'saved month survives reload');
    assert.equal(await day.inputValue(), '29', 'leap-day preference survives reload');
    assert.equal(await page.locator('[name="birthday_offer_consent"]').isChecked(), true, 'consent state survives reload');
    await month.selectOption('4');
    assert.equal(await day.inputValue(), '29', 'valid day is retained when switching to a 30-day month');
    assert.equal(await day.locator('option[value="31"]').count(), 0, 'impossible day is not offered for April');

    await page.locator('[name="birthday_offer_consent"]').uncheck();
    await page.locator('[data-local-form="profile"] button[type="submit"], [data-local-form="profile"] button:not([type])').click();
    const optedOut = await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-profile')));
    assert.equal(optedOut.birthdayConsent, false, 'the user can withdraw birthday-offer consent independently');
    assert.equal(optedOut.birthMonth, '4', 'withdrawing consent does not unexpectedly erase the optional date');

    await page.locator('[data-account-tab="settings"]').click();
    await page.locator('#demo-clear-data').click();
    assert.equal(await page.evaluate(() => localStorage.getItem('tokyo-demo-profile')), null, 'clear-data removes birthday details with the rest of the demo profile');
    await page.locator('[data-account-tab="profile"]').click();
    assert.equal(await month.inputValue(), '', 'cleared birthday month is no longer present in the form');
    assert.equal(await page.locator('[name="birthday_offer_consent"]').isChecked(), false, 'clear-data also resets the separate consent');
    assert.deepEqual(errors, [], 'birthday settings produce no browser exceptions');
    console.log('PASS: local birthday month/day, separate consent, reload, calendar bounds, opt-out and clear-data flow.');
  } finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
}

run().catch(error => { console.error(error); process.exitCode = 1; });
