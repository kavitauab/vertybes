<?php
/**
 * AI value mapping: one OpenAI request maps every answer to a canonical value.
 *
 * - Server-side only (curl), structured JSON output enforced via json_schema.
 * - Retries with backoff on 429/5xx/timeouts.
 * - Mock mode (setting ai_mock_mode=1): diacritics-insensitive keyword matcher
 *   over labels+synonyms, so the whole flow works without a key.
 * - Whatever comes back, only value_keys present in the catalog are accepted;
 *   the rest become null (user picks manually on the review screen).
 */

require_once __DIR__ . '/app.php';

/**
 * @param array $answers  [['id' => int, 'question' => string, 'text' => string], ...]
 * @param array $catalog  [['value_key','label_lt','synonyms_lt'], ...] active values
 * @return array ['status' => 'ok'|'mock'|'error', 'model' => ?, 'request_id' => ?,
 *                'raw' => ?, 'error' => ?, 'duration_ms' => int,
 *                'mappings' => [answer_id => ['value_key' => ?string, 'confidence' => float]]]
 */
function aiMapAnswers(array $answers, array $catalog) {
    $t0 = microtime(true);
    $validKeys = array_column($catalog, 'value_key');

    if (getSetting('ai_mock_mode', '1') === '1' || getOpenAiKey() === '') {
        $mappings = aiMockMap($answers, $catalog);
        return ['status' => 'mock', 'model' => 'mock', 'request_id' => null, 'raw' => null,
                'error' => null, 'duration_ms' => (int)((microtime(true) - $t0) * 1000),
                'mappings' => $mappings];
    }

    $model = (string)getSetting('openai_model', 'gpt-5.5');
    $systemPrompt = (string)getSetting('ai_system_prompt', 'Map each answer to one value key from the catalog.');

    $catalogLines = array_map(fn($v) =>
        $v['value_key'] . ' | ' . $v['label_lt'] . ' | ' . ($v['synonyms_lt'] ?? ''), $catalog);
    $answerLines = [];
    $idx = 0;
    $idxToId = [];
    foreach ($answers as $a) {
        $idxToId[$idx] = $a['id'];
        $answerLines[] = "[$idx] Klausimas: {$a['question']}\nAtsakymas: {$a['text']}";
        $idx++;
    }

    $payload = [
        'model' => $model,
        'store' => false,
        'input' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' =>
                "KANONINIS VERTYBIŲ SĄRAŠAS (value_key | pavadinimas | sinonimai):\n"
                . implode("\n", $catalogLines)
                . "\n\nATSAKYMAI:\n" . implode("\n\n", $answerLines)
                . "\n\nKiekvienam atsakymui [i] priskirk vieną value_key iš sąrašo ir confidence (0-1)."],
        ],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'value_mappings',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'mappings' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'answer_index' => ['type' => 'integer'],
                                    'value_key' => ['type' => 'string'],
                                    'confidence' => ['type' => 'number'],
                                ],
                                'required' => ['answer_index', 'value_key', 'confidence'],
                            ],
                        ],
                    ],
                    'required' => ['mappings'],
                ],
            ],
        ],
    ];

    $result = aiHttpPost('https://api.openai.com/v1/responses', $payload, getOpenAiKey());
    $durationMs = (int)((microtime(true) - $t0) * 1000);

    if (!$result['ok']) {
        return ['status' => 'error', 'model' => $model, 'request_id' => $result['request_id'],
                'raw' => $result['body'], 'error' => $result['error'],
                'duration_ms' => $durationMs, 'mappings' => []];
    }

    $data = json_decode($result['body'], true);
    // Responses API: output[] → message → content[] → output_text.text
    $jsonText = null;
    foreach (($data['output'] ?? []) as $item) {
        if (($item['type'] ?? '') !== 'message') continue;
        foreach (($item['content'] ?? []) as $c) {
            if (($c['type'] ?? '') === 'output_text') { $jsonText = $c['text']; break 2; }
        }
    }
    $parsed = $jsonText !== null ? json_decode($jsonText, true) : null;
    if (!is_array($parsed) || !isset($parsed['mappings'])) {
        return ['status' => 'error', 'model' => $model, 'request_id' => $result['request_id'],
                'raw' => $result['body'], 'error' => 'Unparseable structured output',
                'duration_ms' => $durationMs, 'mappings' => []];
    }

    $mappings = [];
    foreach ($parsed['mappings'] as $m) {
        $i = (int)($m['answer_index'] ?? -1);
        if (!isset($idxToId[$i])) continue;
        $key = in_array($m['value_key'] ?? '', $validKeys, true) ? $m['value_key'] : null;
        $conf = max(0, min(1, (float)($m['confidence'] ?? 0)));
        $mappings[$idxToId[$i]] = ['value_key' => $key, 'confidence' => $conf];
    }
    // Any answer the model skipped → null mapping (user picks manually)
    foreach ($answers as $a) {
        if (!isset($mappings[$a['id']])) {
            $mappings[$a['id']] = ['value_key' => null, 'confidence' => 0.0];
        }
    }

    return ['status' => 'ok', 'model' => $model, 'request_id' => $result['request_id'],
            'raw' => $result['body'], 'error' => null,
            'duration_ms' => $durationMs, 'mappings' => $mappings];
}

/**
 * v3 analysis (PERDAVIMAS.md AI contract): map the user's answers to 3–5
 * values from the FINAL dictionary, with confidence, verbatim evidence quotes
 * and 1–3 short "mentions" (base nominative, ≤3 words / ≤24 chars) distilled
 * only from that value's quotes.
 *
 * @param array $answers [['question_number' => 1-4, 'text' => string], ...]
 * @param array $dict    [value_key => label_lt] — the active dictionary
 * @return array ['status' => 'ok'|'mock'|'error', 'values' => [...], 'raw', 'request_id', 'error', 'duration_ms']
 *               values: [['value_key','label','confidence','evidence'=>[['quote','question_number']],'mentions'=>[...]], ...]
 */
function aiAnalyzeValues(array $answers, array $dict) {
    $t0 = microtime(true);
    $labels = array_values($dict);
    $byLabel = array_flip($dict); // label -> key

    if (getSetting('ai_mock_mode', '1') === '1' || getOpenAiKey() === '') {
        return ['status' => 'mock', 'values' => aiMockAnalyze($answers, $dict), 'raw' => null,
                'request_id' => null, 'error' => null,
                'duration_ms' => (int)((microtime(true) - $t0) * 1000)];
    }

    $system = 'Tu esi vertybių analizės variklis. '
        . 'Taisyklės: (1) "name" TIK iš sąrašo: ' . implode(', ', $labels) . '. '
        . 'Sinonimus suvesk į kanoninį pavadinimą (pvz. „nepriklausomybė“ → Savarankiškumas, „sąžiningumas“ → Tiesa, „humoras“ → Žaismingumas). '
        . '(2) Atrink 3–5 vertybes pagal: kryžminį dažnį tarp klausimų (svarbiausia), konkretumą (konkreti frazė > deklaracija), '
        . '2 klausimo (kas suerzina) svorio daugiklį. Vertybė su viena bendrine citata nekvalifikuojama — geriau 4 stiprios nei 5 su viena silpna. '
        . '(3) "quote" — pažodinės citatos iš atsakymų. '
        . '(4) "mentions" — 1–3 trumpos frazės vardininko forma, mažosiomis, ne daugiau kaip 3 žodžiai (~24 simboliai), '
        . 'TIK iš tos vertybės citatų (pvz. „pats planuoju laiką“ → „savo laikas“), niekada neišgalvotos.';

    $payload = [
        'model' => (string)getSetting('openai_model', 'gpt-5.5'),
        'store' => false,
        'input' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode(['answers' => $answers], JSON_UNESCAPED_UNICODE)],
        ],
        'text' => ['format' => [
            'type' => 'json_schema', 'name' => 'value_analysis', 'strict' => true,
            'schema' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => ['values' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'evidence' => [
                                'type' => 'array', 'maxItems' => 4,
                                'items' => [
                                    'type' => 'object', 'additionalProperties' => false,
                                    'properties' => [
                                        'quote' => ['type' => 'string'],
                                        'question_number' => ['type' => 'integer'],
                                    ],
                                    'required' => ['quote', 'question_number'],
                                ],
                            ],
                            'mentions' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                        ],
                        'required' => ['name', 'confidence', 'evidence', 'mentions'],
                    ],
                ]],
                'required' => ['values'],
            ],
        ]],
    ];

    $res = aiHttpPost('https://api.openai.com/v1/responses', $payload, getOpenAiKey());
    $durationMs = (int)((microtime(true) - $t0) * 1000);
    if (!$res['ok']) {
        return ['status' => 'error', 'values' => [], 'raw' => $res['body'],
                'request_id' => $res['request_id'], 'error' => $res['error'], 'duration_ms' => $durationMs];
    }
    $parsed = aiExtractStructured($res['body']);
    $values = [];
    foreach (($parsed['values'] ?? []) as $v) {
        $label = trim($v['name'] ?? '');
        if (!isset($byLabel[$label])) continue;          // dictionary enforcement
        $values[] = [
            'value_key' => $byLabel[$label],
            'label' => $label,
            'confidence' => max(0, min(1, (float)($v['confidence'] ?? 0))),
            'evidence' => array_slice((array)($v['evidence'] ?? []), 0, 4),
            // three-layer limit: schema maxItems 3 → server slice+trim → UI ellipsis
            'mentions' => array_values(array_filter(array_map(
                fn($m) => mb_substr(trim((string)$m), 0, 24),
                array_slice((array)($v['mentions'] ?? []), 0, 3)))),
        ];
        if (count($values) === 5) break;
    }
    return ['status' => 'ok', 'values' => $values, 'raw' => $res['body'],
            'request_id' => $res['request_id'], 'error' => null, 'duration_ms' => $durationMs];
}

/** Keyless dev path: keyword-match answers against dictionary labels. */
function aiMockAnalyze(array $answers, array $dict) {
    $hits = [];
    foreach ($answers as $a) {
        $text = aiNormalize($a['text']);
        foreach ($dict as $key => $label) {
            $stem = mb_substr(aiNormalize($label), 0, 5);
            if ($stem !== '' && mb_strpos($text, $stem) !== false) {
                $hits[$key]['q'][] = $a['question_number'];
                $hits[$key]['quotes'][] = $a['text'];
            }
        }
    }
    // pad deterministically to reach 3 values in dev
    $keys = array_keys($dict);
    $i = 0;
    while (count($hits) < 3 && $i < count($keys)) {
        $k = $keys[($i * 7) % count($keys)];
        if (!isset($hits[$k])) $hits[$k] = ['q' => [1], 'quotes' => [$answers[0]['text'] ?? '']];
        $i++;
    }
    $values = [];
    foreach (array_slice($hits, 0, 5, true) as $key => $h) {
        $values[] = [
            'value_key' => $key,
            'label' => $dict[$key],
            'confidence' => round(min(.95, .5 + count(array_unique($h['q'])) * .15), 2),
            'evidence' => array_map(fn($q, $i2) => ['quote' => $q, 'question_number' => $h['q'][$i2] ?? 1],
                array_slice($h['quotes'], 0, 3), array_keys(array_slice($h['quotes'], 0, 3))),
            'mentions' => array_map(fn($q) => mb_substr(mb_strtolower($q), 0, 24), array_slice($h['quotes'], 0, 3)),
        ];
    }
    return $values;
}

/**
 * Generate a short first-person Lithuanian statement for each top value,
 * grounded in the user's own answers (shown inside the duel cards).
 *
 * @param array $values [['value_key','label_lt','answers' => [strings]], ...]
 * @return array [value_key => statement]  (falls back to the user's own quote)
 */
function aiGenerateStatements(array $values) {
    $fallback = [];
    foreach ($values as $v) {
        $fallback[$v['value_key']] = $v['answers']
            ? mb_substr($v['answers'][0], 0, 120)
            : ($v['label_lt'] ?? $v['value_key']);
    }

    if (getSetting('ai_mock_mode', '1') === '1' || getOpenAiKey() === '') {
        return $fallback;
    }

    $lines = array_map(fn($v) =>
        $v['value_key'] . ' (' . $v['label_lt'] . '): ' . implode(' | ', $v['answers']), $values);
    $payload = [
        'model' => (string)getSetting('openai_model', 'gpt-5.5'),
        'store' => false,
        'input' => [
            ['role' => 'system', 'content' =>
                'Tu rašai trumpus, natūralius pirmuoju asmeniu suformuluotus sakinius lietuviškai. '
                . 'Kiekvienai vertybei sukurk VIENĄ sakinį (iki 14 žodžių), paremtą žmogaus atsakymais — '
                . 'lyg pats žmogus paaiškintų, kodėl ši vertybė jam svarbi. Be patoso, be kabučių.'],
            ['role' => 'user', 'content' =>
                "Vertybės ir žmogaus atsakymai:\n" . implode("\n", $lines)],
        ],
        'text' => ['format' => [
            'type' => 'json_schema', 'name' => 'value_statements', 'strict' => true,
            'schema' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => ['statements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'properties' => [
                            'value_key' => ['type' => 'string'],
                            'statement' => ['type' => 'string'],
                        ],
                        'required' => ['value_key', 'statement'],
                    ],
                ]],
                'required' => ['statements'],
            ],
        ]],
    ];

    $res = aiHttpPost('https://api.openai.com/v1/responses', $payload, getOpenAiKey());
    if (!$res['ok']) return $fallback;
    $parsed = aiExtractStructured($res['body']);
    if (!is_array($parsed) || empty($parsed['statements'])) return $fallback;

    $out = $fallback;
    foreach ($parsed['statements'] as $s) {
        $k = $s['value_key'] ?? '';
        if (isset($out[$k]) && trim($s['statement'] ?? '') !== '') {
            $out[$k] = trim($s['statement']);
        }
    }
    return $out;
}

/**
 * Generate the result screen's pair copy: "Galima vidinė įtampa" and
 * "Ką tai reiškia" about the top values together (per the design).
 *
 * @param array $top rows from values_catalog (label_lt, meaning_lt, tension_lt)
 * @return array ['tension' => string, 'meaning' => string]
 */
function aiGeneratePairText(array $top) {
    $names = implode(' ir ', array_map(fn($v) => mb_strtoupper($v['label_lt']), array_slice($top, 0, 2)));
    $fallback = [
        'tension' => $names . ' gali tempti į skirtingas puses. Pastebėk, kurioje situacijoje kuri iš jų šiandien svarbesnė — tada sprendimai taps lengvesni.',
        'meaning' => 'Tavo sprendimus šiuo metu stipriausiai veda ' .
            implode(' ir ', array_map(fn($v) => mb_strtolower($v['label_lt']), array_slice($top, 0, 2))) .
            '. Kai renkiesi pagal jas, sprendimai suteikia energijos, o ne vidinio konflikto.',
    ];

    if (getSetting('ai_mock_mode', '1') === '1' || getOpenAiKey() === '') {
        return $fallback;
    }

    $detail = implode("\n", array_map(function ($v) {
        $mentions = isset($v['mentions']) && $v['mentions'] ? ' (užuominos: ' . implode(', ', $v['mentions']) . ')' : '';
        return mb_strtoupper($v['label_lt']) . $mentions;
    }, $top));
    $payload = [
        'model' => (string)getSetting('openai_model', 'gpt-5.5'),
        'store' => false,
        'input' => [
            ['role' => 'system', 'content' =>
                'Rašai lietuviškai, šiltai ir konkrečiai, kreipiniu „tu“, be giminės galūnių, be pažadų ir patoso. '
                . 'Pagal dvi stipriausias žmogaus vertybes parašyk: '
                . '1) "meaning" — 2 sakinius, ką šios vertybės kartu reiškia žmogaus sprendimams; '
                . '2) "tension" — 2 sakinius apie galimą vidinę įtampą TARP šių vertybių '
                . '(pvz.: „X ir Y gali tempti į skirtingas puses: vienur nori erdvės, kitur tikro ryšio.“), '
                . 'baigiant padrąsinimu, kad tai pastebėjus sprendimai lengvėja.'],
            ['role' => 'user', 'content' => "Stipriausios vertybės: $names\n\n$detail"],
        ],
        'text' => ['format' => [
            'type' => 'json_schema', 'name' => 'pair_text', 'strict' => true,
            'schema' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'tension' => ['type' => 'string'],
                    'meaning' => ['type' => 'string'],
                ],
                'required' => ['tension', 'meaning'],
            ],
        ]],
    ];

    $res = aiHttpPost('https://api.openai.com/v1/responses', $payload, getOpenAiKey());
    if (!$res['ok']) return $fallback;
    $parsed = aiExtractStructured($res['body']);
    if (!is_array($parsed) || trim($parsed['tension'] ?? '') === '') return $fallback;
    return ['tension' => trim($parsed['tension']), 'meaning' => trim($parsed['meaning'] ?? $fallback['meaning'])];
}

/** Pull the structured-output JSON out of a Responses API body. */
function aiExtractStructured($body) {
    $data = json_decode($body, true);
    foreach (($data['output'] ?? []) as $item) {
        if (($item['type'] ?? '') !== 'message') continue;
        foreach (($item['content'] ?? []) as $c) {
            if (($c['type'] ?? '') === 'output_text') return json_decode($c['text'], true);
        }
    }
    return null;
}

/**
 * List models usable for the mapping task. Hits GET /v1/models (free, works
 * even without credits) — so it doubles as a key validity check.
 * @return array ['ok' => bool, 'models' => [ids], 'error' => ?string]
 */
function aiListModels($apiKey) {
    $ch = curl_init('https://api.openai.com/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status !== 200) {
        $msg = $curlErr;
        if (!$msg && $body) {
            $d = json_decode($body, true);
            $msg = $d['error']['message'] ?? "HTTP $status";
        }
        return ['ok' => false, 'models' => [], 'error' => $msg ?: "HTTP $status"];
    }

    $data = json_decode($body, true);
    $models = [];
    foreach (($data['data'] ?? []) as $m) {
        $id = $m['id'] ?? '';
        // text-capable chat/reasoning families only
        if (!preg_match('/^(gpt-|o\d)/', $id)) continue;
        if (preg_match('/audio|realtime|tts|whisper|embedding|image|dall-e|moderation|transcribe|search|instruct|codex/', $id)) continue;
        $models[] = $id;
    }
    sort($models);
    return ['ok' => true, 'models' => $models, 'error' => null];
}

/** POST JSON with bearer auth; retries on 429/5xx/network errors. */
function aiHttpPost($url, array $payload, $apiKey, $maxAttempts = 3) {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $lastError = null;
    $requestId = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($url);
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$respHeaders) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                return strlen($header);
            },
        ]);
        $respBody = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $requestId = $respHeaders['x-request-id'] ?? $requestId;

        if ($respBody !== false && $status >= 200 && $status < 300) {
            return ['ok' => true, 'body' => $respBody, 'request_id' => $requestId, 'error' => null];
        }

        // insufficient_quota is a billing problem — retrying can't help
        $retryable = ($respBody === false || $status === 429 || $status >= 500)
                     && strpos((string)$respBody, 'insufficient_quota') === false;
        $lastError = $curlErr ?: "HTTP $status: " . substr((string)$respBody, 0, 500);
        if (!$retryable || $attempt === $maxAttempts) {
            return ['ok' => false, 'body' => (string)$respBody, 'request_id' => $requestId,
                    'error' => $lastError];
        }
        usleep($attempt === 1 ? 700000 : 2500000); // 0.7s, then 2.5s
    }
    return ['ok' => false, 'body' => '', 'request_id' => $requestId, 'error' => $lastError];
}

/** Strip Lithuanian diacritics + lowercase for fuzzy keyword matching. */
function aiNormalize($s) {
    $map = ['ą'=>'a','č'=>'c','ę'=>'e','ė'=>'e','į'=>'i','š'=>'s','ų'=>'u','ū'=>'u','ž'=>'z',
            'Ą'=>'a','Č'=>'c','Ę'=>'e','Ė'=>'e','Į'=>'i','Š'=>'s','Ų'=>'u','Ū'=>'u','Ž'=>'z'];
    return mb_strtolower(strtr($s, $map));
}

/**
 * Mock mapper: scores every catalog value against the answer text by matching
 * normalized label + synonym word stems. Deterministic fallback spreads values
 * by answer id so the dev flow always produces 5+ distinct values.
 */
function aiMockMap(array $answers, array $catalog) {
    $index = [];
    foreach ($catalog as $v) {
        $terms = array_filter(array_map('trim',
            explode(',', $v['label_lt'] . ',' . ($v['synonyms_lt'] ?? ''))));
        $index[$v['value_key']] = array_map('aiNormalize', $terms);
    }

    $mappings = [];
    $pos = 0;
    foreach ($answers as $a) {
        $text = aiNormalize($a['text']);
        $bestKey = null;
        $bestScore = 0;
        foreach ($index as $key => $terms) {
            $score = 0;
            foreach ($terms as $term) {
                if ($term === '') continue;
                // match on the first 5+ chars of the term to catch inflections
                $stem = mb_substr($term, 0, max(4, min(6, mb_strlen($term))));
                if ($stem !== '' && mb_strpos($text, $stem) !== false) {
                    $score += mb_strlen($term) > 5 ? 2 : 1;
                }
            }
            if ($score > $bestScore) { $bestScore = $score; $bestKey = $key; }
        }
        if ($bestKey === null && $catalog) {
            // deterministic spread so testing yields distinct values
            $bestKey = $catalog[($a['id'] + $pos * 7) % count($catalog)]['value_key'];
            $conf = 0.35;
        } else {
            $conf = min(0.95, 0.5 + $bestScore * 0.1);
        }
        $mappings[$a['id']] = ['value_key' => $bestKey, 'confidence' => round($conf, 3)];
        $pos++;
    }
    return $mappings;
}
