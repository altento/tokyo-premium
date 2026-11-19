"""Isolated HTTP regression: no merchant traffic, credentials or real orders."""
import hashlib
import hmac
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

root = Path(__file__).resolve().parents[1]
php = os.environ.get('PHP_BINARY') or shutil.which('php')
assert php, 'PHP is required'
flags = []
if os.name == 'nt':
    flags = ['-d', 'extension_dir=' + str(Path(php).parent / 'ext'), '-d', 'extension=php_pdo_sqlite.dll']
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
base = f'http://127.0.0.1:{port}'
secret = 'isolated-test-secret-not-a-merchant-key'
checks = 0
def check(value, label):
    global checks
    assert value, label
    checks += 1

with tempfile.TemporaryDirectory(prefix='tokyo-payment-') as temporary:
    dbfile = Path(temporary) / 'fixture.sqlite'
    env = {key: value for key, value in os.environ.items() if not key.startswith(('TOKYO_SMTP_', 'TOKYO_MAIL_', 'FREEDOMPAY_'))}
    env.update(RESTAURANT_DB=str(dbfile), FREEDOMPAY_MERCHANT_ID='123456', FREEDOMPAY_SECRET_KEY=secret,
               FREEDOMPAY_TEST_MODE='1', TOKYO_PUBLIC_BASE_URL='https://fixture.example.invalid')
    log = open(Path(temporary) / 'server.log', 'w+', encoding='utf-8')
    process = subprocess.Popen([php, *flags, '-S', f'127.0.0.1:{port}', '-t', str(root), str(root / 'tools/home-router.php')],
                               cwd=root, env=env, stdout=log, stderr=log,
                               creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(path, fields=None):
        data = urllib.parse.urlencode(fields).encode() if fields is not None else None
        try:
            with opener.open(base + path, data=data, timeout=10) as response:
                return response.status, response.read().decode('utf-8'), response.headers
        except urllib.error.HTTPError as error:
            return error.code, error.read().decode('utf-8'), error.headers
    def notify(order, payment, result='1', amount='2000', reject='0'):
        fields = dict(pg_order_id=f'TP{order}A1', pg_payment_id=payment, pg_result=result,
                      pg_amount=amount, pg_currency='KZT', pg_can_reject=reject, pg_salt='fixture')
        values = ';'.join(str(fields[key]) for key in sorted(fields))
        fields['pg_sig'] = hashlib.md5(f'payment.php;{values};{secret}'.encode()).hexdigest()
        return request('/payment.php?action=notify', fields)
    try:
        for attempt in range(50):
            try:
                status, body, _ = request('/')
                break
            except (OSError, urllib.error.URLError):
                time.sleep(.1)
        check(status == 200, 'home initializes isolated database')
        for path in ['/schema.sql', '/seed.sql', '/.git/config', '/.env', '/bootstrap.php', '/setup_admin.php', '/lib/commerce.php', '/tools/export-pages.php', '/tests/payment-home-http.py', '/project-docs/deployment.md', '/assets/../schema.sql', '/assets/%2e%2e/schema.sql']:
            check(request(path)[0] == 404, 'private file blocked: ' + path)
        check(request('/luxury.css')[0] == 200, 'browser stylesheet served')
        for page in ['about', 'reviews']:
            status, markup, _ = request('/' + page + '.php')
            check(status == 200 and f'class="active" href="{page}.php"' in markup, 'active section marker: ' + page)
        connection = sqlite3.connect(dbfile)
        for number, state in [(1, 'new'), (2, 'new'), (3, 'cancelled')]:
            connection.execute("INSERT INTO orders(id,customer_name,phone,total,status,payment_status,payment_provider,payment_access_hash) VALUES(?,?,?,?,?,'pending','freedompay',?)", (number, 'Fixture', '77000000000', 2000, state, hashlib.sha256(('a'*64).encode()).hexdigest() if number == 2 else None))
            connection.execute("INSERT INTO order_payments(order_id,attempt_no,provider,provider_order_id,amount,currency,status,provider_payment_id) VALUES(?,1,'freedompay',?,2000,'KZT',?,?)", (number, f'TP{number}A1', 'creating' if number == 2 else 'pending', None if number == 2 else f'pay-{number}'))
        connection.commit()
        status, cart, _ = request('/pages.php?page=cart')
        csrf = re.search(r'name="csrf" value="([^"]+)"', cart).group(1)
        check(request('/payment.php?action=start', {'payment_token':'a'*64, 'csrf':csrf})[0] == 409, 'uncertain attempt cannot create another payment')
        check(notify(1, 'wrong-id')[0] == 409, 'callback cannot rebind provider identifier')
        notify(1, 'pay-1', amount='1')
        check(connection.execute('SELECT payment_status FROM orders WHERE id=1').fetchone()[0] == 'pending', 'wrong amount cannot pay')
        check(notify(1, 'pay-1')[0] == 200, 'valid callback accepted')
        check(notify(1, 'pay-1')[0] == 200, 'duplicate notification accepted')
        notify(1, 'pay-1', result='0')
        check(connection.execute('SELECT payment_status FROM orders WHERE id=1').fetchone()[0] == 'paid', 'paid state cannot downgrade')
        notify(3, 'pay-3')
        check(connection.execute('SELECT status,payment_status FROM orders WHERE id=3').fetchone() == ('cancelled','paid'), 'non-rejectable payment on cancelled order stays recorded for refund review')
        signature = hmac.new(secret.encode(), b'tokyo-payment-return|1', hashlib.sha256).hexdigest()
        status, body, headers = request('/payment.php?action=return&order=1&sig=' + signature)
        check(status == 200 and 'Платёж подтверждён' in body, 'signed payment status renders')
        check(headers.get('Cache-Control') == 'private, no-store', 'payment status is not cached')
        check(request('/payment.php?action=return&order=1&sig=invalid')[0] == 404, 'invalid status capability rejected')
        check(connection.execute('SELECT COUNT(*) FROM order_payments').fetchone()[0] == 3, 'no duplicate attempt created')
        connection.close()
        print(f'PASS: {checks} isolated payment/home-host HTTP checks')
    finally:
        process.terminate()
        process.wait(timeout=10)
        log.close()
