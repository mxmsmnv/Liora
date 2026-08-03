<?php namespace ProcessWire;

require_once __DIR__ . '/src/Storage/SchemaTrait.php';
require_once __DIR__ . '/src/Storage/ThreadsTrait.php';
require_once __DIR__ . '/src/Storage/MessagesTrait.php';
require_once __DIR__ . '/src/Storage/InsightsTrait.php';
require_once __DIR__ . '/src/Storage/SupportTrait.php';

/**
 * Thread/message persistence for Liora.
 *
 * Session identifiers are one-way hashes; IP addresses and user agents are
 * intentionally not stored.
 */
class LioraStore extends Wire {

    use LioraStoreSchemaTrait;
    use LioraStoreThreadsTrait;
    use LioraStoreMessagesTrait;
    use LioraStoreInsightsTrait;
    use LioraStoreSupportTrait;

    public const THREADS = 'liora_threads';
    public const MESSAGES = 'liora_messages';
    public const LEGACY = 'liora_queries';

    protected bool $tablesEnsured = false;
}
