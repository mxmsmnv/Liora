<?php namespace ProcessWire;

trait ProcessLioraDashboardTrait {

    protected function renderWorkspaceIntro(array $summary): string {
        $reviewCount = (int)($summary['new_count'] ?? 0);
        $reviewUrl = $this->threadListUrl('new', 1);
        $reviewLabel = $reviewCount > 0
            ? sprintf($this->_('Review %d new'), $reviewCount)
            : $this->_('View conversations');
        return "<section class='liora-admin-intro'>"
            . "<div class='liora-admin-intro__copy'><span class='liora-admin-eyebrow'>" . $this->_('Visitor demand') . '</span>'
            . '<h2>' . $this->_('Turn unanswered questions into better content') . '</h2>'
            . '<p>' . $this->_('Review conversations, spot repeated needs, and decide what the site should answer next.') . '</p></div>'
            . "<div class='liora-admin-intro__actions'><a class='uk-button uk-button-primary' href='{$reviewUrl}'>"
            . "<i class='fa fa-inbox' aria-hidden='true'></i> {$reviewLabel}</a>"
            . "<a class='uk-button uk-button-default' href='" . $this->settingsUrl() . "'>"
            . "<i class='fa fa-cog' aria-hidden='true'></i> " . $this->_('Settings') . '</a></div></section>';
    }

    protected function renderSummary(array $summary, Liora $liora): string {
        $primary = [
            ['label' => $this->_('Needs review'), 'value' => (int)($summary['new_count'] ?? 0), 'icon' => 'inbox', 'tone' => 'attention'],
            ['label' => $this->_('Today'), 'value' => (int)($summary['today'] ?? 0), 'icon' => 'calendar', 'tone' => 'neutral'],
            ['label' => $this->_('Conversations'), 'value' => (int)($summary['total'] ?? 0), 'icon' => 'comments', 'tone' => 'neutral'],
            ['label' => $this->_('Failed'), 'value' => (int)($summary['failed'] ?? 0), 'icon' => 'exclamation-circle', 'tone' => (int)($summary['failed'] ?? 0) > 0 ? 'danger' : 'success'],
        ];
        $secondary = [
            $this->_('Messages') => number_format((int)($summary['messages'] ?? 0)),
            $this->_('Questions') => number_format((int)($summary['questions'] ?? 0)),
            $this->_('Tokens used') => number_format((int)($summary['tokens'] ?? 0)),
            $this->_('Average response') => (int)($summary['average_response_ms'] ?? 0) > 0
                ? $this->formatDuration((int)$summary['average_response_ms']) : '—',
            $this->_('Cache hits') => number_format((int)($summary['cache_hits'] ?? 0)),
        ];
        $out = "<section class='liora-admin-metrics' aria-label='" . $this->_('Liora activity summary') . "'>"
            . "<div class='liora-admin-summary'>";
        foreach($primary as $card) {
            $out .= "<div class='liora-admin-metric is-{$card['tone']}'>"
                . "<span class='liora-admin-metric__icon' aria-hidden='true'><i class='fa fa-{$card['icon']}'></i></span>"
                . "<div><strong>{$card['value']}</strong><span>{$card['label']}</span></div></div>";
        }
        $out .= "</div><dl class='liora-admin-details'>";
        foreach($secondary as $label => $value) {
            $out .= "<div><dt>{$label}</dt><dd>{$value}</dd></div>";
        }
        return $out . '</dl></section>';
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
        $visible = array_slice($rows, 0, 8);
        $remaining = array_slice($rows, 8);
        $out = "<section class='liora-admin-panel liora-admin-demand'>"
            . "<header class='liora-admin-section-head'><div><span class='liora-admin-eyebrow'>" . $this->_('Content opportunities') . '</span>'
            . '<h2>' . $this->_('Repeated unmet demand') . '</h2>'
            . '<p>' . $this->_('Questions that recur across conversations are strong candidates for new or improved content.') . '</p></div>'
            . "<span class='liora-admin-count'>" . count($rows) . '</span></header>'
            . $this->renderDemandTable($visible);
        if($remaining) {
            $out .= "<details class='liora-admin-demand__more'><summary>"
                . sprintf($this->_('Show %d more queries'), count($remaining)) . '</summary>'
                . $this->renderDemandTable($remaining) . '</details>';
        }
        return $out . '</section>';
    }

    protected function renderDemandTable(array $rows): string {
        $san = $this->wire('sanitizer');
        $out = "<div class='liora-admin-table-wrap'><table class='liora-admin-table'>"
            . '<thead><tr><th>' . $this->_('Search/context query') . '</th><th>' . $this->_('Conversations')
            . '</th><th>' . $this->_('Messages') . '</th><th>' . $this->_('Last seen') . '</th></tr></thead><tbody>';
        foreach($rows as $row) {
            $out .= '<tr><td data-label="' . $this->_('Query') . '"><strong>'
                . $san->entities((string)$row['original_query']) . '</strong></td><td data-label="'
                . $this->_('Conversations') . '">' . (int)$row['threads'] . '</td><td data-label="'
                . $this->_('Messages') . '">' . (int)$row['messages'] . '</td><td data-label="'
                . $this->_('Last seen') . '">' . $san->entities((string)$row['last_seen']) . '</td></tr>';
        }
        return $out . '</tbody></table></div>';
    }

    protected function renderFilters(string $active, int $totalThreads): string {
        $items = ['' => $this->_('All')] + array_combine(LioraStore::statuses(), [
            $this->_('New'),
            $this->_('Reviewing'),
            $this->_('Content added'),
            $this->_('Dismissed'),
            $this->_('Failed'),
        ]);
        $out = "<section class='liora-admin-review'><header class='liora-admin-section-head'><div>"
            . "<span class='liora-admin-eyebrow'>" . $this->_('Review queue') . '</span><h2>'
            . $this->_('Recent conversations') . '</h2><p>'
            . $this->_('Open a conversation to inspect its context, response, diagnostics, and review status.')
            . "</p></div><span class='liora-admin-count'>{$totalThreads}</span></header>"
            . "<nav class='liora-admin-filters' aria-label='" . $this->_('Filter conversations by status')
            . "'><ul class='uk-subnav uk-subnav-pill'>";
        foreach($items as $value => $label) {
            $current = $value === $active ? " aria-current='page'" : '';
            $class = $value === $active ? " class='uk-active'" : '';
            $href = $this->threadListUrl($value, 1);
            $out .= "<li{$class}><a href='{$href}'{$current}>{$label}</a></li>";
        }
        return $out . '</ul></nav></section>';
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
