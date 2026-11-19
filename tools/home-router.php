<?php
declare(strict_types=1);

// PHP development-server router. Only public routes and browser assets are served.
// Never return false for arbitrary files: the repository also contains SQL and data.
$root = realpath(dirname(__DIR__));
$path = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? ''));
$deny = static function (): never {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found.');
};
if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(?:^|/)\.{1,2}(?:/|$)|//~', $path)) $deny();
if ($path === '/') $path = '/index.php';
$file = realpath($root . $path);
if (!$file || !is_file($file) || !str_starts_with(strtolower($file), strtolower($root . DIRECTORY_SEPARATOR))) $deny();
$relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
$routes = ['index.php','menu.php','pages.php','about.php','reviews.php','account.php','login.php','register.php','logout.php','api.php','admin.php','admin-marketing.php','offers.php','collection.php','set-builder.php','perfect-evening.php','gift-certificates.php','payment.php','get_profile_data.php','check_session.php'];
if (in_array($relative, $routes, true)) {
    $_SERVER['SCRIPT_NAME'] = '/' . $relative;
    $_SERVER['PHP_SELF'] = '/' . $relative;
    require $file;
    return true;
}
$rootAsset = !str_contains($relative, '/') && preg_match('/\.(?:css|js|ico|webmanifest)$/i', $relative);
$nestedAsset = str_starts_with($relative, 'assets/') && preg_match('/\.(?:webp|avif|png|jpe?g|svg|gif|ico|woff2?|ttf|mp4|webm|css|js)$/i', $relative);
if (!$rootAsset && !$nestedAsset) $deny();
header('X-Content-Type-Options: nosniff');
return false;
