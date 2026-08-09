<?php namespace ProcessWire;

trait LioraStoreSchemaTrait {

    public function ensureTable(): void {
        if($this->tablesEnsured) return;
        $db = $this->wire('database');
        $db->exec(
            "CREATE TABLE IF NOT EXISTS `" . self::THREADS . "` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `public_id` VARCHAR(64) NOT NULL,
                `title` VARCHAR(255) NOT NULL DEFAULT '',
                `original_query` VARCHAR(500) NOT NULL DEFAULT '',
                `context` VARCHAR(255) NOT NULL DEFAULT '',
                `source_url` VARCHAR(2048) NOT NULL DEFAULT '',
                `referrer_url` VARCHAR(2048) NOT NULL DEFAULT '',
                `page_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `page_title` VARCHAR(255) NOT NULL DEFAULT '',
                `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `session_hash` CHAR(64) NOT NULL DEFAULT '',
                `country_code` CHAR(2) NOT NULL DEFAULT '',
                `country` VARCHAR(128) NOT NULL DEFAULT '',
                `region` VARCHAR(128) NOT NULL DEFAULT '',
                `city` VARCHAR(128) NOT NULL DEFAULT '',
                `status` VARCHAR(32) NOT NULL DEFAULT 'new',
                `message_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `reviewed_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `public_id` (`public_id`),
                KEY `status_updated` (`status`, `updated_at`),
                KEY `original_query` (`original_query`(191)),
                KEY `page_updated` (`page_id`, `updated_at`),
                KEY `session_updated` (`session_hash`, `updated_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS `" . self::MESSAGES . "` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `thread_id` INT UNSIGNED NOT NULL,
                `legacy_query_id` INT UNSIGNED NULL,
                `role` VARCHAR(24) NOT NULL,
                `content` MEDIUMTEXT NOT NULL,
                `provider` VARCHAR(64) NOT NULL DEFAULT '',
                `model` VARCHAR(255) NOT NULL DEFAULT '',
                `source_url` VARCHAR(2048) NOT NULL DEFAULT '',
                `page_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `tokens_input` INT UNSIGNED NOT NULL DEFAULT 0,
                `tokens_output` INT UNSIGNED NOT NULL DEFAULT 0,
                `tokens_total` INT UNSIGNED NOT NULL DEFAULT 0,
                `cached` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `response_time_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                `metadata` MEDIUMTEXT NULL,
                `error` VARCHAR(1000) NOT NULL DEFAULT '',
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `legacy_query_id` (`legacy_query_id`),
                KEY `thread_created` (`thread_id`, `created_at`),
                CONSTRAINT `liora_messages_thread`
                    FOREIGN KEY (`thread_id`) REFERENCES `" . self::THREADS . "` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->ensureColumn(
            self::MESSAGES,
            'response_time_ms',
            "`response_time_ms` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `cached`"
        );
        $this->ensureColumn(
            self::MESSAGES,
            'metadata',
            "`metadata` MEDIUMTEXT NULL AFTER `response_time_ms`"
        );
        $this->tablesEnsured = true;
    }

    public function dropTable(): void {
        $db = $this->wire('database');
        $db->exec("DROP TABLE IF EXISTS `" . self::MESSAGES . "`");
        $db->exec("DROP TABLE IF EXISTS `" . self::THREADS . "`");
        $db->exec("DROP TABLE IF EXISTS `" . self::LEGACY . "`");
        $this->tablesEnsured = false;
    }

}
