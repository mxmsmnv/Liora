<?php namespace ProcessWire;

trait ProcessLioraThreadsTrait {

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
                (int)($message['response_time_ms'] ?? 0) > 0
                    ? $this->formatDuration((int)$message['response_time_ms'])
                    : '',
                (int)($message['tokens_total'] ?? 0) > 0
                    ? (int)$message['tokens_total'] . ' tokens'
                    : '',
                !empty($message['cached']) ? 'cached' : '',
            ]);
            $lines[] = '';
            $lines[] = '[' . implode(' · ', $details) . '] ' . $role;
            $lines[] = trim((string)($message['content'] ?? ''));
            if(!empty($message['metadata']) && is_array($message['metadata'])) {
                $lines[] = 'Technical metadata:';
                $lines[] = (string)json_encode(
                    $message['metadata'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }
        }
        return trim(implode("\n", $lines));
    }

}
