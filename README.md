# Liora

Liora is a ProcessWire AI answer CTA and visitor-demand analytics module for
LQRS. It helps a visitor when a search or page does not answer their question,
then records the question so editors can improve the underlying site content.

Squad owns provider credentials and transport. Liora adds:

- a reusable `InputfieldLiora` and frontend widget;
- selectable active provider/model settings;
- configurable prompt, token, timeout, cache, rate-limit and CTA settings;
- privacy-conscious question tracking without raw IP addresses;
- a **Setup → Liora Insights** review dashboard;
- import of the legacy `ai` ProFields Table history.

## Template API

```php
$liora = $modules->get('Liora');

echo $liora->renderWidget([
    'originalQuery' => $searchQuery,
    'context' => $page->template->name,
    'pageId' => $page->id,
]);
```

The JSON endpoint template only needs:

```php
$modules->get('Liora')->handleEndpoint();
```

Direct application calls remain available:

```php
$result = $liora->ask('Suggest a food pairing.');
$text = $liora->complete('Explain the difference between Cognac and Armagnac.');
```

Never store provider credentials in Liora. Configure them in Squad.
