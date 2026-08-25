<?php
$module = (string)file_get_contents(dirname(__DIR__) . '/Liora.module.php');
$trait = (string)file_get_contents(dirname(__DIR__) . '/src/McpProviderTrait.php');
$checks = [str_contains($module, "'mcpProvider' => true"), str_contains($trait, "'liora_status'"), !str_contains($trait, '->chat('), !str_contains($trait, 'topDemand('), str_contains($trait, "'additionalProperties' => false")];
if(in_array(false, $checks, true)) { fwrite(STDERR, "Liora MCP provider contract failed.\n"); exit(1); }
echo "Liora MCP provider contract passed.\n";
