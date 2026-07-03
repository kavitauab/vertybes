<?php
/**
 * Migration: Create initial Vertybių testas schema.
 */

class CreateInitialSchemaMigration {
    public function up($db) {
        // ── Users (admin portal humans) ───────────────────────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(100) NOT NULL,
                role ENUM('admin','editor') NOT NULL DEFAULT 'editor',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_users_email (email),
                INDEX idx_users_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Settings (key/value, editable in admin) ───────────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(64) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── UI texts (every user-facing string, editable in admin) ───────────
        $db->query("
            CREATE TABLE IF NOT EXISTS ui_texts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                text_key VARCHAR(64) NOT NULL UNIQUE,
                text_value TEXT NOT NULL,
                context VARCHAR(150) NOT NULL DEFAULT '',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Questions (the 4 open questions) ──────────────────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question_key VARCHAR(16) NOT NULL UNIQUE,
                text VARCHAR(500) NOT NULL,
                hint VARCHAR(500) NOT NULL DEFAULT '',
                sort_order INT NOT NULL DEFAULT 0,
                max_answers TINYINT NOT NULL DEFAULT 6,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Values catalog (the 191 canonical values from the XLS) ────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS values_catalog (
                id INT AUTO_INCREMENT PRIMARY KEY,
                value_key VARCHAR(64) NOT NULL UNIQUE,
                label_lt VARCHAR(120) NOT NULL,
                meaning_lt VARCHAR(500) NOT NULL DEFAULT '',
                tension_lt TEXT NULL,
                synonyms_lt TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_values_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Test sessions (one anonymous test run) ────────────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS test_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL UNIQUE,
                status ENUM('created','consented','answering','ai_suggested','values_confirmed',
                            'comparing','result_ready','email_captured') NOT NULL DEFAULT 'created',
                locale VARCHAR(5) NOT NULL DEFAULT 'lt',
                top5_json TEXT NULL,
                ip_hash CHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_sessions_status (status),
                INDEX idx_sessions_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Consents (privacy/AI + cookies, versioned, GDPR log) ──────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS session_consents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                consent_type ENUM('privacy_ai','cookies') NOT NULL,
                accepted TINYINT(1) NOT NULL,
                version VARCHAR(32) NOT NULL DEFAULT '',
                ip_hash CHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_session_consent (session_id, consent_type),
                FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Answers (up to N short answers per question) ──────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS session_answers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                question_key VARCHAR(16) NOT NULL,
                answer_index TINYINT NOT NULL DEFAULT 0,
                answer_text TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_session_answer (session_id, question_key, answer_index),
                FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── AI suggestion requests (raw audit trail) ──────────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS ai_suggestions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                provider VARCHAR(32) NOT NULL DEFAULT 'openai',
                model VARCHAR(64) NOT NULL DEFAULT '',
                prompt_version VARCHAR(32) NOT NULL DEFAULT '',
                request_id VARCHAR(128) NULL,
                status ENUM('ok','error','mock') NOT NULL DEFAULT 'ok',
                error_message TEXT NULL,
                raw_response MEDIUMTEXT NULL,
                duration_ms INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ai_session (session_id),
                FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Per-answer value mapping (AI suggestion + user confirmation) ──────
        $db->query("
            CREATE TABLE IF NOT EXISTS answer_values (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                answer_id INT NOT NULL,
                suggested_value_key VARCHAR(64) NULL,
                confidence DECIMAL(4,3) NULL,
                confirmed_value_key VARCHAR(64) NULL,
                source ENUM('ai','user') NOT NULL DEFAULT 'ai',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_answer_value (answer_id),
                INDEX idx_av_session (session_id),
                FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (answer_id) REFERENCES session_answers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Pairwise comparisons (10 duels + optional tie-break round) ────────
        $db->query("
            CREATE TABLE IF NOT EXISTS comparisons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                pair_index TINYINT NOT NULL,
                left_value_key VARCHAR(64) NOT NULL,
                right_value_key VARCHAR(64) NOT NULL,
                winner_value_key VARCHAR(64) NULL,
                is_tiebreak TINYINT(1) NOT NULL DEFAULT 0,
                answered_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_session_pair (session_id, pair_index),
                FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Final results ─────────────────────────────────────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS session_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL UNIQUE,
                scores_json TEXT NOT NULL,
                top_keys_json TEXT NOT NULL,
                computed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Leads (waiting list + result email capture) ───────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS leads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                source ENUM('waitlist','result') NOT NULL,
                session_id INT NULL,
                top_values VARCHAR(255) NULL,
                ip_hash CHAR(64) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_email_source (email, source),
                INDEX idx_leads_created (created_at),
                FOREIGN KEY (session_id) REFERENCES test_sessions(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Audit log (admin actions) ─────────────────────────────────────────
        $db->query("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(64) NOT NULL,
                entity VARCHAR(64) NOT NULL DEFAULT '',
                entity_id VARCHAR(64) NOT NULL DEFAULT '',
                details TEXT NULL,
                ip VARCHAR(45) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_created (created_at),
                INDEX idx_audit_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down($db) {
        foreach (['audit_log','leads','session_results','comparisons','answer_values',
                  'ai_suggestions','session_answers','session_consents','test_sessions',
                  'values_catalog','questions','ui_texts','settings','users'] as $t) {
            $db->query("DROP TABLE IF EXISTS `$t`");
        }
    }
}
