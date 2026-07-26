<?php
require_once __DIR__ . '/config.php';

class Database {
    private $db;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $dsn = 'mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT
                 . ';dbname=' . MYSQL_DATABASE . ';charset=utf8mb4';
            $this->db = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD, [
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Set here instead of MYSQL_ATTR_INIT_COMMAND (deprecated in PHP 8.5)
            $this->db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // Avoid leaking credentials in production
            if (ENVIRONMENT === 'development') {
                die('Database connection failed: ' . $e->getMessage());
            }
            http_response_code(500);
            die('Database connection failed.');
        }
    }

    /**
     * Shared-hosting MySQL kills idle connections while long API calls
     * (OpenAI, ~20s) are in flight — "2006 server has gone away". Reconnect
     * and retry once, but never inside a transaction (state would be lost).
     */
    private function isGoneAway(PDOException $e) {
        $m = $e->getMessage();
        return strpos($m, 'server has gone away') !== false
            || strpos($m, 'Lost connection') !== false;
    }

    public function getConnection() { return $this->db; }
    public function now() { return date('Y-m-d H:i:s'); }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if ($this->isGoneAway($e) && !$this->db->inTransaction()) {
                $this->connect();
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            }
            throw $e;
        }
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert($table, array $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $this->query("INSERT INTO `$table` ($columns) VALUES ($placeholders)", $data);
        return $this->db->lastInsertId();
    }

    public function update($table, array $data, $where, array $whereParams = []) {
        $setParts = [];
        $setParams = [];
        foreach ($data as $col => $val) {
            $setParts[] = "`$col` = ?";
            $setParams[] = $val;
        }
        $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE $where";
        return (bool)$this->query($sql, array_merge($setParams, $whereParams));
    }

    public function delete($table, $where, array $params = []) {
        return (bool)$this->query("DELETE FROM `$table` WHERE $where", $params);
    }

    public function batchInsert($table, array $rows, $chunkSize = 500) {
        if (empty($rows)) return 0;
        return $this->batchInsertWithVerb($table, $rows, 'INSERT', $chunkSize);
    }

    public function batchInsertIgnore($table, array $rows, $chunkSize = 500) {
        if (empty($rows)) return 0;
        return $this->batchInsertWithVerb($table, $rows, 'INSERT IGNORE', $chunkSize);
    }

    private function batchInsertWithVerb($table, array $rows, $verb, $chunkSize = 500) {
        $columns = array_keys($rows[0]);
        $colList = '`' . implode('`, `', $columns) . '`';
        $rowPh = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $inserted = 0;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), $rowPh));
            $params = [];
            foreach ($chunk as $row) {
                foreach ($columns as $col) {
                    $params[] = $row[$col] ?? null;
                }
            }
            $stmt = $this->query("$verb INTO `$table` ($colList) VALUES $placeholders", $params);
            $inserted += $verb === 'INSERT IGNORE' ? $stmt->rowCount() : count($chunk);
        }
        return $inserted;
    }

    public function getTableColumns($table) {
        $rows = $this->fetchAll(
            "SELECT COLUMN_NAME AS name, DATA_TYPE AS type, IS_NULLABLE AS notnull,
                    COLUMN_DEFAULT AS dflt_value, COLUMN_KEY AS pk
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [MYSQL_DATABASE, $table]
        );
        return array_map(function($c) {
            return [
                'name' => $c['name'],
                'type' => $c['type'],
                'notnull' => $c['notnull'] === 'NO' ? 1 : 0,
                'dflt_value' => $c['dflt_value'],
                'pk' => $c['pk'] === 'PRI' ? 1 : 0,
            ];
        }, $rows);
    }

    public function columnExists($table, $column) {
        foreach ($this->getTableColumns($table) as $col) {
            if ($col['name'] === $column) return true;
        }
        return false;
    }

    public function tableExists($table) {
        $row = $this->fetchOne(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [MYSQL_DATABASE, $table]
        );
        return !empty($row);
    }

    public function beginTransaction() { return $this->db->beginTransaction(); }
    public function commit() { return $this->db->commit(); }
    public function rollback() { return $this->db->rollBack(); }
    public function lastInsertId() { return $this->db->lastInsertId(); }
}
