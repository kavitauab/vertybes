<?php
/**
 * Migration: go-live copy — fast-finalize states (Go live.docx req. 4–5).
 */

class GoliveTextsMigration {
    public function up($db) {
        $texts = [
            ['calc.title', 'Skaičiuojame tavo rezultatą...', 'Rezultato skaičiavimo būsena'],
            ['result.interpLater', 'Išsamią interpretaciją atsiųsime el. paštu.', 'Kai interpretacija dar ruošiama'],
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
        $db->query("DELETE FROM ui_texts WHERE text_key IN ('calc.title','result.interpLater')");
    }
}
