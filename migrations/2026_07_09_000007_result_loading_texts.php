<?php
/**
 * Migration: loading-state copy for result generation (after the last duel
 * the pair tension/meaning texts are AI-generated — takes a few seconds).
 */

class ResultLoadingTextsMigration {
    public function up($db) {
        $texts = [
            ['loading.result.title', 'Skaičiuoju tavo rezultatą', 'Rezultato laukimo antraštė'],
            ['loading.result.sub', 'Lyginimai baigti. Ruošiu tavo asmeninę vertybių apžvalgą.', 'Rezultato laukimo tekstas'],
            ['loading.result.chip', 'Ruošiu apžvalgą...', 'Rezultato laukimo ženkliukas'],
        ];
        foreach ($texts as [$key, $value, $context]) {
            $db->query(
                "INSERT INTO ui_texts (text_key, text_value, context) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE text_key = text_key",
                [$key, $value, $context]
            );
        }
    }

    public function down($db) {
        $db->query("DELETE FROM ui_texts WHERE text_key IN
            ('loading.result.title','loading.result.sub','loading.result.chip')");
    }
}
