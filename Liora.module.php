<?php namespace ProcessWire;

require_once __DIR__ . '/LioraStore.php';

/**
 * Liora — measurable AI assistance for LQRS.
 *
 * Liora turns an unsuccessful search or unanswered page question into a useful
 * answer and a structured demand signal. Squad remains responsible for
 * credentials and provider transport.
 *
 * @version 1.0.0
 */
class Liora extends WireData implements Module, ConfigurableModule {

    protected ?LioraStore $storeInstance = null;
    protected static bool $assetsRendered = false;

    public static function getModuleInfo(): array {
        return [
            'title' => 'Liora',
            'version' => 100,
            'summary' => 'AI answer CTA with configurable models and content-demand analytics.',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/Liora',
            'icon' => 'comments',
            'singular' => true,
            'autoload' => false,
            'requires' => ['ProcessWire>=3.0.210', 'PHP>=8.1', 'Squad'],
            'installs' => ['InputfieldLiora', 'ProcessLiora'],
        ];
    }

    public function ___install(): void {
        $this->store()->ensureTable();
        $this->importLegacyHistory();
    }

    public function ___upgrade($fromVersion, $toVersion): void {
        $this->store()->ensureTable();
        $this->importLegacyHistory();
    }

    public function ___uninstall(): void {
        if((bool)$this->setting('deleteDataOnUninstall', false)) {
            $this->store()->dropTable();
        }
    }

    public function store(): LioraStore {
        if(!$this->storeInstance) {
            $this->storeInstance = $this->wire(new LioraStore());
        }
        return $this->storeInstance;
    }

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
        $cacheSeconds = (int)$this->setting('cacheSeconds', 3600);
        $request = [
            'systemPrompt' => $systemPrompt,
            'history' => $history,
            'maxTokens' => max(1, min(200000, $maxTokens)),
            'temperature' => max(0.0, min(2.0, $temperature)),
            'timeout' => max(5, min(300, $timeout)),
            'cache' => array_key_exists('cache', $options)
                ? $options['cache']
                : ($cacheSeconds > 0 ? $cacheSeconds : false),
        ];
        if($model !== '') $request['model'] = $model;
        if($provider !== '') $request['provider'] = $provider;
        if(isset($options['pageId'])) $request['pageId'] = (int)$options['pageId'];

        $squad = $this->squad();
        $result = $squad ? (array)$squad->ask($current, $request) : [];
        if(empty($result['success'])) {
            return $this->errorResponse(
                $this->safeSquadError((string)($result['message'] ?? ''))
            );
        }

        $content = trim((string)($result['content'] ?? ''));
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
            ],
        ];
    }

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

        if(!$this->withinRateLimit()) {
            $this->sendJson(['success' => false, 'error' => 'Too many questions. Please try again later.'], 429);
        }

        $san = $this->wire('sanitizer');
        $question = trim($san->textarea((string)($input['message'] ?? ''), [
            'maxLength' => (int)$this->setting('maxQuestionLength', 1000),
        ]));
        $originalQuery = trim($san->text((string)($input['originalQuery'] ?? ''), ['maxLength' => 500]));
        $context = trim($san->text((string)($input['context'] ?? 'site'), ['maxLength' => 255]));
        $sourceUrl = $this->sanitizeSourceUrl((string)($input['sourceUrl'] ?? ''));
        $pageId = max(0, (int)($input['pageId'] ?? 0));

        if($question === '') {
            $this->sendJson(['success' => false, 'error' => 'Please enter a question.'], 400);
        }
        if(!$this->isConfigured()) {
            $this->trackRequest($question, '', [
                'originalQuery' => $originalQuery,
                'context' => $context,
                'sourceUrl' => $sourceUrl,
                'pageId' => $pageId,
                'error' => 'AI service is not configured',
            ]);
            $this->sendJson(['success' => false, 'error' => 'AI service is not configured'], 503);
        }

        $history = $this->sessionHistory();
        $systemPrompt = trim((string)$this->setting('systemPrompt', $this->defaultSystemPrompt()));
        if($originalQuery !== '') {
            $systemPrompt .= "\n\nThe visitor originally searched for: \"{$originalQuery}\". The site did not give them a sufficient answer.";
        }
        if($sourceUrl !== '') {
            $systemPrompt .= "\nThe visitor is asking from this site path: {$sourceUrl}.";
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach($history as $message) $messages[] = $message;
        $messages[] = ['role' => 'user', 'content' => $question];

        $result = $this->chat($messages, ['pageId' => $pageId]);
        if(empty($result['success'])) {
            $this->trackRequest($question, '', [
                'originalQuery' => $originalQuery,
                'context' => $context,
                'sourceUrl' => $sourceUrl,
                'pageId' => $pageId,
                'error' => (string)($result['error'] ?? 'AI request failed'),
            ]);
            $this->sendJson(['success' => false, 'error' => $result['error'] ?? 'AI request failed'], 502);
        }

        $answer = (string)$result['content'];
        $this->appendSessionHistory($question, $answer);
        $data = (array)($result['data'] ?? []);
        $this->trackRequest($question, $answer, [
            'originalQuery' => $originalQuery,
            'context' => $context,
            'sourceUrl' => $sourceUrl,
            'pageId' => $pageId,
            'provider' => (string)($data['provider'] ?? $this->getProvider()),
            'model' => (string)($data['model'] ?? $this->getModel()),
            'usage' => (array)($data['usage'] ?? []),
            'cached' => !empty($data['cached']),
        ]);

        $this->sendJson([
            'success' => true,
            'response' => $answer,
            'model' => (string)($data['model'] ?? $this->getModel()),
            'tokens_used' => (int)($data['usage']['total_tokens'] ?? 0),
            'cached' => !empty($data['cached']),
            'format' => 'markdown',
        ]);
    }

    /**
     * Render the reusable public CTA/chat widget.
     *
     * Options: originalQuery, context, sourceUrl, pageId, heading, intro,
     * placeholder, compact.
     */
    public function renderWidget(array $options = []): string {
        if(!(bool)$this->setting('widgetEnabled', true)) return '';

        $san = $this->wire('sanitizer');
        $page = $this->wire('page');
        $query = trim((string)($options['originalQuery'] ?? ''));
        $context = trim((string)($options['context'] ?? ($page && $page->template ? $page->template->name : 'site')));
        $sourceUrl = (string)($options['sourceUrl'] ?? ($page && $page->id ? $page->url : ''));
        $pageId = (int)($options['pageId'] ?? ($page && $page->id ? $page->id : 0));
        $heading = (string)($options['heading'] ?? $this->setting('widgetHeading', 'Still looking? Ask Liora'));
        $intro = (string)($options['intro'] ?? $this->setting(
            'widgetIntro',
            'Tell me what you want to know. Your question also helps us improve this page.'
        ));
        if($query !== '') {
            $intro = str_replace('{query}', $query, $intro);
        } else {
            $intro = str_replace('{query}', 'this topic', $intro);
        }
        $placeholder = (string)($options['placeholder'] ?? $this->setting(
            'widgetPlaceholder',
            'Ask about products, pairings, brands or cocktails…'
        ));
        $endpoint = (string)$this->setting('endpoint', '/agent/');
        $csrfName = $this->wire('session')->CSRF->getTokenName();
        $csrfValue = $this->wire('session')->CSRF->getTokenValue();
        $id = 'liora-' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
        $compact = !empty($options['compact']) ? ' liora-widget--compact' : '';
        $assets = '';

        if(!self::$assetsRendered) {
            self::$assetsRendered = true;
            $base = $this->wire('config')->urls->siteModules . 'Liora/assets/';
            $version = self::getModuleInfo()['version'];
            $assets = "<link rel='stylesheet' href='{$base}liora.css?v={$version}'>"
                . "<script src='{$base}liora.js?v={$version}' defer></script>";
        }

        $attrs = [
            'data-endpoint' => $endpoint,
            'data-original-query' => $query,
            'data-context' => $context,
            'data-source-url' => $sourceUrl,
            'data-page-id' => (string)$pageId,
            'data-csrf-name' => $csrfName,
            'data-csrf-value' => $csrfValue,
        ];
        $dataAttrs = '';
        foreach($attrs as $name => $value) {
            $dataAttrs .= ' ' . $name . '="' . $san->entities($value) . '"';
        }

        return $assets
            . "<section id='{$id}' class='liora-widget{$compact}'{$dataAttrs}>"
            . "<div class='liora-widget__header'><span class='liora-widget__icon' aria-hidden='true'>✦</span>"
            . "<div><h2>" . $san->entities($heading) . "</h2><p>" . $san->entities($intro) . "</p></div></div>"
            . "<div class='liora-widget__messages' data-liora-messages aria-live='polite'></div>"
            . "<form class='liora-widget__form' data-liora-form>"
            . "<label class='liora-sr-only' for='{$id}-question'>" . $this->_('Ask Liora') . "</label>"
            . "<input id='{$id}-question' data-liora-input type='text' maxlength='"
            . (int)$this->setting('maxQuestionLength', 1000) . "' autocomplete='off' placeholder='"
            . $san->entities($placeholder) . "' required>"
            . "<button type='submit' data-liora-submit>" . $this->_('Ask') . "</button>"
            . "</form><p class='liora-widget__note'>" . $this->_('AI can make mistakes. Please verify important information.') . "</p>"
            . "</section>";
    }

    public function getModuleConfigInputfields(InputfieldWrapper $inputfields): InputfieldWrapper {
        $modules = $this->wire('modules');

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('AI model');
        $fieldset->icon = 'brain';

        $field = $modules->get('InputfieldSelect');
        $field->attr('name', 'providerModel');
        $field->label = $this->_('Provider and model');
        $field->description = $this->_('Credentials remain in Squad. Liora can follow the Squad default or use a specific active provider/model.');
        $field->addOption('', $this->_('Use Squad default'));
        foreach($this->modelOptions() as $value => $label) $field->addOption($value, $label);
        $field->attr('value', (string)$this->setting('providerModel', ''));
        $fieldset->add($field);

        $field = $modules->get('InputfieldTextarea');
        $field->attr('name', 'systemPrompt');
        $field->label = $this->_('System prompt');
        $field->attr('rows', 8);
        $field->attr('value', (string)$this->setting('systemPrompt', $this->defaultSystemPrompt()));
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'maxTokens');
        $field->label = $this->_('Maximum response tokens');
        $field->attr('value', (int)$this->setting('maxTokens', 1200));
        $field->attr('min', 64);
        $field->attr('max', 20000);
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get('InputfieldText');
        $field->attr('name', 'temperature');
        $field->label = $this->_('Temperature');
        $field->attr('type', 'number');
        $field->attr('step', '0.1');
        $field->attr('min', '0');
        $field->attr('max', '2');
        $field->attr('value', (string)$this->setting('temperature', '0.4'));
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'timeout');
        $field->label = $this->_('Timeout (seconds)');
        $field->attr('value', (int)$this->setting('timeout', 60));
        $field->attr('min', 5);
        $field->attr('max', 300);
        $field->columnWidth = 25;
        $fieldset->add($field);

        $field = $modules->get('InputfieldInteger');
        $field->attr('name', 'cacheSeconds');
        $field->label = $this->_('Cache lifetime (seconds)');
        $field->description = $this->_('Set to 0 to disable response caching.');
        $field->attr('value', (int)$this->setting('cacheSeconds', 3600));
        $field->attr('min', 0);
        $field->columnWidth = 25;
        $fieldset->add($field);
        $inputfields->add($fieldset);

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('CTA widget');
        $fieldset->icon = 'commenting';

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'widgetEnabled');
        $field->label = $this->_('Enable the Liora widget');
        if((bool)$this->setting('widgetEnabled', true)) $field->attr('checked', 'checked');
        $fieldset->add($field);

        foreach([
            'widgetHeading' => [$this->_('Heading'), 'Still looking? Ask Liora'],
            'widgetIntro' => [$this->_('Introduction'), 'Tell me what you want to know. Your question also helps us improve this page.'],
            'widgetPlaceholder' => [$this->_('Question placeholder'), 'Ask about products, pairings, brands or cocktails…'],
            'endpoint' => [$this->_('JSON endpoint'), '/agent/'],
        ] as $name => [$label, $default]) {
            $field = $modules->get('InputfieldText');
            $field->attr('name', $name);
            $field->label = $label;
            $field->attr('value', (string)$this->setting($name, $default));
            $fieldset->add($field);
        }

        $preview = $modules->get('InputfieldLiora');
        if($preview) {
            $preview->attr('name', 'lioraPreview');
            $preview->label = $this->_('Preview');
            $preview->value = $this->_('a bottle, cocktail or pairing');
            $preview->collapsed = Inputfield::collapsedYes;
            $fieldset->add($preview);
        }
        $inputfields->add($fieldset);

        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Tracking and privacy');
        $fieldset->icon = 'line-chart';
        $fieldset->collapsed = Inputfield::collapsedYes;

        foreach([
            'maxQuestionLength' => [$this->_('Maximum question length'), 1000, 100, 5000],
            'requestsPerHour' => [$this->_('Questions per session per hour'), 20, 1, 200],
            'historyMessages' => [$this->_('Conversation messages retained in session'), 10, 0, 40],
        ] as $name => [$label, $default, $min, $max]) {
            $field = $modules->get('InputfieldInteger');
            $field->attr('name', $name);
            $field->label = $label;
            $field->attr('value', (int)$this->setting($name, $default));
            $field->attr('min', $min);
            $field->attr('max', $max);
            $field->columnWidth = 33;
            $fieldset->add($field);
        }

        $field = $modules->get('InputfieldCheckbox');
        $field->attr('name', 'deleteDataOnUninstall');
        $field->label = $this->_('Delete tracked questions when Liora is uninstalled');
        $field->description = $this->_('Keep this off unless the demand history should be permanently removed.');
        if((bool)$this->setting('deleteDataOnUninstall', false)) $field->attr('checked', 'checked');
        $fieldset->add($field);
        $inputfields->add($fieldset);

        return $inputfields;
    }

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
            $hash = hash('sha256', 'legacy|' . $original . '|' . $question . '|' . $response);
            if($this->store()->add([
                'request_hash' => $hash,
                'original_query' => $original,
                'question' => $question,
                'response' => $response,
                'context' => 'legacy-field-ai',
                'source_url' => '/agent/',
                'page_id' => (int)$page->id,
                'status' => 'new',
            ])) $count++;
        }
        return $count;
    }

    protected function trackRequest(string $question, string $response, array $meta): void {
        try {
            $usage = (array)($meta['usage'] ?? []);
            $sessionHash = $this->sessionHash();
            $requestHash = hash('sha256', implode('|', [
                $sessionHash,
                $question,
                (string)microtime(true),
                bin2hex(random_bytes(4)),
            ]));
            $this->store()->add([
                'request_hash' => $requestHash,
                'original_query' => (string)($meta['originalQuery'] ?? ''),
                'question' => $question,
                'response' => $response,
                'provider' => (string)($meta['provider'] ?? ''),
                'model' => (string)($meta['model'] ?? ''),
                'context' => (string)($meta['context'] ?? ''),
                'source_url' => (string)($meta['sourceUrl'] ?? ''),
                'page_id' => (int)($meta['pageId'] ?? 0),
                'user_id' => (int)($this->wire('user')->id ?? 0),
                'session_hash' => $sessionHash,
                'tokens_input' => (int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0),
                'tokens_output' => (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0),
                'tokens_total' => (int)($usage['total_tokens'] ?? 0),
                'cached' => !empty($meta['cached']) ? 1 : 0,
                'status' => !empty($meta['error']) ? 'failed' : 'new',
                'error' => (string)($meta['error'] ?? ''),
            ]);
        } catch(\Throwable $e) {
            $this->wire('log')->save('liora', 'Could not track question: ' . $e->getMessage());
        }
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

    protected function sessionHistory(): array {
        $history = $this->wire('session')->get('liora_chat_history');
        return is_array($history) ? $history : [];
    }

    protected function appendSessionHistory(string $question, string $answer): void {
        $limit = max(0, (int)$this->setting('historyMessages', 10));
        if($limit === 0) return;
        $history = $this->sessionHistory();
        $history[] = ['role' => 'user', 'content' => $question];
        $history[] = ['role' => 'assistant', 'content' => $answer];
        $this->wire('session')->set('liora_chat_history', array_slice($history, -$limit));
    }

    protected function configuredProviderModel(): array {
        $selection = trim((string)$this->setting('providerModel', ''));
        if($selection === '' || !str_contains($selection, '|')) return ['', ''];
        [$provider, $model] = explode('|', $selection, 2);
        return [trim($provider), trim($model)];
    }

    protected function modelOptions(): array {
        $squad = $this->squad();
        if(!$squad || !method_exists($squad, 'getProviderDefinitions')) return [];
        $definitions = (array)$squad->getProviderDefinitions();
        $statuses = method_exists($squad, 'getProvidersStatus') ? (array)$squad->getProvidersStatus() : [];
        $options = [];
        foreach($definitions as $provider => $definition) {
            if(empty($statuses[$provider]['active'])) continue;
            $providerLabel = (string)($definition['label'] ?? $provider);
            $models = method_exists($squad, 'getProviderModels')
                ? (array)$squad->getProviderModels((string)$provider)
                : (array)($definition['models'] ?? []);
            foreach($models as $model => $label) {
                $options[$provider . '|' . $model] = $providerLabel . ' — ' . $label . ' (' . $model . ')';
            }
        }
        return $options;
    }

    protected function squad(): ?object {
        try {
            $modules = $this->wire('modules');
            if(!$modules->isInstalled('Squad')) return null;
            $squad = $modules->get('Squad');
            return is_object($squad) && method_exists($squad, 'ask') ? $squad : null;
        } catch(\Throwable $e) {
            return null;
        }
    }

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
        return [(string)$current['content'], $history, trim(implode("\n\n", $systemParts))];
    }

    protected function messageText($content): string {
        if(is_scalar($content)) return trim((string)$content);
        if(!is_array($content)) return '';
        $parts = [];
        foreach($content as $part) {
            if(is_scalar($part)) $parts[] = (string)$part;
            elseif(is_array($part)) {
                $text = $part['text'] ?? $part['content'] ?? '';
                if(is_scalar($text)) $parts[] = (string)$text;
            }
        }
        return trim(implode("\n", array_filter($parts, 'strlen')));
    }

    protected function sanitizeSourceUrl(string $url): string {
        $url = trim($url);
        if($url === '') return '';
        $parts = parse_url($url);
        if($parts === false) return '';
        if(isset($parts['host'])) {
            $host = strtolower((string)$parts['host']);
            $siteHost = strtolower((string)parse_url((string)$this->wire('config')->urls->httpRoot, PHP_URL_HOST));
            if($host !== $siteHost) return '';
        }
        $path = (string)($parts['path'] ?? '/');
        return str_starts_with($path, '/') ? mb_substr($path, 0, 2048) : '';
    }

    protected function setting(string $name, $default) {
        $value = $this->get($name);
        return $value === null || $value === '' ? $default : $value;
    }

    protected function defaultSystemPrompt(): string {
        return 'You are Liora, the concise and trustworthy AI guide for LQRS. '
            . 'Answer questions about spirits, wine, beer, cocktails, brands, production, tasting, food pairings and responsible enjoyment. '
            . 'Use clear Markdown and no more than 250 words. Do not invent product availability, prices, ratings or facts. '
            . 'When uncertain, say so. Suggest relevant ways the visitor could refine their search. Promote responsible drinking.';
    }

    protected function errorResponse(string $message): array {
        return ['success' => false, 'status' => 0, 'error' => $message];
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

    protected function sendJson(array $data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
