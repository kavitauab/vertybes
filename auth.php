<?php
/**
 * Authentication & session management.
 *
 * Session auth only — humans (Vytautas / Tomas) logging in via login.php
 * (cookie sessions, CSRF protected). CLI/ops use the admin API key in api.php.
 */

if (php_sapi_name() !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    if (session_status() === PHP_SESSION_NONE) session_start();
}

define('SESSION_TIMEOUT', 3600);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
$db = new Database();

function isAuthenticated() {
    // Per-request memo: handlers call this several times — verify against the
    // users table only once. Only positive results are cached so a login in
    // the same request (login.php) is still picked up.
    static $verified = false;
    if ($verified) return true;

    if (empty($_SESSION['user_id'])) return false;

    if (!empty($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        logout();
        return false;
    }

    global $db;
    $user = $db->fetchOne(
        "SELECT id, is_active FROM users WHERE id = ? AND is_active = 1",
        [$_SESSION['user_id']]
    );
    if (!$user) { logout(); return false; }

    $_SESSION['last_activity'] = time();
    $verified = true;
    return true;
}

function authenticate($email, $password) {
    global $db;
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE email = ? AND is_active = 1",
        [$email]
    );
    if (!$user) return false;

    if (!password_verify($password, $user['password_hash'])) return false;

    $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

    if (php_sapi_name() !== 'cli') session_regenerate_id(true);

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_email']   = $user['email'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['user_role']    = $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time']   = time();
    $_SESSION['ip']           = $_SERVER['REMOTE_ADDR'] ?? null;

    return true;
}

function logout() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}

function getCurrentUser() {
    if (!isAuthenticated()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'name'  => $_SESSION['user_name'],
        'role'  => $_SESSION['user_role'],
    ];
}

function isAdmin() {
    $u = getCurrentUser();
    return $u && $u['role'] === 'admin';
}

function hashPassword($password) { return password_hash($password, PASSWORD_DEFAULT); }

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    if (!empty($_SESSION['csrf_token_time']) &&
        (time() - $_SESSION['csrf_token_time'] > 3600)) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfToken() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token']);
        exit;
    }
}

function requireAuth() {
    if (!isAuthenticated()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header('Location: /login');
            exit;
        }
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header('Location: /dashboard');
            exit;
        }
    }
}

function getLoginForm($error = '') {
    $errorHtml = $error ? '<div class="login-error">' . htmlspecialchars($error) . '</div>' : '';

    return '<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Vertybių testas — Administravimas</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#F8F3EA;--p:#496E50;--pd:#3a5940;--t:#2b2b26;--m:#6f6a5e;--b:#e4dccb;--s:#fff;--e:#B42318;--eb:#fdf1f0}
html{height:100%}
body{min-height:100%;display:flex;align-items:center;justify-content:center;font-family:"DM Sans",-apple-system,system-ui,sans-serif;background:var(--bg);color:var(--t);line-height:1.5;-webkit-font-smoothing:antialiased;padding:1.5rem}
.card{width:100%;max-width:400px;background:var(--s);border:1px solid var(--b);border-radius:14px;padding:2.25rem}
.lh{margin-bottom:1.75rem;text-align:center}
.lh .logo{font-size:1.35rem;font-weight:700;color:var(--p);letter-spacing:-.02em;margin-bottom:.35rem}
.lh p{color:var(--m);font-size:.9rem}
.login-error{background:var(--eb);border-left:3px solid var(--e);color:var(--e);padding:.8rem 1rem;margin-bottom:1.25rem;font-size:.875rem;font-weight:500;border-radius:0 6px 6px 0}
.fg{margin-bottom:1.1rem}
.fl{display:block;font-size:.85rem;font-weight:600;margin-bottom:.4rem}
.fi{width:100%;height:46px;padding:0 1rem;font-size:.95rem;font-family:inherit;color:var(--t);background:var(--s);border:1.5px solid var(--b);border-radius:8px;transition:border-color .15s,box-shadow .15s}
.fi:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(73,110,80,.12)}
.btn{width:100%;height:46px;font-size:.95rem;font-weight:600;font-family:inherit;color:#fff;background:var(--p);border:none;border-radius:8px;cursor:pointer;transition:background-color .15s}
.btn:hover{background:var(--pd)}
.ft{margin-top:1.5rem;text-align:center;color:var(--m);font-size:.78rem}
</style>
</head>
<body>
  <div class="card">
    <div class="lh"><div class="logo">Vertybių testas</div><p>Administravimo prisijungimas</p></div>' . $errorHtml . '
    <form method="POST" action="">
      <div class="fg"><label class="fl" for="email">El. paštas</label>
        <input type="email" id="email" class="fi" name="email" required autofocus autocomplete="email"></div>
      <div class="fg"><label class="fl" for="password">Slaptažodis</label>
        <input type="password" id="password" class="fi" name="password" required autocomplete="current-password"></div>
      <button type="submit" class="btn">Prisijungti</button>
    </form>
    <div class="ft">Sesija baigiasi po 1 val. neaktyvumo</div>
  </div>
</body></html>';
}
