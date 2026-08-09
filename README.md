# Liora

Liora adds conversational answers and visitor-demand analytics to ProcessWire.
It helps when a page or search result does not fully answer a visitor's
question, continues the conversation with relevant context, and records that
demand so editors can improve the site.

![Liora](assets/readme-doodle.png)

It is made for editorial, commerce, support, directory, and knowledge sites
that want an on-page answer experience without hiding provider access,
retrieval, privacy, or editorial review behind a black box.

**Version:** 1.14.0<br>
**Author:** Maxim Semenov<br>
**Website:** [smnv.org](https://smnv.org)<br>
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development:
[GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or
[smnv.org/sponsor](https://smnv.org/sponsor/).

Liora does not store provider credentials or implement low-level AI transport.
Those responsibilities remain in
[Squad](https://github.com/mxmsmnv/Squad).

## What Liora Does

- Adds a reusable frontend conversation widget and `InputfieldLiora`.
- Supports normal JSON responses and real-time streamed responses.
- Keeps one tracked Thread per visitor conversation with chronological messages.
- Preserves recent constraints so short follow-up answers remain in context.
- Records unanswered questions and conversation demand in ProcessWire.
- Adds **Setup → Liora Insights** for editorial review and diagnostics.
- Supports optional Atlas retrieval from indexed public site content.
- Accepts a separate canonical retrieval hint for typo-tolerant site search
  without rewriting the visitor's recorded question.
- Supports optional published Vox reviews, questions, replies, and discussions.
- Supports optional GeoIP country, region, and city enrichment.
- Supports optional live public search through Squad with normalized citations.
- Provides adaptive Light/Dark themes backed by validated JSON tokens.
- Includes safe Markdown rendering and verified same-site links.
- Includes localized widget text and ready-made language presets.
- Keeps browser history in LocalStorage and restores it only when the visitor
  chooses a previous conversation.
- Provides public PHP APIs and a tracked JSON endpoint for custom interfaces.

## Architecture

| Component | Responsibility |
| --- | --- |
| Liora | Conversations, widget, tracking, retrieval orchestration, and Insights |
| Squad | Credentials, provider discovery, requests, and streaming transport |
| Atlas | Optional retrieval from indexed public site content |
| Vox | Optional published community evidence for relevant pages |
| GeoIP | Optional country, region, and city lookup |
| ProcessWire | Pages, sessions, permissions, localization, and persistence |

Liora installs three ProcessWire modules:

| Module | Purpose |
| --- | --- |
| `Liora` | Service API, endpoint, configuration, and widget renderer |
| `InputfieldLiora` | Reusable ProcessWire Inputfield using the same widget |
| `ProcessLiora` | **Setup → Liora Insights** dashboard |

## Integration Modes

| Mode | Ready-made UI | Creates Insights Threads | Atlas/Vox context |
| --- | --- | --- | --- |
| `renderWidget()` | Yes | Yes | Yes |
| `InputfieldLiora` | Yes | Yes | Yes |
| Custom frontend + JSON endpoint | No | Yes | Yes |
| `ask()`, `chat()`, `complete()`, `streamChat()` | No | No | Call-specific |

The widget setting permits `renderWidget()` and `InputfieldLiora`; it never
inserts a widget automatically. The consuming site owns placement, page
structure, routes, frontend composition, and content policy.

## Ready-Made Widget

```php
<?php namespace ProcessWire;

if($modules->isInstalled('Liora')) {
    /** @var Liora $liora */
    $liora = $modules->get('Liora');

    echo $liora->renderWidget([
        'context' => $page->template->name,
        'sourceUrl' => $page->url,
        'pageId' => $page->id,
        'heading' => 'Still looking? Ask Liora',
        'theme' => 'default',
    ]);
}
```

The tracked endpoint page uses a deliberately thin template:

```php
<?php namespace ProcessWire;

$modules->get('Liora')->handleEndpoint();
```

Keep the configured endpoint URL aligned with that ProcessWire page.

## PHP Service API

```php
$liora = $modules->get('Liora');

$result = $liora->ask('Suggest a food pairing.', [
    'pageId' => $page->id,
    'maxTokens' => 500,
]);

$text = $liora->complete('Summarize this category in one sentence.');

$result = $liora->chat([
    ['role' => 'system', 'content' => 'Keep the answer concise.'],
    ['role' => 'user', 'content' => 'How does Cognac differ from Armagnac?'],
]);
```

Direct PHP calls do not create visitor Threads in Liora Insights. Use the JSON
endpoint for a visitor interface that must preserve ownership, page
attribution, optional retrieval, and demand analytics.

See [API.md](API.md) for exact methods, options, return shapes, endpoint fields,
and internal APIs. See [EXAMPLES.md](EXAMPLES.md) and
[docs/INTEGRATION.md](docs/INTEGRATION.md) for known-good integrations.

## Admin Area

Liora adds **Setup → Liora Insights**, where authorized editors can:

- review summary counts and repeated unanswered searches;
- filter conversations by editorial status;
- inspect chronological visitor, assistant, and error messages;
- review provider, model, timing, token, cache, retrieval, and source metadata;
- copy a secret-free Thread context for debugging;
- update editorial status;
- delete messages or complete Threads when separately permitted.

The dashboard requires `liora-review`. Destructive message and Thread actions
require `liora-delete` or superuser access.

## Requirements

- ProcessWire 3.0.210 or newer;
- PHP 8.1 or newer;
- an installed and configured Squad module;
- a ProcessWire endpoint page for tracked frontend conversations.

Atlas, Vox, and GeoIP are optional. Live web search is optional, disabled by
default, and requires a compatible Squad provider path.

## Installation

1. Copy the `Liora` directory into `/site/modules/`.
2. Refresh modules in ProcessWire Admin.
3. Install **Liora**. ProcessWire installs `InputfieldLiora` and
   `ProcessLiora` as companions.
4. Configure at least one active provider in Squad.
5. Open **Modules → Configure → Liora** and review the model, prompt, widget,
   retrieval, privacy, and retention settings.
6. Create or reuse the JSON endpoint page and keep its URL synchronized with
   Liora configuration.
7. Add the widget, Inputfield, or custom frontend only where the site Blueprint
   calls for it.

## Privacy And Safety

Liora stores conversation content, hashed session ownership, page attribution,
provider/model metadata, and optional coarse GeoIP location. It intentionally
does not store provider credentials, raw IP addresses, browser user agents, or
plaintext session identifiers.

Assistant output, Atlas excerpts, Vox community content, and web results are
untrusted input. Raw model HTML is never trusted. Same-site links are validated,
and the optional stay-on-site policy filters external destinations.

Conversation data is preserved on uninstall by default. Enable destructive
uninstall only when the stored history should be permanently removed.

## Optional Integrations

Liora is usable with Squad alone. Atlas, Vox, GeoIP, and live web search are
capability-detected additions, not hidden requirements.

Liora does not own a site's content model, public routes, editorial workflow,
moderation, commerce, or publishing decisions. The consuming site composes
those responsibilities.

## Documentation

- [API.md](API.md) — public methods, options, results, errors, and internal APIs.
- [EXAMPLES.md](EXAMPLES.md) — known-good integration patterns.
- [docs/INTEGRATION.md](docs/INTEGRATION.md) — detailed widget, Inputfield,
  endpoint, and custom-frontend guidance.
- [AGENTS.md](AGENTS.md) — guidance and safety boundaries for AI agents.
- [ROADMAP.md](ROADMAP.md) — future product direction, not released behavior.
- [CHANGELOG.md](CHANGELOG.md) — release notes.

## Author

Maxim Semenov<br>
[smnv.org](https://smnv.org)<br>
[maxim@smnv.org](mailto:maxim@smnv.org)

## License

MIT. See [LICENSE](LICENSE).
