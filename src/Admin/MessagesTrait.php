<?php namespace ProcessWire;

trait ProcessLioraMessagesTrait {

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
            $diagnostics = $this->renderMessageDiagnostics($message);
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
                    . "<blockquote><pre><code>{$content}</code></pre></blockquote>"
                    . $diagnostics . '</article>';
            } else {
                $out .= "<article class='liora-admin-message liora-admin-message--{$role}'>"
                    . "<header class='liora-admin-message__header'>{$identity}{$delete}</header>"
                    . "<div class='liora-admin-message__content'>{$content}</div>"
                    . $diagnostics . '</article>';
            }
        }
        return $out . '</div>';
    }

    protected function renderMessageDiagnostics(array $message): string {
        $metadata = is_array($message['metadata'] ?? null) ? (array)$message['metadata'] : [];
        $responseTime = max(0, (int)($message['response_time_ms'] ?? 0));
        $tokensInput = max(0, (int)($message['tokens_input'] ?? 0));
        $tokensOutput = max(0, (int)($message['tokens_output'] ?? 0));
        $tokensTotal = max(0, (int)($message['tokens_total'] ?? 0));
        if(!$metadata && !$responseTime && !$tokensInput && !$tokensOutput && !$tokensTotal && empty($message['cached'])) {
            return '';
        }

        $san = $this->wire('sanitizer');
        $chips = [];
        if($responseTime) {
            $chips[] = '<span><i class="fa fa-clock-o" aria-hidden="true"></i> '
                . $san->entities($this->formatDuration($responseTime)) . '</span>';
        }
        if($tokensTotal) {
            $chips[] = '<span><i class="fa fa-calculator" aria-hidden="true"></i> '
                . number_format($tokensTotal) . ' ' . $this->_('tokens')
                . ($tokensInput || $tokensOutput
                    ? ' (' . number_format($tokensInput) . ' in / ' . number_format($tokensOutput) . ' out)'
                    : '')
                . '</span>';
        }
        if(!empty($message['cached'])) {
            $chips[] = '<span class="is-active"><i class="fa fa-bolt" aria-hidden="true"></i> '
                . $this->_('Cached') . '</span>';
        }
        if(!empty($metadata['request']['web_search'])) {
            $chips[] = '<span class="is-active"><i class="fa fa-globe" aria-hidden="true"></i> '
                . $this->_('Web search') . '</span>';
        }
        if(!empty($metadata['retrieval']['atlas']['used'])) {
            $chips[] = '<span class="is-active"><i class="fa fa-database" aria-hidden="true"></i> Atlas</span>';
        }
        if(!empty($metadata['retrieval']['vox']['used'])) {
            $chips[] = '<span class="is-active"><i class="fa fa-comments" aria-hidden="true"></i> Vox</span>';
        }

        $out = $chips
            ? "<div class='liora-admin-message__diagnostics'>" . implode('', $chips) . '</div>'
            : '';
        if($metadata) {
            $json = json_encode(
                $metadata,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            if(is_string($json) && $json !== '') {
                $out .= "<details class='liora-admin-message__technical'><summary><i class='fa fa-code' aria-hidden='true'></i> "
                    . $this->_('Technical details') . "</summary><pre><code>"
                    . $san->entities($json) . '</code></pre></details>';
            }
        }
        return $out;
    }

    protected function formatDuration(int $milliseconds): string {
        if($milliseconds < 1000) return $milliseconds . ' ms';
        return number_format($milliseconds / 1000, 2) . ' s';
    }

    protected function canDeleteMessages(): bool {
        $user = $this->wire('user');
        return $user->isSuperuser() || $user->hasPermission('liora-delete');
    }
}

