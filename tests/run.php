<?php

$root = dirname(__DIR__);
$required = [
    'Liora.module.php',
    'LioraStore.php',
    'InputfieldLiora.module.php',
    'ProcessLiora.module.php',
    'assets/liora.css',
    'assets/liora-admin.css',
    'assets/liora-admin.js',
    'assets/liora.js',
    'assets/Liora.png',
    'docs/INTEGRATION.md',
    'themes/default.json',
    'themes/light.json',
    'themes/dark.json',
];

foreach($required as $file) {
    if(!is_file($root . '/' . $file)) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }
}

$module = file_get_contents($root . '/Liora.module.php');
$store = file_get_contents($root . '/LioraStore.php');
$inputfield = file_get_contents($root . '/InputfieldLiora.module.php');
$process = file_get_contents($root . '/ProcessLiora.module.php');
$javascript = file_get_contents($root . '/assets/liora.js');
$adminJavascript = file_get_contents($root . '/assets/liora-admin.js');
$widgetCss = file_get_contents($root . '/assets/liora.css');
$theme = json_decode(file_get_contents($root . '/themes/default.json'), true);
$lightTheme = json_decode(file_get_contents($root . '/themes/light.json'), true);
$darkTheme = json_decode(file_get_contents($root . '/themes/dark.json'), true);

$checks = [
    'Liora class' => str_contains($module, 'class Liora extends WireData implements Module, ConfigurableModule'),
    'submodule install list' => str_contains($module, "'installs' => ['InputfieldLiora', 'ProcessLiora']"),
    'release versions' => str_contains($module, "'version' => 161")
        && str_contains($inputfield, "'version' => 161")
        && str_contains($process, "'version' => 161"),
    'Squad dependency' => str_contains($module, "'Squad'"),
    'legacy import' => str_contains($module, 'importLegacyHistory'),
    'model selector' => str_contains($module, "attr('name', 'providerModel')"),
    'thread storage' => str_contains($store, 'liora_threads') && str_contains($store, 'liora_messages'),
    'privacy storage' => str_contains($store, '`session_hash`') && !str_contains($store, '`ip_address`'),
    'GeoIP enrichment' => str_contains($module, "isInstalled('GeoIP')") && !str_contains($store, '`ip_address`'),
    'streaming endpoint' => str_contains($module, 'application/x-ndjson') && str_contains($module, 'streamChat('),
    'local conversation history' => str_contains($javascript, 'localStorage') && str_contains($javascript, 'previousLabel'),
    'localized widget text' => str_contains($module, 'widgetTextDefaults()')
        && str_contains($module, 'getWidgetTextPresets()')
        && str_contains($module, '$field->useLanguages = true')
        && str_contains($module, "'data-sources-label'")
        && str_contains($javascript, 'widget.dataset.sourcesLabel'),
    'concise conversation titles' => str_contains($module, 'conversationTitle(')
        && str_contains($store, 'pageBasedThreadTitles')
        && str_contains($javascript, 'titleVersion: 2'),
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
    'thinking state' => str_contains($module, "'data-thinking-label'")
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
    'optional Atlas RAG' => str_contains($module, "attr('name', 'atlasEnabled')")
        && str_contains($module, 'atlasContext(')
        && str_contains($module, "'rag_sources'")
        && str_contains($javascript, 'rag_sources'),
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
