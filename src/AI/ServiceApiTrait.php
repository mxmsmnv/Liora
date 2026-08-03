<?php namespace ProcessWire;

trait LioraServiceApiTrait {

    public function isConfigured(): bool {
        $squad = $this->squad();
        if(!$squad
            || !method_exists($squad, 'getDefaultProviderKey')
            || !method_exists($squad, 'getProvidersStatus')) {
            return false;
        }

        [$provider] = $this->configuredProviderModel();
        $provider = $provider ?: (string)$squad->getDefaultProviderKey();
        $statuses = (array)$squad->getProvidersStatus();
        return !empty($statuses[$provider]['active']);
    }

    /**
     * Return Liora's configured model, Squad's selected default, or an explicit
     * call-level model override.
     */
    public function getModel(string $profile = 'default'): string {
        $profile = trim($profile);
        if($profile !== ''
            && !in_array($profile, ['default', 'cheap', 'non-reasoning', 'reasoning'], true)) {
            return $profile;
        }

        [, $configuredModel] = $this->configuredProviderModel();
        if($configuredModel !== '') return $configuredModel;

        $squad = $this->squad();
        if(!$squad || !method_exists($squad, 'getProvider')) return '';

        $provider = $squad->getProvider((string)$squad->getDefaultProviderKey());
        return $provider && method_exists($provider, 'getModel')
            ? trim((string)$provider->getModel())
            : '';
    }

    public function getProvider(): string {
        [$provider] = $this->configuredProviderModel();
        if($provider !== '') return $provider;
        $squad = $this->squad();
        return $squad && method_exists($squad, 'getDefaultProviderKey')
            ? (string)$squad->getDefaultProviderKey()
            : '';
    }

    /**
     * Return active provider/model choices for integrations that delegate AI
     * transport to Liora without duplicating Squad discovery logic.
     */
    public function getProviderModelOptions(): array {
        return $this->modelOptions();
    }

    public function ask(string $message, array $options = []): array {
        return $this->chat([
            ['role' => 'user', 'content' => $message],
        ], $options);
    }

    public function complete(string $message, array $options = []) {
        $result = $this->ask($message, $options);
        return !empty($result['success']) ? $result['content'] : false;
    }

    /**
     * Send an OpenAI-style message list through Squad.
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
        $provider = trim((string)($options['squad_provider'] ?? $options['provider'] ?? $this->getProvider()));
        $maxTokens = (int)($options['max_tokens'] ?? $options['maxTokens'] ?? $this->setting('maxTokens', 1200));
        $temperature = (float)($options['temperature'] ?? $this->setting('temperature', 0.4));
        $timeout = (int)($options['timeout'] ?? $this->setting('timeout', 60));
        $webSearch = $this->resolveWebSearch($options, $current, $history);
        $webSearchMaxResults = max(1, min(10, (int)($options['webSearchMaxResults'] ?? $this->setting('webSearchMaxResults', 5))));
        if($webSearch) $systemPrompt = $this->withWebSearchInstructions($systemPrompt);
        $cacheSeconds = (int)$this->setting('cacheSeconds', 3600);
        $request = [
            'systemPrompt' => $systemPrompt,
            'history' => $history,
            'maxTokens' => max(1, min(200000, $maxTokens)),
            'temperature' => max(0.0, min(2.0, $temperature)),
            'timeout' => max(5, min(300, $timeout)),
            'webSearch' => $webSearch,
            'webSearchMaxResults' => $webSearchMaxResults,
            'cache' => array_key_exists('cache', $options)
                ? $options['cache']
                : ($cacheSeconds > 0 ? $cacheSeconds : false),
        ];
        if($model !== '') $request['model'] = $model;
        if($provider !== '') $request['provider'] = $provider;
        if(isset($options['pageId'])) $request['pageId'] = (int)$options['pageId'];

        $squad = $this->squad();
        $providerStartedAt = microtime(true);
        $result = $squad ? (array)$squad->ask($current, $request) : [];
        $providerResponseMs = (int)round((microtime(true) - $providerStartedAt) * 1000);
        if(empty($result['success'])) {
            return $this->errorResponse(
                $this->safeSquadError((string)($result['message'] ?? ''))
            );
        }

        $content = $this->restrictExternalLinks(trim((string)($result['content'] ?? '')));
        if($content === '') {
            return $this->errorResponse('The AI provider returned an invalid response');
        }

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
                'sources' => (array)($result['sources'] ?? []),
                'response_time_ms' => $providerResponseMs,
                'metadata' => $this->responseTechnicalMetadata(
                    $request,
                    $result,
                    $provider,
                    (string)($result['model'] ?? $model),
                    false,
                    $providerResponseMs
                ),
            ],
        ];
    }

    /**
     * Stream an OpenAI-style message list through Squad.
     *
     * The callback receives plain-text deltas. The return value contains the
     * complete normalized response and usage metadata.
     */
    public function streamChat(array $messages, callable $onDelta, array $options = []): array {
        if(!$this->isConfigured()) return $this->errorResponse('AI service is not configured');
        [$current, $history, $systemPrompt] = $this->normalizeMessages($messages);
        if($current === '') return $this->errorResponse('AI messages require a user message');
        $squad = $this->squad();
        if(!$squad || !method_exists($squad, 'stream')) {
            return $this->errorResponse('The configured AI gateway does not support streaming');
        }

        $model = $this->getModel((string)($options['model'] ?? 'default'));
        $provider = trim((string)($options['squad_provider'] ?? $options['provider'] ?? $this->getProvider()));
        $webSearch = $this->resolveWebSearch($options, $current, $history);
        $webSearchMaxResults = max(1, min(10, (int)($options['webSearchMaxResults'] ?? $this->setting('webSearchMaxResults', 5))));
        if($webSearch) $systemPrompt = $this->withWebSearchInstructions($systemPrompt);
        $request = [
            'provider' => $provider,
            'model' => $model,
            'systemPrompt' => $systemPrompt,
            'history' => $history,
            'maxTokens' => max(1, min(200000, (int)($options['max_tokens'] ?? $options['maxTokens'] ?? $this->setting('maxTokens', 1200)))),
            'temperature' => max(0.0, min(2.0, (float)($options['temperature'] ?? $this->setting('temperature', 0.4)))),
            'timeout' => max(5, min(300, (int)($options['timeout'] ?? $this->setting('timeout', 60)))),
            'webSearch' => $webSearch,
            'webSearchMaxResults' => $webSearchMaxResults,
        ];
        if(isset($options['pageId'])) $request['pageId'] = (int)$options['pageId'];
        $providerStartedAt = microtime(true);
        $result = (array)$squad->stream($current, $onDelta, $request);
        $providerResponseMs = (int)round((microtime(true) - $providerStartedAt) * 1000);
        if(empty($result['success'])) {
            return $this->errorResponse($this->safeSquadError((string)($result['message'] ?? '')));
        }
        $content = $this->restrictExternalLinks((string)($result['content'] ?? ''));
        return [
            'success' => true,
            'status' => 200,
            'error' => '',
            'content' => $content,
            'data' => [
                'provider' => (string)($result['provider'] ?? $provider),
                'model' => (string)($result['model'] ?? $model),
                'usage' => (array)($result['usage'] ?? []),
                'cached' => false,
                'sources' => (array)($result['sources'] ?? []),
                'response_time_ms' => $providerResponseMs,
                'metadata' => $this->responseTechnicalMetadata(
                    $request,
                    $result,
                    (string)($result['provider'] ?? $provider),
                    (string)($result['model'] ?? $model),
                    true,
                    $providerResponseMs
                ),
            ],
        ];
    }

}

