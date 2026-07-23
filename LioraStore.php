<?php namespace ProcessWire;

/**
 * Persistence for Liora demand signals.
 *
 * Session identifiers are one-way hashes; IP addresses and user agents are
 * intentionally not stored.
 */
class LioraStore extends Wire {

    public const TABLE = 'liora_queries';

    public function ensureTable(): void {
        $table = self::TABLE;
        $this->wire('database')->exec(
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `request_hash` CHAR(64) NOT NULL,
                `original_query` VARCHAR(500) NOT NULL DEFAULT '',
                `question` TEXT NOT NULL,
                `response` MEDIUMTEXT NULL,
                `provider` VARCHAR(64) NOT NULL DEFAULT '',
                `model` VARCHAR(255) NOT NULL DEFAULT '',
                `context` VARCHAR(255) NOT NULL DEFAULT '',
                `source_url` VARCHAR(2048) NOT NULL DEFAULT '',
                `page_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `session_hash` CHAR(64) NOT NULL DEFAULT '',
                `tokens_input` INT UNSIGNED NOT NULL DEFAULT 0,
                `tokens_output` INT UNSIGNED NOT NULL DEFAULT 0,
                `tokens_total` INT UNSIGNED NOT NULL DEFAULT 0,
                `cached` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `status` VARCHAR(32) NOT NULL DEFAULT 'new',
                `error` VARCHAR(1000) NOT NULL DEFAULT '',
                `created_at` DATETIME NOT NULL,
                `reviewed_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `request_hash` (`request_hash`),
                KEY `status_created` (`status`, `created_at`),
                KEY `original_query` (`original_query`(191)),
                KEY `page_created` (`page_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function dropTable(): void {
        $this->wire('database')->exec("DROP TABLE IF EXISTS `" . self::TABLE . "`");
    }

    public function add(array $data): bool {
        $this->ensureTable();
        $defaults = [
            'request_hash' => '',
            'original_query' => '',
            'question' => '',
            'response' => '',
            'provider' => '',
            'model' => '',
            'context' => '',
            'source_url' => '',
            'page_id' => 0,
            'user_id' => 0,
            'session_hash' => '',
            'tokens_input' => 0,
            'tokens_output' => 0,
            'tokens_total' => 0,
            'cached' => 0,
            'status' => 'new',
            'error' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $row = array_merge($defaults, $data);
        if($row['request_hash'] === '' || trim((string)$row['question']) === '') return false;

        $sql = "INSERT IGNORE INTO `" . self::TABLE . "`
            (`request_hash`, `original_query`, `question`, `response`, `provider`, `model`,
             `context`, `source_url`, `page_id`, `user_id`, `session_hash`,
             `tokens_input`, `tokens_output`, `tokens_total`, `cached`, `status`, `error`, `created_at`)
            VALUES
            (:request_hash, :original_query, :question, :response, :provider, :model,
             :context, :source_url, :page_id, :user_id, :session_hash,
             :tokens_input, :tokens_output, :tokens_total, :cached, :status, :error, :created_at)";
        $stmt = $this->wire('database')->prepare($sql);
        $stmt->execute([
            ':request_hash' => (string)$row['request_hash'],
            ':original_query' => mb_substr((string)$row['original_query'], 0, 500),
            ':question' => (string)$row['question'],
            ':response' => (string)$row['response'],
            ':provider' => mb_substr((string)$row['provider'], 0, 64),
            ':model' => mb_substr((string)$row['model'], 0, 255),
            ':context' => mb_substr((string)$row['context'], 0, 255),
            ':source_url' => mb_substr((string)$row['source_url'], 0, 2048),
            ':page_id' => max(0, (int)$row['page_id']),
            ':user_id' => max(0, (int)$row['user_id']),
            ':session_hash' => mb_substr((string)$row['session_hash'], 0, 64),
            ':tokens_input' => max(0, (int)$row['tokens_input']),
            ':tokens_output' => max(0, (int)$row['tokens_output']),
            ':tokens_total' => max(0, (int)$row['tokens_total']),
            ':cached' => !empty($row['cached']) ? 1 : 0,
            ':status' => $this->validStatus((string)$row['status']),
            ':error' => mb_substr((string)$row['error'], 0, 1000),
            ':created_at' => (string)$row['created_at'],
        ]);
        return $stmt->rowCount() > 0;
    }

    public function summary(): array {
        $this->ensureTable();
        $sql = "SELECT
            COUNT(*) total,
            SUM(status='new') new_count,
            SUM(status='failed') failed,
            SUM(cached=1) cached,
            SUM(created_at >= CURDATE()) today,
            COALESCE(SUM(tokens_total), 0) tokens
            FROM `" . self::TABLE . "`";
        return (array)$this->wire('database')->query($sql)->fetch(\PDO::FETCH_ASSOC);
    }

    public function recent(int $limit = 100, string $status = ''): array {
        $this->ensureTable();
        $limit = max(1, min(500, $limit));
        $params = [];
        $where = '';
        if($status !== '' && in_array($status, self::statuses(), true)) {
            $where = ' WHERE status=:status';
            $params[':status'] = $status;
        }
        $stmt = $this->wire('database')->prepare(
            "SELECT * FROM `" . self::TABLE . "`{$where} ORDER BY id DESC LIMIT {$limit}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function topDemand(int $limit = 20): array {
        $this->ensureTable();
        $limit = max(1, min(100, $limit));
        $sql = "SELECT original_query, COUNT(*) hits, MAX(created_at) last_seen
            FROM `" . self::TABLE . "`
            WHERE original_query <> '' AND status <> 'dismissed'
            GROUP BY original_query
            ORDER BY hits DESC, last_seen DESC
            LIMIT {$limit}";
        return $this->wire('database')->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatus(int $id, string $status): bool {
        if($id < 1 || !in_array($status, self::statuses(), true)) return false;
        $reviewed = in_array($status, ['reviewing', 'content_added', 'dismissed'], true)
            ? date('Y-m-d H:i:s')
            : null;
        $stmt = $this->wire('database')->prepare(
            "UPDATE `" . self::TABLE . "` SET status=:status, reviewed_at=:reviewed WHERE id=:id"
        );
        return $stmt->execute([':status' => $status, ':reviewed' => $reviewed, ':id' => $id]);
    }

    public static function statuses(): array {
        return ['new', 'reviewing', 'content_added', 'dismissed', 'failed'];
    }

    protected function validStatus(string $status): string {
        return in_array($status, self::statuses(), true) ? $status : 'new';
    }
}
