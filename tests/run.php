<?php

$root = dirname(__DIR__);
$required = [
    'Liora.module.php',
    'LioraStore.php',
    'InputfieldLiora.module.php',
    'ProcessLiora.module.php',
    'assets/liora.css',
    'assets/liora.js',
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

$checks = [
    'Liora class' => str_contains($module, 'class Liora extends WireData implements Module, ConfigurableModule'),
    'submodule install list' => str_contains($module, "'installs' => ['InputfieldLiora', 'ProcessLiora']"),
    'Squad dependency' => str_contains($module, "'Squad'"),
    'legacy import' => str_contains($module, 'importLegacyHistory'),
    'model selector' => str_contains($module, "attr('name', 'providerModel')"),
    'privacy table' => str_contains($store, '`session_hash`') && !str_contains($store, '`ip_address`'),
    'Inputfield class' => str_contains($inputfield, 'class InputfieldLiora extends Inputfield'),
    'Process class' => str_contains($process, 'class ProcessLiora extends Process'),
    'CSRF validation' => str_contains($process, 'CSRF->validate()'),
];

foreach($checks as $label => $ok) {
    if(!$ok) {
        fwrite(STDERR, "Failed: {$label}\n");
        exit(1);
    }
}

echo "Liora smoke tests passed\n";
