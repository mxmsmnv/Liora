# Liora

Liora is a ProcessWire AI answer CTA and visitor-demand analytics module for
LQRS. It helps a visitor when a search or page does not answer their question,
then records the question so editors can improve the underlying site content.

Squad owns provider credentials and transport. Liora adds:

- a reusable `InputfieldLiora` and frontend widget;
- selectable active provider/model settings;
- configurable prompt, token, timeout, cache, rate-limit and CTA settings;
- conversation threads with chronological visitor and Liora messages;
- optional real-time streamed answers through Squad;
- browser-only conversation history that visitors explicitly restore;
- privacy-conscious demand tracking without raw IP addresses;
- optional country and city enrichment when the GeoIP module is installed;
- an editable, friendly quality-review notice below the widget;
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

## Conversation model

The server stores one row per conversation in `liora_threads` and chronological
messages in `liora_messages`. The public thread ID is accepted only for the
current hashed ProcessWire session (or authenticated user), so a LocalStorage
copy can safely seed a new server-side conversation after a session expires.

Local history is not loaded into the widget automatically. The visitor uses
**Previous conversations** to choose a browser-stored thread. It can be disabled
and its retention limit can be configured in the Liora module settings.

Long answers keep their beginning in view while provider deltas arrive. The
conversation grows until its responsive height limit; **Expand conversation**
removes the inner scrollbar when the visitor wants to read the complete thread.

When Squad supports the selected provider, Liora sends newline-delimited JSON
and renders provider deltas as they arrive. Disabling streaming keeps the
regular JSON response flow.

## Widget themes

Widget behavior lives in `assets/liora.js`, base layout lives in
`assets/liora.css`, and visual tokens live in `themes/*.json`. Select the active
theme in the Liora module settings or pass `theme` to `renderWidget()`.

Theme JSON can set the allowlisted colors, radius, shadow, message width and
responsive conversation height. Liora reads the file server-side and emits
validated CSS custom properties, so the browser does not need another request.
