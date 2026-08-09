<?php

$root = dirname(__DIR__);
$required = [
    'API.md',
    'CHANGELOG.md',
    'EXAMPLES.md',
    'Liora.module.php',
    'LioraStore.php',
    'InputfieldLiora.module.php',
    'LICENSE',
    'ProcessLiora.module.php',
    'README.md',
    'assets/liora.css',
    'assets/liora-admin.css',
    'assets/liora-admin.js',
    'assets/liora.js',
    'assets/Liora.png',
    'assets/readme-doodle.png',
    'docs/INTEGRATION.md',
    'themes/default.json',
    'themes/light.json',
    'themes/dark.json',
    'src/AI/ServiceApiTrait.php',
    'src/Admin/ExecuteTrait.php',
    'src/Config/ConfigUiTrait.php',
    'src/Conversation/ConversationTrait.php',
    'src/Core/LifecycleTrait.php',
    'src/Http/EndpointTrait.php',
    'src/Localization/LocalizationTrait.php',
    'src/Retrieval/AtlasTrait.php',
    'src/Storage/SchemaTrait.php',
    'src/Support/SettingsTrait.php',
    'src/Widget/RenderTrait.php',
];
if(is_dir($root . '/.git')) {
    array_push($required, '.github/FUNDING.yml', 'AGENTS.md');
}

foreach($required as $file) {
    if(!is_file($root . '/' . $file)) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }
}

function phpSources(string $root, string $entry, array $directories): string {
    $source = (string)file_get_contents($root . '/' . $entry);
    foreach($directories as $directory) {
        foreach(glob($root . '/' . $directory . '/*.php') ?: [] as $file) {
            $source .= "\n" . file_get_contents($file);
        }
    }
    return $source;
}

$module = phpSources($root, 'Liora.module.php', [
    'src/AI',
    'src/Config',
    'src/Conversation',
    'src/Core',
    'src/Http',
    'src/Localization',
    'src/Retrieval',
    'src/Support',
    'src/Widget',
]);
$store = phpSources($root, 'LioraStore.php', ['src/Storage']);
$inputfield = file_get_contents($root . '/InputfieldLiora.module.php');
$process = phpSources($root, 'ProcessLiora.module.php', ['src/Admin']);
$javascript = file_get_contents($root . '/assets/liora.js');
$adminJavascript = file_get_contents($root . '/assets/liora-admin.js');
$adminCss = file_get_contents($root . '/assets/liora-admin.css');
$widgetCss = file_get_contents($root . '/assets/liora.css');
$theme = json_decode(file_get_contents($root . '/themes/default.json'), true);
$lightTheme = json_decode(file_get_contents($root . '/themes/light.json'), true);
$darkTheme = json_decode(file_get_contents($root . '/themes/dark.json'), true);

$checks = [
    'Liora class' => str_contains($module, 'class Liora extends WireData implements Module, ConfigurableModule'),
    'submodule install list' => str_contains($module, "'installs' => ['InputfieldLiora', 'ProcessLiora']"),
    'release versions' => str_contains($module, "'version' => 1141")
        && str_contains($inputfield, "'version' => 1141")
        && str_contains($process, "'version' => 1141"),
    'admin dashboard prioritizes review workflow' => str_contains($process, 'renderWorkspaceIntro($summary)')
        && strpos($process, 'renderThreads($threads)') < strpos($process, 'renderTopDemand($top)')
        && str_contains($process, "class='liora-admin-filters'")
        && str_contains($process, "class='uk-subnav uk-subnav-pill'")
        && str_contains($process, "class='uk-active'")
        && str_contains($process, "class='liora-admin-status-badge is-"),
    'admin controls follow design-system alignment' => str_contains($adminCss, '.liora-admin-filters .uk-subnav-pill > .uk-active > a')
        && str_contains($adminCss, '.ProcessLiora .uk-button > .fa')
        && str_contains($adminCss, 'vertical-align: baseline;'),
    'provider model integration API' => str_contains($module, 'public function getProviderModelOptions(): array'),
    'widget initializes without waiting for the whole page' => str_contains($module, '. $script;')
        && str_contains($module, "liora.js?v={\$version}'></script>")
        && str_contains($module, "aria-busy='true'")
        && str_contains($module, "required disabled")
        && str_contains($module, '{$initialWelcome}')
        && str_contains($javascript, "boot();")
        && str_contains($javascript, "widget.removeAttribute('aria-busy')")
        && str_contains($javascript, "document.addEventListener('DOMContentLoaded', boot, {once: true})"),
    'widget visual hierarchy and contrast' => str_contains($widgetCss, '--liora-text-secondary:')
        && str_contains($widgetCss, '--liora-control-border:')
        && str_contains($widgetCss, 'border-left:4px solid var(--liora-accent)')
        && str_contains($widgetCss, '.liora-widget__form {')
        && str_contains($widgetCss, 'background:var(--liora-surface-muted);'),
    'config preview is not a nested form' => str_contains($module, "\$preview->previewOnly = true")
        && str_contains($module, "\$previewOnly = !empty(\$options['preview'])")
        && str_contains($module, "<div class='liora-widget__form'")
        && str_contains($inputfield, "'preview' => (bool)\$this->previewOnly"),
    'Squad dependency' => str_contains($module, "'Squad'"),
    'legacy import' => str_contains($module, 'importLegacyHistory'),
    'model selector' => str_contains($module, "attr('name', 'providerModel')"),
    'optional web search' => str_contains($module, "attr('name', 'webSearchEnabled')")
        && str_contains($module, "attr('name', 'webSearchMode')")
        && str_contains($module, 'resolveWebSearch(')
        && str_contains($module, 'questionNeedsWebSearch(')
        && str_contains($module, "'webSearch' =>")
        && str_contains($module, "'webSearchMaxResults' =>")
        && str_contains($module, "'sources' => (array)"),
    'thread storage' => str_contains($store, 'liora_threads') && str_contains($store, 'liora_messages'),
    'privacy storage' => str_contains($store, '`session_hash`') && !str_contains($store, '`ip_address`'),
    'GeoIP enrichment' => str_contains($module, "isInstalled('GeoIP')") && !str_contains($store, '`ip_address`'),
    'streaming endpoint' => str_contains($module, 'application/x-ndjson') && str_contains($module, 'streamChat('),
    'streamed final answer replacement' => str_contains($module, "'response' => \$answer")
        && str_contains($javascript, "typeof data.response === 'string'"),
    'local conversation history' => str_contains($javascript, 'localStorage') && str_contains($javascript, 'previousLabel'),
    'localized widget text' => str_contains($module, 'widgetTextDefaults()')
        && str_contains($module, 'getWidgetTextPresets()')
        && str_contains($module, '$field->useLanguages = true')
        && str_contains($module, "'data-sources-label'")
        && str_contains($javascript, 'widget.dataset.sourcesLabel'),
    'concise conversation titles' => str_contains($module, 'conversationTitle(')
        && str_contains($store, 'pageBasedThreadTitles')
        && str_contains($javascript, 'titleVersion: 2'),
    'conversation continuity' => str_contains($module, 'conversationContinuityPrompt()')
        && str_contains($module, 'Treat a short visitor reply as an answer')
        && str_contains($module, 'Recent visitor requests and constraints:')
        && str_contains($module, 'count($visitorTurns) < 3'),
    'editable conversation titles' => str_contains($module, "\$action === 'rename'")
        && str_contains($javascript, "action: 'rename'")
        && str_contains($javascript, 'liora-widget__history-edit-form'),
    'long answer usability' => str_contains($javascript, 'scrollToMessageStart') && str_contains($javascript, 'liora-widget--expanded'),
    'message metadata controls' => str_contains($module, "attr('name', 'showCopyButton')")
        && str_contains($module, "attr('name', 'showResponseTime')")
        && str_contains($module, "attr('name', 'showTokenUsage')")
        && str_contains($javascript, 'addMessageMeta')
        && str_contains($javascript, 'responseTimeMs')
        && str_contains($javascript, 'tokensUsed'),
    'persistent technical diagnostics' => str_contains($store, '`response_time_ms`')
        && str_contains($store, '`metadata` MEDIUMTEXT')
        && str_contains($store, 'sanitizeMetadata(')
        && str_contains($module, 'responseTechnicalMetadata(')
        && str_contains($module, 'withEndpointTechnicalMetadata(')
        && str_contains($module, "'system_prompt_sha256'")
        && str_contains($process, 'renderMessageDiagnostics(')
        && str_contains($process, "Technical details")
        && str_contains($process, 'Technical metadata:')
        && str_contains($adminCss, '.liora-admin-message__technical'),
    'thinking state' => str_contains($module, "'data-thinking-label'")
        && str_contains($module, "\$options['thinkingLabel']")
        && str_contains($javascript, 'liora-message--thinking')
        && !str_contains($javascript, "submit.textContent = '…'"),
    'safe structured answer rendering' => str_contains($javascript, 'const inlineMarkdown')
        && str_contains($javascript, 'const safeMarkdown')
        && str_contains($javascript, 'liora-message__citation')
        && str_contains($javascript, 'LIORA_INTERNAL_LINK_')
        && str_contains($javascript, 'liora-message__link')
        && str_contains($javascript, 'escapeHtml(codeLines')
        && str_contains($widgetCss, 'width:fit-content')
        && str_contains($widgetCss, '.liora-message__content h3'),
    'stay-on-site policy' => str_contains($module, "attr('name', 'restrictExternalLinks')")
        && str_contains($module, "attr('name', 'externalLinksPrompt')")
        && str_contains($module, 'defaultExternalLinksPrompt()')
        && str_contains($module, 'restrictExternalLinks(')
        && str_contains($module, 'isSameSiteUrl('),
    'JSON widget theme' => is_array($theme) && !empty($theme['variables']['messagesMaxHeight'])
        && str_contains($module, 'themeStyle('),
    'adaptive dark widget theme' => ($theme['mode'] ?? '') === 'auto'
        && !empty($theme['darkVariables']['surface'])
        && ($lightTheme['mode'] ?? '') === 'light'
        && ($darkTheme['mode'] ?? '') === 'dark'
        && str_contains($module, 'prefers-color-scheme:dark')
        && str_contains($module, 'themeCss(')
        && str_contains($module, 'color-scheme:')
        && str_contains($widgetCss, 'var(--liora-header-surface)')
        && str_contains($widgetCss, 'var(--liora-input-surface)'),
    'welcome preview' => str_contains($module, "attr('name', 'showWelcomeMessage')")
        && str_contains($javascript, 'showWelcome')
        && str_contains($javascript, 'dataset.lioraWelcome'),
    'starter question prompts' => str_contains($module, "attr('name', 'showSuggestedPrompts')")
        && str_contains($module, "'suggestedPrompt1'")
        && str_contains($module, "'widgetSuggestionsLabel'")
        && str_contains($javascript, "form.requestSubmit()")
        && str_contains($javascript, "data-liora-suggestion")
        && str_contains($widgetCss, '.liora-widget__suggestions')
        && !str_contains($widgetCss, 'transform:translateY(-1px)'),
    'initial question auto submit' => str_contains($module, "'data-initial-question'")
        && str_contains($module, "'data-auto-submit-initial-question'")
        && str_contains($javascript, 'widget.dataset.initialQuestion')
        && str_contains($javascript, 'widget.dataset.autoSubmitInitialQuestion')
        && str_contains($javascript, 'queueMicrotask')
        && str_contains($javascript, 'form.requestSubmit()'),
    'canonical retrieval query' => str_contains($module, "\$options['retrievalQuery']")
        && str_contains($module, "'data-retrieval-query'")
        && str_contains($module, "\$input['retrievalQuery']")
        && str_contains($module, "? \$retrievalQuery")
        && str_contains($module, "\$retrievalQuery . \"\\n\" . \$retrievalQuestion")
        && str_contains($module, 'Treat this as a search hint rather than proof')
        && str_contains($module, "'retrieval_query_supplied'")
        && str_contains($javascript, 'retrievalQuery: widget.dataset.retrievalQuery'),
    'optional Atlas RAG' => str_contains($module, "attr('name', 'atlasEnabled')")
        && str_contains($module, 'atlasContext(')
        && str_contains($module, "'rag_sources'")
        && str_contains($javascript, 'rag_sources'),
    'adaptive Atlas routing' => str_contains($module, "attr('name', 'atlasRetrievalMode')")
        && str_contains($module, "addOption('auto'")
        && str_contains($module, "attr('name', 'atlasLexicalMinScore')")
        && str_contains($module, 'atlasNeedsSemanticFallback(')
        && str_contains($module, "method_exists(\$atlas, 'lexicalSearch')")
        && str_contains($module, "\$atlas->search(")
        && str_contains($module, "'semantic_attempted'")
        && str_contains($module, "'lexical_ms'")
        && str_contains($module, "'semantic_ms'")
        && str_contains($module, 'answer from reliable general knowledge instead'),
    'optional Vox context' => str_contains($module, "attr('name', 'voxEnabled')")
        && str_contains($module, "isInstalled('Vox')")
        && str_contains($module, "\$vox->getEntries(")
        && str_contains($module, "'status' => 'published'")
        && str_contains($module, 'getEntryFieldValues')
        && str_contains($module, 'Vox excerpts are published user-generated community content')
        && str_contains($module, 'retrievalQuestion('),
    'Inputfield class' => str_contains($inputfield, 'class InputfieldLiora extends Inputfield'),
    'integration guidance' => str_contains($module, 'integrationExamplesMarkup()')
        && str_contains($module, 'Allow the ready-made Liora chat widget to render')
        && str_contains(file_get_contents($root . '/docs/INTEGRATION.md'), 'Custom frontend without the widget'),
    'Process class' => str_contains($process, 'class ProcessLiora extends Process'),
    'admin thread rendering' => str_contains($process, 'recentThreads') && str_contains($process, '<blockquote'),
    'admin conversation UX' => str_contains($process, 'renderConfigurationNotice(')
        && str_contains($process, 'renderSettingsFooter(')
        && str_contains($process, 'liora-admin-message__identity')
        && str_contains($process, 'liora-admin-thread__query'),
    'admin conversation pagination' => str_contains($store, 'function countThreads(')
        && str_contains($store, 'OFFSET {$offset}')
        && str_contains($process, 'renderPagination('),
    'admin collapsed thread state' => str_contains($process, 'data-liora-thread-toggle')
        && str_contains($process, 'is-collapsed')
        && str_contains($adminJavascript, 'localStorage')
        && str_contains($adminJavascript, 'scrollY')
        && str_contains($adminJavascript, 'topBefore'),
    'admin conversation copy' => str_contains($process, 'threadContextText(')
        && str_contains($process, 'data-liora-thread-copy')
        && str_contains($process, 'data-liora-thread-context')
        && str_contains($adminJavascript, 'navigator.clipboard.writeText')
        && str_contains($adminJavascript, 'copyText(context.value'),
    'CSRF validation' => str_contains($process, 'CSRF->validate()'),
    'admin message deletion' => str_contains($store, 'function deleteMessage(')
        && str_contains($process, "post('action') === 'delete_message'")
        && str_contains($process, "hasPermission('liora-delete')")
        && str_contains($process, "name='message_id'")
        && str_contains($module, 'ensurePermissions()'),
    'admin thread deletion' => str_contains($store, 'function deleteThread(')
        && str_contains($process, "post('action') === 'delete_thread'")
        && str_contains($process, "name='thread_id'")
        && str_contains($process, 'Delete conversation'),
];

foreach($checks as $label => $ok) {
    if(!$ok) {
        fwrite(STDERR, "Failed: {$label}\n");
        exit(1);
    }
}

echo "Liora smoke tests passed\n";
