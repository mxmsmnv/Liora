<?php namespace ProcessWire;

/** Authenticated admin workspace for the optional LioraGit companion. */
class ProcessLioraGit extends Process {

    public static function getModuleInfo(): array {
        return [
            'title' => 'Liora Git Memory',
            'version' => 100,
            'summary' => 'Chat with and contribute to a Git-backed shared memory.',
            'author' => 'Maxim Semenov',
            'icon' => 'github',
            'requires' => ['LioraGit'],
            'permission' => 'liora-git-chat',
            'permissions' => [
                'liora-git-chat' => 'Use the private Liora Git chat',
                'liora-git-write' => 'Propose and confirm Liora Git writes',
                'liora-git-sync' => 'Synchronize Liora Git repositories',
            ],
            'page' => ['name' => 'liora-git', 'parent' => 'setup', 'title' => 'Liora Git Memory'],
        ];
    }

    public function execute(): string {
        $user = $this->wire('user');
        if(!$user->isSuperuser() && !$user->hasPermission('liora-git-chat')) throw new WirePermissionException($this->_('You do not have permission to use Liora Git.'));
        $lioraGit = $this->wire('modules')->get('LioraGit');
        $input = $this->wire('input');
        $session = $this->wire('session');
        $action = (string)$input->post('action');

        if($action !== '') {
            $session->CSRF->validate();
            try {
                if($action === 'ask') {
                    $question = trim($this->wire('sanitizer')->textarea((string)$input->post('question'), ['maxLength' => 2000]));
                    if(preg_match('/^\/remember(?:\s+(.+?))?\R([\s\S]+)$/u', $question, $remember)) {
                        if(!$user->isSuperuser() && !$user->hasPermission('liora-git-write')) throw new WirePermissionException($this->_('You do not have permission to propose repository writes.'));
                        $title = trim((string)($remember[1] ?? ''));
                        $content = trim((string)($remember[2] ?? ''));
                        if($title === '') $title = mb_substr(trim(strtok($content, "\n")), 0, 180);
                        $proposal = $lioraGit->proposeCreate($title, $content);
                        $session->set('liora_git_proposal_id', (string)$proposal['public_id']);
                        $session->redirect('./#liora-git-proposal');
                    }
                    $history = (array)$session->get('liora_git_chat_history');
                    $result = $lioraGit->askMemory($question, $history);
                    if(empty($result['success'])) throw new WireException((string)($result['error'] ?? 'Liora could not answer'));
                    $history[] = ['role' => 'user', 'content' => $question];
                    $history[] = ['role' => 'assistant', 'content' => (string)$result['content'], 'sources' => (array)($result['sources'] ?? [])];
                    $session->set('liora_git_chat_history', array_slice($history, -20));
                    $session->redirect('./#liora-git-chat');
                }
                if($action === 'clear_chat') {
                    $session->remove('liora_git_chat_history');
                    $this->message($this->_('Chat history cleared from this session.'));
                    $session->redirect('./');
                }
                if($action === 'sync') {
                    $summary = $lioraGit->sync();
                    $this->message(sprintf($this->_('Synchronized %1$d documents: %2$d changed, %3$d unchanged, %4$d removed.'), $summary['documents'], $summary['indexed'], $summary['unchanged'], $summary['deleted']));
                    $session->redirect('./');
                }
                if($action === 'propose') {
                    $title = trim($this->wire('sanitizer')->text((string)$input->post('title'), ['maxLength' => 180]));
                    $content = trim($this->wire('sanitizer')->textarea((string)$input->post('content'), ['maxLength' => 100000]));
                    $proposal = $lioraGit->proposeCreate($title, $content);
                    $session->set('liora_git_proposal_id', (string)$proposal['public_id']);
                    $session->redirect('./#liora-git-proposal');
                }
                if($action === 'confirm') {
                    $id = trim((string)$input->post('proposal_id'));
                    $result = $lioraGit->confirmProposal($id);
                    $session->remove('liora_git_proposal_id');
                    $this->message(sprintf($this->_('Committed %1$s at %2$s.'), $result['path'], $result['commit_sha']));
                    $session->redirect('./');
                }
                if($action === 'cancel') {
                    $lioraGit->cancelProposal(trim((string)$input->post('proposal_id')));
                    $session->remove('liora_git_proposal_id');
                    $this->message($this->_('Proposal cancelled. Nothing was written.'));
                    $session->redirect('./');
                }
            } catch(\Throwable $error) {
                $this->error($error->getMessage());
            }
        }

        $this->headline($this->_('Liora Git Memory'));
        $this->browserTitle($this->_('Liora Git Memory'));
        return $this->renderWorkspace($lioraGit);
    }

    protected function renderWorkspace(LioraGit $lioraGit): string {
        $san = $this->wire('sanitizer');
        $csrf = $this->wire('session')->CSRF->renderInput();
        $history = (array)$this->wire('session')->get('liora_git_chat_history');
        $proposalId = (string)$this->wire('session')->get('liora_git_proposal_id');
        $proposal = $proposalId !== '' ? $lioraGit->store()->findOwned($proposalId, (int)$this->wire('user')->id, true) : null;
        $canWrite = $this->wire('user')->isSuperuser() || $this->wire('user')->hasPermission('liora-git-write');
        $canSync = $this->wire('user')->isSuperuser() || $this->wire('user')->hasPermission('liora-git-sync');
        $settings = $this->wire('config')->urls->admin . 'module/edit?name=LioraGit';

        $out = "<div class='ProcessLioraGit uk-container uk-container-large'>"
            . "<section class='uk-card uk-card-default uk-card-body uk-margin-bottom'><div class='uk-flex uk-flex-between uk-flex-middle uk-flex-wrap'>"
            . "<div><h2 class='uk-card-title'>" . $this->_('Shared Git-backed memory') . "</h2><p class='uk-text-meta'>" . $this->_('GitHub is the source of truth. Liora answers from the private index and every write requires an exact preview and confirmation.') . "</p></div>"
            . "<div class='uk-button-group'>";
        if($canSync) $out .= "<form method='post'>{$csrf}<input type='hidden' name='action' value='sync'><button class='uk-button uk-button-primary' type='submit'><i class='fa fa-refresh'></i> " . $this->_('Sync repository') . '</button></form>';
        if($this->wire('user')->isSuperuser()) $out .= "<a class='uk-button uk-button-default' href='{$settings}'><i class='fa fa-cog'></i> " . $this->_('Settings') . '</a>';
        $out .= '</div></div></section>';

        $out .= "<section id='liora-git-chat' class='uk-card uk-card-default uk-card-body uk-margin-bottom'><div class='uk-flex uk-flex-between uk-flex-middle'><h2 class='uk-card-title'>" . $this->_('Ask Liora') . '</h2>';
        if($history) $out .= "<form method='post'>{$csrf}<input type='hidden' name='action' value='clear_chat'><button class='uk-button uk-button-text' type='submit'>" . $this->_('Clear session history') . '</button></form>';
        $out .= '</div>';
        if(!$history) $out .= "<p class='uk-text-muted'>" . $this->_('Ask what is known, decided, unresolved or contradictory. Answers cite the indexed commit.') . ($canWrite ? ' ' . $this->_('To prepare a write proposal in chat, use /remember Title on the first line and the note on following lines.') : '') . '</p>';
        foreach($history as $message) {
            $role = ($message['role'] ?? '') === 'user' ? $this->_('You') : $this->_('Liora');
            $class = ($message['role'] ?? '') === 'user' ? 'uk-background-muted' : 'uk-background-default';
            $out .= "<article class='uk-padding-small uk-margin-small {$class}'><strong>" . $san->entities($role) . "</strong><div style='white-space:pre-wrap'>" . $san->entities((string)($message['content'] ?? '')) . '</div>';
            if(!empty($message['sources'])) {
                $out .= "<ul class='uk-list uk-list-collapse uk-text-small'>";
                foreach((array)$message['sources'] as $source) {
                    $url = (string)($source['url'] ?? ''); $label = '[' . (string)($source['label'] ?? 'S') . '] ' . (string)($source['path'] ?? $source['title'] ?? 'Source');
                    $out .= '<li>' . ($url !== '' ? "<a href='" . $san->entities($url) . "' rel='noreferrer'>" . $san->entities($label) . '</a>' : $san->entities($label)) . '</li>';
                }
                $out .= '</ul>';
            }
            $out .= '</article>';
        }
        $out .= "<form method='post' class='uk-form-stacked'>{$csrf}<input type='hidden' name='action' value='ask'><label class='uk-form-label' for='liora-git-question'>" . $this->_('Question') . "</label><textarea class='uk-textarea' id='liora-git-question' name='question' rows='3' maxlength='2000' required></textarea><button class='uk-button uk-button-primary uk-margin-small-top' type='submit'>" . $this->_('Ask') . '</button></form></section>';

        if($canWrite) {
            if($proposal) $out .= $this->renderProposal($proposal, $csrf);
            else $out .= "<section class='uk-card uk-card-default uk-card-body'><h2 class='uk-card-title'>" . $this->_('Remember something') . "</h2><p class='uk-text-meta'>" . $this->_('This prepares a draft Markdown note. Nothing is written until you review and confirm the diff.') . "</p><form method='post' class='uk-form-stacked'>{$csrf}<input type='hidden' name='action' value='propose'><label class='uk-form-label' for='liora-git-title'>" . $this->_('Title') . "</label><input class='uk-input' id='liora-git-title' name='title' maxlength='180' required><label class='uk-form-label uk-margin-small-top' for='liora-git-content'>" . $this->_('What should be remembered?') . "</label><textarea class='uk-textarea' id='liora-git-content' name='content' rows='7' maxlength='100000' required></textarea><button class='uk-button uk-button-default uk-margin-small-top' type='submit'>" . $this->_('Prepare preview') . '</button></form></section>';
        }
        return $out . '</div>';
    }

    protected function renderProposal(array $proposal, string $csrf): string {
        $san = $this->wire('sanitizer');
        $id = $san->entities((string)$proposal['public_id']);
        $diff = "--- /dev/null\n+++ b/" . (string)$proposal['path'] . "\n@@ new file @@\n+" . str_replace("\n", "\n+", rtrim((string)$proposal['content']));
        return "<section id='liora-git-proposal' class='uk-card uk-card-default uk-card-body uk-margin-bottom'><h2 class='uk-card-title'>" . $this->_('Confirm exact repository change') . "</h2><dl class='uk-description-list'><dt>" . $this->_('Repository') . '</dt><dd>' . $san->entities((string)$proposal['repository']) . '</dd><dt>' . $this->_('Branch and base commit') . '</dt><dd>' . $san->entities((string)$proposal['branch'] . ' @ ' . (string)$proposal['base_commit']) . '</dd><dt>' . $this->_('Path') . '</dt><dd>' . $san->entities((string)$proposal['path']) . "</dd></dl><pre style='max-height:30rem;overflow:auto'>" . $san->entities($diff) . "</pre><div class='uk-flex uk-flex-wrap uk-flex-middle uk-grid-small' uk-grid><form method='post'>{$csrf}<input type='hidden' name='action' value='confirm'><input type='hidden' name='proposal_id' value='{$id}'><button class='uk-button uk-button-primary' type='submit'>" . $this->_('Confirm and commit') . "</button></form><form method='post'>{$csrf}<input type='hidden' name='action' value='cancel'><input type='hidden' name='proposal_id' value='{$id}'><button class='uk-button uk-button-default' type='submit'>" . $this->_('Cancel') . '</button></form></div></section>';
    }
}
