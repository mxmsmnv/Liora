<?php

$root = dirname(__DIR__);
$required = [
    'Liora.module.php',
    'LioraStore.php',
    'InputfieldLiora.module.php',
    'ProcessLiora.module.php',
    'assets/liora.css',
    'assets/liora-admin.css',
    'assets/liora.js',
    'themes/default.json',
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
$theme = json_decode(file_get_contents($root . '/themes/default.json'), true);

$checks = [
    'Liora class' => str_contains($module, 'class Liora extends WireData implements Module, ConfigurableModule'),
    'submodule install list' => str_contains($module, "'installs' => ['InputfieldLiora', 'ProcessLiora']"),
    'release versions' => str_contains($module, "'version' => 121")
        && str_contains($inputfield, "'version' => 121")
        && str_contains($process, "'version' => 121"),
    'Squad dependency' => str_contains($module, "'Squad'"),
    'legacy import' => str_contains($module, 'importLegacyHistory'),
    'model selector' => str_contains($module, "attr('name', 'providerModel')"),
    'thread storage' => str_contains($store, 'liora_threads') && str_contains($store, 'liora_messages'),
    'privacy storage' => str_contains($store, '`session_hash`') && !str_contains($store, '`ip_address`'),
    'GeoIP enrichment' => str_contains($module, "isInstalled('GeoIP')") && !str_contains($store, '`ip_address`'),
    'streaming endpoint' => str_contains($module, 'application/x-ndjson') && str_contains($module, 'streamChat('),
    'local conversation history' => str_contains($javascript, 'localStorage') && str_contains($javascript, 'Previous conversations'),
    'concise conversation titles' => str_contains($module, 'conversationTitle(')
        && str_contains($store, 'pageBasedThreadTitles')
        && str_contains($javascript, 'titleVersion: 2'),
    'long answer usability' => str_contains($javascript, 'scrollToMessageStart') && str_contains($javascript, 'liora-widget--expanded'),
    'JSON widget theme' => is_array($theme) && !empty($theme['variables']['messagesMaxHeight'])
        && str_contains($module, 'themeStyle('),
    'welcome preview' => str_contains($module, "attr('name', 'showWelcomeMessage')")
        && str_contains($javascript, 'showWelcome')
        && str_contains($javascript, 'dataset.lioraWelcome'),
    'optional Atlas RAG' => str_contains($module, "attr('name', 'atlasEnabled')")
        && str_contains($module, 'atlasContext(')
        && str_contains($module, "'rag_sources'")
        && str_contains($javascript, 'rag_sources'),
    'Inputfield class' => str_contains($inputfield, 'class InputfieldLiora extends Inputfield'),
    'Process class' => str_contains($process, 'class ProcessLiora extends Process'),
    'admin thread rendering' => str_contains($process, 'recentThreads') && str_contains($process, '<blockquote'),
    'CSRF validation' => str_contains($process, 'CSRF->validate()'),
];

foreach($checks as $label => $ok) {
    if(!$ok) {
        fwrite(STDERR, "Failed: {$label}\n");
        exit(1);
    }
}

echo "Liora smoke tests passed\n";
