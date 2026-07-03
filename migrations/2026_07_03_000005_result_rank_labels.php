<?php
/**
 * Migration: rank labels shown above each result value (admin-editable).
 */

class ResultRankLabelsMigration {
    public function up($db) {
        $texts = [
            ['result.rank1', 'Stipriausia vertybė', 'Rezultato 1 vietos etiketė'],
            ['result.rank2', 'Antra vertybė',       'Rezultato 2 vietos etiketė'],
            ['result.rank3', 'Trečia vertybė',      'Rezultato 3 vietos etiketė (lygiosios)'],
            ['result.rank4', 'Ketvirta vertybė',    'Rezultato 4 vietos etiketė (lygiosios)'],
            ['result.rank5', 'Penkta vertybė',      'Rezultato 5 vietos etiketė (lygiosios)'],
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
            ('result.rank1','result.rank2','result.rank3','result.rank4','result.rank5')");
    }
}
