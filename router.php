<?php
/**
 * Dev router for PHP's built-in server — mirrors the production rewrites:
 *   php -S 127.0.0.1:8080 router.php
 * Clean URLs (/privatumas, /dashboard, …) map to their .php files.
 * Production uses nginx rules (see README) or .htaccess instead.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Block the same paths nginx denies in production
if (preg_match('#^/(includes|helpers|migrations|scripts|seeds|logs)/#', $path) ||
    preg_match('#^/(config|database|auth|logger)\.php$#', $path) ||
    $path === '/.env' || $path === '/CLAUDE.md' || $path === '/router.php') {
    http_response_code(404);
    exit('Not found');
}

// /page.php → /page (except api.php)
if (preg_match('#^/([a-z0-9_-]+)\.php$#', $path, $m) && $m[1] !== 'api') {
    $target = $m[1] === 'index' ? '/' : '/' . $m[1];
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $target . ($qs !== '' ? "?$qs" : ''), true, 301);
    exit;
}

// /page → page.php
if ($path !== '/' && strpos($path, '.') === false) {
    $file = __DIR__ . $path . '.php';
    if (is_file($file)) {
        require $file;
        return true;
    }
}

return false; // static files & everything else: default handling
