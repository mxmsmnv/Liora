<?php namespace ProcessWire;

require_once __DIR__ . '/src/Admin/ExecuteTrait.php';
require_once __DIR__ . '/src/Admin/DashboardTrait.php';
require_once __DIR__ . '/src/Admin/ThreadsTrait.php';
require_once __DIR__ . '/src/Admin/MessagesTrait.php';

/**
 * ProcessLiora — conversation and demand dashboard.
 */
class ProcessLiora extends Process {

    use ProcessLioraExecuteTrait;
    use ProcessLioraDashboardTrait;
    use ProcessLioraThreadsTrait;
    use ProcessLioraMessagesTrait;

    public static function getModuleInfo(): array {
        return [
            'title' => 'Liora Insights',
            'version' => 100,
            'summary' => 'Review Liora conversations and turn visitor demand into site content.',
            'author' => 'Maxim Semenov',
            'icon' => 'comments',
            'requires' => ['Liora'],
            'permission' => 'liora-review',
            'permissions' => [
                'liora-review' => 'Review Liora visitor conversations',
                'liora-delete' => 'Delete individual Liora messages',
            ],
            'page' => ['name' => 'liora', 'parent' => 'setup', 'title' => 'Liora Insights'],
        ];
    }

    public function init(): void {
        parent::init();
        $base = $this->wire('config')->urls->siteModules . 'Liora/assets/';
        $version = self::getModuleInfo()['version'];
        $this->wire('config')->styles->add($base . 'liora-admin.css?v=' . $version);
        $this->wire('config')->scripts->add($base . 'liora-admin.js?v=' . $version);
    }

}
