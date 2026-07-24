(() => {
    'use strict';

    const STORAGE_KEY = 'liora:conversations:v1';

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

    const newId = () => {
        if(globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
        return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
    };

    const scrollToMessageStart = (widget, container, item, behavior = 'smooth') => {
        requestAnimationFrame(() => requestAnimationFrame(() => {
            item.scrollIntoView({behavior, block: 'start'});
        }));
    };

    const scrollToBottom = (container, behavior = 'smooth') => {
        requestAnimationFrame(() => {
            container.scrollTo({top: container.scrollHeight, behavior});
        });
    };

    const addMessage = (container, role, text = '', scroll = 'bottom') => {
        const item = document.createElement('div');
        item.className = `liora-message liora-message--${role}`;
        item.innerHTML = safeMarkdown(text);
        container.append(item);
        if(scroll === 'bottom') scrollToBottom(container);
        return item;
    };

    const updateMessage = (item, text) => {
        item.innerHTML = safeMarkdown(text);
    };

    const addSources = (item, sources) => {
        if(!item || !Array.isArray(sources) || !sources.length) return;
        const list = document.createElement('div');
        list.className = 'liora-message__sources';
        const label = document.createElement('strong');
        label.textContent = 'Sources';
        list.append(label);
        sources.forEach(source => {
            if(!source || !source.title) return;
            let url = '';
            try {
                const rawUrl = String(source.url || '').trim();
                if(rawUrl) {
                    const parsed = new URL(rawUrl, location.origin);
                    if(parsed.origin === location.origin) url = parsed.pathname + parsed.search + parsed.hash;
                }
            } catch {
                // A source title remains useful when its URL is invalid.
            }
            const sourceItem = url ? document.createElement('a') : document.createElement('span');
            sourceItem.textContent = String(source.title).slice(0, 180);
            if(url) sourceItem.href = url;
            list.append(sourceItem);
        });
        if(list.children.length > 1) item.append(list);
    };

    const readThreads = () => {
        try {
            const value = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            return Array.isArray(value) ? value.filter(thread => thread && Array.isArray(thread.messages)) : [];
        } catch {
            return [];
        }
    };

    const writeThreads = (threads, limit) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(
                threads.sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt))).slice(0, limit)
            ));
        } catch {
            // The chat still works when LocalStorage is unavailable or full.
        }
    };

    const conversationTitle = value => {
        const title = String(value || '').replace(/\s+/g, ' ').trim();
        if(!title) return 'Conversation';
        const maxLength = 72;
        if(title.length <= maxLength) return title;
        let short = title.slice(0, maxLength - 1).trimEnd();
        const lastSpace = short.lastIndexOf(' ');
        if(lastSpace >= Math.floor(maxLength * 0.6)) short = short.slice(0, lastSpace);
        return `${short.replace(/[.,;:!?—–-]+$/u, '')}…`;
    };

    const initialize = widget => {
        if(widget.dataset.lioraReady === '1') return;
        widget.dataset.lioraReady = '1';
        const form = widget.querySelector('[data-liora-form]');
        const input = widget.querySelector('[data-liora-input]');
        const submit = widget.querySelector('[data-liora-submit]');
        const messages = widget.querySelector('[data-liora-messages]');
        const toolbar = widget.querySelector('[data-liora-toolbar]');
        const historyButton = widget.querySelector('[data-liora-history-button]');
        const newButton = widget.querySelector('[data-liora-new-button]');
        const expandButton = widget.querySelector('[data-liora-expand-button]');
        const historyPanel = widget.querySelector('[data-liora-history-panel]');
        if(!form || !input || !submit || !messages) return;

        const localHistory = widget.dataset.localHistory === '1';
        const historyLimit = Math.max(1, Math.min(50, Number(widget.dataset.historyLimit || 10)));
        const welcomeMessage = String(widget.dataset.welcomeMessage || '').trim();
        let currentThread = null;

        const showWelcome = () => {
            messages.replaceChildren();
            if(!welcomeMessage) return;
            const item = addMessage(messages, 'assistant', welcomeMessage, 'none');
            item.classList.add('liora-message--welcome');
            item.dataset.lioraWelcome = '1';
        };

        const freshThread = () => ({
            id: newId(),
            title: '',
            titleVersion: 2,
            sourceUrl: location.pathname + location.search,
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString(),
            messages: [],
        });

        const persist = () => {
            if(!localHistory || !currentThread || !currentThread.messages.length) return;
            currentThread.updatedAt = new Date().toISOString();
            const threads = readThreads().filter(thread => thread.id !== currentThread.id);
            threads.unshift(currentThread);
            writeThreads(threads, historyLimit);
            renderHistory();
        };

        const renderHistory = () => {
            if(!localHistory || !toolbar || !historyButton || !historyPanel) return;
            const threads = readThreads().slice(0, historyLimit);
            let migratedTitles = false;
            threads.forEach(thread => {
                if(Number(thread.titleVersion || 0) >= 2) return;
                const firstQuestion = thread.messages.find(message => message.role === 'user')?.content || '';
                thread.title = conversationTitle(firstQuestion);
                thread.titleVersion = 2;
                migratedTitles = true;
            });
            if(migratedTitles) writeThreads(threads, historyLimit);
            toolbar.hidden = false;
            historyButton.textContent = threads.length
                ? `Previous conversations (${threads.length})`
                : 'Previous conversations';
            historyButton.disabled = threads.length === 0;
            historyPanel.replaceChildren();
            threads.forEach(thread => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'liora-widget__history-item';
                const title = thread.title || thread.messages.find(message => message.role === 'user')?.content || 'Conversation';
                const date = thread.updatedAt ? new Date(thread.updatedAt).toLocaleString() : '';
                button.innerHTML = `<strong>${escapeHtml(String(title).slice(0, 100))}</strong><span>${escapeHtml(date)}</span>`;
                button.addEventListener('click', () => {
                    currentThread = thread;
                    messages.replaceChildren();
                    let lastMessage = null;
                    thread.messages.forEach(message => {
                        lastMessage = addMessage(messages, message.role, message.content, 'none');
                        if(message.role === 'assistant') addSources(lastMessage, message.sources);
                    });
                    historyPanel.hidden = true;
                    historyButton.setAttribute('aria-expanded', 'false');
                    if(lastMessage) scrollToMessageStart(widget, messages, lastMessage);
                });
                historyPanel.append(button);
            });
        };

        const startNew = () => {
            currentThread = null;
            showWelcome();
            if(historyPanel) historyPanel.hidden = true;
            if(historyButton) historyButton.setAttribute('aria-expanded', 'false');
            input.focus();
        };

        if(toolbar) toolbar.hidden = false;
        if(historyButton) historyButton.hidden = !localHistory;
        if(localHistory && toolbar && historyButton && newButton && historyPanel) {
            renderHistory();
            historyButton.addEventListener('click', () => {
                historyPanel.hidden = !historyPanel.hidden;
                historyButton.setAttribute('aria-expanded', historyPanel.hidden ? 'false' : 'true');
            });
            newButton.addEventListener('click', startNew);
        } else if(newButton) {
            newButton.addEventListener('click', startNew);
        }
        if(expandButton) {
            expandButton.addEventListener('click', () => {
                const expanded = widget.classList.toggle('liora-widget--expanded');
                expandButton.setAttribute('aria-pressed', expanded ? 'true' : 'false');
                expandButton.textContent = expanded
                    ? widget.dataset.collapseLabel || 'Compact conversation'
                    : widget.dataset.expandLabel || 'Expand conversation';
                const last = messages.lastElementChild;
                if(last) scrollToMessageStart(widget, messages, last);
            });
        }
        showWelcome();

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const question = input.value.trim();
            if(!question || input.disabled) return;
            if(!currentThread) currentThread = freshThread();
            if(!currentThread.title) currentThread.title = conversationTitle(question);
            const priorHistory = currentThread.messages
                .filter(message => message.role === 'user' || message.role === 'assistant')
                .map(({role, content}) => ({role, content}));
            currentThread.messages.push({role: 'user', content: question, createdAt: new Date().toISOString()});
            messages.querySelector('[data-liora-welcome]')?.remove();
            addMessage(messages, 'user', question);
            persist();
            input.value = '';
            input.disabled = true;
            submit.disabled = true;
            submit.textContent = '…';

            let assistantItem = null;
            let assistantText = '';
            let ragSources = [];
            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                };
                headers[`X-${widget.dataset.csrfName}`] = widget.dataset.csrfValue;
                const wantsStream = widget.dataset.stream === '1' && 'ReadableStream' in globalThis;
                const response = await fetch(widget.dataset.endpoint || '/agent/', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        message: question,
                        threadId: currentThread.id,
                        history: priorHistory,
                        originalQuery: widget.dataset.originalQuery || '',
                        context: widget.dataset.context || 'site',
                        sourceUrl: location.pathname + location.search,
                        referrerUrl: document.referrer || '',
                        pageId: Number(widget.dataset.pageId || 0),
                        stream: wantsStream,
                    }),
                });

                const contentType = response.headers.get('content-type') || '';
                if(wantsStream && response.body && contentType.includes('application/x-ndjson')) {
                    if(!response.ok) throw new Error('Liora could not answer right now.');
                    assistantItem = addMessage(messages, 'assistant', '', 'none');
                    scrollToMessageStart(widget, messages, assistantItem);
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';
                    let finished = false;
                    while(!finished) {
                        const chunk = await reader.read();
                        finished = chunk.done;
                        buffer += decoder.decode(chunk.value || new Uint8Array(), {stream: !finished});
                        const lines = buffer.split('\n');
                        buffer = lines.pop() || '';
                        for(const line of lines) {
                            if(!line.trim()) continue;
                            const data = JSON.parse(line);
                            if(data.type === 'thread' && data.thread_id) {
                                currentThread.id = data.thread_id;
                                if(data.thread_title) currentThread.title = data.thread_title;
                            }
                            if(data.type === 'delta') {
                                assistantText += data.content || '';
                                updateMessage(assistantItem, assistantText);
                            }
                            if(data.type === 'error') throw new Error(data.error || 'Liora could not answer right now.');
                            if(data.type === 'done') {
                                if(data.thread_id) currentThread.id = data.thread_id;
                                if(Array.isArray(data.rag_sources)) ragSources = data.rag_sources;
                            }
                        }
                    }
                    if(!assistantText.trim()) throw new Error('Liora returned an empty answer.');
                    addSources(assistantItem, ragSources);
                } else {
                    const data = await response.json().catch(() => ({}));
                    if(!response.ok || !data.success) {
                        throw new Error(data.error || 'Liora could not answer right now.');
                    }
                    if(data.thread_id) currentThread.id = data.thread_id;
                    if(data.thread_title) currentThread.title = data.thread_title;
                    if(Array.isArray(data.rag_sources)) ragSources = data.rag_sources;
                    assistantText = data.response || '';
                    assistantItem = addMessage(messages, 'assistant', assistantText, 'none');
                    addSources(assistantItem, ragSources);
                    scrollToMessageStart(widget, messages, assistantItem);
                }
                currentThread.messages.push({
                    role: 'assistant',
                    content: assistantText,
                    sources: ragSources,
                    createdAt: new Date().toISOString(),
                });
                persist();
                if(assistantItem) scrollToMessageStart(widget, messages, assistantItem);
            } catch(error) {
                if(assistantItem && !assistantText) assistantItem.remove();
                addMessage(messages, 'error', error.message || 'Connection error. Please try again.');
            } finally {
                input.disabled = false;
                submit.disabled = false;
                submit.textContent = 'Ask';
            }
        });
    };

    const boot = () => document.querySelectorAll('.liora-widget').forEach(initialize);
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
