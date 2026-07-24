(function() {
    'use strict';

    function init() {
    var storageKey = 'liora:admin:threads:v1';
    var pageKey = window.location.pathname + window.location.search;
    var articles = Array.prototype.slice.call(document.querySelectorAll('[data-liora-thread]'));

    if(!articles.length) return;

    function readState() {
        try {
            var value = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
            if(!value || typeof value !== 'object') return { pages: {} };
            if(!value.pages || typeof value.pages !== 'object') value.pages = {};
            return value;
        } catch(error) {
            return { pages: {} };
        }
    }

    function writeState(state) {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(state));
        } catch(error) {
            // The dashboard remains usable when private browsing blocks storage.
        }
    }

    function currentPageState(state) {
        if(!state.pages[pageKey] || typeof state.pages[pageKey] !== 'object') {
            state.pages[pageKey] = { open: [], scrollY: 0 };
        }
        if(!Array.isArray(state.pages[pageKey].open)) state.pages[pageKey].open = [];
        return state.pages[pageKey];
    }

    var state = readState();
    var pageState = currentPageState(state);

    function setExpanded(article, expanded) {
        var button = article.querySelector('[data-liora-thread-toggle]');
        var body = article.querySelector('[data-liora-thread-body]');
        if(!button || !body) return;

        article.classList.toggle('is-collapsed', !expanded);
        body.hidden = !expanded;
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        var icon = button.querySelector('i');
        if(icon) {
            icon.classList.toggle('fa-chevron-down', !expanded);
            icon.classList.toggle('fa-chevron-up', expanded);
        }

        var label = button.querySelector('span');
        if(label) label.textContent = expanded ? button.dataset.closeLabel : button.dataset.openLabel;
    }

    function saveOpenThreads() {
        pageState.open = articles
            .filter(function(article) { return !article.classList.contains('is-collapsed'); })
            .map(function(article) { return String(article.dataset.lioraThread); });
        pageState.scrollY = Math.max(0, Math.round(window.scrollY));
        writeState(state);
    }

    function copyText(text) {
        if(window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function(resolve, reject) {
            var helper = document.createElement('textarea');
            helper.value = text;
            helper.setAttribute('readonly', 'readonly');
            helper.style.position = 'fixed';
            helper.style.opacity = '0';
            document.body.appendChild(helper);
            helper.select();
            try {
                if(!document.execCommand('copy')) throw new Error('Copy command failed');
                resolve();
            } catch(error) {
                reject(error);
            } finally {
                helper.remove();
            }
        });
    }

    articles.forEach(function(article) {
        var id = String(article.dataset.lioraThread);
        setExpanded(article, pageState.open.indexOf(id) !== -1);

        var button = article.querySelector('[data-liora-thread-toggle]');
        if(!button) return;
        button.addEventListener('click', function() {
            var topBefore = article.getBoundingClientRect().top;
            var expanded = article.classList.contains('is-collapsed');
            setExpanded(article, expanded);
            saveOpenThreads();

            window.requestAnimationFrame(function() {
                var topAfter = article.getBoundingClientRect().top;
                window.scrollBy(0, topAfter - topBefore);
                pageState.scrollY = Math.max(0, Math.round(window.scrollY));
                writeState(state);
            });
        });

        var copyButton = article.querySelector('[data-liora-thread-copy]');
        var context = article.querySelector('[data-liora-thread-context]');
        if(copyButton && context) {
            copyButton.addEventListener('click', function() {
                var label = copyButton.querySelector('span');
                copyText(context.value || context.textContent || '').then(function() {
                    if(label) label.textContent = copyButton.dataset.copiedLabel || 'Copied';
                    copyButton.classList.add('is-copied');
                    window.setTimeout(function() {
                        if(label) label.textContent = copyButton.dataset.copyLabel || 'Copy context';
                        copyButton.classList.remove('is-copied');
                    }, 1800);
                }).catch(function() {
                    if(label) label.textContent = copyButton.dataset.copyLabel || 'Copy context';
                });
            });
        }
    });

    if('scrollRestoration' in window.history) window.history.scrollRestoration = 'manual';

    window.requestAnimationFrame(function() {
        var hashThread = window.location.hash
            ? document.getElementById(window.location.hash.slice(1))
            : null;
        if(hashThread && !hashThread.hasAttribute('data-liora-thread')) hashThread = null;
        if(hashThread) {
            setExpanded(hashThread, true);
            saveOpenThreads();
            hashThread.scrollIntoView({ block: 'start' });
            return;
        }
        if(pageState.scrollY > 0) window.scrollTo(0, pageState.scrollY);
    });

    document.addEventListener('submit', saveOpenThreads);
    window.addEventListener('pagehide', saveOpenThreads);
    window.addEventListener('beforeunload', saveOpenThreads);
    }

    if(document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
