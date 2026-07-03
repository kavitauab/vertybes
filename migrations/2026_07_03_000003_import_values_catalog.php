<?php
/**
 * Migration: Import the canonical 191-value catalog from seeds/values_lt.csv
 * (exported from Tomas's vertybes_values_LT.xlsx). Idempotent — existing keys
 * are left untouched so admin edits survive re-runs.
 */

class ImportValuesCatalogMigration {
    public function up($db) {
        $csv = __DIR__ . '/../seeds/values_lt.csv';
        if (!is_file($csv)) {
            throw new RuntimeException('seeds/values_lt.csv not found');
        }

        $fh = fopen($csv, 'r');
        $header = fgetcsv($fh, null, ',', '"', '');
        $expected = ['value_key','label_lt','meaning_lt','tension_lt','synonyms_lt'];
        if ($header !== $expected) {
            fclose($fh);
            throw new RuntimeException('Unexpected CSV header: ' . implode(',', (array)$header));
        }

        $rows = [];
        $order = 1;
        while (($r = fgetcsv($fh, null, ',', '"', '')) !== false) {
            if (count($r) < 5 || trim($r[0]) === '') continue;
            $rows[] = [
                'value_key'   => trim($r[0]),
                'label_lt'    => trim($r[1]),
                'meaning_lt'  => trim($r[2]),
                'tension_lt'  => trim($r[3]),
                'synonyms_lt' => trim($r[4]),
                'is_active'   => 1,
                'sort_order'  => $order++,
            ];
        }
        fclose($fh);

        if (count($rows) < 100) {
            throw new RuntimeException('Suspiciously few values in CSV: ' . count($rows));
        }

        $db->batchInsertIgnore('values_catalog', $rows);
    }

    public function down($db) {
        $db->query("DELETE FROM values_catalog");
    }
}
