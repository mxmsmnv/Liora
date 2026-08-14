<?php namespace ProcessWire;

trait LioraGitLifecycleTrait {

    public function ___install(): void {
        foreach([
            'liora-git-chat' => 'Use the private Liora Git chat',
            'liora-git-write' => 'Propose and confirm Liora Git writes',
            'liora-git-sync' => 'Synchronize Liora Git repositories',
        ] as $name => $title) {
            $permission = $this->wire('permissions')->get($name);
            if(!$permission || !$permission->id) $permission = $this->wire('permissions')->add($name);
            if($permission && $permission->id) { $permission->title = $title; $permission->save(); }
        }
        $this->store()->ensureTable();
    }

    public function ___upgrade($fromVersion, $toVersion): void {
        $this->___install();
    }

    public function ___uninstall(): void {
        // Shared-memory proposals are audit data and remain by default.
    }

    protected function requirePermission(string $permission): void {
        if((bool)$this->wire('config')->cli) return;
        $user = $this->wire('user');
        if(!$user || !$user->isLoggedin() || (!$user->isSuperuser() && !$user->hasPermission($permission))) {
            throw new WirePermissionException("Permission {$permission} is required");
        }
    }
}
