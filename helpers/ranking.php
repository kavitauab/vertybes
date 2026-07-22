<?php
/**
 * Ranking engine — pure functions, no DB.
 *
 * Rules (settled in the Tomas ↔ Konstantin email thread):
 *  - Top-5 selection: frequency of confirmed values across all answers;
 *    ties broken by AI confidence (desc), then first occurrence.
 *  - Fewer than 5 distinct values → caller sends the user back to questions.
 *  - Comparisons: all 10 unique pairs of the 5 values (5C2), shuffled once.
 *  - Score = win count. Ties that cross the top-2 boundary:
 *      exactly 2 tied for the last slot → one extra tie-breaker duel;
 *      otherwise (3+ tied, or tie across both slots) → result shows ALL tied
 *      values (can be 3-4).
 */

/**
 * Pick the top-N value keys (design: 6 → 15 duels).
 * @param array $items [['key' => string, 'freq' => int, 'conf' => float, 'first' => int], ...]
 *                     one row per DISTINCT value key
 * @return array|null  N keys, or null when fewer than N distinct values exist
 */
function rankingTopN(array $items, $n) {
    if (count($items) < $n) return null;
    usort($items, function ($a, $b) {
        if ($a['freq'] !== $b['freq']) return $b['freq'] <=> $a['freq'];
        if ($a['conf'] != $b['conf']) return $b['conf'] <=> $a['conf'];
        return $a['first'] <=> $b['first'];
    });
    return array_column(array_slice($items, 0, $n), 'key');
}

/**
 * All unique pairs of the 5 keys, shuffled (pair order and left/right).
 * @return array [['left' => k, 'right' => k], ...] — exactly 10 pairs
 */
function rankingMakePairs(array $keys) {
    $pairs = [];
    $n = count($keys);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $pair = [$keys[$i], $keys[$j]];
            if (random_int(0, 1)) $pair = array_reverse($pair);
            $pairs[] = ['left' => $pair[0], 'right' => $pair[1]];
        }
    }
    shuffle($pairs);
    return $pairs;
}

/**
 * Win counts from answered non-tiebreak comparisons.
 * @param array $comparisons rows with left_value_key/right_value_key/winner_value_key/is_tiebreak
 * @return array [key => wins] for every key that appeared (0 included)
 */
function rankingScores(array $comparisons, array $keys) {
    $scores = array_fill_keys($keys, 0);
    foreach ($comparisons as $c) {
        if (!empty($c['is_tiebreak'])) continue;
        if (empty($c['winner_value_key'])) continue;
        if (isset($scores[$c['winner_value_key']])) $scores[$c['winner_value_key']]++;
    }
    return $scores;
}

/**
 * Decide the final top-2 (v3 silent tie rules, PERDAVIMAS.md):
 *   1) total duel wins, 2) original frequency in answers,
 *   3) only if still tied — ONE extra direct comparison (tie-break screen).
 * The result is always exactly two values (value_1 dominant).
 *
 * @param array $scores   [key => wins]
 * @param array $freq     [key => original answer frequency/evidence strength]
 * @param array $order    [key => selection order index] (stable last resort)
 * @param array|null $tiebreak answered tie-break ['left','right','winner'] or null
 * @return array ['status' => 'final', 'top' => [k1, k2]]
 *               ['status' => 'tiebreak', 'pair' => [a, b]]
 */
function rankingResolve(array $scores, array $freq = [], array $order = [], $tiebreak = null) {
    $keys = array_keys($scores);
    usort($keys, function ($a, $b) use ($scores, $freq, $order) {
        if ($scores[$a] !== $scores[$b]) return $scores[$b] <=> $scores[$a];
        $fa = $freq[$a] ?? 0; $fb = $freq[$b] ?? 0;
        if ($fa !== $fb) return $fb <=> $fa;
        return ($order[$a] ?? 0) <=> ($order[$b] ?? 0);
    });

    // A tie-break duel is needed only when rank-2 and rank-3 are inseparable
    // by BOTH silent rules (same wins AND same frequency).
    if (count($keys) > 2) {
        $k2 = $keys[1]; $k3 = $keys[2];
        $hardTie = $scores[$k2] === $scores[$k3] &&
                   ($freq[$k2] ?? 0) === ($freq[$k3] ?? 0);
        if ($hardTie) {
            if ($tiebreak && !empty($tiebreak['winner'])) {
                $pairSet = [$tiebreak['left'], $tiebreak['right']];
                sort($pairSet);
                $tiedSet = [$k2, $k3];
                sort($tiedSet);
                if ($pairSet === $tiedSet && in_array($tiebreak['winner'], $tiedSet, true)) {
                    return ['status' => 'final', 'top' => [$keys[0], $tiebreak['winner']]];
                }
            }
            return ['status' => 'tiebreak', 'pair' => [$k2, $k3]];
        }
    }

    return ['status' => 'final', 'top' => [$keys[0], $keys[1]]];
}
