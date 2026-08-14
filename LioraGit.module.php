<?php namespace ProcessWire;

require_once __DIR__ . '/LioraGitStore.php';
require_once __DIR__ . '/src/Git/LifecycleTrait.php';
require_once __DIR__ . '/src/Git/ConfigTrait.php';
require_once __DIR__ . '/src/Git/GitHubTrait.php';
require_once __DIR__ . '/src/Git/IndexTrait.php';
require_once __DIR__ . '/src/Git/ChatTrait.php';
require_once __DIR__ . '/src/Git/ProposalTrait.php';
require_once __DIR__ . '/src/Git/EndpointTrait.php';

/** Optional GitHub-backed shared memory companion for Liora. */
class LioraGit extends WireData implements Module, ConfigurableModule {

    use LioraGitLifecycleTrait;
    use LioraGitConfigTrait;
    use LioraGitGitHubTrait;
    use LioraGitIndexTrait;
    use LioraGitChatTrait;
    use LioraGitProposalTrait;
    use LioraGitEndpointTrait;

    protected ?LioraGitStore $storeInstance = null;
    protected string $lastError = '';

    public static function getModuleInfo(): array {
        return [
            'title' => 'Liora Git',
            'version' => 100,
            'summary' => 'Optional authenticated GitHub-backed shared memory for Liora.',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/Liora',
            'icon' => 'github',
            'singular' => true,
            'autoload' => false,
            'requires' => ['ProcessWire>=3.0.210', 'PHP>=8.1', 'Liora', 'Atlas'],
            'installs' => ['ProcessLioraGit'],
        ];
    }

    public function store(): LioraGitStore {
        if(!$this->storeInstance) $this->storeInstance = $this->wire(new LioraGitStore());
        return $this->storeInstance;
    }

    public function lastError(): string { return $this->lastError; }
}
