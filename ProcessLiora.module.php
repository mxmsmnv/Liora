<?php namespace ProcessWire;

/**
 * ProcessLiora — conversation and demand dashboard.
 */
class ProcessLiora extends Process {

    public static function getModuleInfo(): array {
        return [
            'title' => 'Liora Insights',
            'version' => 120,
            'summary' => 'Review Liora conversations and turn visitor demand into site content.',
            'author' => 'Maxim Semenov',
            'icon' => 'comments',
            'requires' => ['Liora'],
            'permission' => 'liora-review',
            'permissions' => ['liora-review' => 'Review Liora visitor conversations'],
            'page' => ['name' => 'liora', 'parent' => 'setup', 'title' => 'Liora Insights'],
        ];
    }

    public function init(): void {
        parent::init();
        $url = $this->wire('config')->urls->siteModules . 'Liora/assets/liora-admin.css?v=120';
        $this->wire('config')->styles->add($url);
    }

    public function execute(): string {
        $user = $this->wire('user');
        if(!$user->isSuperuser() && !$user->hasPermission('liora-review')) {
            throw new WirePermissionException($this->_('You do not have permission to review Liora conversations.'));
        }

        $liora = $this->wire('modules')->get('Liora');
        $store = $liora->store();
        $input = $this->wire('input');
        $session = $this->wire('session');
        $san = $this->wire('sanitizer');

        if($input->post('action') === 'status') {
            $session->CSRF->validate();
            $id = (int)$input->post('id');
            $status = $san->pageName((string)$input->post('status'));
            if($store->updateStatus($id, $status)) $this->message($this->_('Conversation status updated.'));
            else $this->error($this->_('Could not update the conversation.'));
            $session->redirect('./' . ($input->get('status') ? '?status=' . urlencode((string)$input->get('status')) : ''));
        }

        $status = $san->pageName((string)$input->get('status'));
        if(!in_array($status, LioraStore::statuses(), true)) $status = '';
        $summary = $store->summary();
        $threads = $store->recentThreads(100, $status);
        $top = $store->topDemand(20);

        $this->headline($this->_('Liora Insights'));
        $this->browserTitle($this->_('Liora Insights'));

        return "<div class='ProcessLiora'>"
            . $this->renderSummary($summary, $liora)
            . $this->renderTopDemand($top)
            . $this->renderFilters($status)
            . $this->renderThreads($threads)
            . '</div>';
    }

    protected function renderSummary(array $summary, Liora $liora): string {
        $cards = [
            $this->_('Conversations') => (int)($summary['total'] ?? 0),
            $this->_('Messages') => (int)($summary['messages'] ?? 0),
            $this->_('Questions') => (int)($summary['questions'] ?? 0),
            $this->_('Needs review') => (int)($summary['new_count'] ?? 0),
            $this->_('Today') => (int)($summary['today'] ?? 0),
            $this->_('Failed') => (int)($summary['failed'] ?? 0),
            $this->_('Tokens') => number_format((int)($summary['tokens'] ?? 0)),
        ];
        $out = "<div class='liora-admin-summary'>";
        foreach($cards as $label => $value) {
            $out .= "<div class='uk-card uk-card-default uk-card-body uk-padding-small'><div class='uk-text-meta'>{$label}</div><strong>{$value}</strong></div>";
        }
        $settings = $this->wire('config')->urls->admin . 'module/edit?name=Liora';
        return $out . "</div><p><strong>" . $this->_('Active model:') . '</strong> '
            . $this->wire('sanitizer')->entities($liora->getProvider() . ' / ' . $liora->getModel())
            . " · <a href='{$settings}'>" . $this->_('Liora settings') . '</a></p>';
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
            $href = $value === '' ? './' : './?status=' . urlencode($value);
            $out .= "<li{$class}><a href='{$href}'>{$label}</a></li>";
        }
        return $out . '</ul>';
    }

    protected function renderThreads(array $threads): string {
        if(!$threads) return '<p>' . $this->_('No conversations match this filter.') . '</p>';
        $san = $this->wire('sanitizer');
        $csrf = $this->wire('session')->CSRF->renderInput();
        $out = '';
        foreach($threads as $thread) {
            $title = $san->entities((string)($thread['title'] ?: $this->_('Untitled conversation')));
            $original = $san->entities((string)$thread['original_query']);
            $location = implode(', ', array_filter([
                trim((string)$thread['city']),
                trim((string)$thread['country']),
            ]));
            $source = trim((string)$thread['source_url']);
            $referrer = trim((string)$thread['referrer_url']);
            $pageTitle = $san->entities((string)$thread['page_title']);

            $out .= "<article class='uk-card uk-card-default uk-card-body uk-margin liora-admin-thread'>"
                . "<header class='liora-admin-thread__header'><div><span class='uk-label'>"
                . $san->entities((string)$thread['status']) . "</span> <span class='uk-text-meta'>"
                . $san->entities((string)$thread['updated_at']) . "</span></div>"
                . "<div class='uk-text-meta'>" . (int)$thread['message_count'] . ' ' . $this->_('messages')
                . ($location !== '' ? ' · ' . $san->entities($location) : '') . '</div></header>'
                . "<h3>{$title}</h3>"
                . ($original !== '' ? "<p><strong>" . $this->_('Original search:') . "</strong> {$original}</p>" : '')
                . $this->renderMessages((array)$thread['messages']);

            if($source !== '') {
                $safeSource = $san->entities($source);
                $out .= "<p class='uk-text-meta'><strong>" . $this->_('Page:') . '</strong> '
                    . ($pageTitle !== '' ? "{$pageTitle} · " : '')
                    . "<a href='{$safeSource}' target='_blank' rel='noopener'>{$safeSource}</a></p>";
            }
            if($referrer !== '') {
                $safeReferrer = $san->entities($referrer);
                $out .= "<p class='uk-text-meta'><strong>" . $this->_('Referrer:') . "</strong> {$safeReferrer}</p>";
            }

            $out .= "<form method='post' class='uk-flex uk-flex-middle liora-admin-status'>{$csrf}"
                . "<input type='hidden' name='action' value='status'><input type='hidden' name='id' value='" . (int)$thread['id'] . "'>"
                . "<select name='status' class='uk-select uk-form-width-medium'>";
            foreach(LioraStore::statuses() as $status) {
                $selected = $status === $thread['status'] ? ' selected' : '';
                $out .= "<option value='{$status}'{$selected}>{$status}</option>";
            }
            $out .= "</select><button class='uk-button uk-button-default' type='submit'>" . $this->_('Update') . '</button></form></article>';
        }
        return $out;
    }

    protected function renderMessages(array $messages): string {
        $san = $this->wire('sanitizer');
        $out = "<div class='liora-admin-messages'>";
        foreach($messages as $message) {
            $role = in_array($message['role'], ['user', 'assistant', 'error'], true)
                ? $message['role']
                : 'user';
            $label = $role === 'assistant'
                ? $this->_('Liora')
                : ($role === 'error' ? $this->_('Error') : $this->_('Visitor'));
            $content = $san->entities((string)$message['content']);
            $model = trim((string)$message['provider'] . ' / ' . (string)$message['model'], ' /');
            $meta = $san->entities((string)$message['created_at'] . ($model !== '' ? ' · ' . $model : ''));
            if($role === 'assistant') {
                $out .= "<blockquote class='liora-admin-message liora-admin-message--assistant'>"
                    . "<div class='liora-admin-message__meta'>{$label} · {$meta}</div>"
                    . "<pre><code>{$content}</code></pre></blockquote>";
            } else {
                $out .= "<div class='liora-admin-message liora-admin-message--{$role}'>"
                    . "<div class='liora-admin-message__meta'>{$label} · {$meta}</div>"
                    . "<div class='liora-admin-message__content'>{$content}</div></div>";
            }
        }
        return $out . '</div>';
    }
}
