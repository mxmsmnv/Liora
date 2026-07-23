(() => {
    'use strict';

    const escapeHtml = value => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const safeMarkdown = value => {
        let html = escapeHtml(value);
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/`(.+?)`/g, '<code>$1</code>');
        html = html.replace(/(^|\n)[*-] (.+)/g, '$1• $2');
        return html;
    };

    const addMessage = (container, role, text) => {
        const item = document.createElement('div');
        item.className = `liora-message liora-message--${role}`;
        item.innerHTML = safeMarkdown(text);
        container.append(item);
        container.scrollTop = container.scrollHeight;
    };

    const initialize = widget => {
        if(widget.dataset.lioraReady === '1') return;
        widget.dataset.lioraReady = '1';
        const form = widget.querySelector('[data-liora-form]');
        const input = widget.querySelector('[data-liora-input]');
        const submit = widget.querySelector('[data-liora-submit]');
        const messages = widget.querySelector('[data-liora-messages]');
        if(!form || !input || !submit || !messages) return;

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const question = input.value.trim();
            if(!question || input.disabled) return;
            addMessage(messages, 'user', question);
            input.value = '';
            input.disabled = true;
            submit.disabled = true;
            submit.textContent = '…';

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                };
                headers[`X-${widget.dataset.csrfName}`] = widget.dataset.csrfValue;
                const response = await fetch(widget.dataset.endpoint || '/agent/', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        message: question,
                        originalQuery: widget.dataset.originalQuery || '',
                        context: widget.dataset.context || 'site',
                        sourceUrl: widget.dataset.sourceUrl || location.pathname,
                        pageId: Number(widget.dataset.pageId || 0),
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if(!response.ok || !data.success) {
                    throw new Error(data.error || 'Liora could not answer right now.');
                }
                addMessage(messages, 'assistant', data.response || '');
            } catch(error) {
                addMessage(messages, 'error', error.message || 'Connection error. Please try again.');
            } finally {
                input.disabled = false;
                submit.disabled = false;
                submit.textContent = 'Ask';
                input.focus();
            }
        });
    };

    const boot = () => document.querySelectorAll('.liora-widget').forEach(initialize);
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
