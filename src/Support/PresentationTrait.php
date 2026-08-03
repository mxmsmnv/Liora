<?php namespace ProcessWire;

trait LioraPresentationTrait {

    protected function configuredProviderModel(): array {
        $selection = trim((string)$this->setting('providerModel', ''));
        if($selection === '' || !str_contains($selection, '|')) return ['', ''];
        [$provider, $model] = explode('|', $selection, 2);
        return [trim($provider), trim($model)];
    }

    protected function themeOptions(): array {
        $options = [];
        foreach(glob(__DIR__ . '/themes/*.json') ?: [] as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            if(!preg_match('/^[a-z0-9_-]+$/i', $key)) continue;
            $data = json_decode((string)file_get_contents($file), true);
            if(!is_array($data)) continue;
            $options[$key] = trim((string)($data['name'] ?? '')) ?: ucfirst($key);
        }
        if(isset($options['default'])) {
            $default = $options['default'];
            unset($options['default']);
            $options = ['default' => $default] + $options;
        }
        return $options ?: ['default' => 'Liora default'];
    }

    protected function integrationExamplesMarkup(): string {
        $san = $this->wire('sanitizer');
        $widgetExample = <<<'PHP'
<?php
$liora = $modules->get('Liora');
echo $liora->renderWidget([
    'originalQuery' => $searchQuery ?? '',
    'context' => $page->template->name,
    'sourceUrl' => $page->url,
    'pageId' => $page->id,
]);
PHP;
        $serverExample = <<<'PHP'
<?php
$result = $modules->get('Liora')->ask('Suggest a food pairing.', [
    'pageId' => $page->id,
]);

if($result['success']) {
    echo $sanitizer->entities($result['content']);
}
PHP;
        $docsUrl = 'https://github.com/mxmsmnv/Liora/blob/main/docs/INTEGRATION.md';

        return "<div class='liora-integration-help'>"
            . '<p>' . $this->_('Choose the integration that matches the page:') . '</p>'
            . '<ul>'
            . '<li><strong>' . $this->_('Ready-made chat:') . '</strong> '
            . $this->_('render the complete conversation UI, LocalStorage history, streaming and tracked Threads.') . '</li>'
            . '<li><strong>' . $this->_('Inputfield:') . '</strong> '
            . $this->_('add InputfieldLiora to a ProcessWire form or admin preview.') . '</li>'
            . '<li><strong>' . $this->_('Server API without a widget:') . '</strong> '
            . $this->_('call ask(), chat(), complete(), or streamChat() from PHP. Direct calls do not create Insights Threads.') . '</li>'
            . '<li><strong>' . $this->_('Custom frontend:') . '</strong> '
            . $this->_('POST to the configured JSON endpoint to retain Threads, page context, Atlas sources and analytics.') . '</li>'
            . '</ul>'
            . '<h4>' . $this->_('Ready-made chat in a template') . '</h4>'
            . '<pre><code>' . $san->entities($widgetExample) . '</code></pre>'
            . '<h4>' . $this->_('Server-side call without chat UI') . '</h4>'
            . '<pre><code>' . $san->entities($serverExample) . '</code></pre>'
            . "<p><a class='uk-button uk-button-default uk-button-small' href='{$docsUrl}' target='_blank' "
            . "rel='noopener noreferrer'><i class='fa fa-book' aria-hidden='true'></i> "
            . $this->_('Open the complete integration guide') . '</a></p></div>';
    }

    protected function themeData(string $theme): array {
        $theme = preg_match('/^[a-z0-9_-]+$/i', $theme) ? $theme : 'default';
        $path = __DIR__ . '/themes/' . $theme . '.json';
        if(!is_file($path)) $path = __DIR__ . '/themes/default.json';
        $data = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        return is_array($data) ? $data : [];
    }

    protected function themeStyle(string $theme, string $group = 'variables'): string {
        $data = $this->themeData($theme);
        $variables = (array)($data[$group] ?? []);
        $allowed = [
            'accent' => '--liora-accent',
            'accentDark' => '--liora-accent-dark',
            'onAccent' => '--liora-on-accent',
            'surface' => '--liora-surface',
            'surfaceMuted' => '--liora-surface-muted',
            'headerSurface' => '--liora-header-surface',
            'hoverSurface' => '--liora-hover-surface',
            'inputSurface' => '--liora-input-surface',
            'text' => '--liora-text',
            'textMuted' => '--liora-text-muted',
            'border' => '--liora-border',
            'focusRing' => '--liora-focus-ring',
            'dangerSurface' => '--liora-danger-surface',
            'dangerBorder' => '--liora-danger-border',
            'dangerText' => '--liora-danger-text',
            'radius' => '--liora-radius',
            'shadow' => '--liora-shadow',
            'messagesMaxHeight' => '--liora-messages-max-height',
            'messageMaxWidth' => '--liora-message-max-width',
        ];
        $style = [];
        foreach($allowed as $key => $property) {
            $value = trim((string)($variables[$key] ?? ''));
            if($value === '' || preg_match('/[;{}<>]/', $value)) continue;
            $style[] = $property . ':' . mb_substr($value, 0, 200);
        }
        return implode(';', $style);
    }

    protected function themeCss(string $theme, string $id): string {
        $data = $this->themeData($theme);
        $mode = (string)($data['mode'] ?? 'light');
        if(!in_array($mode, ['auto', 'light', 'dark'], true)) $mode = 'light';
        $base = $this->themeStyle($theme);
        $dark = $mode === 'auto' ? $this->themeStyle($theme, 'darkVariables') : '';
        $colorScheme = $mode === 'auto'
            ? ($dark !== '' ? 'light dark' : 'light')
            : $mode;
        $selector = '#' . preg_replace('/[^a-z0-9_-]/i', '', $id);
        $css = $selector . '{color-scheme:' . $colorScheme . ';' . $base . '}';
        if($dark !== '') {
            $css .= '@media (prefers-color-scheme:dark){' . $selector . '{' . $dark . '}}';
        }
        return "<style data-liora-theme='{$mode}'>{$css}</style>";
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

}
