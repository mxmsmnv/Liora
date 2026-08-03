<?php namespace ProcessWire;

trait LioraEndpointTrait {

    /**
     * Serve the JSON endpoint used by the Liora widget.
     */
    public function handleEndpoint(): void {
        header('Content-Type: application/json; charset=utf-8');

        if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->sendJson(['success' => false, 'error' => 'Method not allowed'], 405);
        }

        $input = json_decode((string)file_get_contents('php://input'), true);
        if(!is_array($input)) {
            $this->sendJson(['success' => false, 'error' => 'Invalid request'], 400);
        }

        if((bool)$this->wire('config')->protectCSRF && !$this->wire('session')->CSRF->hasValidToken()) {
            $this->sendJson(['success' => false, 'error' => 'The form session expired. Reload the page.'], 403);
        }

        $san = $this->wire('sanitizer');
        $action = trim($san->name((string)($input['action'] ?? '')));
        if($action === 'rename') {
            $title = trim($san->text((string)($input['title'] ?? ''), ['maxLength' => 72]));
            if($title === '') $this->sendJson(['success' => false, 'error' => 'Enter a conversation title.'], 400);
            $thread = $this->store()->findOwnedThread(
                (string)($input['threadId'] ?? ''),
                $this->sessionHash(),
                (int)($this->wire('user')->id ?? 0)
            );
            if(!$thread) $this->sendJson(['success' => false, 'error' => 'Conversation not found.'], 404);
            if(!$this->store()->updateThreadTitle((int)$thread['id'], $title)) {
                $this->sendJson(['success' => false, 'error' => 'The title could not be saved.'], 500);
            }
            $this->sendJson(['success' => true, 'thread_id' => $thread['public_id'], 'thread_title' => $title]);
        }

        if(!$this->withinRateLimit()) {
            $this->sendJson(['success' => false, 'error' => 'Too many questions. Please try again later.'], 429);
        }

        $question = trim($san->textarea((string)($input['message'] ?? ''), [
            'maxLength' => (int)$this->setting('maxQuestionLength', 1000),
        ]));
        $originalQuery = trim($san->text((string)($input['originalQuery'] ?? ''), ['maxLength' => 500]));
        $context = trim($san->text((string)($input['context'] ?? 'site'), ['maxLength' => 255]));
        if($question === '') $this->sendJson(['success' => false, 'error' => 'Please enter a question.'], 400);
        $requestStartedAt = microtime(true);

        $pageContext = $this->resolvePageContext(
            (string)($input['sourceUrl'] ?? ''),
            (string)($_SERVER['HTTP_REFERER'] ?? ''),
            (string)($input['referrerUrl'] ?? '')
        );
        $sessionHash = $this->sessionHash();
        $userId = (int)($this->wire('user')->id ?? 0);
        $requestedThreadId = (string)($input['threadId'] ?? '');
        $thread = $this->store()->findOwnedThread($requestedThreadId, $sessionHash, $userId);
        $newThread = !$thread;

        if($newThread) {
            $geo = $this->geoData();
            $thread = $this->store()->createThread([
                'public_id' => $requestedThreadId,
                'title' => $this->conversationTitle($question),
                'original_query' => $originalQuery,
                'context' => $context,
                'source_url' => $pageContext['source_url'],
                'referrer_url' => $pageContext['referrer_url'],
                'page_id' => $pageContext['page_id'],
                'page_title' => $pageContext['page_title'],
                'user_id' => $userId,
                'session_hash' => $sessionHash,
                'country_code' => $geo['country_code'],
                'country' => $geo['country'],
                'region' => $geo['region'],
                'city' => $geo['city'],
            ]);
            $this->importClientHistory((int)$thread['id'], (array)($input['history'] ?? []), $pageContext);
        }

        $historyRows = $this->store()->threadMessages(
            (int)$thread['id'],
            max(2, (int)$this->setting('historyMessages', 10))
        );
        $history = [];
        foreach($historyRows as $message) {
            if(!in_array($message['role'], ['user', 'assistant'], true)) continue;
            $history[] = ['role' => $message['role'], 'content' => (string)$message['content']];
        }
        $this->store()->addMessage((int)$thread['id'], 'user', $question, [
            'source_url' => $pageContext['source_url'],
            'page_id' => $pageContext['page_id'],
        ]);

        $systemPrompt = trim((string)$this->setting('systemPrompt', $this->defaultSystemPrompt()));
        if($originalQuery !== '') {
            $systemPrompt .= "\n\nThe visitor originally searched for: \"{$originalQuery}\". The site did not give them a sufficient answer.";
        }
        if($pageContext['source_url'] !== '') {
            $systemPrompt .= "\nThe visitor is asking from this site path: {$pageContext['source_url']}.";
        }
        if($context === 'search') {
            $systemPrompt .= "\n\nThis response is rendered as a native search overview. "
                . 'Begin directly with the useful answer. Do not introduce yourself, mention Liora, '
                . 'describe the interface, greet the visitor, or repeat their query as a heading. '
                . 'Prefer concise, scannable paragraphs and lists while preserving source attribution.';
        }
        if($history) {
            $systemPrompt .= "\n\n" . $this->conversationContinuityPrompt();
        }

        if(!$this->isConfigured()) {
            $error = 'AI service is not configured';
            $this->store()->addMessage((int)$thread['id'], 'error', $error, ['error' => $error]);
            $this->store()->updateStatus((int)$thread['id'], 'failed');
            $this->sendJson(['success' => false, 'error' => $error, 'thread_id' => $thread['public_id']], 503);
        }

        $retrievalQuestion = $this->retrievalQuestion($question, $history);
        $atlasStartedAt = microtime(true);
        $rag = $this->atlasContext($retrievalQuestion);
        $atlasResponseMs = (int)round((microtime(true) - $atlasStartedAt) * 1000);
        if($rag['context'] !== '') {
            $systemPrompt .= "\n\n" . $rag['context'];
        }
        $voxStartedAt = microtime(true);
        $vox = $this->voxContext(array_merge(
            [$pageContext['page_id']],
            (array)($rag['page_ids'] ?? [])
        ));
        $voxResponseMs = (int)round((microtime(true) - $voxStartedAt) * 1000);
        if($vox['context'] !== '') {
            $systemPrompt .= "\n\n" . $vox['context'];
        }
        $answerSources = array_merge($rag['sources'], $vox['sources']);
        $technicalContext = [
            'context' => [
                'page_id' => (int)$pageContext['page_id'],
                'page_context' => $context,
                'history_messages' => count($history),
                'new_thread' => $newThread,
                'external_links_restricted' => (bool)$this->setting('restrictExternalLinks', true),
            ],
            'retrieval' => [
                'atlas' => [
                    'enabled' => (bool)$this->setting('atlasEnabled', false),
                    'used' => $rag['context'] !== '',
                    'mode' => (string)($rag['mode'] ?? $this->atlasRetrievalMode()),
                    'strategy' => (string)($rag['strategy'] ?? 'disabled'),
                    'collection' => (string)$this->setting('atlasCollection', 'site'),
                    'top_k' => (int)$this->setting('atlasTopK', 4),
                    'minimum_score' => (float)$this->setting('atlasMinScore', 0.2),
                    'lexical_minimum_score' => (float)$this->setting('atlasLexicalMinScore', 0.24),
                    'maximum_context_chars' => (int)$this->setting('atlasMaxContextChars', 6000),
                    'response_ms' => $atlasResponseMs,
                    'lexical_ms' => (int)($rag['lexical_ms'] ?? 0),
                    'semantic_ms' => (int)($rag['semantic_ms'] ?? 0),
                    'semantic_attempted' => (bool)($rag['semantic_attempted'] ?? false),
                    'source_count' => count((array)$rag['sources']),
                    'page_count' => count((array)($rag['page_ids'] ?? [])),
                ],
                'vox' => [
                    'enabled' => (bool)$this->setting('voxEnabled', true),
                    'used' => $vox['context'] !== '',
                    'maximum_entries' => (int)$this->setting('voxMaxEntries', 8),
                    'maximum_context_chars' => (int)$this->setting('voxMaxContextChars', 5000),
                    'response_ms' => $voxResponseMs,
                    'source_count' => count((array)$vox['sources']),
                ],
            ],
        ];

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach($history as $message) $messages[] = $message;
        $messages[] = ['role' => 'user', 'content' => $question];

        $stream = !empty($input['stream']) && (bool)$this->setting('streamingEnabled', true);
        if($stream) {
            $this->handleStreamResponse(
                $thread,
                $messages,
                $pageContext,
                $answerSources,
                $technicalContext,
                $requestStartedAt
            );
        }

        $result = $this->chat($messages, ['pageId' => $pageContext['page_id']]);
        if(empty($result['success'])) {
            $error = (string)($result['error'] ?? 'AI request failed');
            $this->store()->addMessage((int)$thread['id'], 'error', $error, ['error' => $error]);
            $this->store()->updateStatus((int)$thread['id'], 'failed');
            $this->sendJson(['success' => false, 'error' => $error, 'thread_id' => $thread['public_id']], 502);
        }

        $answer = (string)$result['content'];
        $data = (array)($result['data'] ?? []);
        $answerSources = $this->mergeSources($answerSources, (array)($data['sources'] ?? []));
        $data = $this->withEndpointTechnicalMetadata(
            $data,
            $technicalContext,
            $requestStartedAt,
            $answerSources
        );
        $this->storeAssistantMessage((int)$thread['id'], $answer, $data, $pageContext);

        $this->sendJson([
            'success' => true,
            'response' => $answer,
            'thread_id' => $thread['public_id'],
            'thread_title' => (string)$thread['title'],
            'model' => (string)($data['model'] ?? $this->getModel()),
            'tokens_used' => (int)($data['usage']['total_tokens'] ?? 0),
            'cached' => !empty($data['cached']),
            'format' => 'markdown',
            'rag_sources' => $answerSources,
        ]);
    }

    protected function handleStreamResponse(
        array $thread,
        array $messages,
        array $pageContext,
        array $ragSources = [],
        array $technicalContext = [],
        float $requestStartedAt = 0.0
    ): void {
        header_remove('Content-Type');
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');
        header('Content-Encoding: none');
        ignore_user_abort(true);
        while(ob_get_level() > 0) @ob_end_flush();

        $this->sendStreamEvent('thread', [
            'thread_id' => $thread['public_id'],
            'thread_title' => (string)$thread['title'],
        ]);
        $result = $this->streamChat($messages, function(string $delta): void {
            $this->sendStreamEvent('delta', ['content' => $delta]);
        }, ['pageId' => $pageContext['page_id']]);

        if(empty($result['success'])) {
            $error = (string)($result['error'] ?? 'AI request failed');
            $this->store()->addMessage((int)$thread['id'], 'error', $error, ['error' => $error]);
            $this->store()->updateStatus((int)$thread['id'], 'failed');
            $this->sendStreamEvent('error', ['error' => $error]);
            exit;
        }

        $answer = (string)$result['content'];
        $data = (array)($result['data'] ?? []);
        $ragSources = $this->mergeSources($ragSources, (array)($data['sources'] ?? []));
        $data = $this->withEndpointTechnicalMetadata(
            $data,
            $technicalContext,
            $requestStartedAt,
            $ragSources
        );
        $this->storeAssistantMessage((int)$thread['id'], $answer, $data, $pageContext);
        $this->sendStreamEvent('done', [
            'thread_id' => $thread['public_id'],
            'response' => $answer,
            'model' => (string)($data['model'] ?? $this->getModel()),
            'tokens_used' => (int)($data['usage']['total_tokens'] ?? 0),
            'rag_sources' => $ragSources,
        ]);
        exit;
    }

}

