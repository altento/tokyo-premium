const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), os = require('node:os'), net = require('node:net');
const { spawn, execFileSync } = require('node:child_process');
const { randomUUID } = require('node:crypto');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const prefix = path.join(os.tmpdir(), 'tokyo-marketing-browser-' + randomUUID());
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(root, 'project-docs/visual-qa/admin-final-2026-11-19');
const env = { ...process.env, RESTAURANT_DB: prefix + '.sqlite' };
for (const key of Object.keys(env)) if (/^TOKYO_(SMTP_|MAIL_|BOOKING_NOTIFY_EMAIL)/.test(key)) delete env[key];
const php = execFileSync('php', ['-r', 'echo PHP_BINARY;'], { encoding: 'utf8' }).trim();
const flags = process.platform === 'win32' ? ['-d', 'extension_dir=' + path.join(path.dirname(php), 'ext'), '-d', 'extension=php_pdo_sqlite.dll'] : [];
const sql = query => JSON.parse(execFileSync(php, [...flags, '-r', 'require $argv[1]; echo json_encode(db()->query($argv[2])->fetchAll(PDO::FETCH_ASSOC));', path.join(root, 'bootstrap.php'), query], { env, encoding: 'utf8' }));
const setup = `require $argv[1];$pdo=db();$password=bin2hex(random_bytes(20));$email='admin+'.bin2hex(random_bytes(6)).'@example.test';
$pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
$dish=(int)$pdo->query('SELECT id FROM dishes WHERE active=1 ORDER BY id LIMIT 1')->fetchColumn();
$pdo->prepare("INSERT INTO promotion_campaigns(slug,campaign_type,title,image_url,reward_type,gift_dish_id,minimum_subtotal,active,stackable) VALUES('qa-browser-gift','gift_threshold','QA inventory gift','assets/generated/room-main-hall.webp','gift_dish',?,1,1,1)")->execute([$dish]);
$gift=['id'=>(int)$pdo->lastInsertId(),'title'=>'QA inventory gift','gift_dish_id'=>$dish];
$pdo->prepare('INSERT INTO campaign_gift_options(campaign_id,dish_id,active,remaining_quantity,max_per_order) VALUES(?,?,0,4,1)')->execute([$gift['id'],$dish]);
file_put_contents($argv[2],json_encode(['email'=>$email,'password'=>$password,'gift'=>$gift]));`;
(async () => {
  let server, browser;
  try {
    fs.mkdirSync(evidence, { recursive: true });
    execFileSync(php, [...flags, '-r', setup, path.join(root, 'bootstrap.php'), prefix + '.json'], { env, stdio: 'pipe' });
    const fixture = JSON.parse(fs.readFileSync(prefix + '.json', 'utf8'));
    const probe = net.createServer(); await new Promise(r => probe.listen(0, '127.0.0.1', r));
    const port = probe.address().port; await new Promise(r => probe.close(r));
    const base = 'http://127.0.0.1:' + port;
    server = spawn(php, [...flags, '-S', '127.0.0.1:' + port, '-t', root, path.join(root, 'tools/home-router.php')], { env, stdio: 'ignore', windowsHide: true });
    let ready = false;
    for (let i = 0; i < 100; i++) { try { if ((await fetch(base + '/admin.php')).ok) { ready = true; break; } } catch {} await new Promise(r => setTimeout(r, 50)); }
    assert.ok(ready);
    browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1440, height: 960 }, reducedMotion: 'reduce' });
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.goto(base + '/admin.php');
    const login = page.locator('form:has(input[name="action"][value="login"])');
    await login.locator('[name="email"]').fill(fixture.email); await login.locator('[name="password"]').fill(fixture.password);
    await login.locator('button').click();
    await page.goto(base + '/admin-marketing.php');
    let campaign = page.locator(`form:has(input[name="action"][value="campaign-save"]):has(input[name="id"][value="${fixture.gift.id}"])`);
    await campaign.evaluate(form => { const details = form.closest('details'); if (details) details.open = true; });
    assert.equal(await campaign.locator('[name="gift_quantity"]').getAttribute('readonly'), '');
    assert.equal(await campaign.locator('[name="gift_max_per_order"]').getAttribute('readonly'), '');
    // An order consumes stock after the administrator opened the metadata editor.
    sql(`UPDATE campaign_gift_options SET remaining_quantity=1 WHERE campaign_id=${Number(fixture.gift.id)} RETURNING remaining_quantity`);
    await campaign.locator('[name="title"]').fill(fixture.gift.title + ' QA');
    await campaign.locator('button[type="submit"]').click();
    const stock = sql(`SELECT active,remaining_quantity,max_per_order FROM campaign_gift_options WHERE campaign_id=${Number(fixture.gift.id)} AND dish_id=${Number(fixture.gift.gift_dish_id)}`)[0];
    assert.deepEqual(stock, { active: 0, remaining_quantity: 1, max_per_order: 1 });

    const createPromo = () => page.locator('form:has(input[name="action"][value="promo-save"]):has(h3)');
    await createPromo().locator('[name="code"]').fill('QABROWSER');
    await createPromo().locator('[name="maximum_uses"]').fill('10');
    await createPromo().locator('button[type="submit"]').click();
    assert.equal(sql("SELECT maximum_uses FROM promo_codes WHERE code='QABROWSER'")[0].maximum_uses, 10);
    await createPromo().locator('[name="code"]').fill('QABROWSER');
    await createPromo().locator('[name="maximum_uses"]').fill('1');
    await createPromo().locator('button[type="submit"]').click();
    assert.equal(sql("SELECT maximum_uses FROM promo_codes WHERE code='QABROWSER'")[0].maximum_uses, 10, 'duplicate create cannot overwrite the existing code');
    await page.goto(base + '/admin-marketing.php#codes');
    let row = page.locator('[data-promo-code="QABROWSER"]');
    await row.locator('summary').click();
    assert.equal(await row.locator('[name="original_code"]').inputValue(), 'QABROWSER');
    await row.locator('[name="maximum_uses"]').fill('3');
    await row.locator('[name="active"]').uncheck();
    await row.locator('button[type="submit"]').click();
    assert.deepEqual(sql("SELECT maximum_uses,active FROM promo_codes WHERE code='QABROWSER'")[0], { maximum_uses: 3, active: 0 });
    row = page.locator('[data-promo-code="QABROWSER"]');
    await row.locator('summary').click();
    assert.ok(await row.locator('[name="starts_at"]').evaluate(e => e.getBoundingClientRect().width >= 200), 'expanded date editor has enough room on desktop');
    await row.screenshot({ path: path.join(evidence, 'promo-edit-desktop.jpg'), quality: 78 });

    const createCollection = page.locator('form:has(input[name="action"][value="collection-save"]):not(:has(input[name="id"]))');
    await createCollection.locator('[name="slug"]').fill('qa-browser-collection');
    await createCollection.locator('[name="name"]').fill('Учебная коллекция QA');
    await createCollection.locator('[name="description"]').fill('Учебный состав проверяется через настоящий интерфейс администратора.');
    await createCollection.locator('[name="dish_ids"]').fill('1,3');
    await createCollection.locator('button[type="submit"]').click();
    const collection = sql("SELECT id,name FROM seasonal_collections WHERE slug='qa-browser-collection'")[0];
    assert.ok(collection);
    let editor = page.locator(`form:has(input[name="action"][value="collection-save"]):has(input[name="id"][value="${collection.id}"])`);
    await editor.evaluate(form => { const details = form.closest('details'); if (details) details.open = true; });
    await editor.locator('[name="name"]').fill('Обновлённая коллекция QA');
    await editor.locator('[name="dish_ids"]').fill('3');
    await editor.locator('button[type="submit"]').click();
    assert.equal(sql(`SELECT name FROM seasonal_collections WHERE id=${collection.id}`)[0].name, 'Обновлённая коллекция QA');
    assert.deepEqual(sql(`SELECT dish_id FROM seasonal_collection_items WHERE collection_id=${collection.id}`), [{ dish_id: 3 }]);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base + '/admin-marketing.php#codes');
    row = page.locator('[data-promo-code="QABROWSER"]'); await row.locator('summary').click();
    await row.locator('summary').focus(); await page.keyboard.press('Enter');
    assert.equal(await row.locator('details').evaluate(e => e.open), false);
    await page.keyboard.press('Enter');
    assert.equal(await row.locator('details').evaluate(e => e.open), true, 'keyboard opens and closes the editor');
    for (const width of [320, 390, 768, 1024, 1440, 1920]) {
      await page.setViewportSize({ width, height: 844 });
      await page.evaluate(() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))));
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `admin at ${width}px`);
      const clipped = await row.locator('input:not([type="hidden"]),select').evaluateAll(controls => controls.filter(e => { const r = e.getBoundingClientRect(); return r.left < -1 || r.right > innerWidth + 1; }).map(e => e.name));
      assert.deepEqual(clipped, [], `promo controls fit ${width}px`);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))));
    await page.addStyleTag({ content: '.site-header,.tp20-admin-shortcuts{position:relative!important;top:auto!important}' });
    await row.screenshot({ path: path.join(evidence, 'promo-edit-mobile.jpg'), quality: 78 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(evidence, 'marketing-browser.json'), JSON.stringify({ status: 'passed', stockAfterMetadataSave: stock, flows: ['login', 'stock preservation', 'promo create/duplicate/edit/disable', 'collection create/edit/items', 'keyboard editor controls'], widths: [320, 390, 768, 1024, 1440, 1920], pageErrors: errors }, null, 2) + '\n');
    console.log('PASS: authenticated marketing browser, metadata/stock, promo create/edit/duplicate, collection create/edit/items and 390px layout.');
  } finally {
    if (browser) await browser.close();
    if (server && server.exitCode === null) { server.kill(); await new Promise(r => { server.once('exit', r); setTimeout(r, 1500); }); }
    for (const suffix of ['.json', '.sqlite', '.sqlite-wal', '.sqlite-shm']) { try { fs.unlinkSync(prefix + suffix); } catch {} }
  }
})().catch(e => { console.error(e); process.exitCode = 1; });
