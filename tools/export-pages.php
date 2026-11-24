<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__);
$docs = rtrim(getenv('TOKYO_EXPORT_DIR') ?: ($root . '/docs'), '/\\');
$source = getenv('TOKYO_EXPORT_URL') ?: 'http://127.0.0.1:8080';
if (!is_dir($docs) && !mkdir($docs, 0775, true) && !is_dir($docs)) throw new RuntimeException('Cannot create docs directory');
foreach (['luxury.css','editorial.css','premium.css','luxury-refinements.css','tokyo20.css','experience-ui.css','account-demo.css','cart-store.js','luxury.js','pages-extra.js','pages-demo.js','account-demo.js','reviews.js','navigation.js','experience-ui.js','tokyo-secret-demo.js','tokyo20.js','home-booking.js','assets/vendor/gsap.min.js','assets/vendor/ScrollTrigger.min.js','motion-system.js'] as $asset) {
    $target = $docs . '/' . $asset;
    $targetDirectory = dirname($target);
    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) throw new RuntimeException('Cannot create ' . $targetDirectory);
    if (!copy($root . '/' . $asset, $target)) throw new RuntimeException('Cannot copy ' . $asset);
}
$copyAssets = function (string $sourceDir, string $targetDir) use (&$copyAssets): void {
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) throw new RuntimeException('Cannot create ' . $targetDir);
    foreach (scandir($sourceDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $entry;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($sourcePath)) { $copyAssets($sourcePath, $targetPath); continue; }
        if (!preg_match('/\.(webp|jpe?g|svg|woff2?|css)$/i', $entry)) continue;
        if (!copy($sourcePath, $targetPath)) throw new RuntimeException('Cannot copy ' . $sourcePath);
    }
};
$copyAssets($root . '/assets', $docs . '/assets');
file_put_contents($docs . '/.nojekyll', '');
$routes = [
  '/' => 'index.html','/menu.php' => 'menu.html','/about.php' => 'about.html','/reviews.php' => 'reviews.html',
  '/pages.php?page=omakase' => 'omakase.html','/pages.php?page=gallery' => 'gallery.html',
  '/pages.php?page=booking' => 'booking.html','/pages.php?page=contacts' => 'contacts.html',
  '/pages.php?page=cart' => 'cart.html',
  '/offers.php' => 'offers.html','/set-builder.php' => 'set-builder.html','/perfect-evening.php' => 'perfect-evening.html','/gift-certificates.php' => 'gift-certificates.html'
];
foreach (commerce_public_collections(db()) as $collection) { $slug=(string)$collection['slug']; if (preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/',$slug)) $routes['/collection.php?slug='.$slug]='collection-'.$slug.'.html'; }
foreach (db()->query('SELECT slug FROM custom_set_variants WHERE active=1 ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN) as $variantSlug) { $variantSlug=(string)$variantSlug; if (preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/',$variantSlug)) $routes['/set-builder.php?variant='.$variantSlug]='set-builder-'.$variantSlug.'.html'; }
foreach (dishes() as $dish) $routes['/pages.php?page=dish&id='.(int)$dish['id']] = 'dish-'.(int)$dish['id'].'.html';
$expectedStaticFiles=array_fill_keys(array_values($routes),true);
// The destination can contain an earlier export. Remove only stale generated dish
// detail pages so a previously public Secret Menu route cannot survive a rebuild.
foreach (glob($docs.'/dish-*.html')?:[] as $staleDishPage) {
    if (preg_match('/^dish-\d+\.html$/',basename($staleDishPage)) && !isset($expectedStaticFiles[basename($staleDishPage)])) @unlink($staleDishPage);
}
foreach ($routes as $route => $filename) {
    $html = @file_get_contents($source . $route);
    if ($html === false || !str_contains($html, '<!doctype html>')) throw new RuntimeException('Could not export ' . $route);
    if (!str_contains($html, 'id="tp20-view-transition"')) throw new RuntimeException('Page transition opt-in must be present in the document head before exporting ' . $route);
    $html = str_replace(
      ['href="index.php#booking"','href="index.php"','href="menu.php"','href="about.php"','href="reviews.php"','href="account.php"','href="pages.php?page=omakase"','href="pages.php?page=gallery"','href="pages.php?page=booking"','href="pages.php?page=contacts"','href="pages.php?page=cart"'],
      ['href="booking.html"','href="index.html"','href="menu.html"','href="about.html"','href="reviews.html"','href="account.html"','href="omakase.html"','href="gallery.html"','href="booking.html"','href="contacts.html"','href="cart.html"'],
      $html
    );
    $html = str_replace(['href="offers.php"','href="set-builder.php"','href="perfect-evening.php"','href="gift-certificates.php"'], ['href="offers.html"','href="set-builder.html"','href="perfect-evening.html"','href="gift-certificates.html"'], $html);
    $html = preg_replace('/href="pages\.php\?page=gallery#([^"]*)"/', 'href="gallery.html#$1"', $html) ?? $html;
    $html = preg_replace_callback('/href="set-builder\.php\?variant=([^"&]+)(?:&amp;[^\"]*)?"/', static function (array $match): string {
        $slug = rawurldecode($match[1]);
        return preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/', $slug) ? 'href="set-builder-' . $slug . '.html"' : 'href="set-builder.html"';
    }, $html) ?? $html;
    $html = preg_replace_callback('/href="collection\.php\?slug=([^"&]+)"/', static function (array $match): string {
        $slug = rawurldecode($match[1]);
        return preg_match('/^[a-z0-9][a-z0-9-]{1,50}$/', $slug) ? 'href="collection-' . $slug . '.html"' : 'href="offers.html"';
    }, $html) ?? $html;
    $html = str_replace('href="pages.php?page=booking&amp;zone=', 'href="booking.html?zone=', $html);
    $html = preg_replace('/href="pages\.php\?page=booking&amp;format=([^"]*)"/', 'href="booking.html?format=$1"', $html) ?? $html;
    $html = preg_replace('/href="pages\.php\?page=cart&amp;promo=([^"]*)"/', 'href="cart.html?promo=$1"', $html) ?? $html;
    $html = preg_replace_callback('/href="pages\.php\?page=dish&amp;id=(\d+)"/', fn($m) => 'href="dish-'.$m[1].'.html"', $html) ?? $html;
    $html = preg_replace('/<a href="admin(?:-marketing)?\.php">(?:Управление сайтом|Администрирование|Marketing &amp; Loyalty)<\/a>/', '', $html) ?? $html;
    $html = str_replace(
      ['Мы перезвоним и подготовим стол к выбранному времени.','После заявки команда подтвердит время и пожелания к вечеру.','Отзыв появится на сайте после проверки командой ресторана.','Оставленный отзыв сохраняется в учебной базе и не становится публичной оценкой.','Используйте вымышленные данные. Отзыв сохраняется в базе со статусом проверки и не меняет публичный рейтинг.'],
      ['В этой публичной демоверсии заявка сохраняется только в вашем браузере.','Уведомление ресторану из статической версии не отправляется.','Отзыв останется в этом браузере и не будет опубликован.','Оставленный отзыв сохраняется только в этом браузере и не становится публичной оценкой.','Используйте вымышленные данные. Отзыв остаётся только в этом браузере, не отправляется на проверку и не меняет публичный рейтинг.'],
      $html
    );
    // GitHub Pages cannot enforce the server-side rules used by the PHP checkout.
    $html = str_replace(
      [
        'Соберите японский сет из демонстрационного меню. Итоговую стоимость и состав перед заказом проверяет сервер.',
        'Выберите формат и составьте сет из каталога. Выбор блюда и отображаемая сумма — предварительные; сервер повторно проверит активность и цену.',
        'Выберите формат и добавьте разные блюда. Цены будут перепроверены сервером.',
        'Точная цена и доступность подтвердятся при оформлении.',
        'Предварительная сумма — сумма демонстрационных цен выбранных блюд. Сервер соберёт окончательное предложение сам.',
        'Итоговые скидки и подарки подтверждаются сервером. Цифры карточек меню — оценки до проверки.',
        'Демонстрационный код для проверки корзины и серверного пересчёта. Условия вымышлены и доступны для изменения администратором.',
        'Применимость каждого кода и итоговая стоимость проверяются сервером при расчёте заказа.',
        'Сервер проверит приглашение, роль или уровень привилегий.',
      ],
      [
        'Соберите японский сет из демоменю. В GitHub Pages состав и стоимость остаются локальной оценкой в браузере.',
        'Выберите формат и составьте сет из каталога. В статической демоверсии состав и стоимость — предварительная локальная оценка без серверной проверки.',
        'Выберите формат и добавьте разные блюда. Цены в GitHub Pages — демо-оценка без серверной проверки.',
        'В статической демоверсии доступность и цена не проверяются сервером.',
        'Предварительная сумма — сумма демонстрационных цен выбранных блюд. Статическая демоверсия не проверяет итог на сервере.',
        'Скидки и подарки в GitHub Pages — локальная демонстрация без серверной проверки. Цены меню также предварительные.',
        'Демо-код проверяет только локальную корзину и предварительный расчёт в браузере. Условия вымышлены.',
        'В GitHub Pages коды работают только как локальная демонстрация; серверной проверки скидки и итоговой стоимости нет.',
        'В этой демоверсии код открывает локальный просмотр; серверной авторизации и проверки уровня доступа нет.',
      ],
      $html
    );
    // A static export cannot know the visitor's current restaurant-local day.
    // Leave the date bounds to navigation.js instead of shipping stale export-time dates.
    $html = preg_replace_callback('/<form\b[^>]*\bclass="[^"]*\b(?:home-booking-form|booking-form)\b[^"]*"[^>]*>.*?<\/form>/s', static function (array $match): string {
        return preg_replace('/\s(?:min|max)="\d{4}-\d{2}-\d{2}"/', '', $match[0]) ?? $match[0];
    }, $html) ?? $html;
    $html = preg_replace('/(<script\b[^>]*\bsrc="pages-extra\.js(?:\?[^"]*)?"[^>]*><\/script>)/i', '$1<script src="pages-demo.js" defer></script>', $html, 1) ?? $html;
    if (!str_contains($html, '<script src="pages-demo.js" defer></script>')) throw new RuntimeException('Static pages must load the local demo form handler.');
    $html = str_replace('</body>', '<script>window.TOKYO_STATIC_DEMO=true;</script></body>', $html);
    if ($filename === 'cart.html') {
        $html = preg_replace('/<head\b[^>]*>/i', '$0<meta name="robots" content="noindex,follow">', $html, 1) ?? $html;
    }
    if (file_put_contents($docs . '/' . $filename, $html) === false) throw new RuntimeException('Cannot write ' . $filename);
}
$secretIds=commerce_secret_menu_restricted_dish_ids(db());
$secretDemoItems=[];
if($secretIds){$marks=implode(',',array_fill(0,count($secretIds),'?'));$q=db()->prepare("SELECT d.*,c.name AS category_name FROM dishes d JOIN categories c ON c.slug=d.category WHERE d.active=1 AND d.id IN ($marks) ORDER BY d.sort_order,d.id");$q->execute($secretIds);$secretDemoItems=$q->fetchAll();}
foreach($secretDemoItems as &$secretItem) unset($secretItem['created_at']);
unset($secretItem);
$secretDemoJson=json_encode($secretDemoItems,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
if(file_put_contents($docs.'/tokyo-secret-demo.js','window.TOKYO_SECRET_DEMO_ITEMS = '.$secretDemoJson.";\n")===false)throw new RuntimeException('Cannot write static Secret Menu preview data');

// Browser-only account uses a committed presentation template, not the smaller
// legacy scaffold or authenticated account.php output. This prevents Pages from
// silently losing demo tabs and panels during export.
$accountTemplatePath = $root . '/templates/account-demo.html';
$account = file_get_contents($accountTemplatePath);
if ($account === false) throw new RuntimeException('Could not read the browser-only account template.');
foreach (['data-account-tab="notifications"', 'data-account-panel="notifications"', 'id="demo-notifications"', 'data-account-demo-seed'] as $marker) {
    if (!str_contains($account, $marker)) throw new RuntimeException('The account template is missing required feature: ' . $marker);
}
if (!str_contains($account, 'name="robots"')) {
    $account = preg_replace('/<head\b[^>]*>/i', '$0<meta name="robots" content="noindex,follow">', $account, 1) ?? $account;
}
if (file_put_contents($docs . '/account.html', $account) === false) throw new RuntimeException('Could not write the browser-only account export.');
$expected = array_values($routes);
$expected[] = 'account.html';
foreach (array_unique($expected) as $filename) {
    $output = file_get_contents($docs . '/' . $filename);
    if ($output === false || !str_contains($output, '<title>')) throw new RuntimeException('Invalid exported document: ' . $filename);
    if (preg_match('/(?:href|src)="[^"]+\\.php(?:[?#"]|&amp;)/', $output)) throw new RuntimeException('PHP-only link left in static page: ' . $filename);
    preg_match_all('/<script\b[^>]*\bsrc="([^"]+)"[^>]*><\/script>/i', $output, $scripts);
    if (count($scripts[1]) !== count(array_unique($scripts[1]))) throw new RuntimeException('Duplicate external script in static page: ' . $filename);
    preg_match_all('/<link\b[^>]*>/i', $output, $links);
    foreach ($links[0] as $link) {
        if (!preg_match('/\brel="stylesheet"/i', $link) || !preg_match('/\bhref="([^"]+)"/i', $link, $hrefMatch)) continue;
        $href = html_entity_decode($hrefMatch[1], ENT_QUOTES, 'UTF-8');
        if (preg_match('~^(?:https?:)?//~i', $href)) continue;
        $assetPath = rawurldecode((string)(parse_url($href, PHP_URL_PATH) ?? ''));
        if ($assetPath === '' || !is_file($docs . '/' . $assetPath)) throw new RuntimeException('Missing local stylesheet in ' . $filename . ': ' . $href);
    }
}
$publicBaseOverride = trim((string)(getenv('TOKYO_PUBLIC_BASE_URL') ?: ''));
if ($publicBaseOverride !== '') {
    $publicBase = $publicBaseOverride;
} else {
    $repository = trim((string)(getenv('GITHUB_REPOSITORY') ?: 'altento/tokyo-premium'));
    if (!preg_match('/^([A-Za-z0-9-]+)\/([A-Za-z0-9._-]+)$/', $repository, $repositoryMatch)) {
        throw new RuntimeException('Invalid GITHUB_REPOSITORY for static sitemap.');
    }
    $owner = strtolower($repositoryMatch[1]);
    $repositoryName = strtolower($repositoryMatch[2]);
    $projectPath = strcasecmp($repositoryName, $owner . '.github.io') === 0 ? '' : '/' . $repositoryName;
    $publicBase = 'https://' . $owner . '.github.io' . $projectPath;
}
$publicBase = rtrim($publicBase, '/') . '/';
$publicBaseParts = parse_url($publicBase);
if (!is_array($publicBaseParts) || strtolower((string)($publicBaseParts['scheme'] ?? '')) !== 'https' || empty($publicBaseParts['host']) || isset($publicBaseParts['query']) || isset($publicBaseParts['fragment'])) {
    throw new RuntimeException('TOKYO_PUBLIC_BASE_URL must be a public HTTPS base URL without a query or fragment.');
}
$sitemapFiles = array_values(array_unique(array_filter(array_values($routes), static fn(string $filename): bool => !in_array($filename, ['cart.html', 'account.html'], true))));
$sitemap = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($sitemapFiles as $filename) {
    $location = $publicBase . ($filename === 'index.html' ? '' : rawurlencode($filename));
    $sitemap .= '  <url><loc>' . htmlspecialchars($location, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc></url>\n";
}
$sitemap .= "</urlset>\n";
if (file_put_contents($docs . '/sitemap.xml', $sitemap, LOCK_EX) === false) throw new RuntimeException('Cannot write static sitemap.');
$robots = "User-agent: *\nAllow: /\nSitemap: " . $publicBase . "sitemap.xml\n";
if (file_put_contents($docs . '/robots.txt', $robots, LOCK_EX) === false) throw new RuntimeException('Cannot write robots.txt.');
echo "Exported " . count(array_unique($expected)) . " static pages to $docs\n";
