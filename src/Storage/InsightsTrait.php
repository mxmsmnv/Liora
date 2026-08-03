<?php namespace ProcessWire;

trait LioraStoreInsightsTrait {

    public function countThreads(string $status = ''): int {
        $this->ensureTable();
        if($status !== '' && in_array($status, self::statuses(), true)) {
            $stmt = $this->wire('database')->prepare(
                "SELECT COUNT(*) FROM `" . self::THREADS . "` WHERE status=:status"
            );
            $stmt->execute([':status' => $status]);
            return (int)$stmt->fetchColumn();
        }
        return (int)$this->wire('database')->query(
            "SELECT COUNT(*) FROM `" . self::THREADS . "`"
        )->fetchColumn();
    }

    public function recentThreads(int $limit = 20, string $status = '', int $offset = 0): array {
        $this->ensureTable();
        $limit = max(1, min(300, $limit));
        $offset = max(0, $offset);
        $params = [];
        $where = '';
        if($status !== '' && in_array($status, self::statuses(), true)) {
            $where = ' WHERE status=:status';
            $params[':status'] = $status;
        }
        $stmt = $this->wire('database')->prepare(
            "SELECT * FROM `" . self::THREADS . "`{$where}
             ORDER BY updated_at DESC, id DESC LIMIT {$limit} OFFSET {$offset}"
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
            $message = $this->hydrateMessage($message);
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
                COALESCE(SUM(tokens_total),0) tokens,
                COALESCE(SUM(tokens_input),0) tokens_input,
                COALESCE(SUM(tokens_output),0) tokens_output,
                COALESCE(SUM(cached),0) cache_hits,
                COALESCE(AVG(NULLIF(response_time_ms,0)),0) average_response_ms
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

}

