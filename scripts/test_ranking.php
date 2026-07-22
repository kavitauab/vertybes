<?php
/**
 * Ranking engine unit tests. Run: php scripts/test_ranking.php
 */
require __DIR__ . '/../helpers/ranking.php';

$failures = 0;
function check($name, $cond) {
    global $failures;
    if ($cond) { echo "  OK  $name\n"; }
    else { echo "FAIL  $name\n"; $failures++; }
}

// ── rankingTop5 ──────────────────────────────────────────────────────────────
$items = [
    ['key' => 'a', 'freq' => 3, 'conf' => 0.9, 'first' => 1],
    ['key' => 'b', 'freq' => 2, 'conf' => 0.9, 'first' => 2],
    ['key' => 'c', 'freq' => 2, 'conf' => 0.8, 'first' => 3],
    ['key' => 'd', 'freq' => 1, 'conf' => 0.9, 'first' => 4],
    ['key' => 'e', 'freq' => 1, 'conf' => 0.9, 'first' => 5],
    ['key' => 'f', 'freq' => 1, 'conf' => 0.5, 'first' => 0],
];
check('top5 by frequency', rankingTopN($items, 5) === ['a', 'b', 'c', 'd', 'e']);

$items2 = $items;
$items2[3]['conf'] = 0.4; // freq-1 group now sorts e(0.9), f(0.5), d(0.4) → e and f make the cut
check('freq tie broken by confidence', rankingTopN($items2, 5) === ['a', 'b', 'c', 'e', 'f']);

$items3 = [
    ['key' => 'a', 'freq' => 1, 'conf' => 0.5, 'first' => 2],
    ['key' => 'b', 'freq' => 1, 'conf' => 0.5, 'first' => 1],
    ['key' => 'c', 'freq' => 1, 'conf' => 0.5, 'first' => 3],
    ['key' => 'd', 'freq' => 1, 'conf' => 0.5, 'first' => 5],
    ['key' => 'e', 'freq' => 1, 'conf' => 0.5, 'first' => 4],
];
check('conf tie broken by first occurrence', rankingTopN($items3, 5) === ['b', 'a', 'c', 'e', 'd']);
check('fewer than 5 distinct returns null', rankingTopN(array_slice($items3, 0, 4), 5) === null);

// ── rankingMakePairs ─────────────────────────────────────────────────────────
$keys = ['a', 'b', 'c', 'd', 'e'];
$pairs = rankingMakePairs($keys);
check('10 pairs generated', count($pairs) === 10);
$seen = [];
$dupes = false;
foreach ($pairs as $p) {
    $norm = [$p['left'], $p['right']];
    sort($norm);
    $sig = implode('|', $norm);
    if (isset($seen[$sig])) $dupes = true;
    $seen[$sig] = true;
    if ($p['left'] === $p['right']) $dupes = true;
}
check('pairs unique, no self-pairs', !$dupes && count($seen) === 10);
$pairs6 = rankingMakePairs(['a', 'b', 'c', 'd', 'e', 'f']);
check('6 values yield 15 pairs', count($pairs6) === 15);

// ── rankingScores ────────────────────────────────────────────────────────────
$comps = [
    ['left_value_key'=>'a','right_value_key'=>'b','winner_value_key'=>'a','is_tiebreak'=>0],
    ['left_value_key'=>'a','right_value_key'=>'c','winner_value_key'=>'a','is_tiebreak'=>0],
    ['left_value_key'=>'b','right_value_key'=>'c','winner_value_key'=>'b','is_tiebreak'=>0],
    ['left_value_key'=>'d','right_value_key'=>'e','winner_value_key'=>null,'is_tiebreak'=>0],
    ['left_value_key'=>'a','right_value_key'=>'d','winner_value_key'=>'a','is_tiebreak'=>1], // ignored
];
$scores = rankingScores($comps, $keys);
check('scores counted, unanswered & tiebreak ignored',
    $scores === ['a' => 2, 'b' => 1, 'c' => 0, 'd' => 0, 'e' => 0]);

// ── rankingResolve (v3 silent ties: wins → freq → one extra duel) ───────────
$r = rankingResolve(['a' => 4, 'b' => 3, 'c' => 2, 'd' => 1, 'e' => 0]);
check('clean top2', $r['status'] === 'final' && $r['top'] === ['a', 'b']);

$r = rankingResolve(['a' => 3, 'b' => 3, 'c' => 2], ['a' => 1, 'b' => 5]);
check('rank1-2 tie resolved by freq (both stay in)', $r['status'] === 'final' && $r['top'] === ['b', 'a']);

$r = rankingResolve(['a' => 4, 'b' => 2, 'c' => 2, 'd' => 1], ['b' => 3, 'c' => 1]);
check('boundary tie resolved silently by freq', $r['status'] === 'final' && $r['top'] === ['a', 'b']);

$r = rankingResolve(['a' => 4, 'b' => 2, 'c' => 2, 'd' => 1], ['b' => 2, 'c' => 2]);
check('hard boundary tie requests one extra duel', $r['status'] === 'tiebreak'
    && count($r['pair']) === 2 && in_array('b', $r['pair']) && in_array('c', $r['pair']));

$r = rankingResolve(['a' => 4, 'b' => 2, 'c' => 2, 'd' => 1], ['b' => 2, 'c' => 2], [],
                    ['left' => 'b', 'right' => 'c', 'winner' => 'c']);
check('tiebreak winner takes slot 2', $r['status'] === 'final' && $r['top'] === ['a', 'c']);

$r = rankingResolve(['a' => 2, 'b' => 2, 'c' => 2], ['a' => 4, 'b' => 3, 'c' => 2]);
check('3-way wins tie fully separated by freq', $r['status'] === 'final' && $r['top'] === ['a', 'b']);

$r = rankingResolve(['a' => 4, 'b' => 2, 'c' => 2, 'd' => 1], ['b' => 2, 'c' => 2], [],
                    ['left' => 'b', 'right' => 'd', 'winner' => 'b']); // stale pair
check('mismatched tiebreak row is ignored', $r['status'] === 'tiebreak');

$r = rankingResolve(['a' => 1, 'b' => 1], ['a' => 2, 'b' => 2]);
check('two values → always final top2', $r['status'] === 'final' && count($r['top']) === 2);

echo $failures ? "\n$failures FAILURES\n" : "\nAll tests passed.\n";
exit($failures ? 1 : 0);
