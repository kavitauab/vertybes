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
