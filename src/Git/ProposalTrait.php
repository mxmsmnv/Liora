<?php namespace ProcessWire;

trait LioraGitProposalTrait {

    public function proposeCreate(string $title, string $content): array {
        $this->requirePermission('liora-git-write');
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags($title)) ?? '');
        $content = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content) ?? '');
        if($title === '' || $content === '') throw new WireException('Title and content are required');
        if(mb_strlen($title) > 180 || mb_strlen($content) > 100000) throw new WireException('Proposal exceeds the safety limit');
        $slug = $this->wire('sanitizer')->pageNameTranslate($title) ?: 'note';
        $path = $this->writeDirectory() . '/' . gmdate('Y-m-d-His') . '-' . $slug . '.md';
        $author = (string)$this->wire('user')->name;
        $document = "---\nid: note-" . gmdate('Y-m-d-His') . "-{$slug}\ntype: note\ntitle: " . $this->yamlValue($title) . "\nstatus: draft\ncreated: " . gmdate('Y-m-d') . "\nupdated: " . gmdate('Y-m-d') . "\nauthors:\n  - " . $this->yamlValue($author) . "\ntopics: []\nvisibility: private\nsources: []\nrelated: []\n---\n\n# {$title}\n\n## Summary\n\n{$content}\n";
        $proposal = $this->store()->create([
            'user_id' => (int)$this->wire('user')->id, 'operation' => 'create',
            'repository' => $this->repository(), 'branch' => $this->branch(), 'path' => $path,
            'base_commit' => $this->headCommit(), 'blob_sha' => '', 'title' => $title, 'content' => $document,
        ]);
        $proposal['diff'] = $this->createDiff($path, $document);
        return $proposal;
    }

    public function confirmProposal(string $publicId): array {
        $this->requirePermission('liora-git-write');
        $proposal = $this->store()->findOwned($publicId, (int)$this->wire('user')->id, true);
        if(!$proposal) throw new WireException('Proposal is missing, expired or already resolved');
        if(!hash_equals((string)$proposal['content_sha256'], hash('sha256', (string)$proposal['content']))) throw new WireException('Proposal integrity check failed');
        if($proposal['repository'] !== $this->repository() || $proposal['branch'] !== $this->branch()) throw new WireException('Configured target changed; create a new proposal');
        if(!str_starts_with((string)$proposal['path'], $this->writeDirectory() . '/') || !str_ends_with(strtolower((string)$proposal['path']), '.md')) throw new WireException('Proposal path is outside the write policy');
        $head = $this->headCommit();
        if(!hash_equals((string)$proposal['base_commit'], $head)) {
            $this->store()->finish((int)$proposal['id'], 'conflict');
            throw new WireException('Repository changed after preview; nothing was written. Create a fresh proposal.');
        }
        $result = $this->github('/repos/' . $this->repository() . '/contents/' . $this->encodedPath((string)$proposal['path']), 'PUT', [
            'message' => 'Add Liora memory: ' . (string)$proposal['title'],
            'content' => base64_encode((string)$proposal['content']),
            'branch' => $this->branch(),
        ], true);
        $commitSha = (string)($result['commit']['sha'] ?? '');
        if(!preg_match('/^[a-f0-9]{40}$/i', $commitSha)) {
            $this->store()->finish((int)$proposal['id'], 'failed');
            throw new WireException('GitHub did not return a valid commit; verify repository state');
        }
        $this->store()->finish((int)$proposal['id'], 'committed', $commitSha);
        return ['success' => true, 'repository' => $this->repository(), 'branch' => $this->branch(), 'path' => (string)$proposal['path'], 'commit_sha' => $commitSha, 'url' => (string)($result['content']['html_url'] ?? '')];
    }

    public function cancelProposal(string $publicId): bool {
        $this->requirePermission('liora-git-write');
        $proposal = $this->store()->findOwned($publicId, (int)$this->wire('user')->id, true);
        return $proposal ? $this->store()->finish((int)$proposal['id'], 'cancelled') : false;
    }

    protected function yamlValue(string $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    protected function createDiff(string $path, string $content): string {
        $lines = explode("\n", rtrim($content, "\n"));
        return "--- /dev/null\n+++ b/{$path}\n@@ -0,0 +1," . count($lines) . " @@\n+" . implode("\n+", $lines);
    }
}
