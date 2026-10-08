<?php namespace ProcessWire;

trait LioraMessageTrait {

    protected function normalizeMessages(array $messages): array {
        $systemParts = [];
        $history = [];
        foreach($messages as $message) {
            if(!is_array($message)) continue;
            $role = (string)($message['role'] ?? 'user');
            $content = $this->messageText($message['content'] ?? '');
            if($content === '') continue;
            if($role === 'system' || $role === 'developer') {
                $systemParts[] = $content;
                continue;
            }
            $history[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }
        if((bool)$this->setting('restrictExternalLinks', true)) {
            $instruction = $this->configuredExternalLinksPrompt();
            if($instruction !== '') $systemParts[] = $instruction;
        }
        if(!$history) return ['', [], trim(implode("\n\n", $systemParts))];
        $current = array_pop($history);
        if($current['role'] !== 'user') {
            $history[] = $current;
            $current = ['role' => 'user', 'content' => 'Continue.'];
        }
        return [(string)$current['content'], $history, trim(implode("\n\n", $systemParts))];
    }

    protected function messageText($content): string {
        if(is_scalar($content)) return trim((string)$content);
        if(!is_array($content)) return '';
        $parts = [];
        foreach($content as $part) {
            if(is_scalar($part)) $parts[] = (string)$part;
            elseif(is_array($part)) {
                $text = $part['text'] ?? $part['content'] ?? '';
                if(is_scalar($text)) $parts[] = (string)$text;
            }
        }
        return trim(implode("\n", array_filter($parts, 'strlen')));
    }

    protected function restrictExternalLinks(string $content): string {
        $content = trim($content);
        if($content === '' || !(bool)$this->setting('restrictExternalLinks', true)) return $content;

        $content = preg_replace_callback(
            '~\[([^\]\r\n]+)\]\((https?:)?//([^)[:space:]]+)\)~iu',
            function(array $match): string {
                $url = ($match[2] ?? '') . '//' . ($match[3] ?? '');
                return $this->isSameSiteUrl($url) ? $match[0] : trim((string)$match[1]);
            },
            $content
        ) ?? $content;

        $content = preg_replace_callback(
            '~(?<![\w@])(?:https?:)?//[^\s<>()\]]+~iu',
            fn(array $match): string => $this->isSameSiteUrl($match[0]) ? $match[0] : '',
            $content
        ) ?? $content;

        $content = preg_replace('/[ \t]{2,}/u', ' ', $content) ?? $content;
        $content = preg_replace('/ +([,.;:!?])/u', '$1', $content) ?? $content;
        return trim($content);
    }

    /**
     * Keep only source URLs that resolve to an existing public ProcessWire page.
     */
    protected function normalizeVerifiedSources(array $sources): array {
        $verified = [];
        $seen = [];
        foreach($sources as $source) {
            if(!is_array($source)) continue;
            $url = $this->verifiedSourceUrl((string)($source['url'] ?? ''));
            if($url === '' || isset($seen[$url])) continue;
            $seen[$url] = true;
            $title = trim((string)($source['title'] ?? ''));
            $verified[] = [
                'url' => $url,
                'title' => mb_substr($title !== '' ? $title : $url, 0, 255),
                'verified' => true,
            ];
        }
        return $verified;
    }

    /**
     * Remove model-authored links and numbered citations unless their exact URL
     * is present in the module-verified source list. Source attribution is
     * rendered separately by the widget.
     */
    protected function restrictAnswerToVerifiedSources(string $content, array $sources): string {
        $allowed = [];
        foreach($sources as $source) {
            if(!is_array($source) || empty($source['verified'])) continue;
            $url = trim((string)($source['url'] ?? ''));
            if($url !== '') $allowed[$url] = true;
        }

        $content = preg_replace_callback(
            '~\[([^\]\r\n]{1,180})\]\(([^)\s<>"\']{1,2048})\)~u',
            function(array $match) use ($allowed): string {
                $label = trim((string)$match[1]);
                $url = $this->verifiedSourceUrl((string)$match[2]);
                return $url !== '' && isset($allowed[$url])
                    ? '[' . $label . '](' . $url . ')'
                    : $label;
            },
            $content
        ) ?? $content;

        $content = preg_replace('/\s*\[Source\s+\d+\]/iu', '', $content) ?? $content;
        $content = preg_replace('/[ \t]{2,}/u', ' ', $content) ?? $content;
        return trim($content);
    }

    protected function verifiedSourcesPrompt(array $sources): string {
        $prompt = 'The interface renders the verified source list separately. Never write [Source N] markers. '
            . 'Never construct, infer, autocomplete, translate, or guess a URL, slug, route, anchor, or citation. ';
        if(!$sources) return $prompt . 'Do not output Markdown links.';

        $lines = [];
        foreach($sources as $source) {
            if(!is_array($source) || empty($source['verified'])) continue;
            $lines[] = '- ' . (string)$source['url'] . ' — ' . (string)$source['title'];
        }
        return $prompt . 'A Markdown link is allowed only when its destination exactly matches one of these verified URLs; '
            . "otherwise write its label as plain text:\n" . implode("\n", $lines);
    }

    protected function isSameSiteUrl(string $url): bool {
        $url = trim($url);
        if(str_starts_with($url, '//')) $url = 'https:' . $url;
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        if($host === '') return false;
        $siteHost = strtolower(preg_replace('/:\d+$/', '', (string)$this->wire('config')->httpHost));
        return $host === $siteHost
            || $host === 'www.' . $siteHost
            || ('www.' . $host) === $siteHost;
    }

    protected function sanitizeSourceUrl(string $url): string {
        $url = trim($url);
        if($url === '') return '';
        $parts = parse_url($url);
        if($parts === false) return '';
        if(isset($parts['host'])) {
            $host = strtolower((string)$parts['host']);
            $siteHost = strtolower((string)parse_url((string)$this->wire('config')->urls->httpRoot, PHP_URL_HOST));
            if($host !== $siteHost) return '';
        }
        $path = (string)($parts['path'] ?? '/');
        return str_starts_with($path, '/') ? mb_substr($path, 0, 2048) : '';
    }

    protected function verifiedSourceUrl(string $url): string {
        $url = trim($url);
        if($url === '') return '';
        $parts = parse_url($url);
        if($parts === false) return '';
        if(isset($parts['scheme']) && !in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) return '';
        if(isset($parts['host'])) {
            $host = strtolower((string)$parts['host']);
            $siteHost = strtolower((string)parse_url((string)$this->wire('config')->urls->httpRoot, PHP_URL_HOST));
            if($host !== $siteHost) return '';
        }

        $path = (string)($parts['path'] ?? '/');
        if(!str_starts_with($path, '/') || str_starts_with($path, '//')) return '';
        $page = $this->wire('pages')->get($path);
        if(!$page || !$page->id || !$page->isPublic()) return '';
        $pageUrl = (string)$page->url;
        if(rtrim($pageUrl, '/') !== rtrim($path, '/')) return '';

        $suffix = isset($parts['query']) ? '?' . $parts['query'] : '';
        $suffix .= isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return mb_substr($path . $suffix, 0, 2048);
    }

    protected function sanitizeReferrerUrl(string $url): string {
        $url = trim($url);
        if($url === '') return '';
        $parts = parse_url($url);
        if($parts === false || empty($parts['host'])) return '';
        $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
        if(!in_array($scheme, ['http', 'https'], true)) return '';
        $host = strtolower((string)$parts['host']);
        $path = (string)($parts['path'] ?? '/');
        return mb_substr($scheme . '://' . $host . (str_starts_with($path, '/') ? $path : '/' . $path), 0, 2048);
    }

}
