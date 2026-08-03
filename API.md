# Liora Public API

This document describes the public interface of Liora 1.0.0. Verify the
installed module version and current ProcessWire site state before using it.

Provider credentials belong to Squad. Do not pass credentials through Liora
options, templates, endpoint payloads, or browser JavaScript.

## Obtaining The Module

```php
<?php namespace ProcessWire;

if(!$modules->isInstalled('Liora')) {
    return;
}

/** @var Liora $liora */
$liora = $modules->get('Liora');
```

In a module class, use the injected ProcessWire API:

```php
$liora = $this->wire()->modules->get('Liora');
```

## Service Status And Selection

### `isConfigured(): bool`

Returns `true` when Squad is installed and the effective provider is active.

It confirms configuration availability, not that a provider request will
succeed.

### `getProvider(): string`

Returns Liora's configured provider key or Squad's current default provider key.
Returns an empty string when no provider can be resolved.

### `getModel(string $profile = 'default'): string`

Returns the configured model, Squad's selected default model, or an explicit
model string supplied as `$profile`.

Recognized profile labels are `default`, `cheap`, `non-reasoning`, and
`reasoning`. Any other non-empty value is treated as an explicit model name.

### `getProviderModelOptions(): array`

Returns the active provider/model choices exposed by the current Squad
installation. The result is an option map suitable for a ProcessWire select
input:

```php
[
    'provider:model' => 'Provider — Model',
]
```

The exact keys depend on the installed Squad version and configured providers.

## Text Completion

### `ask(string $message, array $options = []): array`

Sends one user message through Squad and returns Liora's normalized response.

Common options:

| Option | Type | Meaning |
| --- | --- | --- |
| `provider` or `squad_provider` | string | Provider override |
| `model` | string | Model or profile override |
| `maxTokens` or `max_tokens` | int | Output-token limit |
| `temperature` | float | Provider temperature, clamped to 0–2 |
| `timeout` | int | Request timeout, clamped to 5–300 seconds |
| `cache` | bool/int | Squad cache override |
| `pageId` | int | Optional ProcessWire page context |
| `webSearch` | bool/string | Per-call search override |
| `webSearchMaxResults` | int | Search result limit, clamped to 1–10 |

Success shape:

```php
[
    'success' => true,
    'status' => 200,
    'error' => '',
    'content' => 'Normalized assistant text',
    'data' => [
        'provider' => 'provider-key',
        'model' => 'model-name',
        'usage' => [],
        'cached' => false,
        'sources' => [],
        'response_time_ms' => 1234,
        'metadata' => [],
    ],
]
```

Failure shape:

```php
[
    'success' => false,
    'status' => 0,
    'error' => 'Safe error message',
]
```

Endpoint HTTP failures use their own explicit HTTP status. Direct service
errors use the normalized shape above. Do not display raw provider errors to
visitors.

### `complete(string $message, array $options = [])`

Returns the successful assistant text as a string or `false` on failure.

```php
$text = $liora->complete('Summarize this page in one sentence.');

if($text === false) {
    // Handle the failure without inventing an answer.
}
```

### `chat(array $messages, array $options = []): array`

Sends an OpenAI-style message list through Squad and returns the same normalized
shape as `ask()`.

Supported roles are interpreted as follows:

- `system` and `developer` contribute to the system prompt;
- `assistant` contributes assistant history;
- other roles are normalized to user history;
- the last user message becomes the current request.

```php
$result = $liora->chat([
    ['role' => 'system', 'content' => 'Answer concisely.'],
    ['role' => 'user', 'content' => 'I prefer bitter drinks.'],
    ['role' => 'assistant', 'content' => 'Do you prefer herbal or citrus?'],
    ['role' => 'user', 'content' => 'Herbal.'],
]);
```

Empty messages and unsupported content shapes are ignored. A request without a
usable user message returns an error response.

### `streamChat(array $messages, callable $onDelta, array $options = []): array`

Streams plain-text deltas through `$onDelta` and returns the complete normalized
response when Squad finishes.

```php
$result = $liora->streamChat(
    [['role' => 'user', 'content' => 'Describe an Aperol Spritz.']],
    static function(string $delta): void {
        echo $delta;
        flush();
    },
    ['timeout' => 90]
);
```

The callback receives untrusted plain text. Escape or render it through an
audited Markdown path appropriate to the response format.

Direct calls to `ask()`, `complete()`, `chat()`, and `streamChat()` do not
create Liora Insights Threads.

## Ready-Made Widget

### `renderWidget(array $options = []): string`

Returns the complete public widget markup or an empty string when widget
rendering is disabled.

Supported options:

| Option | Type | Purpose |
| --- | --- | --- |
| `originalQuery` | string | Search/query context shown to Liora |
| `context` | string | Site-defined context label |
| `sourceUrl` | string | Current same-site source URL |
| `pageId` | int | Current ProcessWire page ID |
| `heading` | string | Widget heading override |
| `intro` | string | Intro text override |
| `placeholder` | string | Composer placeholder |
| `thinkingLabel` | string | In-conversation loading label |
| `initialQuestion` | string | Composer's initial question |
| `autoSubmitInitialQuestion` | bool | Submit the initial question after initialization |
| `showWelcomeMessage` | bool | Show the empty-state welcome message |
| `welcomeMessage` | string | Welcome-message override |
| `showSuggestedPrompts` | bool | Show starter-question buttons |
| `suggestedPrompts` | string[] | Up to three non-empty starter questions |
| `theme` | string | `default`, `light`, or `dark` |
| `compact` | bool | Compact widget presentation |
| `preview` | bool | Static configuration-preview mode |

The method emits module CSS and JavaScript once per request. It does not insert
itself into templates.

## ProcessWire Inputfield

`InputfieldLiora` renders the same widget inside a ProcessWire form:

```php
$field = $modules->get('InputfieldLiora');
$field->attr('name', 'liora_help');
$field->label = 'Ask Liora';
$field->value = $page->title;
$form->add($field);
```

The Inputfield is display-only. Its submitted value is not conversation
storage; tracked messages belong to `LioraStore`.

## Tracked JSON Endpoint

### `handleEndpoint(): void`

Handles the JSON endpoint used by the widget and custom visitor interfaces.

Use it only in a thin ProcessWire template:

```php
<?php namespace ProcessWire;

$modules->get('Liora')->handleEndpoint();
```

The endpoint:

- accepts POST only;
- validates ProcessWire CSRF when site protection is enabled;
- enforces question length and rate limits;
- binds Thread ownership to the current session hash or authenticated user;
- records page/referrer attribution;
- optionally adds Atlas, Vox, GeoIP, and web-search context;
- supports normal JSON and newline-delimited streamed responses.

Common request fields:

| Field | Type | Meaning |
| --- | --- | --- |
| `message` | string | Required visitor question |
| `threadId` | string | Existing opaque public Thread ID |
| `originalQuery` | string | Original site search/query |
| `context` | string | Site-defined context label |
| `sourceUrl` | string | Current same-site page URL |
| `referrerUrl` | string | Browser referrer |
| `history` | array | Client history used only when seeding a new Thread |
| `stream` | bool | Request NDJSON streaming |
| `action` | string | Currently `rename` for an owned Thread |
| `title` | string | New title for `rename` |

Do not expose internal database IDs. Persist and send only the opaque
`thread_id` returned by the endpoint.

## Configuration Areas

Liora's ProcessWire module configuration covers:

- Squad provider/model selection;
- prompt, generation, timeout, cache, and streaming behavior;
- optional adaptive/always live web search;
- widget text, starter prompts, metadata, themes, and local history;
- endpoint URL, rate limit, and question length;
- Atlas retrieval routing and context limits;
- Vox community context;
- privacy disclosure, external-link restrictions, and data retention;
- destructive uninstall.

Changing provider costs, live search, public endpoint behavior, privacy text,
external-link policy, retention, or destructive uninstall requires project
review.

## Hooks

Liora 1.0.0 does not document a stable public hook API. Do not invent hook
names. Use the public methods above or open an issue for a required extension
point.

## Internal And Maintenance APIs

Do not call these from site templates or third-party modules:

- protected methods in any `src/` trait;
- `LioraStore` SQL and persistence helpers;
- `importLegacyHistory()`;
- `___install()`, `___upgrade()`, and `___uninstall()`;
- `ProcessLiora` rendering and action methods;
- JavaScript implementation details or LocalStorage keys.

`store()` is public for module composition but exposes persistence internals.
Prefer the tracked endpoint and documented service APIs. If an integration must
read Liora data, add and document a narrow public method rather than coupling it
to tables.

## Compatibility And Errors

Liora 1.0.0 requires ProcessWire 3.0.210+, PHP 8.1+, and Squad. Optional
capabilities must be feature-detected.

Atlas, Vox, GeoIP, live search, streaming, and provider metadata can be absent
or fail independently. Integrations must keep a safe empty/error state and must
not fabricate sources, answers, or installation state.
