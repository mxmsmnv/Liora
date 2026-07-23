# LQRS AI

`LqrsAi` is the site-level AI facade for LQRS. It keeps public templates and
CLI scripts independent from provider SDKs while delegating credentials,
provider selection, and model selection to [Squad](https://github.com/mxmsmnv/Squad).

```php
$ai = $modules->get('LqrsAi');

$result = $ai->chat([
    ['role' => 'system', 'content' => 'Answer concisely.'],
    ['role' => 'user', 'content' => 'What is mezcal?'],
]);

if($result['success']) echo $result['content'];
```

## Contract

- `isConfigured(): bool`
- `getModel(string $profile = 'default'): string`
- `ask(string $message, array $options = []): array`
- `complete(string $message, array $options = []): string|false`
- `chat(array $messages, array $options = []): array`

The module stores no API keys. Configure the active key, provider, and model in
Squad. Errors returned to public application code are deliberately normalized
so provider diagnostics and credentials are not exposed.

## Requirements

- ProcessWire 3.0.210+
- PHP 8.1+
- Squad with an active default provider key
