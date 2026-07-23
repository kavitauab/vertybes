<?php
/**
 * Migration: full 32-value content from Tomas's vertybiu_lentele.xlsx
 * (2026-07-23): short description, inner tension, example user phrases and
 * the Active flag. Idempotent per run — overwrites catalog content so the
 * sheet stays the source of truth (further edits go through the admin).
 */

class ValuesContentMigration {
    public function up($db) {
        $csv = __DIR__ . '/../seeds/values_v3.csv';
        if (!is_file($csv)) throw new RuntimeException('seeds/values_v3.csv not found');

        $fh = fopen($csv, 'r');
        $header = fgetcsv($fh, null, ',', '"', '');
        if ($header !== ['key', 'label', 'meaning', 'tension', 'phrases', 'active']) {
            fclose($fh);
            throw new RuntimeException('Unexpected CSV header: ' . implode(',', (array)$header));
        }
        $n = 0;
        while (($r = fgetcsv($fh, null, ',', '"', '')) !== false) {
            if (count($r) < 6 || trim($r[0]) === '') continue;
            $db->query(
                "UPDATE values_catalog
                 SET label_lt = ?, meaning_lt = ?, tension_lt = ?, synonyms_lt = ?, is_active = ?
                 WHERE value_key = ?",
                [trim($r[1]), trim($r[2]), trim($r[3]), trim($r[4]),
                 strtolower(trim($r[5])) === 'yes' ? 1 : 0, trim($r[0])]
            );
            $n++;
        }
        fclose($fh);
        if ($n < 30) throw new RuntimeException("Suspiciously few rows updated: $n");
    }

    public function down($db) {
        // Content migration — no rollback.
    }
}
