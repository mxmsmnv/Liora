<?php namespace ProcessWire;

trait LioraStoreThreadsTrait {

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

}
