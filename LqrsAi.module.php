<?php namespace ProcessWire;

/**
 * LqrsAi
 *
 * Site-level AI facade for LQRS. Provider credentials and model selection live
 * exclusively in Squad; this module keeps the public site and CLI response
 * contract small, stable, and safe to expose to application code.
 *
 * @version 1.0.0
 */
class LqrsAi extends WireData implements Module {

    public static function getModuleInfo(): array {
        return [
            'title' => 'LQRS AI',
            'version' => 100,
            'summary' => 'Site AI facade backed by the centralized Squad gateway.',
            'author' => 'Maxim Semenov',
            'href' => 'https://lqrs.com',
            'icon' => 'comments',
            'singular' => true,
            'autoload' => false,
            'requires' => ['ProcessWire>=3.0.210', 'PHP>=8.1', 'Squad'],
        ];
    }

    public function isConfigured(): bool {
        $squad = $this->squad();
        if(!$squad
            || !method_exists($squad, 'getDefaultProviderKey')
            || !method_exists($squad, 'getProvidersStatus')) {
            return false;
        }

        $provider = (string)$squad->getDefaultProviderKey();
        $statuses = (array)$squad->getProvidersStatus();
        return !empty($statuses[$provider]['active']);
    }

    /**
     * Return Squad's selected model, or preserve an explicit model override.
     */
    public function getModel(string $profile = 'default'): string {
        $profile = trim($profile);
        if($profile !== ''
            && !in_array($profile, ['default', 'cheap', 'non-reasoning', 'reasoning'], true)) {
            return $profile;
        }

        $squad = $this->squad();
        if(!$squad || !method_exists($squad, 'getProvider')) return '';

        $provider = $squad->getProvider((string)$squad->getDefaultProviderKey());
        return $provider && method_exists($provider, 'getModel')
            ? trim((string)$provider->getModel())
            : '';
    }

    /**
     * Convenience one-message call with the same response contract as chat().
     */
    public function ask(string $message, array $options = []): array {
        return $this->chat([
            ['role' => 'user', 'content' => $message],
        ], $options);
    }

    /**
     * Convenience one-message call that returns text or false.
     */
    public function complete(string $message, array $options = []) {
        $result = $this->ask($message, $options);
        return !empty($result['success']) ? $result['content'] : false;
    }

    /**
     * Send an OpenAI-style message list through Squad.
     *
     * Supported options: model, max_tokens/maxTokens, temperature, timeout,
     * cache, and squad_provider.
     */
    public function chat(array $messages, array $options = []): array {
        if(!$this->isConfigured()) {
            return $this->errorResponse('AI service is not configured');
        }

        [$current, $history, $systemPrompt] = $this->normalizeMessages($messages);
        if($current === '') {
            return $this->errorResponse('AI messages require a user message');
        }

        $model = $this->getModel((string)($options['model'] ?? 'default'));
        $maxTokens = (int)($options['max_tokens'] ?? $options['maxTokens'] ?? 20000);
        $temperature = (float)($options['temperature'] ?? 0.5);
        $timeout = (int)($options['timeout'] ?? 60);
        $request = [
            'systemPrompt' => $systemPrompt,
            'history' => $history,
            'maxTokens' => max(1, min(200000, $maxTokens)),
            'temperature' => max(0.0, min(2.0, $temperature)),
            'timeout' => max(5, min(300, $timeout)),
            'cache' => $options['cache'] ?? false,
        ];
        if($model !== '') $request['model'] = $model;
        if(!empty($options['squad_provider'])) {
            $request['provider'] = trim((string)$options['squad_provider']);
        }

        $result = (array)$this->squad()->ask($current, $request);
        if(empty($result['success'])) {
            return $this->errorResponse(
                $this->safeSquadError((string)($result['message'] ?? ''))
            );
        }

        $content = (string)($result['content'] ?? '');
        if($content === '') {
            return $this->errorResponse('The AI provider returned an invalid response');
        }

        $provider = (string)$this->squad()->getDefaultProviderKey();
        return [
            'success' => true,
            'status' => 200,
            'error' => '',
            'content' => $content,
            'data' => [
                'provider' => $provider,
                'model' => (string)($result['model'] ?? $model),
                'usage' => (array)($result['usage'] ?? []),
                'cached' => !empty($result['cached']),
            ],
        ];
    }

    protected function squad(): ?object {
        try {
            $modules = $this->wire()->modules;
            if(!$modules->isInstalled('Squad')) return null;
            $squad = $modules->get('Squad');
            return is_object($squad) && method_exists($squad, 'ask') ? $squad : null;
        } catch(\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{0:string,1:array,2:string}
     */
    protected function normalizeMessages(array $messages): array {
        $systemParts = [];
        $history = [];

        foreach($messages as $message) {
            if(!is_array($message)) continue;
            $role = (string)($message['role'] ?? 'user');
            $content = $this->messageText($message['content'] ?? '');
            if($content === '') continue;

            if($role === 'system' || $role === 'developer') {
                $systemParts[] = $content;
                continue;
            }
            $history[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        if(!$history) return ['', [], trim(implode("\n\n", $systemParts))];

        $current = array_pop($history);
        if($current['role'] !== 'user') {
            $history[] = $current;
            $current = ['role' => 'user', 'content' => 'Continue.'];
        }

        return [
            (string)$current['content'],
            $history,
            trim(implode("\n\n", $systemParts)),
        ];
    }

    protected function messageText($content): string {
        if(is_scalar($content)) return trim((string)$content);
        if(!is_array($content)) return '';

        $parts = [];
        foreach($content as $part) {
            if(is_scalar($part)) {
                $parts[] = (string)$part;
            } elseif(is_array($part)) {
                $text = $part['text'] ?? $part['content'] ?? '';
                if(is_scalar($text)) $parts[] = (string)$text;
            }
        }
        return trim(implode("\n", array_filter($parts, 'strlen')));
    }

    protected function errorResponse(string $message): array {
        return [
            'success' => false,
            'status' => 0,
            'error' => $message,
        ];
    }

    protected function safeSquadError(string $message): string {
        $message = strtolower($message);
        if(str_contains($message, 'auth') || str_contains($message, '401') || str_contains($message, '403')) {
            return 'AI provider authentication failed';
        }
        if(str_contains($message, 'credit') || str_contains($message, '402')) {
            return 'AI provider credits are insufficient';
        }
        if(str_contains($message, 'rate') || str_contains($message, '429')) {
            return 'AI provider rate limit reached';
        }
        if(str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'AI provider request timed out';
        }
        return 'AI request failed';
    }
}
