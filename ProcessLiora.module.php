<?php namespace ProcessWire;

/**
 * ProcessLiora — demand dashboard for questions the site did not answer.
 */
class ProcessLiora extends Process {

    public static function getModuleInfo(): array {
        return [
            'title' => 'Liora Insights',
            'version' => 100,
            'summary' => 'Review AI questions and turn visitor demand into site content.',
            'author' => 'Maxim Semenov',
            'icon' => 'comments',
            'requires' => ['Liora'],
            'permission' => 'liora-review',
            'permissions' => ['liora-review' => 'Review Liora visitor questions'],
            'page' => ['name' => 'liora', 'parent' => 'setup', 'title' => 'Liora Insights'],
        ];
    }

    public function execute(): string {
        $user = $this->wire('user');
        if(!$user->isSuperuser() && !$user->hasPermission('liora-review')) {
            throw new WirePermissionException($this->_('You do not have permission to review Liora questions.'));
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
            if($store->updateStatus($id, $status)) $this->message($this->_('Question status updated.'));
            else $this->error($this->_('Could not update the question.'));
            $session->redirect('./' . ($input->get('status') ? '?status=' . urlencode((string)$input->get('status')) : ''));
        }

        $status = $san->pageName((string)$input->get('status'));
        if(!in_array($status, LioraStore::statuses(), true)) $status = '';
        $summary = $store->summary();
        $rows = $store->recent(100, $status);
        $top = $store->topDemand(20);

        $this->headline($this->_('Liora Insights'));
        $this->browserTitle($this->_('Liora Insights'));

        return "<div class='ProcessLiora'>"
            . $this->renderSummary($summary, $liora)
            . $this->renderTopDemand($top)
            . $this->renderFilters($status)
            . $this->renderRows($rows)
            . '</div>';
    }

    protected function renderSummary(array $summary, Liora $liora): string {
        $cards = [
            $this->_('All questions') => (int)($summary['total'] ?? 0),
            $this->_('Needs review') => (int)($summary['new_count'] ?? 0),
            $this->_('Today') => (int)($summary['today'] ?? 0),
            $this->_('Failed') => (int)($summary['failed'] ?? 0),
            $this->_('Tokens') => number_format((int)($summary['tokens'] ?? 0)),
        ];
        $out = "<div style='display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px'>";
        foreach($cards as $label => $value) {
            $out .= "<div class='uk-card uk-card-default uk-card-body uk-padding-small'><div class='uk-text-meta'>{$label}</div><strong style='font-size:1.6rem'>{$value}</strong></div>";
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
        $table->headerRow([$this->_('Search/context query'), $this->_('Questions'), $this->_('Last seen')]);
        foreach($rows as $row) {
            $table->row([
                (string)$row['original_query'],
                (int)$row['hits'],
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
        $out = "<h2 style='margin-top:28px'>" . $this->_('Recent questions') . "</h2><ul class='uk-subnav uk-subnav-pill'>";
        foreach($items as $value => $label) {
            $class = $value === $active ? " class='uk-active'" : '';
            $href = $value === '' ? './' : './?status=' . urlencode($value);
            $out .= "<li{$class}><a href='{$href}'>{$label}</a></li>";
        }
        return $out . '</ul>';
    }

    protected function renderRows(array $rows): string {
        if(!$rows) return '<p>' . $this->_('No questions match this filter.') . '</p>';
        $san = $this->wire('sanitizer');
        $csrf = $this->wire('session')->CSRF->renderInput();
        $out = '';
        foreach($rows as $row) {
            $question = nl2br($san->entities((string)$row['question']));
            $response = nl2br($san->entities((string)$row['response']));
            $original = $san->entities((string)$row['original_query']);
            $source = $san->entities((string)$row['source_url']);
            $meta = $san->entities(trim((string)$row['provider'] . ' / ' . (string)$row['model'], ' /'));
            $error = trim((string)$row['error']);
            $out .= "<article class='uk-card uk-card-default uk-card-body uk-margin-small'>"
                . "<div class='uk-flex uk-flex-between uk-flex-middle'><div><span class='uk-label'>"
                . $san->entities((string)$row['status']) . "</span> <span class='uk-text-meta'>"
                . $san->entities((string)$row['created_at']) . "</span></div><span class='uk-text-meta'>{$meta}</span></div>"
                . ($original !== '' ? "<p><strong>" . $this->_('Original search:') . "</strong> {$original}</p>" : '')
                . "<h3>{$question}</h3>"
                . ($response !== '' ? "<details><summary>" . $this->_('Liora response') . "</summary><p>{$response}</p></details>" : '')
                . ($error !== '' ? "<p class='uk-text-danger'>" . $san->entities($error) . '</p>' : '')
                . ($source !== '' ? "<p class='uk-text-meta'>" . $this->_('Source:') . " <a href='{$source}' target='_blank' rel='noopener'>{$source}</a></p>" : '')
                . "<form method='post' class='uk-flex uk-flex-middle' style='gap:8px'>{$csrf}"
                . "<input type='hidden' name='action' value='status'><input type='hidden' name='id' value='" . (int)$row['id'] . "'>"
                . "<select name='status' class='uk-select uk-form-width-medium'>";
            foreach(LioraStore::statuses() as $status) {
                $selected = $status === $row['status'] ? ' selected' : '';
                $out .= "<option value='{$status}'{$selected}>{$status}</option>";
            }
            $out .= "</select><button class='uk-button uk-button-default' type='submit'>" . $this->_('Update') . '</button></form></article>';
        }
        return $out;
    }
}
