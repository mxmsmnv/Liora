<?php namespace ProcessWire;

/** Secret-free MCP readiness and aggregate demand telemetry. */
trait LioraMcpProviderTrait {
    public function mcpProviderInfo(): array {
        return ['name' => 'liora', 'title' => 'Liora', 'version' => '1.15.2'];
    }

    public function mcpTools(): array {
        return [[
            'name' => 'liora_status',
            'title' => 'Liora readiness',
            'description' => 'Return assistant readiness and aggregate conversation demand without prompts, messages, visitor identifiers, provider credentials, or retrieved context.',
            'handler' => [$this, 'mcpLioraStatus'],
            'scope' => 'read', 'read_only' => true, 'destructive' => false,
            'idempotent' => true, 'open_world' => false,
            'input_schema' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => [], 'additionalProperties' => false],
        ]];
    }

    public function mcpLioraStatus(): array {
        $summary = [];
        try {
            $threads = $this->wire('database')->query(
                "SELECT COUNT(*) total, SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) new_count, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) failed, SUM(CASE WHEN updated_at >= CURDATE() THEN 1 ELSE 0 END) today FROM `" . LioraStore::THREADS . "`"
            )->fetch(\PDO::FETCH_ASSOC) ?: [];
            $messages = $this->wire('database')->query(
                "SELECT COUNT(*) messages, SUM(CASE WHEN role='user' THEN 1 ELSE 0 END) questions, COALESCE(SUM(tokens_total),0) tokens, COALESCE(SUM(cached),0) cache_hits, COALESCE(AVG(NULLIF(response_time_ms,0)),0) average_response_ms FROM `" . LioraStore::MESSAGES . "`"
            )->fetch(\PDO::FETCH_ASSOC) ?: [];
            $summary = array_merge($threads, $messages);
        } catch(\Throwable) {
            $summary = [];
        }
        return [
            'version' => '1.15.2', 'configured' => $this->isConfigured(),
            'provider' => $this->getProvider(), 'model' => $this->getModel(),
            'counts' => array_intersect_key($summary, array_flip(['total', 'new_count', 'failed', 'today', 'messages', 'questions', 'tokens', 'cache_hits', 'average_response_ms'])),
            'content_exposed' => false,
        ];
    }
}
