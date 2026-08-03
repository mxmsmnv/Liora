<?php namespace ProcessWire;

trait LioraConversationTrait {

    public function importLegacyHistory(): int {
        $field = $this->wire('fields')->get('ai');
        $page = $this->wire('pages')->findOne('template=agent, include=all');
        if(!$field || !$field->id || !$page || !$page->id || !$page->hasField('ai')) return 0;

        $rows = $page->getUnformatted('ai');
        if(!$rows instanceof WireArray && !is_iterable($rows)) return 0;
        $count = 0;
        foreach($rows as $row) {
            $original = trim((string)$row->get('original'));
            $question = trim((string)$row->get('query'));
            $response = trim((string)$row->get('response'));
            if($question === '') continue;
            $publicId = substr(hash('sha256', 'legacy-field|' . $original . '|' . $question . '|' . $response), 0, 32);
            $thread = $this->store()->threadByPublicId($publicId);
            if($thread) continue;
            $thread = $this->store()->createThread([
                'public_id' => $publicId,
                'title' => $this->conversationTitle($question),
                'original_query' => $original,
                'context' => 'legacy-field-ai',
                'source_url' => '/agent/',
                'page_id' => (int)$page->id,
            ]);
            $this->store()->addMessage((int)$thread['id'], 'user', $question, [
                'source_url' => '/agent/',
                'page_id' => (int)$page->id,
            ]);
            if($response !== '') {
                $this->store()->addMessage((int)$thread['id'], 'assistant', $response, [
                    'source_url' => '/agent/',
                    'page_id' => (int)$page->id,
                ]);
            }
            $count++;
        }
        return $count;
    }

    /**
     * Build a useful label without another provider request.
     *
     * The first visitor question identifies a conversation better than the
     * source-page title shared by every thread launched from that page.
     */
    protected function conversationTitle(string $question, int $maxLength = 72): string {
        $title = trim((string)preg_replace('/\s+/u', ' ', strip_tags($question)));
        if($title === '') return $this->_('Conversation');
        $maxLength = max(24, min(120, $maxLength));
        if(mb_strlen($title) <= $maxLength) return $title;
        $short = rtrim(mb_substr($title, 0, $maxLength - 1));
        $lastSpace = mb_strrpos($short, ' ');
        if($lastSpace !== false && $lastSpace >= (int)floor($maxLength * 0.6)) {
            $short = rtrim(mb_substr($short, 0, $lastSpace));
        }
        return rtrim($short, " \t\n\r\0\x0B.,;:!?—–-") . '…';
    }

    /** Replace legacy page-name labels with titles based on first questions. */
    protected function refreshPageBasedThreadTitles(): int {
        $updated = 0;
        foreach($this->store()->pageBasedThreadTitles() as $thread) {
            if($this->store()->updateThreadTitle(
                (int)$thread['id'],
                $this->conversationTitle((string)$thread['question'])
            )) {
                $updated++;
            }
        }
        return $updated;
    }

    protected function sessionHash(): string {
        $session = $this->wire('session');
        $id = (string)$session->get('liora_tracking_id');
        if($id === '') {
            $id = bin2hex(random_bytes(24));
            $session->set('liora_tracking_id', $id);
        }
        return hash('sha256', $id);
    }

    protected function withinRateLimit(): bool {
        $session = $this->wire('session');
        $now = time();
        $timestamps = (array)$session->get('liora_request_times');
        $timestamps = array_values(array_filter($timestamps, static fn($time) => (int)$time > $now - 3600));
        if(count($timestamps) >= (int)$this->setting('requestsPerHour', 20)) return false;
        $timestamps[] = $now;
        $session->set('liora_request_times', $timestamps);
        return true;
    }

    /**
     * Diagnostic snapshot returned by chat()/streamChat() and persisted by the
     * endpoint. It deliberately excludes prompts, credentials and identifiers.
     */
    protected function responseTechnicalMetadata(
        array $request,
        array $result,
        string $provider,
        string $model,
        bool $streaming,
        int $providerResponseMs
    ): array {
        $systemPrompt = (string)($request['systemPrompt'] ?? '');
        $usage = [];
        foreach((array)($result['usage'] ?? []) as $key => $value) {
            if(is_numeric($value)) $usage[(string)$key] = (int)$value;
        }
        $metadata = [
            'schema_version' => 1,
            'liora_version' => (int)self::getModuleInfo()['version'],
            'request' => [
                'provider' => $provider,
                'model' => $model,
                'streaming' => $streaming,
                'max_tokens' => (int)($request['maxTokens'] ?? 0),
                'temperature' => (float)($request['temperature'] ?? 0),
                'timeout_seconds' => (int)($request['timeout'] ?? 0),
                'cache' => $streaming ? false : ($request['cache'] ?? false),
                'web_search' => !empty($request['webSearch']),
                'web_search_mode' => (string)$this->setting('webSearchMode', 'auto'),
                'web_search_max_results' => (int)($request['webSearchMaxResults'] ?? 0),
                'history_messages' => count((array)($request['history'] ?? [])),
                'page_id' => (int)($request['pageId'] ?? 0),
                'system_prompt_chars' => mb_strlen($systemPrompt),
                'system_prompt_sha256' => $systemPrompt !== '' ? hash('sha256', $systemPrompt) : '',
            ],
            'response' => [
                'provider' => (string)($result['provider'] ?? $provider),
                'model' => (string)($result['model'] ?? $model),
                'cached' => !empty($result['cached']),
                'usage' => $usage,
                'source_count' => count((array)($result['sources'] ?? [])),
                'provider_metadata' => $this->providerResponseMetadata($result),
            ],
            'timing' => [
                'provider_response_ms' => max(0, $providerResponseMs),
            ],
        ];
        return $metadata;
    }

    /** Extract useful provider diagnostics without response content or raw bodies. */
    protected function providerResponseMetadata(array $result): array {
        $raw = is_array($result['raw'] ?? null) ? (array)$result['raw'] : [];
        $metadata = [];
        foreach(['id', 'object', 'type', 'created', 'service_tier', 'system_fingerprint', 'stop_reason', 'stop_sequence'] as $key) {
            $value = $raw[$key] ?? $result[$key] ?? null;
            if(is_scalar($value) && (string)$value !== '') $metadata[$key] = $value;
        }
        $finishReason = $result['finish_reason']
            ?? $raw['finish_reason']
            ?? $raw['choices'][0]['finish_reason']
            ?? null;
        if(is_scalar($finishReason) && (string)$finishReason !== '') {
            $metadata['finish_reason'] = $finishReason;
        }
        return $metadata;
    }

    protected function withEndpointTechnicalMetadata(
        array $data,
        array $technicalContext,
        float $requestStartedAt,
        array $sources
    ): array {
        $totalResponseMs = $requestStartedAt > 0
            ? (int)round((microtime(true) - $requestStartedAt) * 1000)
            : max(0, (int)($data['response_time_ms'] ?? 0));
        $metadata = (array)($data['metadata'] ?? []);
        $metadata['context'] = (array)($technicalContext['context'] ?? []);
        $metadata['retrieval'] = (array)($technicalContext['retrieval'] ?? []);
        $metadata['timing'] = array_merge(
            (array)($metadata['timing'] ?? []),
            ['total_response_ms' => $totalResponseMs]
        );
        $metadata['response'] = array_merge(
            (array)($metadata['response'] ?? []),
            [
                'source_count' => count($sources),
                'sources' => array_values(array_map(static fn(array $source): array => [
                    'title' => mb_substr((string)($source['title'] ?? ''), 0, 180),
                    'url' => mb_substr((string)($source['url'] ?? ''), 0, 2048),
                ], $sources)),
            ]
        );
        $data['response_time_ms'] = $totalResponseMs;
        $data['metadata'] = $metadata;
        return $data;
    }

    protected function storeAssistantMessage(int $threadId, string $answer, array $data, array $pageContext): void {
        $usage = (array)($data['usage'] ?? []);
        $this->store()->addMessage($threadId, 'assistant', $answer, [
            'provider' => (string)($data['provider'] ?? $this->getProvider()),
            'model' => (string)($data['model'] ?? $this->getModel()),
            'source_url' => (string)$pageContext['source_url'],
            'page_id' => (int)$pageContext['page_id'],
            'tokens_input' => (int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0),
            'tokens_output' => (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0),
            'tokens_total' => (int)($usage['total_tokens'] ?? 0),
            'cached' => !empty($data['cached']),
            'response_time_ms' => max(0, (int)($data['response_time_ms'] ?? 0)),
            'metadata' => (array)($data['metadata'] ?? []),
        ]);
    }

    protected function importClientHistory(int $threadId, array $history, array $pageContext): void {
        $san = $this->wire('sanitizer');
        $limit = max(2, min(40, (int)$this->setting('historyMessages', 10)));
        foreach(array_slice($history, -$limit) as $message) {
            if(!is_array($message)) continue;
            $role = ($message['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim($san->textarea((string)($message['content'] ?? ''), ['maxLength' => 5000]));
            if($content === '') continue;
            $this->store()->addMessage($threadId, $role, $content, [
                'source_url' => (string)$pageContext['source_url'],
                'page_id' => (int)$pageContext['page_id'],
            ]);
        }
    }

    protected function resolvePageContext(
        string $clientSource,
        string $httpReferer,
        string $browserReferrer = ''
    ): array {
        $source = $this->sanitizeSourceUrl($clientSource)
            ?: $this->sanitizeSourceUrl($httpReferer);
        $pageId = 0;
        $pageTitle = '';
        if($source !== '') {
            $path = (string)(parse_url($source, PHP_URL_PATH) ?: '/');
            $page = $this->wire('pages')->get($path);
            if($page && $page->id && $page->template && $page->template->name !== 'admin') {
                $pageId = (int)$page->id;
                $pageTitle = (string)$page->title;
                $source = (string)$page->url;
            }
        }
        return [
            'source_url' => $source,
            'referrer_url' => $this->sanitizeReferrerUrl($browserReferrer),
            'page_id' => $pageId,
            'page_title' => $pageTitle,
        ];
    }

}

