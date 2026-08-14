<?php namespace ProcessWire;

/** Persistent, user-bound write proposals for LioraGit. */
class LioraGitStore extends Wire {

    public const TABLE = 'liora_git_proposals';

    public function ensureTable(): void {
        $table = $this->wire('database')->escapeTable(self::TABLE);
        $this->wire('database')->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `public_id` CHAR(36) NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `operation` VARCHAR(16) NOT NULL,
            `repository` VARCHAR(255) NOT NULL,
            `branch` VARCHAR(190) NOT NULL,
            `path` VARCHAR(512) NOT NULL,
            `base_commit` CHAR(40) NOT NULL,
            `blob_sha` CHAR(40) NOT NULL DEFAULT '',
            `title` VARCHAR(255) NOT NULL,
            `content` MEDIUMTEXT NOT NULL,
            `content_sha256` CHAR(64) NOT NULL,
            `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
            `commit_sha` CHAR(40) NOT NULL DEFAULT '',
            `created` DATETIME NOT NULL,
            `expires` DATETIME NOT NULL,
            `confirmed` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `public_id` (`public_id`),
            KEY `owner_status` (`user_id`, `status`),
            KEY `expires` (`expires`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function create(array $data): array {
        $this->ensureTable();
        $publicId = $this->uuid();
        $content = (string)($data['content'] ?? '');
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + 3600);
        $stmt = $this->wire('database')->prepare("INSERT INTO `" . self::TABLE . "`
            (`public_id`,`user_id`,`operation`,`repository`,`branch`,`path`,`base_commit`,`blob_sha`,`title`,`content`,`content_sha256`,`status`,`created`,`expires`)
            VALUES (:public_id,:user_id,:operation,:repository,:branch,:path,:base_commit,:blob_sha,:title,:content,:content_sha256,'pending',:created,:expires)");
        $stmt->execute([
            ':public_id' => $publicId,
            ':user_id' => (int)($data['user_id'] ?? 0),
            ':operation' => (string)($data['operation'] ?? 'create'),
            ':repository' => (string)($data['repository'] ?? ''),
            ':branch' => (string)($data['branch'] ?? ''),
            ':path' => (string)($data['path'] ?? ''),
            ':base_commit' => (string)($data['base_commit'] ?? ''),
            ':blob_sha' => (string)($data['blob_sha'] ?? ''),
            ':title' => (string)($data['title'] ?? ''),
            ':content' => $content,
            ':content_sha256' => hash('sha256', $content),
            ':created' => $now,
            ':expires' => $expires,
        ]);
        return $this->findOwned($publicId, (int)$data['user_id']) ?: [];
    }

    public function findOwned(string $publicId, int $userId, bool $pendingOnly = false): ?array {
        $this->ensureTable();
        $sql = "SELECT * FROM `" . self::TABLE . "` WHERE `public_id`=:public_id AND `user_id`=:user_id";
        if($pendingOnly) $sql .= " AND `status`='pending' AND `expires`>=UTC_TIMESTAMP()";
        $stmt = $this->wire('database')->prepare($sql . ' LIMIT 1');
        $stmt->execute([':public_id' => $publicId, ':user_id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function finish(int $id, string $status, string $commitSha = ''): bool {
        if(!in_array($status, ['committed', 'cancelled', 'conflict', 'failed'], true)) return false;
        $stmt = $this->wire('database')->prepare("UPDATE `" . self::TABLE . "` SET `status`=:status, `commit_sha`=:commit_sha, `confirmed`=UTC_TIMESTAMP() WHERE `id`=:id AND `status`='pending'");
        $stmt->execute([':status' => $status, ':commit_sha' => $commitSha, ':id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function cleanup(): int {
        $this->ensureTable();
        return (int)$this->wire('database')->exec("DELETE FROM `" . self::TABLE . "` WHERE (`status`!='pending' AND `created`<UTC_TIMESTAMP()-INTERVAL 30 DAY) OR (`status`='pending' AND `expires`<UTC_TIMESTAMP()-INTERVAL 1 DAY)");
    }

    protected function uuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
