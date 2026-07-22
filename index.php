<?php
/**
 * Public entry point.
 * Captures partner attribution (?source=coach&referral_code=tomas123) into
 * 90-day first-party cookies, FIRST TOUCH wins (PERDAVIMAS.md). While
 * waitlist_mode = 1 visitors see the waiting-list landing; otherwise the test.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
$db = new Database();
require_once __DIR__ . '/helpers/app.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
foreach (['source' => 'vt_src', 'referral_code' => 'vt_ref'] as $param => $cookie) {
    $val = trim((string)($_GET[$param] ?? ''));
    if ($val !== '' && empty($_COOKIE[$cookie])) {   // first touch wins
        setcookie($cookie, substr($val, 0, 60), [
            'expires' => time() + 60 * 60 * 24 * 90,
            'path' => '/',
            'secure' => $secure,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$cookie] = substr($val, 0, 60);
    }
}

if (getSetting('waitlist_mode', '1') === '1') {
    require __DIR__ . '/includes/waitlist_view.php';
    exit;
}

require __DIR__ . '/includes/test_view.php';
