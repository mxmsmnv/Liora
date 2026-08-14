<?php namespace ProcessWire;

trait LioraGitEndpointTrait {

    /** Render the normal Liora widget against the authenticated memory endpoint. */
    public function renderMemoryWidget(array $options = []): string {
        $this->requirePermission('liora-git-chat');
        $liora = $this->wire('modules')->get('Liora');
        if(!$liora || !$liora->isConfigured()) return '';
        return $liora->renderWidget(array_merge([
            'enabled' => true,
            'endpoint' => (string)$this->gitSetting('endpoint', '/liora-memory/'),
            'streaming' => false,
            'localHistory' => true,
            'context' => 'git-memory',
            'heading' => $this->_('Ask Liora'),
            'intro' => $this->_('Ask about our shared knowledge or use /remember to prepare a new note.'),
            'placeholder' => $this->_('Ask or remember something…'),
            'showSuggestedPrompts' => true,
            'suggestedPrompts' => [
                $this->_('What have we already decided?'),
                $this->_('What remains unresolved?'),
                $this->_('Find contradictions in our notes.'),
            ],
        ], $options));
    }

    /** Serve the private JSON endpoint used by renderMemoryWidget(). */
    public function handleMemoryEndpoint(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        try {
            $this->requirePermission('liora-git-chat');
            if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->memoryJson(['success' => false, 'error' => 'Method not allowed'], 405);
            if((bool)$this->wire('config')->protectCSRF && !$this->wire('session')->CSRF->hasValidToken()) {
                $this->memoryJson(['success' => false, 'error' => 'The form session expired. Reload the page.'], 403);
            }
            if(!$this->withinMemoryRateLimit()) $this->memoryJson(['success' => false, 'error' => 'Too many requests. Please try again later.'], 429);
            $input = json_decode((string)file_get_contents('php://input'), true);
            if(!is_array($input)) $this->memoryJson(['success' => false, 'error' => 'Invalid request'], 400);
            $threadId = $this->memoryThreadId((string)($input['threadId'] ?? ''));
            $action = $this->wire('sanitizer')->name((string)($input['action'] ?? ''));
            if($action === 'rename') {
                $title = trim($this->wire('sanitizer')->text((string)($input['title'] ?? ''), ['maxLength' => 72]));
                $this->memoryJson(['success' => true, 'thread_id' => $threadId, 'thread_title' => $title]);
            }
            $question = trim($this->wire('sanitizer')->textarea((string)($input['message'] ?? ''), ['maxLength' => 2000]));
            if($question === '') $this->memoryJson(['success' => false, 'error' => 'Enter a question.'], 400);

            if(preg_match('/^\/remember(?:\s+(.+?))?\R([\s\S]+)$/u', $question, $remember)) {
                $this->requirePermission('liora-git-write');
                $title = trim((string)($remember[1] ?? ''));
                $content = trim((string)($remember[2] ?? ''));
                if($title === '') $title = mb_substr(trim(strtok($content, "\n")), 0, 180);
                $proposal = $this->proposeCreate($title, $content);
                $this->wire('session')->set('liora_git_frontend_proposal_id', (string)$proposal['public_id']);
                $response = "Prepared a repository change. Nothing has been written yet.\n\n"
                    . "Repository: `{$proposal['repository']}`\n\nBranch: `{$proposal['branch']}`\n\nPath: `{$proposal['path']}`\n\n"
                    . "```diff\n{$proposal['diff']}\n```\n\nType `/confirm` to commit this exact proposal or `/cancel` to discard it.";
                $this->memorySuccess($response, $threadId);
            }

            if(preg_match('/^\/confirm\s*$/iu', $question)) {
                $this->requirePermission('liora-git-write');
                $proposalId = (string)$this->wire('session')->get('liora_git_frontend_proposal_id');
                if($proposalId === '') throw new WireException('There is no pending proposal to confirm.');
                $result = $this->confirmProposal($proposalId);
                $this->wire('session')->remove('liora_git_frontend_proposal_id');
                $response = "Committed `{$result['path']}` to `{$result['repository']}` on `{$result['branch']}`.\n\nCommit: `{$result['commit_sha']}`";
                $sources = $result['url'] !== '' ? [['title' => $result['path'], 'url' => $result['url']]] : [];
                $this->memorySuccess($response, $threadId, $sources);
            }

            if(preg_match('/^\/cancel\s*$/iu', $question)) {
                $this->requirePermission('liora-git-write');
                $proposalId = (string)$this->wire('session')->get('liora_git_frontend_proposal_id');
                if($proposalId !== '') $this->cancelProposal($proposalId);
                $this->wire('session')->remove('liora_git_frontend_proposal_id');
                $this->memorySuccess('Proposal cancelled. Nothing was written.', $threadId);
            }

            $history = $this->memoryHistory((array)($input['history'] ?? []));
            $result = $this->askMemory($question, $history);
            if(empty($result['success'])) throw new WireException((string)($result['error'] ?? 'Liora could not answer'));
            $this->memorySuccess((string)$result['content'], $threadId, (array)($result['sources'] ?? []), (array)($result['data'] ?? []));
        } catch(WirePermissionException $error) {
            $this->memoryJson(['success' => false, 'error' => 'Sign in with an authorized account.'], 403);
        } catch(\Throwable $error) {
            $this->memoryJson(['success' => false, 'error' => $error->getMessage()], 400);
        }
    }

    protected function memorySuccess(string $response, string $threadId, array $sources = [], array $data = []): void {
        $this->memoryJson([
            'success' => true,
            'response' => $response,
            'thread_id' => $threadId,
            'thread_title' => '',
            'model' => (string)($data['model'] ?? ''),
            'tokens_used' => (int)($data['usage']['total_tokens'] ?? 0),
            'cached' => !empty($data['cached']),
            'format' => 'markdown',
            'rag_sources' => array_map(static fn(array $source): array => [
                'title' => (string)($source['title'] ?? $source['path'] ?? 'Source'),
                'url' => (string)($source['url'] ?? ''),
            ], $sources),
        ]);
    }

    protected function memoryHistory(array $history): array {
        $result = [];
        foreach(array_slice($history, -8) as $message) {
            if(!is_array($message) || !in_array(($message['role'] ?? ''), ['user', 'assistant'], true)) continue;
            $content = mb_substr(trim((string)($message['content'] ?? '')), 0, 4000);
            if($content !== '') $result[] = ['role' => $message['role'], 'content' => $content];
        }
        return $result;
    }

    protected function memoryThreadId(string $value): string {
        $value = trim($value);
        if($value !== '' && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $value)) return $value;
        return bin2hex(random_bytes(16));
    }

    protected function withinMemoryRateLimit(): bool {
        $now = time();
        $times = array_values(array_filter((array)$this->wire('session')->get('liora_git_request_times'), static fn($time): bool => (int)$time > $now - 60));
        if(count($times) >= 20) return false;
        $times[] = $now;
        $this->wire('session')->set('liora_git_request_times', $times);
        return true;
    }

    protected function memoryJson(array $data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
