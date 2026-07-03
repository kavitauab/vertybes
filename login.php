<?php
require_once __DIR__ . '/auth.php';

if (isAuthenticated()) {
    header('Location: /dashboard');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Small brute-force brake: sleep on failures
    if ($email && $password && authenticate($email, $password)) {
        $to = $_SESSION['redirect_after_login'] ?? '/dashboard';
        unset($_SESSION['redirect_after_login']);
        // Only allow same-site relative redirects
        if (!is_string($to) || $to === '' || str_starts_with($to, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $to)) {
            $to = '/dashboard';
        }
        header('Location: ' . $to);
        exit;
    }
    usleep(500000);
    $error = 'Neteisingas el. paštas arba slaptažodis.';
}

echo getLoginForm($error);
