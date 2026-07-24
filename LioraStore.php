<?php namespace ProcessWire;

/**
 * Thread/message persistence for Liora.
 *
 * Session identifiers are one-way hashes; IP addresses and user agents are
 * intentionally not stored.
 */
class LioraStore extends Wire {

    public const THREADS = 'liora_threads';
    public const MESSAGES = 'liora_messages';
    public const LEGACY = 'liora_queries';

    protected bool $tablesEnsured = false;

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
        $this->tablesEnsured = true;
    }

    public function dropTable(): void {
        $db = $this->wire('database');
        $db->exec("DROP TABLE IF EXISTS `" . self::MESSAGES . "`");
        $db->exec("DROP TABLE IF EXISTS `" . self::THREADS . "`");
        $db->exec("DROP TABLE IF EXISTS `" . self::LEGACY . "`");
        $this->tablesEnsured = false;
    }

    public function createThread(array $data): array {
        $this->ensureTable();
        $now = (string)($data['created_at'] ?? date('Y-m-d H:i:s'));
        $publicId = $this->validPublicId((string)($data['public_id'] ?? ''))
            ?: bin2hex(random_bytes(16));
        if($this->threadByPublicId($publicId)) $publicId = bin2hex(random_bytes(16));
        $row = array_merge([
            'title' => '',
            'original_query' => '',
            'context' => '',
            'source_url' => '',
            'referrer_url' => '',
            'page_id' => 0,
            'page_title' => '',
            'user_id' => 0,
            'session_hash' => '',
            'country_code' => '',
            'country' => '',
            'region' => '',
            'city' => '',
            'status' => 'new',
        ], $data);

        $stmt = $this->wire('database')->prepare(
            "INSERT INTO `" . self::THREADS . "`
            (`public_id`,`title`,`original_query`,`context`,`source_url`,`referrer_url`,
             `page_id`,`page_title`,`user_id`,`session_hash`,`country_code`,`country`,
             `region`,`city`,`status`,`created_at`,`updated_at`)
            VALUES
            (:public_id,:title,:original_query,:context,:source_url,:referrer_url,
             :page_id,:page_title,:user_id,:session_hash,:country_code,:country,
             :region,:city,:status,:created_at,:updated_at)"
        );
        $stmt->execute([
            ':public_id' => $publicId,
            ':title' => mb_substr((string)$row['title'], 0, 255),
            ':original_query' => mb_substr((string)$row['original_query'], 0, 500),
            ':context' => mb_substr((string)$row['context'], 0, 255),
            ':source_url' => mb_substr((string)$row['source_url'], 0, 2048),
            ':referrer_url' => mb_substr((string)$row['referrer_url'], 0, 2048),
            ':page_id' => max(0, (int)$row['page_id']),
            ':page_title' => mb_substr((string)$row['page_title'], 0, 255),
            ':user_id' => max(0, (int)$row['user_id']),
            ':session_hash' => mb_substr((string)$row['session_hash'], 0, 64),
            ':country_code' => mb_substr(strtoupper((string)$row['country_code']), 0, 2),
            ':country' => mb_substr((string)$row['country'], 0, 128),
            ':region' => mb_substr((string)$row['region'], 0, 128),
            ':city' => mb_substr((string)$row['city'], 0, 128),
            ':status' => $this->validStatus((string)$row['status']),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        return $this->threadById((int)$this->wire('database')->lastInsertId());
    }

    public function findOwnedThread(string $publicId, string $sessionHash, int $userId = 0): array {
        $this->ensureTable();
        $publicId = $this->validPublicId($publicId);
        if($publicId === '') return [];
        $sql = "SELECT * FROM `" . self::THREADS . "` WHERE public_id=:public_id";
        $params = [':public_id' => $publicId];
        if($userId > 0) {
            $sql .= ' AND (user_id=:user_id OR session_hash=:session_hash)';
            $params[':user_id'] = $userId;
            $params[':session_hash'] = $sessionHash;
        } else {
            $sql .= ' AND session_hash=:session_hash';
            $params[':session_hash'] = $sessionHash;
        }
        $stmt = $this->wire('database')->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (array)($stmt->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    public function threadById(int $id): array {
        if($id < 1) return [];
        $stmt = $this->wire('database')->prepare(
            "SELECT * FROM `" . self::THREADS . "` WHERE id=:id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return (array)($stmt->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    /** Threads whose legacy title was copied from the shared source page. */
    public function pageBasedThreadTitles(int $limit = 10000): array {
        $this->ensureTable();
        $limit = max(1, min(10000, $limit));
        $stmt = $this->wire('database')->query(
            "SELECT t.id, first_message.content AS question
             FROM `" . self::THREADS . "` t
             JOIN (
                SELECT thread_id, MIN(id) AS first_message_id
                FROM `" . self::MESSAGES . "`
                WHERE role='user'
                GROUP BY thread_id
             ) first_user ON first_user.thread_id=t.id
             JOIN `" . self::MESSAGES . "` first_message
                ON first_message.id=first_user.first_message_id
             WHERE (t.page_title<>'' AND t.title=t.page_title)
                OR LOWER(TRIM(t.title)) IN ('ask liora ai', 'ask liora')
             ORDER BY t.id
             LIMIT {$limit}"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function updateThreadTitle(int $threadId, string $title): bool {
        $title = trim($title);
        if($threadId < 1 || $title === '') return false;
        $stmt = $this->wire('database')->prepare(
            "UPDATE `" . self::THREADS . "` SET title=:title WHERE id=:id"
        );
        return $stmt->execute([
            ':title' => mb_substr($title, 0, 255),
            ':id' => $threadId,
        ]);
    }

    public function addMessage(int $threadId, string $role, string $content, array $meta = []): int {
        $this->ensureTable();
        if($threadId < 1 || trim($content) === '') return 0;
        $role = in_array($role, ['user', 'assistant', 'error'], true) ? $role : 'user';
        $created = (string)($meta['created_at'] ?? date('Y-m-d H:i:s'));
        $legacyId = isset($meta['legacy_query_id']) ? (int)$meta['legacy_query_id'] : null;
        $stmt = $this->wire('database')->prepare(
            "INSERT IGNORE INTO `" . self::MESSAGES . "`
            (`thread_id`,`legacy_query_id`,`role`,`content`,`provider`,`model`,`source_url`,
             `page_id`,`tokens_input`,`tokens_output`,`tokens_total`,`cached`,`error`,`created_at`)
            VALUES
            (:thread_id,:legacy_query_id,:role,:content,:provider,:model,:source_url,
             :page_id,:tokens_input,:tokens_output,:tokens_total,:cached,:error,:created_at)"
        );
        $stmt->execute([
            ':thread_id' => $threadId,
            ':legacy_query_id' => $legacyId ?: null,
            ':role' => $role,
            ':content' => $content,
            ':provider' => mb_substr((string)($meta['provider'] ?? ''), 0, 64),
            ':model' => mb_substr((string)($meta['model'] ?? ''), 0, 255),
            ':source_url' => mb_substr((string)($meta['source_url'] ?? ''), 0, 2048),
            ':page_id' => max(0, (int)($meta['page_id'] ?? 0)),
            ':tokens_input' => max(0, (int)($meta['tokens_input'] ?? 0)),
            ':tokens_output' => max(0, (int)($meta['tokens_output'] ?? 0)),
            ':tokens_total' => max(0, (int)($meta['tokens_total'] ?? 0)),
            ':cached' => !empty($meta['cached']) ? 1 : 0,
            ':error' => mb_substr((string)($meta['error'] ?? ''), 0, 1000),
            ':created_at' => $created,
        ]);
        if($stmt->rowCount() < 1) return 0;
        $messageId = (int)$this->wire('database')->lastInsertId();
        $update = $this->wire('database')->prepare(
            "UPDATE `" . self::THREADS . "`
             SET message_count=message_count+1, updated_at=:updated_at
             WHERE id=:id"
        );
        $update->execute([':updated_at' => $created, ':id' => $threadId]);
        return $messageId;
    }

    public function threadMessages(int $threadId, int $limit = 40): array {
        $this->ensureTable();
        $limit = max(1, min(200, $limit));
        $stmt = $this->wire('database')->prepare(
            "SELECT * FROM (
                SELECT * FROM `" . self::MESSAGES . "`
                WHERE thread_id=:thread_id ORDER BY id DESC LIMIT {$limit}
             ) recent ORDER BY id ASC"
        );
        $stmt->execute([':thread_id' => $threadId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteMessage(int $messageId): bool {
        if($messageId < 1) return false;
        $this->ensureTable();
        $database = $this->wire('database');
        $database->beginTransaction();
        try {
            $find = $database->prepare(
                "SELECT thread_id FROM `" . self::MESSAGES . "` WHERE id=:id FOR UPDATE"
            );
            $find->execute([':id' => $messageId]);
            $threadId = (int)$find->fetchColumn();
            if($threadId < 1) {
                $database->rollBack();
                return false;
            }

            $delete = $database->prepare(
                "DELETE FROM `" . self::MESSAGES . "` WHERE id=:id AND thread_id=:thread_id"
            );
            $delete->execute([':id' => $messageId, ':thread_id' => $threadId]);
            if($delete->rowCount() !== 1) {
                $database->rollBack();
                return false;
            }

            $update = $database->prepare(
                "UPDATE `" . self::THREADS . "` t
                 SET t.message_count=(
                        SELECT COUNT(*) FROM `" . self::MESSAGES . "` m WHERE m.thread_id=t.id
                     ),
                     t.updated_at=COALESCE((
                        SELECT MAX(m.created_at) FROM `" . self::MESSAGES . "` m WHERE m.thread_id=t.id
                     ), t.created_at)
                 WHERE t.id=:thread_id"
            );
            $update->execute([':thread_id' => $threadId]);
            $database->commit();
            return true;
        } catch(\Throwable $error) {
            if($database->inTransaction()) $database->rollBack();
            throw $error;
        }
    }

    public function recentThreads(int $limit = 100, string $status = ''): array {
        $this->ensureTable();
        $limit = max(1, min(300, $limit));
        $params = [];
        $where = '';
        if($status !== '' && in_array($status, self::statuses(), true)) {
            $where = ' WHERE status=:status';
            $params[':status'] = $status;
        }
        $stmt = $this->wire('database')->prepare(
            "SELECT * FROM `" . self::THREADS . "`{$where} ORDER BY updated_at DESC, id DESC LIMIT {$limit}"
        );
        $stmt->execute($params);
        $threads = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if(!$threads) return [];

        $ids = array_map('intval', array_column($threads, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $messageStmt = $this->wire('database')->prepare(
            "SELECT * FROM `" . self::MESSAGES . "`
             WHERE thread_id IN ({$placeholders}) ORDER BY thread_id, id"
        );
        $messageStmt->execute($ids);
        $grouped = [];
        foreach($messageStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $message) {
            $grouped[(int)$message['thread_id']][] = $message;
        }
        foreach($threads as &$thread) {
            $thread['messages'] = $grouped[(int)$thread['id']] ?? [];
        }
        unset($thread);
        return $threads;
    }

    public function summary(): array {
        $this->ensureTable();
        $threads = $this->wire('database')->query(
            "SELECT COUNT(*) total,
                SUM(status='new') new_count,
                SUM(status='failed') failed,
                SUM(updated_at >= CURDATE()) today
             FROM `" . self::THREADS . "`"
        )->fetch(\PDO::FETCH_ASSOC) ?: [];
        $messages = $this->wire('database')->query(
            "SELECT COUNT(*) messages,
                SUM(role='user') questions,
                COALESCE(SUM(tokens_total),0) tokens
             FROM `" . self::MESSAGES . "`"
        )->fetch(\PDO::FETCH_ASSOC) ?: [];
        return array_merge($threads, $messages);
    }

    public function topDemand(int $limit = 20): array {
        $this->ensureTable();
        $limit = max(1, min(100, $limit));
        $sql = "SELECT original_query, COUNT(*) threads, SUM(message_count) messages,
                MAX(updated_at) last_seen
            FROM `" . self::THREADS . "`
            WHERE original_query <> '' AND status <> 'dismissed'
            GROUP BY original_query
            ORDER BY threads DESC, messages DESC, last_seen DESC
            LIMIT {$limit}";
        return $this->wire('database')->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatus(int $id, string $status): bool {
        if($id < 1 || !in_array($status, self::statuses(), true)) return false;
        $reviewed = in_array($status, ['reviewing', 'content_added', 'dismissed'], true)
            ? date('Y-m-d H:i:s')
            : null;
        $stmt = $this->wire('database')->prepare(
            "UPDATE `" . self::THREADS . "` SET status=:status, reviewed_at=:reviewed WHERE id=:id"
        );
        return $stmt->execute([':status' => $status, ':reviewed' => $reviewed, ':id' => $id]);
    }

    public function migrateLegacyQueries(): int {
        $this->ensureTable();
        if(!$this->tableExists(self::LEGACY)) return 0;
        $rows = $this->wire('database')->query(
            "SELECT * FROM `" . self::LEGACY . "` ORDER BY id"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $count = 0;
        foreach($rows as $row) {
            $sessionHash = trim((string)($row['session_hash'] ?? ''));
            $group = $sessionHash === ''
                ? 'legacy-row-' . (int)$row['id']
                : $sessionHash . '|' . (string)($row['source_url'] ?? '') . '|' . substr((string)$row['created_at'], 0, 10);
            $publicId = substr(hash('sha256', 'legacy|' . $group), 0, 32);
            $thread = $this->threadByPublicId($publicId);
            if(!$thread) {
                $thread = $this->createThread([
                    'public_id' => $publicId,
                    'title' => (string)($row['original_query'] ?: $row['question']),
                    'original_query' => (string)($row['original_query'] ?? ''),
                    'context' => (string)($row['context'] ?? 'legacy'),
                    'source_url' => (string)($row['source_url'] ?? ''),
                    'page_id' => (int)($row['page_id'] ?? 0),
                    'user_id' => (int)($row['user_id'] ?? 0),
                    'session_hash' => (string)($row['session_hash'] ?? ''),
                    'status' => (string)($row['status'] ?? 'new'),
                    'created_at' => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
                ]);
            }
            $userMessageId = $this->addMessage((int)$thread['id'], 'user', (string)$row['question'], [
                'legacy_query_id' => (int)$row['id'],
                'source_url' => (string)($row['source_url'] ?? ''),
                'page_id' => (int)($row['page_id'] ?? 0),
                'created_at' => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
            ]);
            if(!$userMessageId) continue;
            $count++;
            $response = trim((string)($row['response'] ?? ''));
            if($response !== '') {
                $this->addMessage((int)$thread['id'], 'assistant', $response, [
                    'provider' => (string)($row['provider'] ?? ''),
                    'model' => (string)($row['model'] ?? ''),
                    'source_url' => (string)($row['source_url'] ?? ''),
                    'page_id' => (int)($row['page_id'] ?? 0),
                    'tokens_input' => (int)($row['tokens_input'] ?? 0),
                    'tokens_output' => (int)($row['tokens_output'] ?? 0),
                    'tokens_total' => (int)($row['tokens_total'] ?? 0),
                    'cached' => (int)($row['cached'] ?? 0),
                    'error' => (string)($row['error'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? date('Y-m-d H:i:s')),
                ]);
            }
        }
        return $count;
    }

    public static function statuses(): array {
        return ['new', 'reviewing', 'content_added', 'dismissed', 'failed'];
    }

    public function threadByPublicId(string $publicId): array {
        $stmt = $this->wire('database')->prepare(
            "SELECT * FROM `" . self::THREADS . "` WHERE public_id=:public_id LIMIT 1"
        );
        $stmt->execute([':public_id' => $publicId]);
        return (array)($stmt->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    protected function tableExists(string $table): bool {
        $stmt = $this->wire('database')->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    protected function validPublicId(string $id): string {
        $id = strtolower(trim($id));
        return preg_match('/^[a-z0-9-]{24,64}$/', $id) ? $id : '';
    }

    protected function validStatus(string $status): string {
        return in_array($status, self::statuses(), true) ? $status : 'new';
    }
}
