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
 * Pick the top-5 value keys.
 * @param array $items [['key' => string, 'freq' => int, 'conf' => float, 'first' => int], ...]
 *                     one row per DISTINCT value key
 * @return array|null  5 keys, or null when fewer than 5 distinct values exist
 */
function rankingTop5(array $items) {
    if (count($items) < 5) return null;
    usort($items, function ($a, $b) {
        if ($a['freq'] !== $b['freq']) return $b['freq'] <=> $a['freq'];
        if ($a['conf'] != $b['conf']) return $b['conf'] <=> $a['conf'];
        return $a['first'] <=> $b['first'];
    });
    return array_column(array_slice($items, 0, 5), 'key');
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
 * Decide the final result or request a tie-breaker.
 *
 * @param array $scores        [key => wins] over the 10 base duels
 * @param array|null $tiebreak answered tie-break row ['left','right','winner'] or null
 * @return array ['status' => 'final', 'top' => [keys]]        — result ready (2+ keys)
 *               ['status' => 'tiebreak', 'pair' => [a, b]]    — needs one more duel
 */
function rankingResolve(array $scores, $tiebreak = null) {
    arsort($scores);
    $keys = array_keys($scores);
    $vals = array_values($scores);

    $s2 = $vals[1];                       // score of rank-2
    $definite = [];                       // strictly above the boundary score
    $tied = [];                           // exactly at the boundary score
    foreach ($scores as $k => $s) {
        if ($s > $s2) $definite[] = $k;
        elseif ($s === $s2) $tied[] = $k;
    }
    $slots = 2 - count($definite);

    if (count($tied) === $slots) {
        return ['status' => 'final', 'top' => array_merge($definite, $tied)];
    }

    if (count($tied) === 2 && $slots === 1) {
        // Exactly two compete for the last slot → tie-breaker duel decides.
        if ($tiebreak && !empty($tiebreak['winner'])) {
            $pairSet = [$tiebreak['left'], $tiebreak['right']];
            sort($pairSet);
            $tiedSet = $tied;
            sort($tiedSet);
            if ($pairSet === $tiedSet && in_array($tiebreak['winner'], $tied, true)) {
                return ['status' => 'final', 'top' => array_merge($definite, [$tiebreak['winner']])];
            }
        }
        return ['status' => 'tiebreak', 'pair' => $tied];
    }

    // 3+ tied at the boundary (or tie across both slots) → show them all.
    return ['status' => 'final', 'top' => array_merge($definite, $tied)];
}
