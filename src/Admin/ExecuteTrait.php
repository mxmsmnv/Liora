<?php namespace ProcessWire;

trait ProcessLioraExecuteTrait {

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

}
