<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$docs = $root . '/docs';
$source = getenv('TOKYO_EXPORT_URL') ?: 'http://127.0.0.1:8080';
if (!is_dir($docs) && !mkdir($docs, 0775, true) && !is_dir($docs)) {
    throw new RuntimeException('Cannot create docs directory');
}
foreach (['luxury.css', 'luxury.js'] as $asset) {
    if (!copy($root . '/' . $asset, $docs . '/' . $asset)) {
        throw new RuntimeException('Cannot copy ' . $asset);
    }
}
file_put_contents($docs . '/.nojekyll', '');
$routes = ['/' => 'index.html', '/menu.php' => 'menu.html', '/about.php' => 'about.html', '/reviews.php' => 'reviews.html'];
foreach ($routes as $route => $filename) {
    $html = @file_get_contents($source . $route);
    if ($html === false || !str_contains($html, '<!doctype html>')) {
        throw new RuntimeException('Could not export ' . $route);
    }
    $html = str_replace(
        ['href="index.php#booking"', 'href="index.php"', 'href="menu.php"', 'href="about.php"', 'href="reviews.php"', 'href="account.php"', 'class="add-to-cart favorite-btn"'],
        ['href="index.html#booking"', 'href="index.html"', 'href="menu.html"', 'href="about.html"', 'href="reviews.html"', 'href="account.html"', 'class="favorite-btn"'],
        $html
    );
    $html = preg_replace('/<a href="admin\.php">Управление сайтом<\/a>/', '', $html) ?? $html;
    $html = str_replace('<script src="luxury.js" defer></script>', '<script src="luxury.js" defer></script><script src="pages-demo.js" defer></script>', $html);
    if (file_put_contents($docs . '/' . $filename, $html) === false) {
        throw new RuntimeException('Cannot write ' . $filename);
    }
}

$account = <<<'HTML'
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Демо-история Tokyo Premium в этом браузере."><meta name="theme-color" content="#111310"><title>История — Tokyo Premium</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Manrope:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="luxury.css"></head><body><header class="site-header"><a class="wordmark" href="index.html"><span class="wordmark-kanji">東京</span><span>TOKYO <i>PREMIUM</i></span></a><nav class="main-nav" aria-label="Основная навигация"><a href="index.html">Главная</a><a href="about.html">Философия</a><a href="menu.html">Меню</a><a href="reviews.html">Гости</a><a class="active" href="account.html">История</a></nav><a href="index.html#booking" class="header-cta">Забронировать ↗</a><button class="mobile-menu" aria-label="Открыть меню" aria-expanded="false">☰</button></header><main><section class="page-hero"><div class="kicker">Tokyo Premium · демонстрационная версия</div><h1>Ваша история</h1><p>Заказы и брони сохраняются только в локальном хранилище этого браузера. Здесь нет регистрации, сервера или отправки личных данных.</p></section><section class="section"><div class="intro-grid"><div><div class="kicker">Локальные записи</div><h2>Заказы</h2><div id="demo-orders"></div></div><div><div class="kicker">Локальные записи</div><h2>Бронирования</h2><div id="demo-bookings"></div></div></div><p class="menu-note">Чтобы начать, выберите блюдо или заполните форму бронирования на главной странице.</p><a class="button" href="menu.html">Перейти к меню ↗</a></section></main><footer class="footer"><div class="footer-top"><a class="wordmark" href="index.html"><span class="wordmark-kanji">東京</span><span>TOKYO <i>PREMIUM</i></span></a><p>Сезонность. Точность. Тишина.</p><a href="index.html#booking">Ваш вечер начинается здесь ↗</a></div><div class="footer-bottom"><span>© 2026 Tokyo Premium</span><span>Демонстрационная версия</span></div><p class="school-note">Учебный проект: ресторан, адрес, меню, гости и контакты вымышлены.</p></footer><div class="toast" role="status" aria-live="polite"></div><script src="luxury.js" defer></script><script src="account-demo.js" defer></script></body></html>
HTML;
file_put_contents($docs . '/account.html', $account);
file_put_contents($docs . '/pages-demo.js', <<<'JS'
document.addEventListener('submit', event => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.matches('form[data-api]')) return;
  event.preventDefault(); event.stopImmediatePropagation();
  const status = form.querySelector('.form-status'); const data = Object.fromEntries(new FormData(form).entries()); const type = form.dataset.api;
  const key = type === 'order' ? 'tokyo-demo-orders' : type === 'booking' ? 'tokyo-demo-bookings' : 'tokyo-demo-reviews'; let rows = [];
  try { rows = JSON.parse(localStorage.getItem(key) || '[]'); } catch {}
  if (type === 'order') {
    let items = []; try { items = JSON.parse(localStorage.getItem('tokyo-cart') || '[]'); } catch {}
    const total = items.reduce((sum, item) => sum + Number((window.DISHES || []).find(d => Number(d.id) === Number(item.id))?.price || 0) * Number(item.qty || 0), 0);
    if (!items.length || !total) { status.textContent = 'Добавьте блюда в заказ перед подтверждением.'; status.className = 'form-status error'; return; }
    rows.unshift({ name: data.name, date: new Date().toLocaleString('ru-RU'), total, count: items.reduce((s, i) => s + Number(i.qty || 0), 0) }); localStorage.setItem('tokyo-cart', '[]');
  } else if (type === 'booking') rows.unshift({ name: data.name, date: data.date, time: data.time, guests: data.guests });
  else if (type === 'review') rows.unshift({ name: data.name, rating: data.rating, text: data.body, date: new Date().toLocaleDateString('ru-RU') });
  localStorage.setItem(key, JSON.stringify(rows.slice(0, 20))); status.textContent = type === 'review' ? 'Отзыв сохранён как демо-запись в этом браузере.' : 'Готово: запись сохранена только в этом браузере. Это демоверсия без отправки данных.'; status.className = 'form-status success'; form.reset();
});
JS
);
file_put_contents($docs . '/account-demo.js', <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
  const esc = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const blocks = [
    ['tokyo-demo-orders','demo-orders', rows => rows.map(row => '<article class="dish-card"><div class="dish-meta"><h3>' + esc(row.date) + '</h3><p>' + esc(row.name) + ' · ' + row.count + ' поз. · ' + Number(row.total).toLocaleString('ru-RU') + ' ₸</p></div></article>').join('')],
    ['tokyo-demo-bookings','demo-bookings', rows => rows.map(row => '<article class="dish-card"><div class="dish-meta"><h3>' + esc(row.date) + ' · ' + esc(row.time) + '</h3><p>' + esc(row.name) + ' · гостей: ' + esc(row.guests) + '</p></div></article>').join('')]
  ];
  for (const [key, id, render] of blocks) { let rows = []; try { rows = JSON.parse(localStorage.getItem(key) || '[]'); } catch {} document.getElementById(id).innerHTML = rows.length ? render(rows) : '<p class="menu-note">Пока записей нет.</p>'; }
});
JS
);
echo "Static preview built in $docs\n";