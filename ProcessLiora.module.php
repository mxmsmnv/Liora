<?php namespace ProcessWire;

/**
 * ProcessLiora — conversation and demand dashboard.
 */
class ProcessLiora extends Process {

    public static function getModuleInfo(): array {
        return [
            'title' => 'Liora Insights',
            'version' => 1101,
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
        $status = $san->pageName((string)$input->get('status'));
        if(!in_array($status, LioraStore::statuses(), true)) $status = '';
        $pageNumber = max(1, (int)$input->get('p'));
        $returnUrl = $this->threadListUrl($status, $pageNumber);

        if($input->post('action') === 'status') {
            $session->CSRF->validate();
            $id = (int)$input->post('id');
            $status = $san->pageName((string)$input->post('status'));
            if($store->updateStatus($id, $status)) $this->message($this->_('Conversation status updated.'));
            else $this->error($this->_('Could not update the conversation.'));
            $session->redirect($returnUrl . '#liora-thread-' . $id);
        }

        if($input->post('action') === 'delete_message') {
            $session->CSRF->validate();
            if(!$this->canDeleteMessages()) {
                throw new WirePermissionException($this->_('You do not have permission to delete Liora messages.'));
            }
            $messageId = (int)$input->post('message_id');
            if($store->deleteMessage($messageId)) $this->message($this->_('Message deleted.'));
            else $this->error($this->_('The message could not be deleted.'));
            $session->redirect($returnUrl);
        }

        if($input->post('action') === 'delete_thread') {
            $session->CSRF->validate();
            if(!$this->canDeleteMessages()) {
                throw new WirePermissionException($this->_('You do not have permission to delete Liora conversations.'));
            }
            $threadId = (int)$input->post('thread_id');
            if($store->deleteThread($threadId)) $this->message($this->_('Conversation and all its messages deleted.'));
            else $this->error($this->_('The conversation could not be deleted.'));
            $session->redirect($returnUrl);
        }

        $perPage = 10;
        $totalThreads = $store->countThreads($status);
        $totalPages = max(1, (int)ceil($totalThreads / $perPage));
        $pageNumber = min($pageNumber, $totalPages);
        $offset = ($pageNumber - 1) * $perPage;
        $summary = $store->summary();
        $threads = $store->recentThreads($perPage, $status, $offset);
        $top = $store->topDemand(20);

        $this->headline($this->_('Liora Insights'));
        $this->browserTitle($this->_('Liora Insights'));

        return "<div class='ProcessLiora'>"
            . $this->renderSummary($summary, $liora)
            . $this->renderConfigurationNotice($liora)
            . $this->renderTopDemand($top)
            . $this->renderFilters($status)
            . $this->renderThreads($threads)
            . $this->renderPagination($pageNumber, $totalPages, $totalThreads, $perPage, $status)
            . $this->renderSettingsFooter($liora)
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

    protected function threadListUrl(string $status = '', int $page = 1): string {
        $query = [];
        if($status !== '') $query['status'] = $status;
        if($page > 1) $query['p'] = $page;
        return './' . ($query ? '?' . http_build_query($query) : '');
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

            $threadId = (int)$thread['id'];
            $bodyId = 'liora-thread-body-' . $threadId;
            $contextText = $san->entities($this->threadContextText($thread));
            $out .= "<article id='liora-thread-{$threadId}' data-liora-thread='{$threadId}' "
                . "class='uk-card uk-card-default uk-card-body uk-margin liora-admin-thread is-collapsed'>"
                . "<header class='liora-admin-thread__header'><div class='liora-admin-thread__title'>"
                . "<div class='liora-admin-thread__eyebrow'><span class='uk-label'>"
                . $san->entities((string)$thread['status']) . "</span><time datetime='"
                . $san->entities((string)$thread['updated_at']) . "'>"
                . $san->entities((string)$thread['updated_at']) . "</time></div><h3>{$title}</h3></div>"
                . "<div class='liora-admin-thread__controls'><div class='liora-admin-thread__stats'><strong>"
                . (int)$thread['message_count'] . '</strong><span>'
                . $this->_('messages') . '</span>'
                . ($location !== '' ? "<small><i class='fa fa-map-marker' aria-hidden='true'></i> "
                    . $san->entities($location) . '</small>' : '') . '</div>'
                . "<button type='button' class='uk-button uk-button-default uk-button-small liora-admin-thread__copy' "
                . "data-liora-thread-copy data-copy-label='" . $san->entities($this->_('Copy context'))
                . "' data-copied-label='" . $san->entities($this->_('Copied')) . "'>"
                . "<i class='fa fa-copy' aria-hidden='true'></i> <span>"
                . $san->entities($this->_('Copy context')) . '</span></button>'
                . "<button type='button' class='uk-button uk-button-default uk-button-small liora-admin-thread__toggle' "
                . "data-liora-thread-toggle aria-expanded='false' aria-controls='{$bodyId}' "
                . "data-open-label='" . $san->entities($this->_('Open')) . "' data-close-label='"
                . $san->entities($this->_('Hide')) . "'><i class='fa fa-chevron-down' aria-hidden='true'></i> <span>"
                . $this->_('Open') . "</span></button></div></header>"
                . "<textarea data-liora-thread-context hidden>{$contextText}</textarea>"
                . "<div id='{$bodyId}' class='liora-admin-thread__body' data-liora-thread-body hidden>"
                . ($original !== '' ? "<div class='liora-admin-thread__query'><span>"
                    . $this->_('Original search') . "</span><strong>{$original}</strong></div>" : '')
                . $this->renderMessages((array)$thread['messages']);

            if($source !== '') {
                $safeSource = $san->entities($source);
                $out .= "<div class='liora-admin-thread__references'><p><strong>" . $this->_('Page:') . '</strong> '
                    . ($pageTitle !== '' ? "{$pageTitle} · " : '')
                    . "<a href='{$safeSource}' target='_blank' rel='noopener'>{$safeSource}</a></p>";
            }
            if($referrer !== '') {
                $safeReferrer = $san->entities($referrer);
                if($source === '') $out .= "<div class='liora-admin-thread__references'>";
                $out .= "<p><strong>" . $this->_('Referrer:') . "</strong> {$safeReferrer}</p>";
            }
            if($source !== '' || $referrer !== '') $out .= '</div>';

            $deleteThread = '';
            if($this->canDeleteMessages()) {
                $confirmation = $san->entities(json_encode(
                    $this->_('Delete this conversation and all its messages permanently?'),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));
                $deleteThread = "<form method='post' class='liora-admin-thread__delete' onsubmit=\"return confirm({$confirmation})\">"
                    . $csrf
                    . "<input type='hidden' name='action' value='delete_thread'>"
                    . "<input type='hidden' name='thread_id' value='" . (int)$thread['id'] . "'>"
                    . "<button type='submit' class='uk-button uk-button-text uk-text-danger'>"
                    . "<i class='fa fa-trash' aria-hidden='true'></i> "
                    . $san->entities($this->_('Delete conversation')) . '</button></form>';
            }

            $out .= "<footer class='liora-admin-thread__footer'>{$deleteThread}"
                . "<form method='post' class='uk-flex uk-flex-middle liora-admin-status'>{$csrf}"
                . "<input type='hidden' name='action' value='status'><input type='hidden' name='id' value='" . (int)$thread['id'] . "'>"
                . "<select name='status' class='uk-select uk-form-width-medium'>";
            foreach(LioraStore::statuses() as $status) {
                $selected = $status === $thread['status'] ? ' selected' : '';
                $out .= "<option value='{$status}'{$selected}>{$status}</option>";
            }
            $out .= "</select><button class='uk-button uk-button-default' type='submit'>" . $this->_('Update')
                . '</button></form></footer></div></article>';
        }
        return $out;
    }

    protected function threadContextText(array $thread): string {
        $lines = [
            'Liora conversation #' . (int)($thread['id'] ?? 0),
            'Title: ' . trim((string)($thread['title'] ?? '')),
            'Status: ' . trim((string)($thread['status'] ?? '')),
            'Created: ' . trim((string)($thread['created_at'] ?? '')),
            'Updated: ' . trim((string)($thread['updated_at'] ?? '')),
        ];
        $metadata = [
            'Original search' => $thread['original_query'] ?? '',
            'Page context' => $thread['context'] ?? '',
            'Page title' => $thread['page_title'] ?? '',
            'Source URL' => $thread['source_url'] ?? '',
            'Referrer URL' => $thread['referrer_url'] ?? '',
            'Location' => implode(', ', array_filter([
                trim((string)($thread['city'] ?? '')),
                trim((string)($thread['region'] ?? '')),
                trim((string)($thread['country'] ?? '')),
            ])),
        ];
        foreach($metadata as $label => $value) {
            $value = trim((string)$value);
            if($value !== '') $lines[] = $label . ': ' . $value;
        }
        $lines[] = '';
        $lines[] = 'Messages:';
        foreach((array)($thread['messages'] ?? []) as $message) {
            $role = match((string)($message['role'] ?? 'user')) {
                'assistant' => 'LIORA',
                'error' => 'ERROR',
                default => 'VISITOR',
            };
            $details = array_filter([
                trim((string)($message['created_at'] ?? '')),
                trim((string)($message['provider'] ?? '')),
                trim((string)($message['model'] ?? '')),
                (int)($message['tokens_total'] ?? 0) > 0
                    ? (int)$message['tokens_total'] . ' tokens'
                    : '',
            ]);
            $lines[] = '';
            $lines[] = '[' . implode(' · ', $details) . '] ' . $role;
            $lines[] = trim((string)($message['content'] ?? ''));
        }
        return trim(implode("\n", $lines));
    }

    protected function renderMessages(array $messages): string {
        $san = $this->wire('sanitizer');
        $csrf = $this->wire('session')->CSRF->renderInput();
        $canDelete = $this->canDeleteMessages();
        $confirmation = $san->entities(json_encode(
            $this->_('Delete this message permanently?'),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        if(!$messages) return "<div class='liora-admin-messages liora-admin-messages--empty'>"
            . $this->_('This conversation has no messages.') . '</div>';
        $out = "<div class='liora-admin-messages' aria-label='" . $san->entities($this->_('Conversation messages')) . "'>";
        foreach($messages as $message) {
            $role = in_array($message['role'], ['user', 'assistant', 'error'], true)
                ? $message['role']
                : 'user';
            $label = $role === 'assistant'
                ? $this->_('Liora')
                : ($role === 'error' ? $this->_('Error') : $this->_('Visitor'));
            $content = $san->entities((string)$message['content']);
            $model = trim((string)$message['provider'] . ' / ' . (string)$message['model'], ' /');
            $created = $san->entities((string)$message['created_at']);
            $icon = $role === 'assistant' ? 'magic' : ($role === 'error' ? 'exclamation-circle' : 'user');
            $identity = "<div class='liora-admin-message__identity'><span class='liora-admin-message__avatar' aria-hidden='true'>"
                . "<i class='fa fa-{$icon}'></i></span><div><strong>{$label}</strong>"
                . "<div class='liora-admin-message__meta'><time datetime='{$created}'>{$created}</time>"
                . ($model !== '' ? '<span>' . $san->entities($model) . '</span>' : '')
                . '</div></div></div>';
            $delete = '';
            if($canDelete) {
                $delete = "<form method='post' class='liora-admin-message__delete' onsubmit=\"return confirm({$confirmation})\">"
                    . $csrf
                    . "<input type='hidden' name='action' value='delete_message'>"
                    . "<input type='hidden' name='message_id' value='" . (int)$message['id'] . "'>"
                    . "<button type='submit' class='uk-button uk-button-text uk-text-danger' title='"
                    . $san->entities($this->_('Delete message')) . "'><i class='fa fa-trash' aria-hidden='true'></i> "
                    . $san->entities($this->_('Delete')) . '</button></form>';
            }
            if($role === 'assistant') {
                $out .= "<article class='liora-admin-message liora-admin-message--assistant'>"
                    . "<header class='liora-admin-message__header'>{$identity}{$delete}</header>"
                    . "<blockquote><pre><code>{$content}</code></pre></blockquote></article>";
            } else {
                $out .= "<article class='liora-admin-message liora-admin-message--{$role}'>"
                    . "<header class='liora-admin-message__header'>{$identity}{$delete}</header>"
                    . "<div class='liora-admin-message__content'>{$content}</div></article>";
            }
        }
        return $out . '</div>';
    }

    protected function canDeleteMessages(): bool {
        $user = $this->wire('user');
        return $user->isSuperuser() || $user->hasPermission('liora-delete');
    }
}
