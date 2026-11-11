<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__);
$docs = $root . '/docs';
$source = getenv('TOKYO_EXPORT_URL') ?: 'http://127.0.0.1:8080';
if (!is_dir($docs) && !mkdir($docs, 0775, true) && !is_dir($docs)) throw new RuntimeException('Cannot create docs directory');
foreach (['luxury.css','editorial.css','premium.css','luxury.js','pages-extra.js','pages-demo.js','account-demo.js','reviews.js'] as $asset) {
    if (!copy($root . '/' . $asset, $docs . '/' . $asset)) throw new RuntimeException('Cannot copy ' . $asset);
}
$copyAssets = function (string $sourceDir, string $targetDir) use (&$copyAssets): void {
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) throw new RuntimeException('Cannot create ' . $targetDir);
    foreach (scandir($sourceDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $entry;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($sourcePath)) { $copyAssets($sourcePath, $targetPath); continue; }
        if (!preg_match('/\.(webp|svg|woff2?)$/i', $entry)) continue;
        if (!copy($sourcePath, $targetPath)) throw new RuntimeException('Cannot copy ' . $sourcePath);
    }
};
$copyAssets($root . '/assets', $docs . '/assets');
file_put_contents($docs . '/.nojekyll', '');
$routes = [
  '/' => 'index.html','/menu.php' => 'menu.html','/about.php' => 'about.html','/reviews.php' => 'reviews.html',
  '/pages.php?page=omakase' => 'omakase.html','/pages.php?page=gallery' => 'gallery.html',
  '/pages.php?page=booking' => 'booking.html','/pages.php?page=contacts' => 'contacts.html',
  '/pages.php?page=cart' => 'cart.html'
];
foreach (dishes() as $dish) $routes['/pages.php?page=dish&id='.(int)$dish['id']] = 'dish-'.(int)$dish['id'].'.html';
foreach ($routes as $route => $filename) {
    $html = @file_get_contents($source . $route);
    if ($html === false || !str_contains($html, '<!doctype html>')) throw new RuntimeException('Could not export ' . $route);
    $html = str_replace(
      ['href="index.php#booking"','href="index.php"','href="menu.php"','href="about.php"','href="reviews.php"','href="account.php"','href="pages.php?page=omakase"','href="pages.php?page=gallery"','href="pages.php?page=booking"','href="pages.php?page=contacts"','href="pages.php?page=cart"'],
      ['href="booking.html"','href="index.html"','href="menu.html"','href="about.html"','href="reviews.html"','href="account.html"','href="omakase.html"','href="gallery.html"','href="booking.html"','href="contacts.html"','href="cart.html"'],
      $html
    );
    $html = preg_replace_callback('/href="pages\.php\?page=dish&amp;id=(\d+)"/', fn($m) => 'href="dish-'.$m[1].'.html"', $html) ?? $html;
    $html = preg_replace('/<a href="admin\.php">(?:Управление сайтом|Администрирование)<\/a>/', '', $html) ?? $html;
    $html = str_replace(
      ['Мы перезвоним и подготовим стол к выбранному времени.','После заявки команда подтвердит время и пожелания к вечеру.','Отзыв появится на сайте после проверки командой ресторана.'],
      ['В этой публичной демоверсии заявка сохраняется только в вашем браузере.','Уведомление ресторану из статической версии не отправляется.','Отзыв останется в этом браузере и не будет опубликован.'],
      $html
    );
    $html = str_replace('<script src="luxury.js" defer></script><script src="pages-extra.js" defer></script>', '<script src="luxury.js" defer></script><script src="pages-extra.js" defer></script><script src="pages-demo.js" defer></script>', $html);
    if (file_put_contents($docs . '/' . $filename, $html) === false) throw new RuntimeException('Cannot write ' . $filename);
}

// Browser-only account: no account, password, payment, or private data is sent to a server.
$account = <<<'HTML'
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Демо-профиль и локальная история Tokyo Premium в этом браузере."><meta name="theme-color" content="#111310"><title>Ваш профиль — Tokyo Premium</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Manrope:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="luxury.css"><link rel="stylesheet" href="editorial.css"><link rel="stylesheet" href="premium.css"></head><body><header class="site-header"><a class="wordmark" href="index.html"><span class="wordmark-kanji">東京</span><span>TOKYO <i>PREMIUM</i></span></a><nav class="main-nav" aria-label="Основная навигация"><a href="index.html">Главная</a><a href="about.html">Философия</a><a href="menu.html">Меню</a><a href="reviews.html">Гости</a><a href="gallery.html">Пространство</a><a href="omakase.html">Omakase</a><a href="contacts.html">Контакты</a><a class="active" href="account.html">Профиль</a><a href="cart.html">Корзина</a></nav><a href="booking.html" class="header-cta">Забронировать ↗</a><button class="mobile-menu" aria-label="Открыть меню" aria-expanded="false">☰</button></header><main class="editorial-page"><section class="page-hero"><div class="kicker">Tokyo Premium · браузерная демоверсия</div><h1>Ваш профиль</h1><p>Сохранённые позиции, настройки и записи существуют только в локальном хранилище этого браузера. Хранилище браузера не зашифровано: используйте вымышленные данные; не вводите реальные контакты или адрес.</p></section><section class="section"><div class="account-demo-layout"><nav class="account-demo-nav" aria-label="Разделы профиля"><button class="active" data-account-tab="history">История</button><button data-account-tab="favorites">Избранное</button><button data-account-tab="profile">Профиль</button><button data-account-tab="addresses">Адреса</button><button data-account-tab="settings">Настройки</button></nav><div><section class="account-demo-panel" data-account-panel="history"><div class="intro-grid"><div><div class="kicker">Локальные записи</div><h2>Заказы</h2><div id="demo-orders" class="account-list"></div></div><div><div class="kicker">Локальные записи</div><h2>Бронирования</h2><div id="demo-bookings" class="account-list"></div></div></div></section><section class="account-demo-panel" data-account-panel="favorites" hidden><div class="kicker">Избранное</div><h2>Сохранённые блюда</h2><div id="demo-favorites" class="dish-grid"></div><p id="demo-favorites-empty" class="menu-note" hidden>Пока ничего не сохранено. Добавьте сердце на карточке блюда.</p></section><section class="account-demo-panel" data-account-panel="profile" hidden><div class="kicker">Только этот браузер</div><h2>Контактный профиль</h2><form class="account-demo-form" data-local-form="profile"><div class="field"><label for="demo-name">Имя</label><input id="demo-name" name="name" maxlength="80"></div><div class="field"><label for="demo-phone">Телефон</label><input id="demo-phone" name="phone" maxlength="30"></div><button class="button">Сохранить профиль ↗</button><p class="form-status" aria-live="polite"></p></form></section><section class="account-demo-panel" data-account-panel="addresses" hidden><div class="kicker">Адреса · локально</div><h2>Сохранённые адреса</h2><form class="account-demo-form" data-local-form="address"><div class="field full"><label for="demo-address">Адрес</label><input id="demo-address" name="address" required maxlength="180" autocomplete="street-address"></div><button class="button">Добавить адрес ↗</button><p class="form-status" aria-live="polite"></p></form><div id="demo-addresses" class="account-list"></div></section><section class="account-demo-panel" data-account-panel="settings" hidden><div class="kicker">Контроль данных</div><h2>Настройки демо-профиля</h2><p>Сотрите все локальные записи этого сайта с устройства. Это действие нельзя отменить.</p><button id="demo-clear-data" class="button secondary">Очистить данные в этом браузере</button><p class="form-status" id="demo-settings-status" aria-live="polite"></p></section></div></div></section></main><footer class="footer"><div class="footer-top"><a class="wordmark" href="index.html"><span class="wordmark-kanji">東京</span><span>TOKYO <i>PREMIUM</i></span></a><p>Сезонность. Точность. Тишина.</p><a href="booking.html">Ваш вечер начинается здесь ↗</a></div><div class="footer-bottom"><span>© 2026 Tokyo Premium</span><span>Демонстрационная версия</span></div><p class="school-note">Учебный проект: ресторан, адрес, меню, гости и контакты вымышлены.</p></footer><div class="toast" role="status" aria-live="polite"></div><script src="luxury.js" defer></script><script src="pages-extra.js" defer></script><script src="account-demo.js" defer></script></body></html>
HTML;
$dishJson = json_encode(dishes(), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
$account = str_replace('</main>', '<script>window.DISHES='.$dishJson.';</script></main>', $account);
file_put_contents($docs . '/account.html', $account);
$expected = array_values($routes);
$expected[] = 'account.html';
foreach (array_unique($expected) as $filename) {
    $output = file_get_contents($docs . '/' . $filename);
    if ($output === false || !str_contains($output, '<title>')) throw new RuntimeException('Invalid exported document: ' . $filename);
    if (preg_match('/(?:href|src)="[^"]+\\.php(?:[?#"]|&amp;)/', $output)) throw new RuntimeException('PHP-only link left in static page: ' . $filename);
}
echo "Exported " . count(array_unique($expected)) . " static pages to $docs\n";
