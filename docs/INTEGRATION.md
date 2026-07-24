# Integrating Liora

Liora has a service layer and an optional ready-made chat interface. Choose the
integration based on whether visitors need a conversation UI and whether their
questions should become tracked Threads in **Setup → Liora Insights**.

| Integration | Ready-made UI | Tracked Thread | Atlas and page context |
| --- | --- | --- | --- |
| `renderWidget()` | Yes | Yes | Yes |
| `InputfieldLiora` | Yes | Yes | Yes |
| Custom frontend using the JSON endpoint | No | Yes | Yes |
| PHP `ask()`, `chat()`, `complete()` or `streamChat()` | No | No | Call-specific |

## Requirements

1. Install and configure Squad with an active provider and model.
2. Install Liora and select the provider/model or keep the Squad default.
   Live web search is disabled by default. Enable it in Liora settings or pass
   `webSearch => true` and an optional `webSearchMaxResults` value (1–10) to
   `ask()`, `chat()` or `streamChat()`. Squad 1.9.0+ translates the option to
   OpenRouter, Anthropic, OpenAI, Google or xAI and returns normalized sources.
3. Keep the endpoint setting aligned with the ProcessWire endpoint page. LQRS
   uses `/agent/` with this template:

```php
<?php namespace ProcessWire;

$modules->get('Liora')->handleEndpoint();
```

Provider credentials belong in Squad. Do not put them in templates, browser
JavaScript or Liora configuration.

## Ready-made chat in a template

The module emits its own CSS and deferred JavaScript once per request.

```php
<?php namespace ProcessWire;

$liora = $modules->get('Liora');

echo $liora->renderWidget([
    'originalQuery' => $searchQuery ?? '',
    'context' => $page->template->name,
    'sourceUrl' => $page->url,
    'pageId' => $page->id,
    'heading' => 'Still looking? Ask Liora',
    'intro' => 'Ask a question about this page.',
    'placeholder' => 'What would you like to know?',
    'showSuggestedPrompts' => true,
    'suggestedPrompts' => [
        'Help me choose a bottle',
        'Suggest a food pairing',
        'What do people think about this?',
    ],
    'theme' => 'default',
    'compact' => false,
]);
```

Starter questions appear only in an empty conversation. Clicking one submits it
immediately and starts the same tracked Thread flow as a typed question. The
visitor can then continue normally. Configure and translate the three defaults
in Liora settings, or override them per widget with `suggestedPrompts` (up to
three non-empty strings). Set `showSuggestedPrompts` to `false` to hide them for
one widget.

Use `theme => 'default'` for the adaptive Light + Dark theme, `light` to force
the light palette, or `dark` to force the dark palette. The adaptive theme uses
`prefers-color-scheme` and switches live when the visitor changes the operating
system appearance.

For reuse across templates, put that code in an include such as
`site/templates/includes/liora.php`:

```php
include $config->paths->templates . 'includes/liora.php';
```

The **Allow the ready-made Liora chat widget to render** setting must be enabled.
The setting only permits rendering; it does not automatically insert the widget
into every page.

## ProcessWire Inputfield

`InputfieldLiora` uses the same widget and endpoint but lets a form control its
placement:

```php
<?php namespace ProcessWire;

$form = $modules->get('InputfieldForm');
$lioraField = $modules->get('InputfieldLiora');
$lioraField->attr('name', 'liora_help');
$lioraField->label = 'Ask Liora';
$lioraField->value = $page->title;
$form->add($lioraField);

echo $form->render();
```

## Server-side use without a widget

Use `ask()` when PHP code needs the normalized response and metadata:

```php
<?php namespace ProcessWire;

$liora = $modules->get('Liora');
$result = $liora->ask('Suggest a food pairing for this product.', [
    'pageId' => $page->id,
    'maxTokens' => 500,
    'temperature' => 0.3,
]);

if($result['success']) {
    echo '<p>' . $sanitizer->entities($result['content']) . '</p>';
} else {
    $log->save('liora-integration', $result['error']);
}
```

Use `complete()` when only the text or `false` is needed:

```php
$text = $liora->complete('Summarize this category in one sentence.');
```

Use `chat()` for an OpenAI-style message list:

```php
$result = $liora->chat([
    ['role' => 'system', 'content' => 'Answer as a concise product editor.'],
    ['role' => 'user', 'content' => 'How does Cognac differ from Armagnac?'],
]);
```

These direct calls do not create a visitor conversation in Liora Insights. They
are intended for trusted server-side application logic.

## Custom frontend without the widget

A custom interface can POST to the configured JSON endpoint. This keeps the
Thread model, session ownership, rate limiting, page attribution, optional
GeoIP, Atlas retrieval and Insights analytics without loading `liora.css` or
`liora.js`.

```php
<?php namespace ProcessWire;

$csrfName = $session->CSRF->getTokenName();
$csrfValue = $session->CSRF->getTokenValue();
$configData = [
    'endpoint' => '/agent/',
    'csrfName' => $csrfName,
    'csrfValue' => $csrfValue,
    'context' => $page->template->name,
    'sourceUrl' => $page->url,
];
?>

<form id="custom-liora">
    <label for="custom-liora-question">Ask about this page</label>
    <input id="custom-liora-question" required maxlength="1000">
    <button type="submit">Ask</button>
</form>
<pre id="custom-liora-answer" aria-live="polite"></pre>

<script>
(() => {
    const config = <?= json_encode(
        $configData,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const form = document.querySelector('#custom-liora');
    const input = document.querySelector('#custom-liora-question');
    const answer = document.querySelector('#custom-liora-answer');
    let threadId = '';

    form.addEventListener('submit', async event => {
        event.preventDefault();
        answer.textContent = 'Liora is thinking…';

        const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            [`X-${config.csrfName}`]: config.csrfValue,
        };
        const response = await fetch(config.endpoint, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                message: input.value,
                threadId,
                context: config.context,
                sourceUrl: config.sourceUrl,
                referrerUrl: document.referrer || '',
                stream: false,
            }),
        });
        const data = await response.json();
        if(data.thread_id) threadId = data.thread_id;
        answer.textContent = data.success ? data.response : data.error;
    });
})();
</script>
```

Render model output as text unless the application adds an audited Markdown
renderer and HTML sanitizer. Do not place raw model output into `innerHTML`.

## Which option should LQRS use?

- Use the ready-made widget for search fallbacks, product help, collections and
  the dedicated AI page.
- Use the JSON endpoint for a future page-specific custom chat design that must
  still produce measurable demand signals.
- Use the PHP service API for internal enrichment or editorial tools where no
  visitor conversation exists.
