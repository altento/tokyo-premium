const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), os = require('node:os'), net = require('node:net');
const { spawn, execFileSync } = require('node:child_process');
const { randomUUID } = require('node:crypto');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const prefix = path.join(os.tmpdir(), 'tokyo-admin-flow-' + randomUUID());
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(root, 'project-docs/visual-qa/admin-mail-2026-11-19');
const env = { ...process.env, RESTAURANT_DB: prefix + '.sqlite' };
for (const key of Object.keys(env)) if (/^TOKYO_(SMTP_|MAIL_|BOOKING_NOTIFY_EMAIL)/.test(key)) delete env[key];
const php = execFileSync('php', ['-r', 'echo PHP_BINARY;'], { encoding: 'utf8' }).trim();
const flags = process.platform === 'win32' ? ['-d', 'extension_dir=' + path.join(path.dirname(php), 'ext'), '-d', 'extension=php_pdo_sqlite.dll'] : [];
const setup = `require $argv[1]; $pdo=db(); $password=bin2hex(random_bytes(20));
$email='admin+'.bin2hex(random_bytes(6)).'@example.test';
$pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
$userEmail='guest+'.bin2hex(random_bytes(6)).'@example.test';
$pdo->prepare('INSERT INTO users(email,full_name,password_hash) VALUES(?,?,?)')->execute([$userEmail,'QA Guest',password_hash($password,PASSWORD_DEFAULT)]);$user=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO user_profiles(user_id) VALUES(?)')->execute([$user]);
$pdo->prepare("INSERT INTO bookings(user_id,guest_name,guest_email,phone,guest_count,booking_date,booking_time) VALUES(?,?,?,?,?,?,?)")->execute([$user,'QA Бронь Поиск',$userEmail,'+77010000000',2,date('Y-m-d',strtotime('+2 days')),'19:00']);$booking=(int)$pdo->lastInsertId();
$review=$pdo->prepare('INSERT INTO reviews(guest_name,rating,body,status) VALUES(?,?,?,?)');
$review->execute(['QA Pending Review',5,'Fictitious browser review for moderation.','pending']);
$review->execute(['QA Published Review',5,'Fictitious published browser review.','published']);
file_put_contents($argv[2],json_encode(['email'=>$email,'password'=>$password,'userEmail'=>$userEmail,'booking'=>$booking]));`;
const inspect = sql => JSON.parse(execFileSync(php, [...flags, '-r', 'require $argv[1]; echo json_encode(db()->query($argv[2])->fetchAll(PDO::FETCH_ASSOC));', path.join(root, 'bootstrap.php'), sql], { env, encoding: 'utf8' }));
(async () => {
  let server, browser;
  try {
    fs.mkdirSync(evidence, { recursive: true });
    execFileSync(php, [...flags, '-r', setup, path.join(root, 'bootstrap.php'), prefix + '.json'], { env, stdio: 'pipe' });
    const fixture = JSON.parse(fs.readFileSync(prefix + '.json', 'utf8'));
    const probe = net.createServer(); await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
    const port = probe.address().port; await new Promise(resolve => probe.close(resolve));
    const base = 'http://127.0.0.1:' + port;
    server = spawn(php, [...flags, '-S', '127.0.0.1:' + port, '-t', root, path.join(root, 'tools/home-router.php')], { env, stdio: 'ignore', windowsHide: true });
    let ready = false;
    for (let tries = 0; tries < 100; tries++) { try { if ((await fetch(base + '/admin.php')).ok) { ready = true; break; } } catch {} await new Promise(r => setTimeout(r, 50)); }
    assert.ok(ready, 'isolated PHP server starts');
    browser = await chromium.launch();
    const admin = await browser.newPage({ viewport: { width: 1440, height: 960 }, reducedMotion: 'reduce' });
    const guest = await browser.newPage({ viewport: { width: 390, height: 960 }, reducedMotion: 'reduce' });
    const errors = []; for (const page of [admin, guest]) page.on('pageerror', e => errors.push(e.message));
    const login = async (page, route, email) => {
      await page.goto(base + route);
      const form = page.locator('form:has(input[name="action"][value="login"])');
      await form.locator('[name="email"]').fill(email); await form.locator('[name="password"]').fill(fixture.password);
      await form.locator('button').click(); await page.waitForLoadState('domcontentloaded');
    };
    await login(admin, '/admin.php', fixture.email);
    await admin.goto(base + '/admin.php?notification=reviews#reviews');
    assert.equal(await admin.locator('#filter-status-reviews').inputValue(), 'pending', 'notification filter describes the actual pending-review query');
    assert.equal(await admin.locator('#reviews .admin-record').filter({ hasText: 'QA Published Review' }).count(), 0);
    await admin.locator('#reviews').screenshot({ path: path.join(evidence, 'reviews-filter.jpg'), quality: 78 });
    await admin.locator('#admin-search-q').fill('БРОНЬ ПОИСК');
    await admin.locator('.admin-search-form button[type="submit"]').first().click();
    assert.equal(await admin.locator('#bookings .admin-record').filter({ hasText: 'QA Бронь Поиск' }).count(), 1, 'Cyrillic search finds the booking');
    await admin.locator('[name="clear_search"]').click(); await admin.waitForLoadState('domcontentloaded');
    const changeStatus = async status => {
      await admin.goto(base + '/admin.php#bookings');
      const record = admin.locator('#bookings .admin-record').filter({ hasText: 'QA Бронь Поиск' });
      await record.locator('.admin-status-form select').selectOption(status);
      await record.locator('.admin-status-form button').click(); await admin.waitForLoadState('domcontentloaded');
    };
    await changeStatus('confirmed');
    assert.equal(inspect(`SELECT status FROM email_outbox WHERE event_key='booking:${fixture.booking}:confirmed:guest'`)[0].status, 'pending');
    await admin.locator('#bookings').screenshot({ path: path.join(evidence, 'booking-confirmed-admin.jpg'), quality: 78 });
    await login(guest, '/account.php', fixture.userEmail);
    assert.ok(await guest.locator('.booking-confirmation-notice').isVisible());
    await guest.locator('#account-bookings').screenshot({ path: path.join(evidence, 'booking-confirmed-guest.jpg'), quality: 78 });
    await changeStatus('cancelled');
    assert.equal(inspect(`SELECT status FROM email_outbox WHERE event_key='booking:${fixture.booking}:confirmed:guest'`)[0].status, 'cancelled');
    assert.match(await admin.locator('#bookings').innerText(), /Отменено: бронь не подтверждена/);
    await guest.reload();
    assert.equal(await guest.locator('.booking-confirmation-notice').count(), 0);
    assert.match(await guest.locator('#account-bookings').innerText(), /отмен/i);
    assert.equal(inspect("SELECT COUNT(*) AS count FROM user_notifications WHERE notification_type='booking_status'")[0].count, 2);
    await admin.setViewportSize({ width: 390, height: 960 });
    await admin.locator('#bookings').screenshot({ path: path.join(evidence, 'booking-cancelled-admin-mobile.jpg'), quality: 78 });
    assert.ok(await admin.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    assert.deepEqual(errors, []);
    console.log('PASS: isolated admin login, pending-review filter, Cyrillic search, booking confirm/cancel, guest badge and two status notifications; SMTP disabled.');
  } finally {
    if (browser) await browser.close();
    if (server && server.exitCode === null) { server.kill(); await new Promise(resolve => { server.once('exit', resolve); setTimeout(resolve, 1500); }); }
    for (const suffix of ['.json', '.sqlite', '.sqlite-wal', '.sqlite-shm']) { try { fs.unlinkSync(prefix + suffix); } catch {} }
  }
})().catch(e => { console.error(e); process.exit(1); });
