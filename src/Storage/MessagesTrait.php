<?php namespace ProcessWire;

trait LioraStoreMessagesTrait {

    public function addMessage(int $threadId, string $role, string $content, array $meta = []): int {
        $this->ensureTable();
        if($threadId < 1 || trim($content) === '') return 0;
        $role = in_array($role, ['user', 'assistant', 'error'], true) ? $role : 'user';
        $created = (string)($meta['created_at'] ?? date('Y-m-d H:i:s'));
        $legacyId = isset($meta['legacy_query_id']) ? (int)$meta['legacy_query_id'] : null;
        $stmt = $this->wire('database')->prepare(
            "INSERT IGNORE INTO `" . self::MESSAGES . "`
            (`thread_id`,`legacy_query_id`,`role`,`content`,`provider`,`model`,`source_url`,
             `page_id`,`tokens_input`,`tokens_output`,`tokens_total`,`cached`,`response_time_ms`,
             `metadata`,`error`,`created_at`)
            VALUES
            (:thread_id,:legacy_query_id,:role,:content,:provider,:model,:source_url,
             :page_id,:tokens_input,:tokens_output,:tokens_total,:cached,:response_time_ms,
             :metadata,:error,:created_at)"
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
            ':response_time_ms' => max(0, (int)($meta['response_time_ms'] ?? 0)),
            ':metadata' => $this->encodeMetadata((array)($meta['metadata'] ?? [])),
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
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach($rows as &$row) $row = $this->hydrateMessage($row);
        unset($row);
        return $rows;
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

    public function deleteThread(int $threadId): bool {
        if($threadId < 1) return false;
        $this->ensureTable();
        $stmt = $this->wire('database')->prepare(
            "DELETE FROM `" . self::THREADS . "` WHERE id=:id"
        );
        $stmt->execute([':id' => $threadId]);
        return $stmt->rowCount() === 1;
    }

}
