<?php
/**
 * Public entry point.
 * While waitlist_mode = 1 (admin setting) visitors see the waiting-list
 * landing; once the test ships the same URL serves the test app.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
$db = new Database();
require_once __DIR__ . '/helpers/app.php';

if (getSetting('waitlist_mode', '1') === '1') {
    require __DIR__ . '/includes/waitlist_view.php';
    exit;
}

require __DIR__ . '/includes/test_view.php';
