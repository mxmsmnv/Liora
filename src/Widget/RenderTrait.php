<?php namespace ProcessWire;

trait LioraWidgetRenderTrait {

    /**
     * Render the reusable public CTA/chat widget.
     *
     * Options: originalQuery, retrievalQuery, context, sourceUrl, pageId, heading, intro,
     * placeholder, thinkingLabel, initialQuestion, autoSubmitInitialQuestion,
     * showWelcomeMessage, welcomeMessage, showSuggestedPrompts,
     * suggestedPrompts, theme, compact, enabled, endpoint, streaming, localHistory.
     */
    public function renderWidget(array $options = []): string {
        if(!(bool)($options['enabled'] ?? $this->setting('widgetEnabled', true))) return '';

        $san = $this->wire('sanitizer');
        $page = $this->wire('page');
        $query = trim((string)($options['originalQuery'] ?? ''));
        $retrievalQuery = mb_substr(
            trim((string)($options['retrievalQuery'] ?? '')),
            0,
            max(1, (int)$this->setting('maxQuestionLength', 1000))
        );
        $context = trim((string)($options['context'] ?? ($page && $page->template ? $page->template->name : 'site')));
        $sourceUrl = (string)($options['sourceUrl'] ?? ($page && $page->id ? $page->url : ''));
        $pageId = (int)($options['pageId'] ?? ($page && $page->id ? $page->id : 0));
        $heading = (string)($options['heading'] ?? $this->widgetText('widgetHeading'));
        $intro = (string)($options['intro'] ?? $this->widgetText('widgetIntro'));
        if($query !== '') {
            $intro = str_replace('{query}', $query, $intro);
        } else {
            $intro = str_replace('{query}', 'this topic', $intro);
        }
        $placeholder = (string)($options['placeholder'] ?? $this->widgetText('widgetPlaceholder'));
        $thinkingLabel = (string)($options['thinkingLabel'] ?? $this->widgetText('widgetThinking'));
        $initialQuestion = mb_substr(
            trim((string)($options['initialQuestion'] ?? '')),
            0,
            max(1, (int)$this->setting('maxQuestionLength', 1000))
        );
        $autoSubmitInitialQuestion = $initialQuestion !== ''
            && !empty($options['autoSubmitInitialQuestion']);
        $welcomeMessage = (bool)($options['showWelcomeMessage'] ?? $this->setting('showWelcomeMessage', true))
            ? trim((string)($options['welcomeMessage'] ?? $this->widgetText('welcomeMessage')))
            : '';
        $suggestedPrompts = [];
        if((bool)($options['showSuggestedPrompts'] ?? $this->setting('showSuggestedPrompts', true))) {
            $optionPrompts = $options['suggestedPrompts'] ?? null;
            for($index = 1; $index <= 3; $index++) {
                $prompt = is_array($optionPrompts)
                    ? trim((string)($optionPrompts[$index - 1] ?? ''))
                    : trim($this->widgetText('suggestedPrompt' . $index));
                $prompt = mb_substr($prompt, 0, 180);
                if($prompt !== '' && !in_array($prompt, $suggestedPrompts, true)) $suggestedPrompts[] = $prompt;
            }
        }
        $privacyNotice = trim($this->widgetText('privacyNotice'));
        $theme = trim((string)($options['theme'] ?? $this->setting('widgetTheme', 'default')));
        $endpoint = (string)($options['endpoint'] ?? $this->setting('endpoint', '/agent/'));
        $localHistoryEnabled = (bool)($options['localHistory'] ?? $this->setting('localHistoryEnabled', true));
        $csrfName = $this->wire('session')->CSRF->getTokenName();
        $csrfValue = $this->wire('session')->CSRF->getTokenValue();
        $id = 'liora-' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
        $themeCss = $this->themeCss($theme, $id);
        $compact = !empty($options['compact']) ? ' liora-widget--compact' : '';
        $previewOnly = !empty($options['preview']);
        $styles = '';
        $script = '';

        if(!self::$assetsRendered) {
            self::$assetsRendered = true;
            $base = $this->wire('config')->urls->siteModules . 'Liora/assets/';
            $version = self::getModuleInfo()['version'];
            $styles = "<link rel='stylesheet' href='{$base}liora.css?v={$version}'>";
            $script = "<script src='{$base}liora.js?v={$version}'></script>";
        }

        $attrs = [
            'data-endpoint' => $endpoint,
            'data-original-query' => $query,
            'data-retrieval-query' => $retrievalQuery,
            'data-context' => $context,
            'data-source-url' => $sourceUrl,
            'data-page-id' => (string)$pageId,
            'data-initial-question' => $initialQuestion,
            'data-auto-submit-initial-question' => $autoSubmitInitialQuestion ? '1' : '0',
            'data-csrf-name' => $csrfName,
            'data-csrf-value' => $csrfValue,
            'data-stream' => (bool)($options['streaming'] ?? $this->setting('streamingEnabled', true)) ? '1' : '0',
            'data-local-history' => $localHistoryEnabled ? '1' : '0',
            'data-history-limit' => (string)max(1, (int)$this->setting('localHistoryThreads', 10)),
            'data-show-copy' => (bool)$this->setting('showCopyButton', true) ? '1' : '0',
            'data-show-response-time' => (bool)$this->setting('showResponseTime', true) ? '1' : '0',
            'data-show-token-usage' => (bool)$this->setting('showTokenUsage', true) ? '1' : '0',
            'data-welcome-message' => $welcomeMessage,
            'data-previous-label' => $this->widgetText('widgetPrevious'),
            'data-new-label' => $this->widgetText('widgetNew'),
            'data-expand-label' => $this->widgetText('widgetExpand'),
            'data-collapse-label' => $this->widgetText('widgetCompact'),
            'data-edit-title-label' => $this->widgetText('widgetEditTitle'),
            'data-save-title-label' => $this->widgetText('widgetSave'),
            'data-cancel-title-label' => $this->widgetText('widgetCancel'),
            'data-ask-label' => $this->widgetText('widgetAsk'),
            'data-thinking-label' => $thinkingLabel,
            'data-copy-label' => $this->widgetText('widgetCopy'),
            'data-copied-label' => $this->widgetText('widgetCopied'),
            'data-response-time-label' => $this->widgetText('widgetResponseTime'),
            'data-tokens-label' => $this->widgetText('widgetTokens'),
            'data-sources-label' => $this->widgetText('widgetSources'),
            'data-conversation-label' => $this->widgetText('widgetConversation'),
            'data-error-label' => $this->widgetText('widgetGenericError'),
            'data-empty-error-label' => $this->widgetText('widgetEmptyError'),
            'data-connection-error-label' => $this->widgetText('widgetConnectionError'),
        ];
        $dataAttrs = '';
        foreach($attrs as $name => $value) {
            $dataAttrs .= ' ' . $name . '="' . $san->entities($value) . '"';
        }
        $suggestions = '';
        if($suggestedPrompts) {
            $suggestions = "<div class='liora-widget__suggestions' data-liora-suggestions aria-label='"
                . $san->entities($this->widgetText('widgetSuggestionsLabel')) . "'>";
            foreach($suggestedPrompts as $prompt) {
                $escapedPrompt = $san->entities($prompt);
                $suggestions .= "<button type='button' class='liora-widget__suggestion' data-liora-suggestion='{$escapedPrompt}'>{$escapedPrompt}</button>";
            }
            $suggestions .= '</div>';
        }
        $initialWelcome = $welcomeMessage !== ''
            ? "<div class='liora-message liora-message--assistant liora-message--welcome' data-liora-welcome>"
                . "<div class='liora-message__content'><p>" . $san->entities($welcomeMessage) . '</p></div></div>'
            : '';

        $composer = $previewOnly
            ? "<div class='liora-widget__form' aria-label='" . $san->entities($this->widgetText('widgetAskLiora')) . "'>"
                . "<label class='liora-sr-only' for='{$id}-question'>" . $san->entities($this->widgetText('widgetAskLiora')) . "</label>"
                . "<input id='{$id}-question' type='text' value='' readonly tabindex='-1' placeholder='"
                . $san->entities($placeholder) . "'>"
                . "<button type='button' tabindex='-1'>" . $san->entities($this->widgetText('widgetAsk')) . "</button>"
                . "</div>"
            : "<form class='liora-widget__form' data-liora-form>"
                . "<label class='liora-sr-only' for='{$id}-question'>" . $san->entities($this->widgetText('widgetAskLiora')) . "</label>"
                . "<input id='{$id}-question' data-liora-input type='text' maxlength='"
                . (int)$this->setting('maxQuestionLength', 1000) . "' autocomplete='off' placeholder='"
                . $san->entities($placeholder) . "' required disabled>"
                . "<button type='submit' data-liora-submit disabled>" . $san->entities($this->widgetText('widgetAsk')) . "</button>"
                . "</form>";

        return $styles . $themeCss
            . "<section id='{$id}' class='liora-widget{$compact}' aria-busy='true'{$dataAttrs}>"
            . "<div class='liora-widget__header'><span class='liora-widget__icon' aria-hidden='true'>✦</span>"
            . "<div><h2>" . $san->entities($heading) . "</h2><p>" . $san->entities($intro) . "</p></div></div>"
            . "<div class='liora-widget__toolbar' data-liora-toolbar hidden>"
            . "<button type='button' data-liora-history-button>" . $san->entities($this->widgetText('widgetPrevious')) . "</button>"
            . "<button type='button' data-liora-new-button>" . $san->entities($this->widgetText('widgetNew')) . "</button>"
            . "<button type='button' data-liora-expand-button aria-pressed='false'>" . $san->entities($this->widgetText('widgetExpand')) . "</button></div>"
            . "<div class='liora-widget__history' data-liora-history-panel hidden></div>"
            . "<div class='liora-widget__messages' data-liora-messages aria-live='polite'>{$initialWelcome}</div>"
            . $suggestions
            . $composer
            . "<div class='liora-widget__notes'><p>" . $san->entities($this->widgetText('widgetAiDisclaimer')) . '</p>'
            . ((bool)$this->setting('showPrivacyNotice', true) && $privacyNotice !== ''
                ? "<p class='liora-widget__privacy'>" . $san->entities($privacyNotice) . '</p>'
                : '')
            . ($localHistoryEnabled
                ? "<p>" . $san->entities($this->widgetText('widgetHistoryNotice')) . '</p>'
                : '')
            . '</div>'
            . "</section>"
            . $script;
    }

}
