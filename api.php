<?php
/**
 * Vertybių testas — Main API Endpoint.
 *
 * All requests routed via ?action=XXX. Auth tiers:
 *   1. Admin API key — header X-API-KEY or ?key=  (migrations, debug, CLI ops)
 *   2. Session auth  — humans on the admin portal (CSRF-protected mutations)
 *   3. Public        — anonymous test-taker actions, tied to a session uuid cookie
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/auth.php';   // also creates $db
require_once __DIR__ . '/helpers/app.php';

$logger = getLogger();

// ── Headers ──────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ── Fatal handler ────────────────────────────────────────────────────────────
register_shutdown_function(function () use ($logger) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        $logger->critical('Fatal: ' . $err['message'], ['file' => $err['file'], 'line' => $err['line']], 'error');
        echo json_encode(['success' => false, 'message' => 'Internal server error']);
    }
});

// ── Helpers ──────────────────────────────────────────────────────────────────
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function jsonSuccess($data = [], $message = null) {
    $r = ['success' => true];
    if ($message !== null) $r['message'] = $message;
    jsonResponse(array_merge($r, $data));
}
function jsonError($message, $code = 400) {
    jsonResponse(['success' => false, 'message' => $message], $code);
}
function getJsonInput() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}
function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);
}
function adminKeyProvided() {
    $k = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($k === '') return false;
    // Refuse the shipped placeholder outside development — a deployment that
    // never set ADMIN_API_KEY must not accept the publicly-known default.
    if (ENVIRONMENT !== 'development' && ADMIN_API_KEY === 'vertybes_admin_change_me') return false;
    return hash_equals(ADMIN_API_KEY, $k);
}
function requireAdminKey() {
    if (!adminKeyProvided()) jsonError('Invalid admin key', 401);
}
function requireSession() {
    if (adminKeyProvided()) return;
    if (!isAuthenticated()) jsonError('Authentication required', 401);
}
function requireAdminSession() {
    requireSession();
    if (!adminKeyProvided() && !isAdmin()) jsonError('Administrator privileges required', 403);
}
// Centralized guard for admin state-changing actions. A valid admin API key
// passes with ANY method (CLI ops); everyone else must be an admin session
// issuing a POST with a valid CSRF token.
function requireAdminMutation() {
    if (adminKeyProvided()) return;
    requirePost();
    requireAdminSession();
    requireCsrfToken();
}
// Editor-level mutation: any signed-in portal user (admin or editor).
function requireEditorMutation() {
    if (adminKeyProvided()) return;
    requirePost();
    requireSession();
    requireCsrfToken();
}
function clientIp() { return $_SERVER['REMOTE_ADDR'] ?? null; }

/**
 * Resolve the anonymous test session from the vt_session cookie.
 * Public test-flow actions require it (created by startTest).
 */
function currentTestSession() {
    global $db;
    $uuid = $_COOKIE['vt_session'] ?? '';
    if (!$uuid || !preg_match('/^[a-f0-9-]{36}$/', $uuid)) return null;
    return $db->fetchOne("SELECT * FROM test_sessions WHERE uuid = ? LIMIT 1", [$uuid]);
}
function requireTestSession() {
    $s = currentTestSession();
    if (!$s) jsonError('Test session not found', 404);
    return $s;
}

// ── Router ───────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
if ($action === '') jsonError('Missing action parameter', 400);

try {
    switch ($action) {

        // ════════════════════════════════════════════════════════════════════
        // ADMIN API KEY (no session) — migrations & ops
        // ════════════════════════════════════════════════════════════════════
        case 'runMigrations': {
            requireAdminKey();
            require_once __DIR__ . '/migrations/migrator.php';
            $m = new Migrator();
            jsonSuccess(['output' => $m->migrate()]);
        }
        case 'migrationStatus': {
            requireAdminKey();
            require_once __DIR__ . '/migrations/migrator.php';
            $m = new Migrator();
            jsonSuccess(['output' => $m->status()]);
        }
        case 'rollbackMigration': {
            requireAdminKey();
            require_once __DIR__ . '/migrations/migrator.php';
            $m = new Migrator();
            jsonSuccess(['output' => $m->rollback()]);
        }
        case 'getDatabaseInfo': {
            requireAdminKey();
            $tables = $db->fetchAll(
                "SELECT TABLE_NAME AS table_name, TABLE_ROWS AS row_count
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME",
                [MYSQL_DATABASE]
            );
            jsonSuccess(['tables' => $tables]);
        }
        case 'debugQuery': {
            requireAdminKey();
            $allowed = ['users','settings','ui_texts','questions','values_catalog',
                        'test_sessions','session_consents','session_answers','ai_suggestions',
                        'answer_values','comparisons','session_results','leads','audit_log','migrations'];
            $t = $_GET['table'] ?? '';
            if (!in_array($t, $allowed, true)) jsonError('Invalid table', 400);
            $limit = min(500, max(1, (int)($_GET['limit'] ?? 50)));
            $rows = $db->fetchAll("SELECT * FROM `$t` ORDER BY id DESC LIMIT $limit");
            jsonSuccess(['table' => $t, 'count' => count($rows), 'rows' => $rows]);
        }

        // ════════════════════════════════════════════════════════════════════
        // SESSION (admin portal)
        // ════════════════════════════════════════════════════════════════════
        case 'getCsrfToken': {
            requireSession();
            jsonSuccess(['csrf_token' => generateCsrfToken()]);
        }

        // ── Leads ─────────────────────────────────────────────────────────────
        case 'getLeads': {
            requireSession();
            $source = $_GET['source'] ?? '';
            $where = ''; $params = [];
            if (in_array($source, ['waitlist','result'], true)) {
                $where = 'WHERE l.source = ?';
                $params[] = $source;
            }
            $limit = min(500, max(1, (int)($_GET['limit'] ?? 200)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $rows = $db->fetchAll(
                "SELECT l.id, l.email, l.source, l.top_values, l.created_at, s.uuid AS session_uuid
                 FROM leads l LEFT JOIN test_sessions s ON s.id = l.session_id
                 $where ORDER BY l.id DESC LIMIT $limit OFFSET $offset", $params);
            $total = $db->fetchOne("SELECT COUNT(*) AS c FROM leads l $where", $params);
            jsonSuccess(['leads' => $rows, 'total' => (int)$total['c']]);
        }
        case 'exportLeadsCsv': {
            requireSession();
            $source = $_GET['source'] ?? '';
            $where = ''; $params = [];
            if (in_array($source, ['waitlist','result'], true)) {
                $where = 'WHERE source = ?';
                $params[] = $source;
            }
            $rows = $db->fetchAll(
                "SELECT email, source, top_values, created_at FROM leads $where ORDER BY id", $params);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="leads_' . date('Ymd_His') . '.csv"');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['email', 'source', 'top_values', 'created_at'], ',', '"', '');
            foreach ($rows as $r) fputcsv($out, $r, ',', '"', '');
            fclose($out);
            auditLog('export_leads', 'leads', '', ['count' => count($rows), 'source' => $source ?: 'all']);
            exit;
        }

        // ── UI texts ──────────────────────────────────────────────────────────
        case 'getUiTextsAdmin': {
            requireSession();
            jsonSuccess(['texts' => $db->fetchAll(
                "SELECT id, text_key, text_value, context, updated_at FROM ui_texts ORDER BY text_key")]);
        }
        case 'saveUiText': {
            requireEditorMutation();
            $in = getJsonInput();
            $key = trim($in['text_key'] ?? '');
            $value = (string)($in['text_value'] ?? '');
            if ($key === '' || $value === '') jsonError('text_key and text_value are required');
            if (!preg_match('/^[a-zA-Z0-9._]{2,64}$/', $key)) jsonError('Invalid text_key');
            $db->query(
                "INSERT INTO ui_texts (text_key, text_value, context) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE text_value = VALUES(text_value)",
                [$key, $value, trim($in['context'] ?? '')]
            );
            auditLog('save_ui_text', 'ui_texts', $key);
            jsonSuccess([], 'Išsaugota');
        }

        // ── Questions ─────────────────────────────────────────────────────────
        case 'getQuestionsAdmin': {
            requireSession();
            jsonSuccess(['questions' => $db->fetchAll(
                "SELECT * FROM questions ORDER BY sort_order, id")]);
        }
        case 'saveQuestion': {
            requireEditorMutation();
            $in = getJsonInput();
            $id = (int)($in['id'] ?? 0);
            $data = [
                'text' => trim($in['text'] ?? ''),
                'hint' => trim($in['hint'] ?? ''),
                'sort_order' => (int)($in['sort_order'] ?? 0),
                'max_answers' => min(10, max(1, (int)($in['max_answers'] ?? 6))),
                'is_active' => !empty($in['is_active']) ? 1 : 0,
            ];
            if ($data['text'] === '') jsonError('Klausimo tekstas privalomas');
            if ($id) {
                $db->update('questions', $data, 'id = ?', [$id]);
            } else {
                $key = trim($in['question_key'] ?? '');
                if (!preg_match('/^[a-z0-9_]{1,16}$/', $key)) jsonError('Invalid question_key');
                $data['question_key'] = $key;
                $id = $db->insert('questions', $data);
            }
            auditLog('save_question', 'questions', $id);
            jsonSuccess(['id' => $id], 'Išsaugota');
        }

        // ── Values catalog ────────────────────────────────────────────────────
        case 'getValuesAdmin': {
            requireSession();
            jsonSuccess(['values' => $db->fetchAll(
                "SELECT * FROM values_catalog ORDER BY label_lt")]);
        }
        case 'saveValue': {
            requireEditorMutation();
            $in = getJsonInput();
            $id = (int)($in['id'] ?? 0);
            $data = [
                'label_lt' => trim($in['label_lt'] ?? ''),
                'meaning_lt' => trim($in['meaning_lt'] ?? ''),
                'tension_lt' => trim($in['tension_lt'] ?? ''),
                'synonyms_lt' => trim($in['synonyms_lt'] ?? ''),
                'is_active' => !empty($in['is_active']) ? 1 : 0,
            ];
            if ($data['label_lt'] === '') jsonError('Pavadinimas privalomas');
            if ($id) {
                $db->update('values_catalog', $data, 'id = ?', [$id]);
            } else {
                $key = trim($in['value_key'] ?? '');
                if (!preg_match('/^[a-z0-9_]{2,64}$/', $key)) jsonError('Invalid value_key');
                $data['value_key'] = $key;
                $id = $db->insert('values_catalog', $data);
            }
            auditLog('save_value', 'values_catalog', $id);
            jsonSuccess(['id' => $id], 'Išsaugota');
        }

        // ── Settings ──────────────────────────────────────────────────────────
        case 'getSettingsAdmin': {
            requireAdminSession();
            $rows = $db->fetchAll("SELECT setting_key, setting_value, updated_at FROM settings ORDER BY setting_key");
            // Never ship the stored API key back to the browser — only whether one exists.
            foreach ($rows as &$r) {
                if ($r['setting_key'] === 'openai_api_key') {
                    $r['setting_value'] = $r['setting_value'] !== '' ? '••••' : '';
                }
            }
            jsonSuccess(['settings' => $rows, 'openai_key_from_env' => OPENAI_API_KEY_ENV !== '']);
        }
        case 'saveSettings': {
            requireAdminMutation();
            $in = getJsonInput();
            $allowed = ['site_name','waitlist_mode','booking_url','openai_api_key','openai_model',
                        'ai_mock_mode','ai_system_prompt','ai_prompt_version',
                        'privacy_policy_version','cookie_policy_version',
                        'min_distinct_values','answers_per_question_max'];
            $saved = [];
            foreach ($allowed as $k) {
                if (!array_key_exists($k, $in)) continue;
                $v = (string)$in[$k];
                if ($k === 'openai_api_key' && ($v === '••••' || $v === '')) continue; // masked/blank = unchanged
                setSetting($k, $v);
                $saved[] = $k;
            }
            auditLog('save_settings', 'settings', '', ['keys' => $saved]);
            jsonSuccess(['saved' => $saved], 'Išsaugota');
        }

        // ── Users (admin only) ────────────────────────────────────────────────
        case 'getUsers': {
            requireAdminSession();
            jsonSuccess(['users' => $db->fetchAll(
                "SELECT id, email, name, role, is_active, last_login, created_at FROM users ORDER BY id")]);
        }
        case 'saveUser': {
            requireAdminMutation();
            $in = getJsonInput();
            $id = (int)($in['id'] ?? 0);
            $email = strtolower(trim($in['email'] ?? ''));
            $name = trim($in['name'] ?? '');
            $role = in_array($in['role'] ?? '', ['admin','editor'], true) ? $in['role'] : 'editor';
            $password = (string)($in['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Neteisingas el. paštas');
            if ($name === '') jsonError('Vardas privalomas');
            $data = ['email' => $email, 'name' => $name, 'role' => $role,
                     'is_active' => !empty($in['is_active']) ? 1 : 0];
            if ($password !== '') {
                if (strlen($password) < 10) jsonError('Slaptažodis per trumpas (min. 10 simbolių)');
                $data['password_hash'] = hashPassword($password);
            }
            if ($id) {
                $db->update('users', $data, 'id = ?', [$id]);
            } else {
                if ($password === '') jsonError('Naujam vartotojui reikia slaptažodžio');
                $id = $db->insert('users', $data);
            }
            auditLog('save_user', 'users', $id, ['email' => $email]);
            jsonSuccess(['id' => $id], 'Išsaugota');
        }

        // ── Dashboard stats ───────────────────────────────────────────────────
        case 'getStats': {
            requireSession();
            $stats = [
                'leads_waitlist' => (int)$db->fetchOne("SELECT COUNT(*) c FROM leads WHERE source='waitlist'")['c'],
                'leads_result'   => (int)$db->fetchOne("SELECT COUNT(*) c FROM leads WHERE source='result'")['c'],
                'sessions_total' => (int)$db->fetchOne("SELECT COUNT(*) c FROM test_sessions")['c'],
                'sessions_completed' => (int)$db->fetchOne("SELECT COUNT(*) c FROM test_sessions WHERE status IN ('result_ready','email_captured')")['c'],
                'leads_7d' => $db->fetchAll(
                    "SELECT DATE(created_at) d, COUNT(*) c FROM leads
                     WHERE created_at > DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     GROUP BY DATE(created_at) ORDER BY d"),
            ];
            jsonSuccess(['stats' => $stats]);
        }

        // ── Test sessions browser ─────────────────────────────────────────────
        case 'getSessions': {
            requireSession();
            $limit = min(200, max(1, (int)($_GET['limit'] ?? 100)));
            $rows = $db->fetchAll(
                "SELECT s.id, s.uuid, s.status, s.started_at, s.completed_at,
                        r.top_keys_json,
                        (SELECT COUNT(*) FROM session_answers a WHERE a.session_id = s.id) AS answers
                 FROM test_sessions s
                 LEFT JOIN session_results r ON r.session_id = s.id
                 ORDER BY s.id DESC LIMIT $limit");
            jsonSuccess(['sessions' => $rows]);
        }

        // ════════════════════════════════════════════════════════════════════
        // PUBLIC — waiting list
        // ════════════════════════════════════════════════════════════════════
        case 'joinWaitlist': {
            requirePost();
            $in = getJsonInput();
            // Honeypot: bots fill every field; humans never see this one.
            if (!empty($in['website'])) jsonSuccess([], t('waitlist.success'));
            $email = strtolower(trim($in['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                jsonError(t('common.errorEmail'));
            }
            $ipHash = hashIp(clientIp());
            if (rateLimited('leads', $ipHash, 10, 60)) jsonError(t('common.errorGeneric'), 429);
            $existing = $db->fetchOne(
                "SELECT id FROM leads WHERE email = ? AND source = 'waitlist'", [$email]);
            if ($existing) jsonSuccess(['duplicate' => true], t('waitlist.duplicate'));
            $db->insert('leads', [
                'email' => $email,
                'source' => 'waitlist',
                'ip_hash' => $ipHash,
            ]);
            jsonSuccess([], t('waitlist.success'));
        }

        default:
            jsonError('Unknown action: ' . $action, 404);
    }
} catch (Throwable $e) {
    $logger->error('API error', ['action' => $action, 'error' => $e->getMessage(),
                                 'file' => $e->getFile(), 'line' => $e->getLine()], 'error');
    if (ENVIRONMENT === 'development') {
        jsonError('Server error: ' . $e->getMessage(), 500);
    }
    jsonError('Server error', 500);
}
