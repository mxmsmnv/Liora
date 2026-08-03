<?php namespace ProcessWire;

trait ProcessLioraDashboardTrait {

    protected function renderSummary(array $summary, Liora $liora): string {
        $cards = [
            $this->_('Conversations') => (int)($summary['total'] ?? 0),
            $this->_('Messages') => (int)($summary['messages'] ?? 0),
            $this->_('Questions') => (int)($summary['questions'] ?? 0),
            $this->_('Needs review') => (int)($summary['new_count'] ?? 0),
            $this->_('Today') => (int)($summary['today'] ?? 0),
            $this->_('Failed') => (int)($summary['failed'] ?? 0),
            $this->_('Tokens') => number_format((int)($summary['tokens'] ?? 0)),
            $this->_('Average response') => (int)($summary['average_response_ms'] ?? 0) > 0
                ? $this->formatDuration((int)$summary['average_response_ms'])
                : '—',
            $this->_('Cache hits') => number_format((int)($summary['cache_hits'] ?? 0)),
        ];
        $out = "<div class='liora-admin-summary'>";
        foreach($cards as $label => $value) {
            $out .= "<div class='uk-card uk-card-default uk-card-body uk-padding-small'><div class='uk-text-meta'>{$label}</div><strong>{$value}</strong></div>";
        }
        return $out . '</div>';
    }

    protected function renderConfigurationNotice(Liora $liora): string {
        if($liora->isConfigured()) return '';
        $settings = $this->settingsUrl();
        return "<div class='liora-admin-config-warning' role='alert'>"
            . "<span class='liora-admin-config-warning__icon' aria-hidden='true'><i class='fa fa-exclamation-triangle'></i></span>"
            . "<div class='liora-admin-config-warning__content'><strong>" . $this->_('Liora is not configured') . '</strong>'
            . '<p>' . $this->_('Choose an active Squad provider and model before visitors can receive answers.') . '</p></div>'
            . "<a class='uk-button uk-button-primary' href='{$settings}'><i class='fa fa-cog' aria-hidden='true'></i> "
            . $this->_('Configure Liora') . '</a></div>';
    }

    protected function renderSettingsFooter(Liora $liora): string {
        $san = $this->wire('sanitizer');
        $configured = $liora->isConfigured();
        $model = trim($liora->getProvider() . ' / ' . $liora->getModel(), ' /');
        if($model === '') $model = $this->_('Not configured');
        $stateClass = $configured ? ' is-configured' : ' is-unconfigured';
        return "<footer class='liora-admin-settings{$stateClass}'>"
            . "<div class='liora-admin-settings__model'><span>" . $this->_('Active model') . '</span>'
            . '<strong>' . $san->entities($model) . '</strong></div>'
            . "<a class='uk-button uk-button-default' href='" . $this->settingsUrl() . "'>"
            . "<i class='fa fa-cog' aria-hidden='true'></i> " . $this->_('Liora settings') . '</a></footer>';
    }

    protected function settingsUrl(): string {
        return $this->wire('config')->urls->admin . 'module/edit?name=Liora';
    }

    protected function renderTopDemand(array $rows): string {
        if(!$rows) return '';
        $table = $this->wire('modules')->get('MarkupAdminDataTable');
        $table->setEncodeEntities(true);
        $table->headerRow([
            $this->_('Search/context query'),
            $this->_('Conversations'),
            $this->_('Messages'),
            $this->_('Last seen'),
        ]);
        foreach($rows as $row) {
            $table->row([
                (string)$row['original_query'],
                (int)$row['threads'],
                (int)$row['messages'],
                (string)$row['last_seen'],
            ]);
        }
        return '<h2>' . $this->_('Repeated unmet demand') . '</h2>' . $table->render();
    }

    protected function renderFilters(string $active): string {
        $items = ['' => $this->_('All')] + array_combine(LioraStore::statuses(), [
            $this->_('New'),
            $this->_('Reviewing'),
            $this->_('Content added'),
            $this->_('Dismissed'),
            $this->_('Failed'),
        ]);
        $out = "<h2 class='liora-admin-recent'>" . $this->_('Recent conversations') . "</h2><ul class='uk-subnav uk-subnav-pill'>";
        foreach($items as $value => $label) {
            $class = $value === $active ? " class='uk-active'" : '';
            $href = $this->threadListUrl($value, 1);
            $out .= "<li{$class}><a href='{$href}'>{$label}</a></li>";
        }
        return $out . '</ul>';
    }

    protected function renderPagination(
        int $current,
        int $totalPages,
        int $totalItems,
        int $perPage,
        string $status
    ): string {
        if($totalItems < 1) return '';
        $first = (($current - 1) * $perPage) + 1;
        $last = min($totalItems, $current * $perPage);
        $out = "<nav class='liora-admin-pagination' aria-label='" . $this->_('Conversation pages') . "'>"
            . "<p>" . sprintf($this->_('Showing %1$d–%2$d of %3$d conversations'), $first, $last, $totalItems) . '</p>';
        if($totalPages > 1) {
            $pages = [1, $totalPages];
            for($page = max(1, $current - 2); $page <= min($totalPages, $current + 2); $page++) {
                $pages[] = $page;
            }
            $pages = array_values(array_unique($pages));
            sort($pages);
            $out .= "<ul class='uk-pagination uk-flex-center'>";
            if($current > 1) {
                $out .= "<li><a href='" . $this->threadListUrl($status, $current - 1) . "' aria-label='"
                    . $this->_('Previous page') . "'><span uk-pagination-previous></span></a></li>";
            }
            $previous = 0;
            foreach($pages as $page) {
                if($previous && $page > $previous + 1) $out .= "<li class='uk-disabled'><span>…</span></li>";
                $active = $page === $current ? " class='uk-active' aria-current='page'" : '';
                $out .= "<li{$active}><a href='" . $this->threadListUrl($status, $page) . "'>{$page}</a></li>";
                $previous = $page;
            }
            if($current < $totalPages) {
                $out .= "<li><a href='" . $this->threadListUrl($status, $current + 1) . "' aria-label='"
                    . $this->_('Next page') . "'><span uk-pagination-next></span></a></li>";
            }
            $out .= '</ul>';
        }
        return $out . '</nav>';
    }

}

