<?php
/**
 * Vertybių testas — Configuration
 *
 * All settings can be overridden via environment variables (hosting panel
 * env directives or the project .env file).
 */

// ── Optional .env loader (no Composer dependency) ───────────────────────────
if (file_exists(__DIR__ . '/.env')) {
    $envLines = @file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($envLines)) {
        foreach ($envLines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') ||
                                    ($v[0] === "'" && substr($v, -1) === "'"))) {
                $v = substr($v, 1, -1);
            }
            if (getenv($k) === false) {
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
    }
}

// ── Constants ───────────────────────────────────────────────────────────────
define("SYSTEM_NAME", "Vertybių testas");
define("VERSION", "0.1.0");

define("ENVIRONMENT", getenv('APP_ENV') ?: 'production');

// MySQL Configuration
define("MYSQL_HOST", getenv('MYSQL_HOST') ?: 'localhost');
define("MYSQL_PORT", getenv('MYSQL_PORT') ?: '3306');
define("MYSQL_DATABASE", getenv('MYSQL_DATABASE') ?: 'vertybes');
define("MYSQL_USERNAME", getenv('MYSQL_USERNAME') ?: 'vertybes');
define("MYSQL_PASSWORD", getenv('MYSQL_PASSWORD') ?: '');

// Admin API key for CLI/remote operations (migrations, debug)
define("ADMIN_API_KEY", getenv('ADMIN_API_KEY') ?: 'vertybes_admin_change_me');

// Public URL (no trailing slash)
define("APP_URL", rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'));

// OpenAI — key from env wins over the admin-panel setting
define("OPENAI_API_KEY_ENV", getenv('OPENAI_API_KEY') ?: '');

// Salt for hashing visitor IPs before storage (GDPR data minimization)
define("IP_HASH_SALT", getenv('IP_HASH_SALT') ?: 'vertybes_ip_salt_change_me');

// ── Error reporting ─────────────────────────────────────────────────────────
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Vilnius');

// ── Helpers ─────────────────────────────────────────────────────────────────

function assetVersion($relativePath) {
    static $versions = [];
    if (isset($versions[$relativePath])) return $versions[$relativePath];

    $fullPath = __DIR__ . '/' . ltrim($relativePath, '/');
    $versions[$relativePath] = is_file($fullPath) ? (string)filemtime($fullPath) : VERSION;
    return $versions[$relativePath];
}

function generateRandomToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function hashIp($ip) {
    if (!$ip) return null;
    return hash('sha256', IP_HASH_SALT . $ip);
}
