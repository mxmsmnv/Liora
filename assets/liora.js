(() => {
    'use strict';

    const STORAGE_KEY = 'liora:conversations:v1';

    const escapeHtml = value => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const inlineMarkdown = value => {
        const links = [];
        const withLinkTokens = String(value).replace(
            /\[([^\]\n]{1,180})\]\((\/(?!\/)[^)\s<>"']{1,500})\)/g,
            (match, label, url) => {
                const token = `@@LIORA_INTERNAL_LINK_${links.length}@@`;
                links.push({token, label, url});
                return token;
            }
        );
        let html = escapeHtml(withLinkTokens);
        html = html.replace(/`([^`]+?)`/g, '<code>$1</code>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(
            /\[Source\s+(\d+)\]/gi,
            '<sup class="liora-message__citation" aria-label="Source $1">[$1]</sup>'
        );
        links.forEach(link => {
            html = html.replaceAll(
                link.token,
                `<a class="liora-message__link" href="${escapeHtml(link.url)}">${escapeHtml(link.label)}</a>`
            );
        });
        return html;
    };

    const safeMarkdown = value => {
        const lines = String(value || '').replace(/\r\n?/g, '\n').split('\n');
        const blocks = [];
        let paragraph = [];
        let listType = '';
        let listItems = [];
        let inCode = false;
        let codeLines = [];

        const flushParagraph = () => {
            if(!paragraph.length) return;
            blocks.push(`<p>${inlineMarkdown(paragraph.join(' '))}</p>`);
            paragraph = [];
        };
        const flushList = () => {
            if(!listItems.length || !listType) return;
            blocks.push(`<${listType}>${listItems.map(item => `<li>${inlineMarkdown(item)}</li>`).join('')}</${listType}>`);
            listType = '';
            listItems = [];
        };
        const flushCode = () => {
            if(!codeLines.length) return;
            blocks.push(`<pre><code>${escapeHtml(codeLines.join('\n'))}</code></pre>`);
            codeLines = [];
        };

        lines.forEach(line => {
            if(/^```/.test(line.trim())) {
                flushParagraph();
                flushList();
                if(inCode) flushCode();
                inCode = !inCode;
                return;
            }
            if(inCode) {
                codeLines.push(line);
                return;
            }
            if(!line.trim()) {
                flushParagraph();
                flushList();
                return;
            }

            const heading = line.match(/^\s*(#{1,4})\s+(.+?)\s*$/);
            if(heading) {
                flushParagraph();
                flushList();
                const level = Math.min(5, heading[1].length + 2);
                blocks.push(`<h${level}>${inlineMarkdown(heading[2])}</h${level}>`);
                return;
            }

            const unordered = line.match(/^\s*[-*•]\s+(.+?)\s*$/);
            const ordered = line.match(/^\s*\d+[.)]\s+(.+?)\s*$/);
            if(unordered || ordered) {
                flushParagraph();
                const nextType = unordered ? 'ul' : 'ol';
                if(listType && listType !== nextType) flushList();
                listType = nextType;
                listItems.push((unordered || ordered)[1]);
                return;
            }

            flushList();
            paragraph.push(line.trim());
        });
        flushParagraph();
        flushList();
        if(inCode || codeLines.length) flushCode();
        return blocks.join('');
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
        item.dataset.messageText = text;
        const content = document.createElement('div');
        content.className = 'liora-message__content';
        content.innerHTML = safeMarkdown(text);
        item.append(content);
        container.append(item);
        if(scroll === 'bottom') scrollToBottom(container);
        return item;
    };

    const updateMessage = (item, text) => {
        item.dataset.messageText = text;
        const content = item.querySelector('.liora-message__content');
        if(content) content.innerHTML = safeMarkdown(text);
    };

    const copyText = async text => {
        if(navigator.clipboard?.writeText && globalThis.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.append(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        if(!copied) throw new Error('Copy failed');
    };

    const addMessageMeta = (item, message, options) => {
        if(!item || !message || item.querySelector('.liora-message__meta')) return;
        const showCopy = options.showCopy;
        const responseTimeMs = Math.max(0, Number(message.responseTimeMs || 0));
        const tokensUsed = Math.max(0, Number(message.tokensUsed || 0));
        const showResponseTime = options.showResponseTime && message.role === 'assistant' && responseTimeMs > 0;
        const showTokenUsage = options.showTokenUsage && message.role === 'assistant' && tokensUsed > 0;
        if(!showCopy && !showResponseTime && !showTokenUsage) return;

        const meta = document.createElement('div');
        meta.className = 'liora-message__meta';
        if(showResponseTime) {
            const timing = document.createElement('span');
            timing.textContent = `${options.responseTimeLabel}: ${responseTimeMs < 1000
                ? `${Math.round(responseTimeMs)} ms`
                : `${(responseTimeMs / 1000).toFixed(1)} s`}`;
            meta.append(timing);
        }
        if(showTokenUsage) {
            const tokens = document.createElement('span');
            tokens.textContent = `${tokensUsed.toLocaleString()} ${options.tokensLabel}`;
            meta.append(tokens);
        }
        if(showCopy) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'liora-message__copy';
            button.textContent = options.copyLabel;
            button.addEventListener('click', async () => {
                try {
                    await copyText(String(message.content || item.dataset.messageText || ''));
                    button.textContent = options.copiedLabel;
                    globalThis.setTimeout(() => {
                        button.textContent = options.copyLabel;
                    }, 1600);
                } catch {
                    button.textContent = options.copyLabel;
                }
            });
            meta.append(button);
        }
        item.append(meta);
    };

    const addSources = (item, sources, sourcesLabel = 'Sources') => {
        if(!item || !Array.isArray(sources) || !sources.length) return;
        const list = document.createElement('div');
        list.className = 'liora-message__sources';
        const label = document.createElement('strong');
        label.textContent = sourcesLabel;
        list.append(label);
        sources.forEach((source, index) => {
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
            sourceItem.textContent = `${index + 1}. ${String(source.title).slice(0, 180)}`;
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

    const conversationTitle = (value, fallback = 'Conversation') => {
        const title = String(value || '').replace(/\s+/g, ' ').trim();
        if(!title) return fallback;
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
        const suggestions = widget.querySelector('[data-liora-suggestions]');
        if(!form || !input || !submit || !messages) return;

        const localHistory = widget.dataset.localHistory === '1';
        const historyLimit = Math.max(1, Math.min(50, Number(widget.dataset.historyLimit || 10)));
        const welcomeMessage = String(widget.dataset.welcomeMessage || '').trim();
        const previousLabel = widget.dataset.previousLabel || 'Previous conversations';
        const conversationLabel = widget.dataset.conversationLabel || 'Conversation';
        const sourcesLabel = widget.dataset.sourcesLabel || 'Sources';
        const messageMetaOptions = {
            showCopy: widget.dataset.showCopy === '1',
            showResponseTime: widget.dataset.showResponseTime === '1',
            showTokenUsage: widget.dataset.showTokenUsage === '1',
            copyLabel: widget.dataset.copyLabel || 'Copy',
            copiedLabel: widget.dataset.copiedLabel || 'Copied',
            responseTimeLabel: widget.dataset.responseTimeLabel || 'Response time',
            tokensLabel: widget.dataset.tokensLabel || 'tokens',
        };
        const thinkingLabel = widget.dataset.thinkingLabel || 'Liora is thinking';
        const errorLabel = widget.dataset.errorLabel || 'Liora could not answer right now.';
        const emptyErrorLabel = widget.dataset.emptyErrorLabel || 'Liora returned an empty answer.';
        const connectionErrorLabel = widget.dataset.connectionErrorLabel || 'Connection error. Please try again.';
        let currentThread = null;

        const showWelcome = () => {
            messages.replaceChildren();
            if(suggestions) suggestions.hidden = false;
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

        const syncThreadTitle = async (thread, title) => {
            if(!thread?.id) return;
            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                };
                headers[`X-${widget.dataset.csrfName}`] = widget.dataset.csrfValue;
                await fetch(widget.dataset.endpoint || '/agent/', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({
                        action: 'rename',
                        threadId: thread.id,
                        title,
                    }),
                });
            } catch {
                // The LocalStorage title remains useful after a server session expires.
            }
        };

        const renderHistory = () => {
            if(!localHistory || !toolbar || !historyButton || !historyPanel) return;
            const threads = readThreads().slice(0, historyLimit);
            let migratedTitles = false;
            threads.forEach(thread => {
                if(Number(thread.titleVersion || 0) >= 2) return;
                const firstQuestion = thread.messages.find(message => message.role === 'user')?.content || '';
                thread.title = conversationTitle(firstQuestion, conversationLabel);
                thread.titleVersion = 2;
                migratedTitles = true;
            });
            if(migratedTitles) writeThreads(threads, historyLimit);
            toolbar.hidden = false;
            historyButton.textContent = threads.length
                ? `${previousLabel} (${threads.length})`
                : previousLabel;
            historyButton.disabled = threads.length === 0;
            historyPanel.replaceChildren();
            threads.forEach(thread => {
                const row = document.createElement('div');
                row.className = 'liora-widget__history-row';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'liora-widget__history-item';
                const title = thread.title || thread.messages.find(message => message.role === 'user')?.content || conversationLabel;
                const date = thread.updatedAt ? new Date(thread.updatedAt).toLocaleString() : '';
                button.innerHTML = `<strong>${escapeHtml(String(title).slice(0, 100))}</strong><span>${escapeHtml(date)}</span>`;
                button.addEventListener('click', () => {
                    currentThread = thread;
                    messages.replaceChildren();
                    if(suggestions) suggestions.hidden = true;
                    let lastMessage = null;
                    thread.messages.forEach(message => {
                        lastMessage = addMessage(messages, message.role, message.content, 'none');
                        if(message.role === 'assistant') addSources(lastMessage, message.sources, sourcesLabel);
                        addMessageMeta(lastMessage, message, messageMetaOptions);
                    });
                    historyPanel.hidden = true;
                    historyButton.setAttribute('aria-expanded', 'false');
                    if(lastMessage) scrollToMessageStart(widget, messages, lastMessage);
                });
                const editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'liora-widget__history-edit';
                editButton.textContent = '✎';
                editButton.setAttribute('aria-label', `${widget.dataset.editTitleLabel || 'Edit title'}: ${title}`);
                editButton.addEventListener('click', () => {
                    const form = document.createElement('form');
                    form.className = 'liora-widget__history-edit-form';
                    const titleInput = document.createElement('input');
                    titleInput.type = 'text';
                    titleInput.maxLength = 72;
                    titleInput.required = true;
                    titleInput.value = title;
                    titleInput.setAttribute('aria-label', widget.dataset.editTitleLabel || 'Edit title');
                    const saveButton = document.createElement('button');
                    saveButton.type = 'submit';
                    saveButton.textContent = widget.dataset.saveTitleLabel || 'Save';
                    const cancelButton = document.createElement('button');
                    cancelButton.type = 'button';
                    cancelButton.textContent = widget.dataset.cancelTitleLabel || 'Cancel';
                    cancelButton.addEventListener('click', renderHistory);
                    form.addEventListener('submit', event => {
                        event.preventDefault();
                        const nextTitle = titleInput.value.replace(/\s+/g, ' ').trim().slice(0, 72);
                        if(!nextTitle) return;
                        thread.title = nextTitle;
                        thread.titleVersion = 2;
                        if(currentThread?.id === thread.id) currentThread.title = nextTitle;
                        writeThreads(threads, historyLimit);
                        renderHistory();
                        void syncThreadTitle(thread, nextTitle);
                    });
                    form.append(titleInput, saveButton, cancelButton);
                    row.replaceChildren(form);
                    titleInput.focus();
                    titleInput.select();
                });
                row.append(button, editButton);
                historyPanel.append(row);
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
        input.disabled = false;
        submit.disabled = false;
        widget.removeAttribute('aria-busy');

        suggestions?.addEventListener('click', event => {
            const button = event.target.closest('[data-liora-suggestion]');
            if(!button || input.disabled) return;
            input.value = String(button.dataset.lioraSuggestion || button.textContent || '').trim();
            if(input.value) form.requestSubmit();
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const question = input.value.trim();
            if(!question || input.disabled) return;
            if(!currentThread) currentThread = freshThread();
            if(!currentThread.title) currentThread.title = conversationTitle(question, conversationLabel);
            const priorHistory = currentThread.messages
                .filter(message => message.role === 'user' || message.role === 'assistant')
                .map(({role, content}) => ({role, content}));
            const userMessage = {role: 'user', content: question, createdAt: new Date().toISOString()};
            currentThread.messages.push(userMessage);
            messages.querySelector('[data-liora-welcome]')?.remove();
            if(suggestions) suggestions.hidden = true;
            const userItem = addMessage(messages, 'user', question);
            addMessageMeta(userItem, userMessage, messageMetaOptions);
            persist();
            input.value = '';
            input.disabled = true;
            submit.disabled = true;

            const responseStartedAt = performance.now();
            let assistantItem = addMessage(messages, 'assistant', thinkingLabel, 'none');
            assistantItem.classList.add('liora-message--thinking');
            scrollToMessageStart(widget, messages, assistantItem);
            let assistantText = '';
            let ragSources = [];
            let tokensUsed = 0;
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
                    if(!response.ok) throw new Error(errorLabel);
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
                                if(assistantItem.classList.contains('liora-message--thinking')) {
                                    assistantItem.classList.remove('liora-message--thinking');
                                    updateMessage(assistantItem, '');
                                }
                                assistantText += data.content || '';
                                updateMessage(assistantItem, assistantText);
                            }
                            if(data.type === 'error') throw new Error(data.error || errorLabel);
                            if(data.type === 'done') {
                                if(data.thread_id) currentThread.id = data.thread_id;
                                if(typeof data.response === 'string' && data.response.trim()) {
                                    assistantText = data.response;
                                    updateMessage(assistantItem, assistantText);
                                }
                                if(Array.isArray(data.rag_sources)) ragSources = data.rag_sources;
                                tokensUsed = Math.max(0, Number(data.tokens_used || 0));
                            }
                        }
                    }
                    if(!assistantText.trim()) throw new Error(emptyErrorLabel);
                    addSources(assistantItem, ragSources, sourcesLabel);
                } else {
                    const data = await response.json().catch(() => ({}));
                    if(!response.ok || !data.success) {
                        throw new Error(data.error || errorLabel);
                    }
                    if(data.thread_id) currentThread.id = data.thread_id;
                    if(data.thread_title) currentThread.title = data.thread_title;
                    if(Array.isArray(data.rag_sources)) ragSources = data.rag_sources;
                    tokensUsed = Math.max(0, Number(data.tokens_used || 0));
                    assistantText = data.response || '';
                    assistantItem.classList.remove('liora-message--thinking');
                    updateMessage(assistantItem, assistantText);
                    addSources(assistantItem, ragSources, sourcesLabel);
                    scrollToMessageStart(widget, messages, assistantItem);
                }
                const assistantMessage = {
                    role: 'assistant',
                    content: assistantText,
                    sources: ragSources,
                    responseTimeMs: Math.round(performance.now() - responseStartedAt),
                    tokensUsed,
                    createdAt: new Date().toISOString(),
                };
                addMessageMeta(assistantItem, assistantMessage, messageMetaOptions);
                currentThread.messages.push(assistantMessage);
                persist();
                if(assistantItem) scrollToMessageStart(widget, messages, assistantItem);
            } catch(error) {
                if(assistantItem && !assistantText) assistantItem.remove();
                addMessage(messages, 'error', error.message || connectionErrorLabel);
            } finally {
                input.disabled = false;
                submit.disabled = false;
            }
        });
    };

    const boot = () => document.querySelectorAll('.liora-widget').forEach(initialize);
    boot();
    if(document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    }
})();
