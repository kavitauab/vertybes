<?php
/**
 * MailerLite integration (PERDAVIMAS.md spec).
 * The result email is sent by a MailerLite AUTOMATION on group subscribe —
 * the back-end never sends mail itself. DB-first: the lead row is saved
 * before this is called; failures leave ml_pending=1 for the cron retry.
 *
 * Token: env MAILERLITE_TOKEN wins, else setting mailerlite_token.
 * Group ids: settings ml_group_test / ml_group_marketing.
 */

require_once __DIR__ . '/app.php';

function mlToken() {
    $t = getenv('MAILERLITE_TOKEN');
    return $t !== false && $t !== '' ? $t : (string)getSetting('mailerlite_token', '');
}

function mlRequest($method, $path, $payload = null) {
    $token = mlToken();
    if ($token === '') return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'no_token'];
    $ch = curl_init('https://connect.mailerlite.com/api' . $path);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ];
    if ($payload !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [
        'ok' => $body !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'data' => $body !== false ? json_decode($body, true) : null,
        'error' => $err ?: null,
    ];
}

/**
 * Upsert the subscriber with custom fields; marketing group only on opt-in.
 * @return array ['ok' => bool, 'subscriber_id' => ?string, 'invalid_email' => bool]
 */
function mlSubscribeLead($email, $value1, $value2, $leadSource, $referralCode, $consentVersion, $marketingOptIn, $meaning = '', $tension = '') {
    $groups = array_values(array_filter([
        (string)getSetting('ml_group_test', ''),
        $marketingOptIn ? (string)getSetting('ml_group_marketing', '') : '',
    ]));
    if (mlToken() === '' || !$groups) {
        return ['ok' => false, 'subscriber_id' => null, 'invalid_email' => false];
    }
    $r = mlRequest('POST', '/subscribers', [
        'email' => $email,
        'fields' => [
            'value_1' => (string)$value1,
            'value_2' => (string)$value2,
            'lead_source' => (string)$leadSource,   // "source" is reserved in MailerLite
            'referral_code' => (string)$referralCode,
            'consent_version' => (string)$consentVersion,
            // ML text fields cap at 255 chars — trim, the email shows these
            'meaning_text' => mb_substr((string)$meaning, 0, 255),
            'tension_text' => mb_substr((string)$tension, 0, 255),
        ],
        'groups' => $groups,
    ]);
    return [
        'ok' => $r['ok'],
        'subscriber_id' => $r['ok'] ? ($r['data']['data']['id'] ?? null) : null,
        'invalid_email' => $r['status'] === 422,
    ];
}

/** Cron: retry leads stuck with ml_pending=1. Returns [retried, fixed]. */
function mlRetryPending($db, $limit = 25) {
    $rows = $db->fetchAll(
        "SELECT * FROM leads WHERE ml_pending = 1 AND source = 'result' ORDER BY id LIMIT " . (int)$limit);
    $fixed = 0;
    foreach ($rows as $lead) {
        $texts = $lead['session_id']
            ? $db->fetchOne("SELECT meaning_text, tension_text FROM session_results WHERE session_id = ?",
                [$lead['session_id']])
            : null;
        $r = mlSubscribeLead($lead['email'], $lead['value_1'], $lead['value_2'],
            $lead['lead_source'], $lead['referral_code'], $lead['consent_version'],
            (bool)$lead['marketing_opt_in'],
            $texts['meaning_text'] ?? '', $texts['tension_text'] ?? '');
        if ($r['ok']) {
            $db->update('leads', [
                'ml_pending' => 0,
                'mailerlite_subscriber_id' => $r['subscriber_id'],
            ], 'id = ?', [$lead['id']]);
            $fixed++;
        } elseif ($r['invalid_email']) {
            $db->update('leads', ['ml_pending' => 2], 'id = ?', [$lead['id']]); // permanent
        }
    }
    return [count($rows), $fixed];
}

/**
 * Cron: anonymize leads unsubscribed in MailerLite >30 days ago.
 * Deletes the lead row (breaks the person link); answers stay pseudonymous
 * (policy §6). Returns the number of anonymized leads.
 */
function mlCleanupUnsubscribed($db) {
    $r = mlRequest('GET', '/subscribers?filter[status]=unsubscribed&limit=200');
    if (!$r['ok'] || empty($r['data']['data'])) return 0;
    $n = 0;
    foreach ($r['data']['data'] as $sub) {
        $when = $sub['unsubscribed_at'] ?? null;
        if (!$when || strtotime($when) > strtotime('-30 days')) continue;
        $lead = $db->fetchOne("SELECT id FROM leads WHERE email = ?", [strtolower($sub['email'] ?? '')]);
        if ($lead) {
            $db->delete('leads', 'id = ?', [$lead['id']]);
            $n++;
        }
    }
    return $n;
}
