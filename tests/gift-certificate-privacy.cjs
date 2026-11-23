const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium } = require('playwright');

const docsRoot = path.resolve(__dirname, '..', 'docs');
const phpBase = process.env.TOKYO_PHP_BASE_URL || '';
const mime = { '.css': 'text/css', '.html': 'text/html', '.js': 'text/javascript', '.json': 'application/json', '.svg': 'image/svg+xml', '.webp': 'image/webp' };

function startStaticServer() {
  const server = http.createServer((request, response) => {
    let pathname;
    try { pathname = decodeURIComponent(new URL(request.url, 'http://127.0.0.1').pathname); }
    catch { response.writeHead(400).end(); return; }
    const file = path.resolve(docsRoot, `.${pathname}`);
    if (file !== docsRoot && !file.startsWith(docsRoot + path.sep)) { response.writeHead(403).end(); return; }
    fs.readFile(file, (error, body) => {
      if (error) { response.writeHead(404).end(); return; }
      response.writeHead(200, { 'Content-Type': mime[path.extname(file)] || 'application/octet-stream' }).end(body);
    });
  });
  return new Promise(resolve => server.listen(0, '127.0.0.1', () => resolve({ server, base: `http://127.0.0.1:${server.address().port}` })));
}

async function run() {
  const { server, base } = await startStaticServer();
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const pageErrors = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    await page.goto(`${base}/gift-certificates.html`, { waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('[name="recipient_email"]').count(), 0, 'Pages preview does not collect recipient email');
    await page.locator('[name="recipient_name"]').fill('QA Recipient');
    await page.locator('[name="greeting"]').fill('Preview only');
    await page.locator('[data-tp-gift-form] button[type="submit"]').click();
    await page.waitForFunction(() => /preview_only|localStorage|не является сертификатом/i.test(document.querySelector('[data-tp-gift-status]')?.textContent || ''));
    assert.match(await page.locator('[data-tp-gift-recipient]').innerText(), /QA Recipient/);

    if (phpBase) {
      await page.goto(new URL('/gift-certificates.php', phpBase).href, { waitUntil: 'domcontentloaded' });
      assert.equal(await page.locator('[name="recipient_email"]').count(), 0, 'PHP preview does not collect recipient email');
      const forged = await page.evaluate(async () => {
        const form = document.querySelector('[data-tp-gift-form]');
        const payload = new FormData(form);
        payload.set('type', 'gift_preview');
        payload.set('recipient_name', 'QA Recipient');
        payload.set('recipient_email', 'crafted-recipient@example.test');
        const response = await fetch(new URL('api.php', location.href), { method: 'POST', body: payload, credentials: 'same-origin' });
        return { status: response.status, body: await response.json() };
      });
      assert.equal(forged.status, 201, 'PHP API accepts the valid preview request');
      assert.equal(Object.prototype.hasOwnProperty.call(forged.body.preview || {}, 'recipient_email'), false, 'PHP API response omits recipient email');
    }
    assert.deepEqual(pageErrors, [], 'no browser errors');
    console.log(`PASS: Pages preview does not collect email${phpBase ? '; PHP API ignores forged email' : '; PHP API check skipped (TOKYO_PHP_BASE_URL unset)'}`);
  } finally {
    await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
}

run().catch(error => { console.error(error); process.exitCode = 1; });
