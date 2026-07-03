<?php
/**
 * Test-flow persistence helpers — everything between "user consented" and
 * "result computed". Used only by api.php public actions.
 */

require_once __DIR__ . '/ranking.php';

function tfUuid() {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function tfActiveQuestions($db) {
    return $db->fetchAll(
        "SELECT question_key, text, hint, sort_order, max_answers
         FROM questions WHERE is_active = 1 ORDER BY sort_order, id");
}

function tfActiveCatalog($db) {
    return $db->fetchAll(
        "SELECT value_key, label_lt, meaning_lt, synonyms_lt
         FROM values_catalog WHERE is_active = 1 ORDER BY label_lt");
}

/** Answers joined with their question text and current value mapping. */
function tfSessionAnswers($db, $sessionId) {
    return $db->fetchAll(
        "SELECT a.id, a.question_key, a.answer_index, a.answer_text,
                q.text AS question_text,
                av.suggested_value_key, av.confidence, av.confirmed_value_key, av.source
         FROM session_answers a
         JOIN questions q ON q.question_key = a.question_key
         LEFT JOIN answer_values av ON av.answer_id = a.id
         WHERE a.session_id = ?
         ORDER BY q.sort_order, a.answer_index", [$sessionId]);
}

/** Changing answers invalidates everything computed from them. */
function tfResetDownstream($db, $sessionId) {
    $db->delete('answer_values', 'session_id = ?', [$sessionId]);
    $db->delete('comparisons', 'session_id = ?', [$sessionId]);
    $db->delete('session_results', 'session_id = ?', [$sessionId]);
    $db->update('test_sessions',
        ['top5_json' => null, 'completed_at' => null], 'id = ?', [$sessionId]);
}

/**
 * Aggregate confirmed values → [['key','freq','conf','first'], ...].
 * User-picked values count with confidence 1.0 (an explicit choice outranks
 * any AI guess in cutoff ties).
 */
function tfConfirmedAggregates($db, $sessionId) {
    $rows = $db->fetchAll(
        "SELECT av.confirmed_value_key AS k, av.confidence, av.source, av.answer_id
         FROM answer_values av
         WHERE av.session_id = ? AND av.confirmed_value_key IS NOT NULL
         ORDER BY av.answer_id", [$sessionId]);
    $agg = [];
    $pos = 0;
    foreach ($rows as $r) {
        $k = $r['k'];
        $conf = $r['source'] === 'user' ? 1.0 : (float)$r['confidence'];
        if (!isset($agg[$k])) {
            $agg[$k] = ['key' => $k, 'freq' => 0, 'conf' => 0.0, 'first' => $pos];
        }
        $agg[$k]['freq']++;
        $agg[$k]['conf'] = max($agg[$k]['conf'], $conf);
        $pos++;
    }
    return array_values($agg);
}

function tfCreateComparisons($db, $sessionId, array $top5) {
    $db->delete('comparisons', 'session_id = ?', [$sessionId]);
    $rows = [];
    $i = 1;
    foreach (rankingMakePairs($top5) as $p) {
        $rows[] = [
            'session_id' => $sessionId,
            'pair_index' => $i++,
            'left_value_key' => $p['left'],
            'right_value_key' => $p['right'],
            'is_tiebreak' => 0,
        ];
    }
    $db->batchInsert('comparisons', $rows);
}

function tfComparisons($db, $sessionId) {
    return $db->fetchAll(
        "SELECT pair_index, left_value_key, right_value_key, winner_value_key, is_tiebreak
         FROM comparisons WHERE session_id = ? ORDER BY pair_index", [$sessionId]);
}

/**
 * After every saved comparison: compute scores, resolve, create the tie-break
 * duel if needed, finalize when decided.
 * @return array ['state' => 'comparing'|'tiebreak'|'final',
 *                'next_pair_index' => ?, 'tiebreak' => ?, 'top' => ?, 'scores' => ?]
 */
function tfResolveState($db, array $session) {
    $sessionId = $session['id'];
    $top5 = json_decode($session['top5_json'] ?? '[]', true) ?: [];
    $comps = tfComparisons($db, $sessionId);

    $base = array_filter($comps, fn($c) => !$c['is_tiebreak']);
    $unanswered = array_filter($base, fn($c) => empty($c['winner_value_key']));
    if ($unanswered) {
        $next = min(array_column($unanswered, 'pair_index'));
        return ['state' => 'comparing', 'next_pair_index' => $next];
    }

    $scores = rankingScores($comps, $top5);

    $tbRow = null;
    foreach ($comps as $c) {
        if ($c['is_tiebreak']) $tbRow = $c;
    }
    $tiebreak = ($tbRow && $tbRow['winner_value_key'])
        ? ['left' => $tbRow['left_value_key'], 'right' => $tbRow['right_value_key'],
           'winner' => $tbRow['winner_value_key']]
        : null;

    $res = rankingResolve($scores, $tiebreak);

    if ($res['status'] === 'tiebreak') {
        if (!$tbRow) {
            $db->insert('comparisons', [
                'session_id' => $sessionId,
                'pair_index' => 99,
                'left_value_key' => $res['pair'][0],
                'right_value_key' => $res['pair'][1],
                'is_tiebreak' => 1,
            ]);
        }
        return ['state' => 'tiebreak',
                'tiebreak' => ['left' => $res['pair'][0], 'right' => $res['pair'][1]],
                'scores' => $scores];
    }

    // Final — persist once
    $existing = $db->fetchOne(
        "SELECT id FROM session_results WHERE session_id = ?", [$sessionId]);
    if (!$existing) {
        $db->insert('session_results', [
            'session_id' => $sessionId,
            'scores_json' => json_encode($scores, JSON_UNESCAPED_UNICODE),
            'top_keys_json' => json_encode($res['top'], JSON_UNESCAPED_UNICODE),
        ]);
        $db->update('test_sessions',
            ['status' => 'result_ready', 'completed_at' => date('Y-m-d H:i:s')],
            'id = ?', [$sessionId]);
    }
    return ['state' => 'final', 'top' => $res['top'], 'scores' => $scores];
}

/** Value details for result/review screens. */
function tfValueDetails($db, array $keys) {
    if (!$keys) return [];
    $ph = implode(',', array_fill(0, count($keys), '?'));
    $rows = $db->fetchAll(
        "SELECT value_key, label_lt, meaning_lt, tension_lt
         FROM values_catalog WHERE value_key IN ($ph)", $keys);
    $byKey = array_column($rows, null, 'value_key');
    // preserve requested order
    return array_values(array_filter(array_map(fn($k) => $byKey[$k] ?? null, $keys)));
}
