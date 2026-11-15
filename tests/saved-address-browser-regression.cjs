const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn } = require('node:child_process');
const net = require('node:net');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const php = process.env.TOKYO_PHP_PATH || 'php';
const database = path.join(os.tmpdir(), `tokyo-address-browser-${process.pid}-${Date.now()}.sqlite`);
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function freePort() {
  const server = net.createServer();
  await new Promise((resolve, reject) => server.once('error', reject).listen(0, '127.0.0.1', resolve));
  const { port } = server.address();
  await new Promise(resolve => server.close(resolve));
  return port;
}

async function waitForServer(url, child) {
  for (let attempt = 0; attempt < 40; attempt++) {
    if (child.exitCode !== null) throw new Error(`PHP server exited early (${child.exitCode}).`);
    try {
      const response = await fetch(url);
      if (response.ok) return;
    } catch (_) { /* server is still starting */ }
    await delay(150);
  }
  throw new Error('PHP server did not become ready.');
}

(async () => {
  const port = await freePort();
  const base = `http://127.0.0.1:${port}`;
  const phpArgs = [];
  if (process.env.TOKYO_PHP_EXTENSION_DIR) {
    phpArgs.push('-d', `extension_dir=${process.env.TOKYO_PHP_EXTENSION_DIR}`, '-d', 'extension=php_sqlite3.dll', '-d', 'extension=php_pdo_sqlite.dll');
  }
  phpArgs.push('-S', `127.0.0.1:${port}`, '-t', root);
  const child = spawn(php, phpArgs, {
    cwd: root,
    env: { ...process.env, RESTAURANT_DB: database },
    stdio: ['ignore', 'ignore', 'pipe']
  });
  let serverErrors = '';
  child.stderr.on('data', chunk => { serverErrors += chunk.toString(); });
  let browser;
  try {
    await waitForServer(`${base}/account.php`, child);
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1100, height: 900 } });
    const email = `address.qa.${process.pid}.${Date.now()}@example.test`;
    await page.goto(`${base}/account.php`, { waitUntil: 'domcontentloaded' });
    const registration = page.locator('form').filter({ has: page.locator('input[name="action"][value="register"]') });
    await registration.locator('[name="name"]').fill('Address QA');
    await registration.locator('[name="email"]').fill(email);
    await registration.locator('[name="password"]').fill('Address-QA-Password-123');
    await registration.getByRole('button', { name: 'Регистрация' }).click();
    await page.locator('#account-addresses').waitFor({ state: 'visible' });
    assert.equal(await page.locator('#account-addresses .tp20-saved-address').count(), 0, 'new account has no addresses');

    const addressForm = page.locator('#account-addresses .tp20-address-form');
    await addressForm.locator('[name="label"]').fill('Дом');
    await addressForm.locator('[name="address"]').fill('Кызылорда, улица Тестовая, 10');
    await addressForm.locator('button[type="submit"]').click();
    await page.getByText('Адрес сохранён в вашем аккаунте.').waitFor();
    assert.equal(await page.locator('#account-addresses .tp20-saved-address').count(), 1, 'new address is visible after save');
    assert.equal(await page.locator('#account-addresses .tp20-address-default').innerText(), 'Основной адрес', 'first address is marked as default');

    await addressForm.locator('[name="label"]').fill('Работа');
    await addressForm.locator('[name="address"]').fill('Кызылорда, улица Садовая, 24');
    await addressForm.locator('button[type="submit"]').click();
    await page.getByText('Адрес сохранён в вашем аккаунте.').waitFor();
    assert.equal(await page.locator('#account-addresses .tp20-saved-address').count(), 2, 'second address is added');
    const homeBeforeEdit = page.locator('#account-addresses .tp20-saved-address').filter({ hasText: 'Дом' });
    await homeBeforeEdit.locator('details summary').click();
    const editForm = homeBeforeEdit.locator('details form');
    await editForm.locator('[name="address"]').fill('Кызылорда, улица Тестовая, 12');
    await editForm.locator('button[type="submit"]').click();
    await page.getByText('Адрес обновлён.').waitFor();
    assert.equal(await page.locator('#account-addresses .tp20-saved-address address').filter({ hasText: 'улица Тестовая, 12' }).count(), 1, 'saved address can be edited in the account');
    const workRow = page.locator('#account-addresses .tp20-saved-address').filter({ hasText: 'Работа' });
    await workRow.getByRole('button', { name: 'Сделать основным' }).click();
    await page.getByText('Основной адрес доставки обновлён.').waitFor();
    assert.equal(await page.locator('#account-addresses .tp20-saved-address .tp20-address-default').count(), 1, 'default action leaves exactly one default badge');

    const currentDefault = page.locator('#account-addresses .tp20-saved-address').filter({ hasText: 'Работа' });
    await currentDefault.getByRole('button', { name: 'Удалить адрес Работа' }).click();
    await page.getByText('Адрес удалён из вашего аккаунта.').waitFor();
    const homeRow = page.locator('#account-addresses .tp20-saved-address').filter({ hasText: 'Дом' });
    assert.equal(await page.locator('#account-addresses .tp20-saved-address').count(), 1, 'deleted address is removed');
    assert.equal(await homeRow.locator('.tp20-address-default').innerText(), 'Основной адрес', 'deleting the default promotes the remaining address');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('#account-addresses').scrollIntoViewIfNeeded();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), 390, 'saved addresses fit a 390px mobile viewport');
    await page.goto(`${base}/pages.php?page=cart`, { waitUntil: 'domcontentloaded' });
    const checkoutAddress = page.locator('#checkout-address');
    const savedAddressSelect = page.locator('#checkout-saved-address');
    await savedAddressSelect.waitFor({ state: 'visible' });
    assert.equal(await checkoutAddress.inputValue(), 'Кызылорда, улица Тестовая, 12', 'the account default address prefills checkout');
    await page.locator('#fulfillment').selectOption('pickup');
    assert.equal(await checkoutAddress.isDisabled(), true, 'pickup disables the delivery address');
    await page.locator('#fulfillment').selectOption('delivery');
    assert.equal(await checkoutAddress.evaluate(input => input.required), true, 'switching back to delivery restores required address validation');

    console.log('Saved addresses: focused PHP browser flow passed (register, CRUD, default, mobile width, checkout prefill and fulfillment).');
  } catch (error) {
    if (serverErrors) error.message += `\nPHP server: ${serverErrors}`;
    throw error;
  } finally {
    await browser?.close();
    if (child.exitCode === null) {
      child.kill();
      await new Promise(resolve => child.once('exit', resolve));
    }
    for (const file of [database, `${database}-wal`, `${database}-shm`]) {
      try { fs.rmSync(file, { force: true }); } catch (_) { /* temporary test database cleanup */ }
    }
  }
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
