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
    $rows = $db->fetchAll(
        "SELECT question_key, text, hint, topic_label, placeholders_json, sort_order, max_answers
         FROM questions WHERE is_active = 1 ORDER BY sort_order, id");
    foreach ($rows as &$r) {
        $r['placeholders'] = json_decode($r['placeholders_json'] ?? '[]', true) ?: [];
        unset($r['placeholders_json']);
    }
    return $rows;
}

function tfActiveCatalog($db) {
    return $db->fetchAll(
        "SELECT value_key, label_lt, meaning_lt, synonyms_lt, is_core, is_custom
         FROM values_catalog WHERE is_active = 1 ORDER BY is_core DESC, label_lt");
}

/** Active dictionary [value_key => label_lt] (the FINAL 32-value list). */
function tfDict($db) {
    $rows = $db->fetchAll(
        "SELECT value_key, label_lt FROM values_catalog WHERE is_active = 1 ORDER BY sort_order, id");
    return array_column($rows, 'label_lt', 'value_key');
}

/**
 * Persist an analysis run: replace candidates, mark all as selected (the AI
 * already applied the 3–5 selection rules), create every unique duel pair,
 * advance the session to 'comparing'.
 */
function tfStoreAnalysis($db, $sessionId, array $values) {
    $db->beginTransaction();
    try {
        $db->delete('session_value_candidates', 'session_id = ?', [$sessionId]);
        $db->delete('comparisons', 'session_id = ?', [$sessionId]);
        $db->delete('session_results', 'session_id = ?', [$sessionId]);
        $rows = [];
        foreach ($values as $i => $v) {
            $rows[] = [
                'session_id' => $sessionId,
                'value_key' => $v['value_key'],
                'label_lt' => $v['label'],
                'confidence' => $v['confidence'],
                'mentions_json' => json_encode($v['mentions'], JSON_UNESCAPED_UNICODE),
                'evidence_json' => json_encode($v['evidence'], JSON_UNESCAPED_UNICODE),
                'sort_index' => $i,
                'selected' => 1,
            ];
        }
        $db->batchInsert('session_value_candidates', $rows);
        $keys = array_column($values, 'value_key');
        $db->update('test_sessions', [
            'top5_json' => json_encode($keys, JSON_UNESCAPED_UNICODE),
            'status' => 'comparing',
            'completed_at' => null,
        ], 'id = ?', [$sessionId]);
        tfCreateComparisons($db, $sessionId, $keys);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** Selected candidates with decoded mentions/evidence, in selection order. */
function tfCandidates($db, $sessionId) {
    $rows = $db->fetchAll(
        "SELECT value_key, label_lt, confidence, mentions_json, evidence_json, sort_index
         FROM session_value_candidates WHERE session_id = ? AND selected = 1
         ORDER BY sort_index", [$sessionId]);
    foreach ($rows as &$r) {
        $r['mentions'] = json_decode($r['mentions_json'] ?? '[]', true) ?: [];
        $r['evidence'] = json_decode($r['evidence_json'] ?? '[]', true) ?: [];
        unset($r['mentions_json'], $r['evidence_json']);
    }
    return $rows;
}

/** Cached pair interpretation — the same pair always gets the same text. */
function tfPairText($db, array $topDetails) {
    require_once __DIR__ . '/openai.php';
    $keys = array_map(fn($v) => $v['value_key'], array_slice($topDetails, 0, 2));
    sort($keys);
    $pairKey = implode('|', $keys);
    $cached = $db->fetchOne("SELECT tension_text, meaning_text FROM pair_texts WHERE pair_key = ?", [$pairKey]);
    if ($cached) return ['tension' => $cached['tension_text'], 'meaning' => $cached['meaning_text']];
    $pair = aiGeneratePairText($topDetails);
    $db->query(
        "INSERT INTO pair_texts (pair_key, tension_text, meaning_text) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE pair_key = pair_key",
        [$pairKey, $pair['tension'], $pair['meaning']]);
    return $pair;
}

/**
 * Create (or find) a user-entered custom value. Flagged is_custom so the
 * admin can review them; active immediately so ranking works.
 */
function tfAddCustomValue($db, $label) {
    $label = trim(mb_substr($label, 0, 60));
    if (mb_strlen($label) < 2) return null;
    $existing = $db->fetchOne(
        "SELECT value_key, label_lt, meaning_lt, is_core, is_custom
         FROM values_catalog WHERE label_lt = ? AND is_active = 1", [$label]);
    if ($existing) return $existing;

    $map = ['ą'=>'a','č'=>'c','ę'=>'e','ė'=>'e','į'=>'i','š'=>'s','ų'=>'u','ū'=>'u','ž'=>'z',
            'Ą'=>'a','Č'=>'c','Ę'=>'e','Ė'=>'e','Į'=>'i','Š'=>'s','Ų'=>'u','Ū'=>'u','Ž'=>'z'];
    $slug = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(strtr($label, $map)));
    $slug = trim(preg_replace('/_+/', '_', $slug), '_');
    if ($slug === '') $slug = 'vertybe';
    $key = mb_substr($slug, 0, 56);
    $i = 1;
    while ($db->fetchOne("SELECT 1 FROM values_catalog WHERE value_key = ?", [$key])) {
        $key = mb_substr($slug, 0, 52) . '_' . (++$i);
    }
    $db->insert('values_catalog', [
        'value_key' => $key,
        'label_lt' => $label,
        'meaning_lt' => '',
        'is_active' => 1,
        'is_core' => 0,
        'is_custom' => 1,
        'sort_order' => 9999,
    ]);
    return ['value_key' => $key, 'label_lt' => $label, 'meaning_lt' => '',
            'is_core' => 0, 'is_custom' => 1];
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

    // Silent tie inputs: original evidence frequency + selection order
    $freq = [];
    $order = [];
    foreach (tfCandidates($db, $sessionId) as $c) {
        $freq[$c['value_key']] = count($c['evidence']);
        $order[$c['value_key']] = (int)$c['sort_index'];
    }

    $tbRow = null;
    foreach ($comps as $c) {
        if ($c['is_tiebreak']) $tbRow = $c;
    }
    $tiebreak = ($tbRow && $tbRow['winner_value_key'])
        ? ['left' => $tbRow['left_value_key'], 'right' => $tbRow['right_value_key'],
           'winner' => $tbRow['winner_value_key']]
        : null;

    $res = rankingResolve($scores, $freq, $order, $tiebreak);

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
        "SELECT value_key, label_lt, meaning_lt, tension_lt, is_custom
         FROM values_catalog WHERE value_key IN ($ph)", $keys);
    $byKey = array_column($rows, null, 'value_key');
    // preserve requested order
    return array_values(array_filter(array_map(fn($k) => $byKey[$k] ?? null, $keys)));
}
