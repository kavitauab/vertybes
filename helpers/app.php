<?php
/**
 * Shared app helpers: settings, UI texts, audit log.
 * Requires $db (Database) to be constructed by the includer (auth.php does it).
 */

function getSetting($key, $default = null) {
    global $db;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($db->fetchAll("SELECT setting_key, setting_value FROM settings") as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function setSetting($key, $value) {
    global $db;
    $db->query(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$key, $value]
    );
}

function getOpenAiKey() {
    return OPENAI_API_KEY_ENV !== '' ? OPENAI_API_KEY_ENV : (string)getSetting('openai_api_key', '');
}

/**
 * All UI texts as [key => value]. Cached per request.
 */
function getUiTexts() {
    global $db;
    static $texts = null;
    if ($texts === null) {
        $texts = [];
        foreach ($db->fetchAll("SELECT text_key, text_value FROM ui_texts") as $r) {
            $texts[$r['text_key']] = $r['text_value'];
        }
    }
    return $texts;
}

/** Single UI text with fallback to the key itself (missing keys stay visible). */
function t($key, array $replace = []) {
    $texts = getUiTexts();
    $v = $texts[$key] ?? $key;
    foreach ($replace as $k => $r) $v = str_replace('{' . $k . '}', $r, $v);
    return $v;
}

/** HTML-escaped UI text for direct echo into templates. */
function te($key, array $replace = []) {
    return htmlspecialchars(t($key, $replace), ENT_QUOTES, 'UTF-8');
}

function auditLog($action, $entity = '', $entityId = '', $details = null) {
    global $db;
    $userId = $_SESSION['user_id'] ?? null;
    try {
        $db->insert('audit_log', [
            'user_id'   => $userId,
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => (string)$entityId,
            'details'   => $details === null ? null :
                           (is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE)),
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) { /* audit failures must never break the request */ }
}

/**
 * Cheap DB-backed rate limit for public endpoints: true when the ip_hash
 * created more than $max rows in $table within the last $windowMinutes.
 */
function rateLimited($table, $ipHash, $max, $windowMinutes = 60) {
    global $db;
    if (!$ipHash) return false;
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS c FROM `$table`
         WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [$ipHash, $windowMinutes]
    );
    return (int)($row['c'] ?? 0) >= $max;
}
