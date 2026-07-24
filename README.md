# Liora

![Liora](assets/Liora.png)

Liora is a ProcessWire conversational answer CTA and visitor-demand analytics
module. It helps when a page or search result does not answer a visitor’s
question, continues the conversation with useful context, and records the
unmet demand so editors can improve the site.

Liora does not own provider credentials or low-level AI transport. Those remain
in [Squad](https://github.com/mxmsmnv/Squad). Liora adds the conversation,
retrieval, frontend, privacy and editorial-review layers around Squad.

## What Liora includes

- a reusable frontend chat widget and `InputfieldLiora`;
- configurable Squad provider/model selection;
- normal JSON and real-time streamed responses;
- one Thread per conversation with chronological messages;
- context-aware follow-ups that retain recent visitor constraints;
- optional Atlas retrieval from public site content;
- optional live web search through Squad with normalized source attribution;
- optional Vox reviews, questions, replies and discussions;
- optional GeoIP country and city enrichment;
- browser-only conversation history that the visitor explicitly restores;
- a configurable welcome message and up to three starter-question buttons;
- native ProcessWire localization with eight ready-made language presets;
- adaptive Light/Dark themes backed by JSON design tokens;
- safe Markdown rendering and same-site source links;
- optional copy, response-time and token-usage metadata;
- a paginated **Setup → Liora Insights** editorial dashboard;
- complete Thread-context copying for debugging or sharing with Codex;
- permission-controlled message and Thread deletion;
- migration of legacy LQRS `ai` ProFields Table history.

## Architecture

| Component | Responsibility |
| --- | --- |
| Liora | Conversation flow, widget, tracking, retrieval orchestration and Insights |
| Squad | Credentials, provider/model discovery, requests and streaming transport |
| Atlas | Optional retrieval from indexed public site content |
| Vox | Optional published community content attached to relevant pages |
| GeoIP | Optional country, region and city lookup |
| ProcessWire | Pages, sessions, permissions, localization and persistence |

Liora installs three ProcessWire modules:

| Module | Purpose |
| --- | --- |
| `Liora` | Service API, endpoint, configuration and widget renderer |
| `InputfieldLiora` | Reusable ProcessWire Inputfield using the same widget |
| `ProcessLiora` | **Setup → Liora Insights** dashboard |

## Requirements

- ProcessWire 3.0.210 or newer;
- PHP 8.1 or newer;
- an installed and configured Squad module;
- a ProcessWire endpoint page when using tracked frontend conversations.

Atlas, Vox and GeoIP are optional. Liora continues without them when they are
missing, disabled, empty or temporarily unavailable.

Live web search is also optional and disabled by default because it can add
provider charges and latency. It requires Squad 1.9.0 or newer.

## Installation

1. Place `Liora/` in `site/modules/`.
2. In **Modules**, refresh the module cache.
3. Install **Liora**. ProcessWire also installs `InputfieldLiora` and
   `ProcessLiora`.
4. Configure at least one active provider in Squad.
5. Open **Modules → Liora**, choose a provider/model or keep the Squad default,
   and review the system prompt and visitor-facing settings.
6. Create or reuse the JSON endpoint page used by the widget.

The endpoint template is intentionally thin:

```php
<?php namespace ProcessWire;

$modules->get('Liora')->handleEndpoint();
```

LQRS uses `/agent/` as the endpoint. Keep the configured endpoint URL aligned
with the actual ProcessWire page.

## Choosing an integration

| Mode | Ready-made UI | Creates Insights Threads | Atlas/Vox context |
| --- | --- | --- | --- |
| `renderWidget()` | Yes | Yes | Yes |
| `InputfieldLiora` | Yes | Yes | Yes |
| Custom frontend + JSON endpoint | No | Yes | Yes |
| `ask()`, `chat()`, `complete()`, `streamChat()` | No | No | Call-specific |

The **Allow the ready-made Liora chat widget to render** setting affects
`renderWidget()` and `InputfieldLiora` only. It does not disable the PHP API or
Liora Insights, and it never inserts the widget automatically.

See [docs/INTEGRATION.md](docs/INTEGRATION.md) for complete examples, including
a custom frontend without the ready-made widget.

## Ready-made widget

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
    'showWelcomeMessage' => true,
    'welcomeMessage' => 'Hi — I’m Liora. How can I help?',
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

Supported per-widget options:

| Option | Purpose |
| --- | --- |
| `originalQuery` | The unsuccessful search or initial demand signal |
| `context` | Short application/template context label |
| `sourceUrl` | Current same-site source path |
| `pageId` | Current ProcessWire page ID |
| `heading`, `intro`, `placeholder` | Per-placement text overrides |
| `showWelcomeMessage`, `welcomeMessage` | Empty-conversation introduction |
| `showSuggestedPrompts` | Enable starter-question buttons |
| `suggestedPrompts` | Up to three non-empty starter questions |
| `theme` | `default`, `light`, `dark` or another installed JSON theme |
| `compact` | Reduced outer presentation for embedded placements |

Starter questions appear only while a conversation is empty. Clicking one
submits it through the normal tracked endpoint and starts a Thread. The visitor
can continue from the answer as with a typed question.

## ProcessWire Inputfield

`InputfieldLiora` renders the same interface inside a ProcessWire form:

```php
$field = $modules->get('InputfieldLiora');
$field->attr('name', 'liora_help');
$field->label = 'Ask Liora';
$field->value = $page->title; // original query/context shown to Liora
$form->add($field);
```

The Inputfield is display-only. Conversation data is stored by `LioraStore`,
not in the submitted page field.

## PHP service API

```php
$liora = $modules->get('Liora');

$result = $liora->ask('Suggest a food pairing.');

$text = $liora->complete(
    'Explain the difference between Cognac and Armagnac.'
);

$result = $liora->chat([
    ['role' => 'system', 'content' => 'Keep the answer concise.'],
    ['role' => 'user', 'content' => 'I prefer herbal liqueurs.'],
    ['role' => 'assistant', 'content' => 'Do you prefer bitter or sweet?'],
    ['role' => 'user', 'content' => 'Bitter.'],
]);

$result = $liora->streamChat(
    [['role' => 'user', 'content' => 'Describe an Aperol Spritz.']],
    static function(string $delta): void {
        echo $delta;
        flush();
    }
);
```

Direct PHP calls return service results but do not create visitor Threads.
Use the JSON endpoint for interfaces that must preserve the conversation,
source attribution, GeoIP metadata and Insights analytics.

## Conversation behavior

The server stores one row per conversation in `liora_threads` and chronological
messages in `liora_messages`. Follow-up requests include the configured recent
message history.

Liora explicitly instructs the model to:

- interpret short replies as answers to its preceding question;
- combine new preferences with earlier constraints;
- avoid greeting again or restarting the topic;
- avoid repeating questions already answered;
- move forward with a recommendation or one useful clarification.

For Atlas retrieval, a short follow-up includes up to the three most recent
distinct visitor constraints. A sequence such as “Australian liqueur brands” →
“herbal” therefore retains both the country and category during retrieval.

The public Thread ID is accepted only for the current hashed ProcessWire
session or authenticated user. A browser-stored conversation can seed a new
server Thread after its original server session expires without exposing the
plaintext session identifier.

## Browser conversation history

Browser history is stored only in LocalStorage and is never restored
automatically. The visitor chooses a Thread with **Previous conversations**.

Visitors can:

- start an explicit new conversation;
- restore a previous browser conversation;
- rename a conversation;
- expand a long conversation;
- continue a restored conversation when the server can safely associate it.

The number of retained browser Threads is configurable. Disabling local history
does not disable server-side Insights tracking.

The welcome message and starter questions are presentation-only. They are not
stored as messages or sent to the model.

## Streaming and answer rendering

When Squad and the selected provider support streaming, Liora uses
newline-delimited JSON and replaces the localized **Liora is thinking** state
when the first provider delta arrives. Disabling streaming uses a normal JSON
response.

Assistant output is treated as untrusted text. The widget uses a small escaped
Markdown renderer for:

- paragraphs and headings;
- ordered and unordered lists;
- bold text;
- inline and fenced code;
- numbered Atlas citations;
- verified same-site links.

Raw model HTML is never trusted. External links remain inert or are removed
when the stay-on-site policy is enabled.

Optional message metadata includes:

- copy action;
- response time;
- provider-reported token usage;
- Atlas and Vox source links.

## Staying on the site

Enable **Keep visitors on this website** to append an editable policy to the
system prompt. The default policy prevents recommendations or links to external
retailers, marketplaces, search engines and other off-site destinations while
still allowing reliable general knowledge.

Completed responses also pass through deterministic server-side filtering.
Same-site links and verified site sources remain available.

This setting controls destinations, not knowledge. It should not force Liora to
pretend that its general knowledge is empty.

## Optional Atlas retrieval

Enable **Atlas knowledge** after Atlas has a populated collection. The default
collection name is `site`.

Liora:

1. tries fast lexical retrieval for significant local terms;
2. falls back to semantic search when lexical retrieval has no result;
3. applies the configured result count, score and context-size limits;
4. re-resolves ProcessWire page IDs and rejects non-public pages;
5. treats retrieved excerpts as untrusted reference material;
6. sends safe excerpts to the selected Squad model;
7. displays validated source links below the answer.

Entries without a ProcessWire page ID require explicit `public: true` metadata.
Atlas failures use the normal non-RAG answer path.

Atlas supplements general model knowledge; it does not replace it. Irrelevant
or incomplete excerpts should be ignored rather than forcing a false “the site
knows nothing” response.

### Atlas is not web search

Atlas searches content already indexed from the site. It does not search the
public internet. Live public search is a separate, explicit option.

Liora must not claim that it browsed the web when it did not.

## Optional live web search

Enable **Use live web search** in **Modules → Liora → AI model** when answers
need current public information. The maximum-results setting is passed to
Squad, which selects the correct transport:

- OpenRouter web plugin for every routed model;
- Anthropic's native web-search tool;
- OpenAI or xAI Responses API search;
- native Google Search grounding.

Search citations are normalized by Squad and merged with Atlas/Vox sources in
the widget. With **Keep visitors on this website** enabled, external source
titles remain visible as attribution but do not become outbound links. Web
results must not be treated as proof that a product or price exists in the LQRS
catalogue; Atlas or page context must confirm site-specific facts.

Code integrations can override the module default per call:

```php
$result = $modules->get('Liora')->ask('What is new in Australian herbal liqueurs?', [
    'webSearch' => true,
    'webSearchMaxResults' => 5,
]);
```

## Optional Vox community context

When Vox is installed, its integration is enabled by default and remains
independently configurable.

Liora reads only published reviews, questions, replies and discussions attached
to:

- the current public page; or
- a public page retrieved through Atlas.

Review ratings and recommendation flags may be included. Pending/spam entries,
email addresses, fingerprints, IP data, photos and other private fields are
never sent to Squad.

Vox excerpts are labelled as user-generated opinions. Liora is instructed not
to present one comment as editorial fact or broad community consensus.

## Optional GeoIP enrichment

When GeoIP is installed, new Threads may record country, region and city
metadata. Liora Insights shows the available location beside the conversation.

Liora does not persist the visitor’s raw IP address or user agent. GeoIP
failures leave the location empty and do not block the conversation.

## Localization

All visitor-facing widget strings use ProcessWire multi-language fields and
fall back to the default-language value when a translation is empty.

Included presets:

- English;
- German;
- French;
- Spanish;
- Italian;
- Dutch;
- Polish;
- Russian.

Presets fill headings, controls, errors, notices, welcome copy, starter
questions and accessibility labels. On a multi-language site, select the target
ProcessWire language before applying a preset and save the module configuration.

The default system prompt asks the model to answer in the visitor’s language.

## Themes

Widget behavior lives in `assets/liora.js`, structural styles in
`assets/liora.css`, and visual tokens in `themes/*.json`.

Included themes:

| Theme | Behavior |
| --- | --- |
| `default` | Adaptive Light/Dark using `prefers-color-scheme` |
| `light` | Forces the light palette |
| `dark` | Forces the dark palette |

The adaptive theme switches live when the operating-system preference changes.
Theme JSON can set allowlisted colors, radius, shadow, message width and
responsive conversation height. Liora validates the values and emits scoped
CSS custom properties.

## Liora Insights

Open **Setup → Liora Insights** to review visitor demand.

The dashboard includes:

- summary counts and frequent unanswered searches;
- filters for `new`, `reviewing`, `content_added`, `dismissed` and `failed`;
- ten Threads per page with pagination;
- chronological visitor, Liora and error messages;
- source page, referrer, location, model and message metadata;
- collapsed Threads by default;
- remembered open/closed state and scroll position per filter/page;
- status updates;
- per-message and complete-Thread deletion;
- a model-configuration warning and direct settings link.

**Copy context** copies a complete text block suitable for Codex or another
debugging tool. It contains safe Thread metadata, source information, message
timestamps, provider/model names, token totals and every message in order. It
does not include provider credentials, raw IP addresses, user agents or
plaintext session identifiers.

## Permissions

| Permission | Capability |
| --- | --- |
| `liora-review` | Open and review Liora Insights |
| `liora-delete` | Delete individual messages or complete Threads |

Superusers automatically have both capabilities. Admin mutations use POST and
ProcessWire CSRF validation.

## Privacy and data retention

Liora stores:

- hashed session ownership;
- authenticated ProcessWire user ID when available;
- Thread and message content;
- page/source attribution;
- provider/model and token metadata;
- optional GeoIP country, region and city.

Liora intentionally does not store:

- provider API keys;
- raw IP addresses;
- browser user agents;
- plaintext session identifiers.

The quality-review disclosure beneath the widget is editable and can be hidden.
The default explains that questions may be reviewed to improve LQRS and asks
visitors not to submit personal details.

Conversation data is preserved on uninstall by default. Enable the destructive
uninstall option only when the stored Threads should be permanently removed.

## Important limitations

- Liora is not a general web-search engine.
- Atlas can answer only from content that has been indexed.
- Vox contributes only published entries linked to relevant public pages.
- Token counts appear only when the provider reports them.
- Model output can still be incorrect; the visitor-facing disclaimer should
  remain enabled for factual or safety-sensitive content.
- Direct PHP calls do not create Insights Threads.

## Development and validation

Run the bundled checks:

```bash
for file in *.php; do php -l "$file"; done
node --check assets/liora.js
node --check assets/liora-admin.js
php tests/run.php
git diff --check
```

For a release, also verify in a real ProcessWire installation:

1. module discovery and displayed version;
2. one normal and one streamed Squad response;
3. a multi-turn follow-up conversation;
4. Thread creation and context copying in Liora Insights;
5. Atlas/Vox source handling when enabled;
6. the widget in Light and Dark modes without browser-console errors.

## Repository layout

```text
Liora.module.php             Main service, endpoint, config and widget renderer
LioraStore.php               Thread/message persistence
InputfieldLiora.module.php   Reusable ProcessWire Inputfield
ProcessLiora.module.php      Liora Insights dashboard
assets/liora.js              Public widget behavior
assets/liora.css             Public widget structure
assets/liora-admin.js        Insights interactions
assets/liora-admin.css       Insights presentation
themes/*.json                Validated widget design tokens
docs/INTEGRATION.md          Complete integration examples
tests/run.php                Repository smoke tests
```

Never store provider credentials in Liora, templates or browser JavaScript.
Configure them only in Squad.
