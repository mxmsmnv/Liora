# Liora Examples

These examples target Liora 1.13.0. Confirm that Liora and Squad are installed
and configured before using them.

## Feature-Detect Liora

```php
<?php namespace ProcessWire;

if(!$modules->isInstalled('Liora')) {
    return;
}

/** @var Liora $liora */
$liora = $modules->get('Liora');

if(!$liora->isConfigured()) {
    echo '<p>Conversational help is temporarily unavailable.</p>';
    return;
}
```

## Add The Widget To A Page

```php
echo $liora->renderWidget([
    'context' => $page->template->name,
    'sourceUrl' => $page->url,
    'pageId' => $page->id,
    'heading' => 'Ask about this page',
    'intro' => 'What would you like to know?',
    'theme' => 'default',
]);
```

The configured endpoint must point to a real ProcessWire page whose template
calls `handleEndpoint()`.

## Search Fallback With Automatic Submission

```php
$searchQuery = trim((string)$input->get->text('q'));
$canonicalQuery = $searchSuggestion !== '' ? $searchSuggestion : $searchQuery;

echo $liora->renderWidget([
    'originalQuery' => $searchQuery,
    'retrievalQuery' => $canonicalQuery,
    'initialQuestion' => $searchQuery,
    'autoSubmitInitialQuestion' => $searchQuery !== '',
    'context' => 'search',
    'sourceUrl' => $page->url,
    'pageId' => $page->id,
    'thinkingLabel' => 'Preparing an overview…',
]);
```

This uses the normal tracked endpoint, including Thread ownership, optional
Atlas/Vox context, streaming, and Insights analytics.

`retrievalQuery` is useful when deterministic site search has corrected a
likely typo. Liora keeps `originalQuery` and the visible question unchanged,
but uses the bounded canonical hint to retrieve more relevant site records.

## Starter Questions

```php
echo $liora->renderWidget([
    'context' => 'product-help',
    'sourceUrl' => $page->url,
    'pageId' => $page->id,
    'showSuggestedPrompts' => true,
    'suggestedPrompts' => [
        'Help me choose',
        'Suggest a pairing',
        'What does the community think?',
    ],
]);
```

Starter questions appear only while the conversation is empty.

## ProcessWire Inputfield

```php
$form = $modules->get('InputfieldForm');

$field = $modules->get('InputfieldLiora');
$field->attr('name', 'liora_help');
$field->label = 'Ask Liora';
$field->value = $page->title;

$form->add($field);
echo $form->render();
```

The Inputfield is display-only and does not store conversation data as a page
field.

## Server-Side Answer

```php
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

Direct service calls do not create Insights Threads.

## Text-Only Completion

```php
$summary = $liora->complete(
    'Summarize this category in one sentence.',
    ['maxTokens' => 120]
);

if($summary !== false) {
    echo $sanitizer->entities($summary);
}
```

## Multi-Turn Server Conversation

```php
$result = $liora->chat([
    ['role' => 'system', 'content' => 'Answer as a concise product editor.'],
    ['role' => 'user', 'content' => 'I prefer bitter drinks.'],
    ['role' => 'assistant', 'content' => 'Do you prefer herbal or citrus?'],
    ['role' => 'user', 'content' => 'Herbal.'],
]);
```

Keep server-side history bounded. Do not place credentials, private user data,
or unpublished content in messages without an approved policy.

## Stream A Server-Side Answer

```php
$result = $liora->streamChat(
    [['role' => 'user', 'content' => 'Describe an Aperol Spritz.']],
    static function(string $delta): void {
        echo htmlspecialchars($delta, ENT_QUOTES, 'UTF-8');
        flush();
    },
    ['timeout' => 90]
);
```

The callback receives untrusted text deltas.

## Thin Endpoint Template

```php
<?php namespace ProcessWire;

if(!$modules->isInstalled('Liora')) {
    http_response_code(503);
    return;
}

$modules->get('Liora')->handleEndpoint();
```

Keep this endpoint out of full-page caches. Do not add business logic, provider
credentials, or custom SQL to the endpoint template.

## Optional Live Search Per Call

```php
$result = $liora->ask('What are the current releases?', [
    'webSearch' => true,
    'webSearchMaxResults' => 5,
]);
```

Live search can add provider cost and latency. Returned citations are public
web evidence, not proof of site inventory or unpublished site facts.

## Custom Frontend

Use the custom endpoint example in
[docs/INTEGRATION.md](docs/INTEGRATION.md). Preserve:

- POST and ProcessWire CSRF;
- the opaque `thread_id`;
- text rendering or an audited Markdown renderer;
- same-site source attribution;
- rate-limit and error states;
- anonymous and authenticated ownership boundaries.

Do not copy Liora's internal SQL or LocalStorage implementation into the site.
