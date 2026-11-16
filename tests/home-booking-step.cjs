const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const base = (process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8776').replace(/\/$/, '');
const evidence = path.resolve(__dirname, '..', 'project-docs', 'visual-qa', 'home-booking-progressive-2026-11-16');

(async () => {
  const fs = require('node:fs');
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    for (const viewport of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
      const page = await browser.newPage({ viewport });
      await page.goto(`${base}/index.html`, { waitUntil: 'domcontentloaded' });
      const form = page.locator('[data-home-booking-form]');
      await form.scrollIntoViewIfNeeded();
      assert.equal(await form.count(), 1, `${viewport.name}: progressive booking form exists`);
      const contactGroup = form.locator('[data-home-booking-contact-fields]');
      assert.equal(await contactGroup.isHidden(), true, `${viewport.name}: contact details start collapsed`);
      assert.equal(await form.locator('#guest-name').isDisabled(), true, `${viewport.name}: hidden required contact is disabled`);

      const date = await page.evaluate(() => {
        const parts = new Intl.DateTimeFormat('en-GB', {
          timeZone: 'Asia/Qyzylorda', year: 'numeric', month: '2-digit', day: '2-digit'
        }).formatToParts(new Date(Date.now() + 48 * 60 * 60 * 1000));
        const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
        return `${values.year}-${values.month}-${values.day}`;
      });
      await form.locator('#booking-date').fill(date);
      await page.screenshot({ path: path.join(evidence, `booking-${viewport.name}-selection.png`), fullPage: false });
      await form.locator('button[type="submit"]').click();
      assert.equal(await contactGroup.isVisible(), true, `${viewport.name}: first step reveals contacts`);
      assert.equal(await form.locator('#guest-name').isDisabled(), false, `${viewport.name}: contact input is enabled on second step`);
      assert.equal(await form.locator('button[type="submit"] [data-home-booking-button-label]').innerText(), 'Отправить запрос', `${viewport.name}: CTA changes to request action`);
      assert.equal(await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-bookings') || '[]').length), 0, `${viewport.name}: first step does not save a booking`);

      await page.screenshot({ path: path.join(evidence, `booking-${viewport.name}-contacts.png`), fullPage: false });
      await form.locator('button[type="submit"]').click();
      assert.equal(await form.locator('#guest-name').evaluate(el => el.validity.valueMissing), true, `${viewport.name}: empty contact blocks submit`);
      await form.locator('#guest-name').fill('Демо-гость');
      await form.locator('#guest-phone').fill('+77000000000');
      await form.locator('#guest-email-home').fill('guest@example.test');
      await form.locator('button[type="submit"]').click();
      await page.locator('.home-booking-form .form-status.success').waitFor({ state: 'visible' });
      const record = await page.evaluate(() => JSON.parse(localStorage.getItem('tokyo-demo-bookings') || '[]')[0]);
      assert.equal(record.name, 'Демо-гость', `${viewport.name}: completed demo request is stored`);
      assert.equal(record.date, date, `${viewport.name}: selected date is preserved`);
      assert.match(await form.locator('.form-status').innerText(), /не подтверждена/, `${viewport.name}: UI does not imply confirmed booking`);
      await contactGroup.waitFor({ state: 'hidden' });
      assert.equal(await contactGroup.isHidden(), true, `${viewport.name}: successful reset returns to compact step`);
      assert.equal(await form.locator('#guest-name').isDisabled(), true, `${viewport.name}: reset disables hidden contacts`);
      await page.close();
    }
    console.log('Home booking progressive flow passed at desktop and mobile; screenshots saved.');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
