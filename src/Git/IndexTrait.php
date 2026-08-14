<?php namespace ProcessWire;

trait LioraGitIndexTrait {

    public function sync(): array {
        $this->requirePermission('liora-git-sync');
        $atlas = $this->wire('modules')->get('Atlas');
        if(!$atlas || !$atlas->isReady()) throw new WireException('Atlas is not ready');
        $repository = $this->repository();
        $commit = $this->headCommit();
        $tree = $this->github('/repos/' . $repository . '/git/trees/' . rawurlencode($commit) . '?recursive=1');
        if(!empty($tree['truncated'])) throw new WireException('GitHub truncated the repository tree; sync was not changed');

        $existing = $this->indexedDocuments($atlas);
        if(method_exists($atlas, 'lastError') && $atlas->lastError() !== '') throw new WireException($atlas->lastError());
        $seen = [];
        $summary = ['documents' => 0, 'indexed' => 0, 'unchanged' => 0, 'deleted' => 0, 'chunks' => 0, 'commit' => $commit];
        $limit = max(1, min(10000, (int)$this->gitSetting('maxDocuments', 2000)));
        foreach((array)($tree['tree'] ?? []) as $entry) {
            if(($entry['type'] ?? '') !== 'blob') continue;
            $path = (string)($entry['path'] ?? '');
            if(!$this->indexablePath($path)) continue;
            if(++$summary['documents'] > $limit) throw new WireException("Document limit {$limit} exceeded; sync was not completed");
            $blobSha = (string)($entry['sha'] ?? '');
            $ref = $this->documentRef($path);
            $seen[$ref] = true;
            if(($existing[$ref] ?? '') === $blobSha) { $summary['unchanged']++; continue; }
            if((int)($entry['size'] ?? 0) > max(1000, min(1000000, (int)$this->gitSetting('maxFileBytes', 250000)))) {
                throw new WireException("File exceeds configured size: {$path}");
            }
            $blob = $this->github('/repos/' . $repository . '/git/blobs/' . rawurlencode($blobSha));
            if(($blob['encoding'] ?? '') !== 'base64') throw new WireException("Unsupported encoding: {$path}");
            $text = base64_decode(str_replace(["\r", "\n"], '', (string)($blob['content'] ?? '')), true);
            if($text === false || trim($text) === '') continue;
            $url = 'https://github.com/' . $repository . '/blob/' . $commit . '/' . $this->encodedPath($path);
            $chunks = $atlas->addChunked($this->collection(), $ref, $text, [
                'source_type' => 'liora-git', 'private' => true, 'public' => false,
                'repository' => $repository, 'branch' => $this->branch(), 'path' => $path,
                'title' => $this->documentTitle($text, $path), 'url' => $url,
                'commit_sha' => $commit, 'blob_sha' => $blobSha,
            ], [], max(500, min(5000, (int)$this->gitSetting('chunkChars', 1800))));
            if($chunks < 1) throw new WireException($atlas->lastError() ?: "Indexing failed: {$path}");
            $summary['indexed']++; $summary['chunks'] += $chunks;
        }
        foreach($existing as $ref => $blobSha) {
            if(isset($seen[$ref])) continue;
            if(!$atlas->deleteRef($this->collection(), $ref)) throw new WireException($atlas->lastError() ?: "Stale document removal failed: {$ref}");
            $summary['deleted']++;
        }
        return $summary;
    }

    protected function indexablePath(string $path): bool {
        if(!$this->validPath($path) || !str_ends_with(strtolower($path), '.md')) return false;
        if(preg_match('#(^|/)(?:\.git|\.github|vendor|node_modules)(/|$)#i', $path)) return false;
        if(preg_match('#(^|/)(?:AGENTS|CLAUDE)\.md$#i', $path)) return false;
        $patterns = preg_split('/[\r\n,]+/', (string)$this->gitSetting('readPaths', '**/*.md')) ?: [];
        foreach($patterns as $pattern) {
            $pattern = trim($pattern);
            if($pattern !== '' && $this->globMatches($pattern, $path)) return true;
        }
        return false;
    }

    protected function globMatches(string $glob, string $path): bool {
        $quoted = preg_quote(trim($glob, '/'), '#');
        $quoted = str_replace(['\*\*', '\*', '\?'], ['.*', '[^/]*', '[^/]'], $quoted);
        return preg_match('#^' . $quoted . '$#u', $path) === 1;
    }

    protected function indexedDocuments($atlas): array {
        $result = []; $offset = 0;
        do {
            $rows = $atlas->entries($this->collection(), 500, $offset);
            foreach($rows as $row) {
                $meta = (array)($row['meta'] ?? []);
                if(($meta['source_type'] ?? '') !== 'liora-git' || ($meta['repository'] ?? '') !== $this->repository()) continue;
                $ref = (string)($meta['parent'] ?? preg_replace('/#\d+$/', '', (string)($row['ref'] ?? '')));
                if($ref !== '') $result[$ref] = (string)($meta['blob_sha'] ?? '');
            }
            $offset += count($rows);
        } while(count($rows) === 500);
        return $result;
    }

    protected function documentRef(string $path): string { return 'git-' . sha1($this->repository() . ':' . $path); }
    protected function documentTitle(string $text, string $path): string {
        if(preg_match('/^#\s+(.+)$/m', $text, $match)) return mb_substr(trim(strip_tags($match[1])), 0, 180);
        return mb_substr(pathinfo($path, PATHINFO_FILENAME), 0, 180);
    }
}
