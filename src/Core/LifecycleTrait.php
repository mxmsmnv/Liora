<?php namespace ProcessWire;

trait LioraLifecycleTrait {

    public function ___install(): void {
        $this->ensurePermissions();
        $this->store()->ensureTable();
        $this->store()->migrateLegacyQueries();
        if((int)($this->store()->summary()['total'] ?? 0) === 0) $this->importLegacyHistory();
    }

    public function ___upgrade($fromVersion, $toVersion): void {
        $this->ensurePermissions();
        $this->store()->ensureTable();
        $this->store()->migrateLegacyQueries();
        if((int)($this->store()->summary()['total'] ?? 0) === 0) $this->importLegacyHistory();
        if((int)$fromVersion < 121) $this->refreshPageBasedThreadTitles();
    }

    public function ___uninstall(): void {
        if((bool)$this->setting('deleteDataOnUninstall', false)) {
            $this->store()->dropTable();
        }
    }

    public function store(): LioraStore {
        if(!$this->storeInstance) {
            $this->storeInstance = $this->wire(new LioraStore());
        }
        return $this->storeInstance;
    }

    protected function ensurePermissions(): void {
        $permissions = $this->wire('permissions');
        foreach([
            'liora-review' => 'Review Liora visitor conversations',
            'liora-delete' => 'Delete individual Liora messages',
        ] as $name => $title) {
            $permission = $permissions->get($name);
            if($permission && $permission->id) continue;
            $permission = $permissions->add($name);
            if(!$permission || !$permission->id) continue;
            $permission->title = $title;
            $permission->save();
        }
    }

}
