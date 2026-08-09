<?php namespace ProcessWire;

trait LioraStoreSupportTrait {

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

    protected function ensureColumn(string $table, string $column, string $definition): void {
        $stmt = $this->wire('database')->prepare(
            "SHOW COLUMNS FROM `{$table}` LIKE :column"
        );
        $stmt->execute([':column' => $column]);
        if($stmt->fetchColumn()) return;
        $this->wire('database')->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }

    protected function hydrateMessage(array $row): array {
        $decoded = json_decode((string)($row['metadata'] ?? ''), true);
        $row['metadata'] = is_array($decoded) ? $decoded : [];
        $row['response_time_ms'] = max(0, (int)($row['response_time_ms'] ?? 0));
        return $row;
    }

    /**
     * Persist diagnostic context defensively without credentials or visitor
     * identifiers, even when addMessage() is called by another module.
     */
    protected function encodeMetadata(array $metadata): ?string {
        if(!$metadata) return null;
        $safe = $this->sanitizeMetadata($metadata);
        if(!$safe) return null;
        $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if(!is_string($json) || $json === '') return null;
        return $json;
    }

    protected function sanitizeMetadata(array $metadata, int $depth = 0): array {
        if($depth > 6) return [];
        $safe = [];
        foreach(array_slice($metadata, 0, 100, true) as $key => $value) {
            $key = mb_substr((string)$key, 0, 128);
            if($key === '' || preg_match(
                '/(^|_)(api_?key|authorization|cookie|secret|password|access_token|refresh_token|session_id|session_hash|ip|ip_address|user_agent|headers?|raw|body|content|message)($|_)/i',
                $key
            )) continue;
            if(str_contains(strtolower($key), 'prompt')
                && !in_array($key, ['system_prompt_chars', 'system_prompt_sha256'], true)) {
                continue;
            }
            if(is_array($value)) {
                $safe[$key] = $this->sanitizeMetadata($value, $depth + 1);
            } elseif(is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$key] = $value;
            } elseif(is_scalar($value)) {
                $safe[$key] = mb_substr((string)$value, 0, 2048);
            }
        }
        return $safe;
    }

    protected function validPublicId(string $id): string {
        $id = strtolower(trim($id));
        return preg_match('/^[a-z0-9-]{24,64}$/', $id) ? $id : '';
    }

    protected function validStatus(string $status): string {
        return in_array($status, self::statuses(), true) ? $status : 'new';
    }
}
