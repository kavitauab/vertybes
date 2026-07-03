<?php
/**
 * Database Migration System.
 * Migrations are PHP files in this directory named YYYY_MM_DD_NNNNNN_description.php
 * Each defines a class with up($db) / down($db) methods.
 */

require_once __DIR__ . '/../database.php';

class Migrator {
    private $db;
    private $migrationsDir;
    private $table = 'migrations';

    public function __construct() {
        $this->db = new Database();
        $this->migrationsDir = __DIR__;
        $this->ensureTable();
    }

    private function ensureTable() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_migration_name (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function getFiles() {
        $files = glob($this->migrationsDir . '/*_*.php');
        $out = [];
        foreach ($files as $f) {
            $name = basename($f, '.php');
            if ($name === 'migrator') continue;
            $out[$name] = $f;
        }
        ksort($out);
        return $out;
    }

    private function executed() {
        $rows = $this->db->fetchAll("SELECT migration FROM {$this->table} ORDER BY migration");
        return array_column($rows, 'migration');
    }

    private function pending() {
        return array_diff(array_keys($this->getFiles()), $this->executed());
    }

    private function currentBatch() {
        $r = $this->db->fetchOne("SELECT MAX(batch) AS b FROM {$this->table}");
        return (int)($r['b'] ?? 0);
    }

    public function status() {
        $all = $this->getFiles();
        $exec = $this->executed();
        $out = "Migration Status\n" . str_repeat('=', 60) . "\n";
        if (!$all) return $out . "No migrations.\n";
        foreach ($all as $name => $_) {
            $out .= (in_array($name, $exec) ? '[DONE]    ' : '[PENDING] ') . $name . "\n";
        }
        $p = count(array_diff(array_keys($all), $exec));
        return $out . "\n$p pending\n";
    }

    public function migrate() {
        $pending = $this->pending();
        if (!$pending) return "Nothing to migrate.\n";
        $batch = $this->currentBatch() + 1;
        $files = $this->getFiles();
        $out = "Running migrations...\n";
        foreach ($pending as $name) {
            $out .= "  $name ... ";
            try {
                $migration = $this->load($files[$name]);
                if (method_exists($migration, 'up')) $migration->up($this->db);
                $this->db->insert($this->table, ['migration' => $name, 'batch' => $batch]);
                $out .= "OK\n";
            } catch (Throwable $e) {
                $out .= "FAILED: " . $e->getMessage() . "\n";
                return $out;
            }
        }
        return $out . "Done.\n";
    }

    public function rollback() {
        $batch = $this->currentBatch();
        if ($batch === 0) return "Nothing to rollback.\n";
        $migs = $this->db->fetchAll(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY migration DESC",
            [$batch]
        );
        $files = $this->getFiles();
        $out = "Rolling back batch $batch...\n";
        foreach ($migs as $row) {
            $name = $row['migration'];
            $out .= "  $name ... ";
            try {
                if (isset($files[$name])) {
                    $m = $this->load($files[$name]);
                    if (method_exists($m, 'down')) $m->down($this->db);
                }
                $this->db->query("DELETE FROM {$this->table} WHERE migration = ?", [$name]);
                $out .= "OK\n";
            } catch (Throwable $e) {
                $out .= "FAILED: " . $e->getMessage() . "\n";
                return $out;
            }
        }
        return $out;
    }

    private function load($file) {
        $before = get_declared_classes();
        require_once $file;
        $after = get_declared_classes();
        $new = array_values(array_diff($after, $before));
        if (empty($new)) {
            // Already loaded — derive name from file
            $base = basename($file, '.php');
            $parts = explode('_', $base, 5);
            $desc = $parts[4] ?? '';
            $cls = '';
            foreach (explode('_', $desc) as $w) $cls .= ucfirst($w);
            $cls .= 'Migration';
            if (class_exists($cls)) return new $cls();
            throw new RuntimeException("Cannot resolve class for $base");
        }
        $cls = end($new);
        return new $cls();
    }
}
