<?php namespace ProcessWire;

trait LioraGitGitHubTrait {

    protected function github(string $path, string $method = 'GET', array $payload = [], bool $write = false): array {
        $token = trim((string)$this->gitSetting($write ? 'writeToken' : 'readToken'));
        if($token === '') throw new WireException(($write ? 'Write' : 'Read') . ' token is not configured');
        $http = new WireHttp();
        $http->setTimeout(30);
        $http->setHeader('Accept', 'application/vnd.github+json');
        $http->setHeader('X-GitHub-Api-Version', '2022-11-28');
        $http->setHeader('User-Agent', 'LioraGit/0.1');
        $http->setHeader('Authorization', 'Bearer ' . $token);
        if($method !== 'GET') $http->setHeader('Content-Type', 'application/json');
        $body = $method === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response = $http->send('https://api.github.com' . $path, $body, $method);
        $status = (int)$http->getHttpCode();
        $data = is_string($response) ? json_decode($response, true) : null;
        if($response === false || $status < 200 || $status >= 300 || !is_array($data)) {
            $message = is_array($data) ? (string)($data['message'] ?? 'invalid response') : 'network or JSON error';
            $this->lastError = "GitHub {$method} failed" . ($status ? " ({$status})" : '') . ": {$message}";
            throw new WireException($this->lastError);
        }
        return $data;
    }

    protected function headCommit(): string {
        $data = $this->github('/repos/' . $this->repository() . '/commits/' . rawurlencode($this->branch()));
        $sha = (string)($data['sha'] ?? '');
        if(!preg_match('/^[a-f0-9]{40}$/i', $sha)) throw new WireException('GitHub returned an invalid commit SHA');
        return $sha;
    }

    protected function encodedPath(string $path): string {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
