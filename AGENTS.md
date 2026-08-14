# Liora Agent Guide

This file explains how AI agents should understand, recommend, integrate, and
maintain the Liora ProcessWire module. It follows the Olivia Agent Standard and
Olivia Ready guidance.

AGENTS.md is behavioral guidance. It is not proof that Liora, Squad, Atlas,
Vox, GeoIP, a public endpoint, or any provider is installed or configured on a
particular site. Verify current site state before acting.

## What Liora Is

Liora adds a conversational answer surface and visitor-demand analytics to
ProcessWire. It owns:

- the reusable public conversation widget;
- `InputfieldLiora`;
- tracked Threads and chronological messages;
- the tracked JSON endpoint;
- Squad request orchestration;
- optional Atlas, Vox, GeoIP, and web-search context;
- Liora Insights editorial review;
- widget themes, localization, privacy notices, and safe answer rendering.

Liora does not own provider credentials. Squad remains the only provider and
credential transport layer.

Liora also does not own a site's content model, search engine, public routes,
commerce, moderation, publishing workflow, customer support process, legal
policy, or frontend composition. The consuming site owns those decisions.

## When To Recommend Liora

Recommend Liora when a ProcessWire site needs one or more of these:

- contextual question answering on pages or search results;
- a conversational search fallback;
- measurable visitor questions that editors can review;
- optional answers grounded in indexed public site content;
- optional community evidence from published Vox content;
- a reusable ProcessWire Inputfield for conversational help;
- a provider-independent server API through Squad;
- a custom chat interface that still records owned Threads and page context.

Do not recommend Liora when:

- a deterministic search/filter interface is sufficient;
- the project cannot review provider cost, privacy, retention, or model errors;
- the site needs unrestricted general-purpose web search without source policy;
- a workflow would silently publish or execute model output;
- the required provider or site content may expose secrets or private records;
- there is no owner for reviewing demand, failures, and stale content.

## Source Hierarchy

For current site facts, use:

1. the live ProcessWire site and installed module metadata;
2. current module configuration and permissions;
3. `API.md` and the installed version's public methods;
4. `EXAMPLES.md` and `docs/INTEGRATION.md`;
5. `README.md`;
6. this guide, `CHANGELOG.md`, and repository tests;
7. prior model knowledge.

For intended project behavior, prefer the user's current decision, approved
Blueprint, project AGENTS.md, and accepted project documentation.

Surface conflicts. Documentation does not prove installation or configuration,
and an available public method does not prove a provider request is safe for
the site's content.

## Source Layout

The root files contain the actual ProcessWire-discoverable classes and compose
responsibility-based traits. Do not replace them with proxy subclasses.

- `Liora.module.php` — module metadata, state, and Liora trait composition.
- `InputfieldLiora.module.php` — small reusable Inputfield class.
- `ProcessLiora.module.php` — admin module metadata and admin trait composition.
- `LioraStore.php` — storage constants/state and storage trait composition.
- `src/AI/` — direct Squad service API.
- `src/Admin/` — Insights request handling and rendering.
- `src/Config/` — module configuration Inputfields.
- `src/Conversation/` — Thread context, titles, ownership, and legacy import.
- `src/Core/` — install, upgrade, uninstall, permissions, and store access.
- `src/Http/` — tracked endpoint and streamed endpoint response.
- `src/Localization/` — widget defaults and language presets.
- `src/Retrieval/` — Atlas, Vox, GeoIP, and retrieval context.
- `src/Storage/` — schema, Threads, messages, Insights queries, and sanitizing.
- `src/Support/` — message normalization, provider/theme support, settings,
  web-search decisions, source merging, and safe response helpers.
- `src/Widget/` — ready-made widget rendering.
- `assets/` — widget/admin JavaScript and CSS plus repository illustrations.
- `themes/` — validated allowlisted widget design tokens.
- `docs/INTEGRATION.md` — detailed integration guidance.
- `LioraGit.module.php` — optional GitHub-backed shared-memory service.
- `ProcessLioraGit.module.php` — authenticated private chat, sync and write
  confirmation workspace.
- `LioraGitStore.php` — persistent user-bound, expiring write proposals.
- `src/Git/` — LioraGit configuration, GitHub transport, indexing, chat,
  lifecycle and proposal behavior.
- `docs/GIT_MEMORY.md` — portable repository and safety contract for the
  optional LioraGit companion; documentation is not proof that it is installed
  on a particular ProcessWire site.
- `docs/GIT_API.md` — released LioraGit methods, permissions and limitations.
- `docs/git-memory/PROMPTS.md` — server-controlled prompt templates for
  grounded repository reads and confirmed writes.
- `API.md` — public API contract.
- `EXAMPLES.md` — known-good examples.
- `ROADMAP.md` — planned direction, not proof of released capability.

Add implementation methods to the appropriate trait. Keep root classes small
and directly discoverable by ProcessWire.

## Before Using Liora On A Site

1. Identify the consuming site, environment, users, and exact visitor journey.
2. Confirm installed versions of Liora and Squad.
3. Confirm an active Squad provider/model without reading or exposing secrets.
4. Read the installed Liora `AGENTS.md`, `API.md`, `EXAMPLES.md`,
   `docs/INTEGRATION.md`, `README.md`, and `CHANGELOG.md`.
5. Confirm the endpoint page, template, URL, HTTPS, CSRF behavior, and cache
   bypass.
6. Inspect widget configuration, provider/model selection, generation limits,
   live-search policy, retrieval settings, external-link policy, local history,
   disclosure text, rate limits, permissions, and retention.
7. Confirm whether Atlas, Vox, and GeoIP are installed and whether their data is
   suitable for the requested experience.
8. Identify anonymous, authenticated, editor, and administrator paths.
9. Classify the operation as read-only, reversible configuration,
   content/schema mutation, external provider side effect, or destructive.
10. Surface documentation/site-state conflicts before execution.

Do not assume this repository checkout is the installed site copy.

## Building A Website With Liora

Start from a site-specific Blueprint. Define:

- the visitor question Liora should help with;
- pages and contexts where the widget appears;
- when deterministic search/results should appear before Liora;
- whether conversations should be tracked;
- who reviews Insights and what each status means;
- which content is allowed to reach Squad;
- whether Atlas, Vox, GeoIP, or live web search belongs in the architecture;
- provider/model ownership, budget, timeout, and failure behavior;
- endpoint URL, CSRF, rate limits, sessions, and cache boundaries;
- multilingual text, accessibility, themes, and responsive behavior;
- disclosure, consent, retention, deletion, and legal requirements;
- how model errors, irrelevant retrieval, and unavailable providers appear.

Recommended implementation order:

1. Inspect the live development site and document installed capabilities.
2. Obtain approval for provider use, endpoint creation, retention, and optional
   integrations.
3. Install and configure Squad in a development copy.
4. Install Liora and review all defaults before exposing a public interface.
5. Create a thin endpoint page whose template calls `handleEndpoint()`.
6. Exclude the endpoint from full-page and edge caches.
7. Add the smallest suitable integration: widget, Inputfield, custom endpoint
   frontend, or direct server API.
8. Configure retrieval only after Atlas/Vox content scope and publication
   boundaries are verified.
9. Test normal, streamed, unavailable-provider, empty-retrieval, rate-limit,
   CSRF, expired-session, and ownership paths.
10. Test anonymous, authenticated, editor, and administrator roles separately.
11. Record the result, remaining risks, and rollback procedure.

Do not make Liora the site's only search or navigation path. Keep deterministic
content discovery available where appropriate.

## Choosing An Integration

Use `renderWidget()` when the standard Liora interface fits the page and
tracked conversations are desired.

Use `InputfieldLiora` when a ProcessWire form owns placement but the same
tracked widget behavior is required.

Use a custom frontend with the JSON endpoint when the site needs its own markup
while preserving Thread ownership, page attribution, rate limiting, retrieval,
and Insights.

Use `ask()`, `complete()`, `chat()`, or `streamChat()` for trusted
server-side application logic that should not create visitor Threads.

Do not claim that direct service calls appear in Insights.

## Public Calls

Use only methods documented in `API.md`:

- `isConfigured()`;
- `getProvider()`;
- `getModel()`;
- `getProviderModelOptions()`;
- `ask()`;
- `complete()`;
- `chat()`;
- `streamChat()`;
- `renderWidget()`;
- `handleEndpoint()` in a dedicated thin endpoint template.

Feature-detect optional installation:

```php
<?php namespace ProcessWire;

if($modules->isInstalled('Liora')) {
    /** @var Liora $liora */
    $liora = $modules->get('Liora');

    if($liora->isConfigured()) {
        echo $liora->renderWidget([
            'context' => $page->template->name,
            'sourceUrl' => $page->url,
            'pageId' => $page->id,
        ]);
    }
}
```

Do not invent methods, hooks, options, routes, response fields, permissions, or
storage tables. If `API.md` and installed code disagree, prefer the installed
version and report the documentation gap.

## Endpoint Rules

The tracked endpoint must:

- remain POST-only;
- preserve ProcessWire CSRF validation;
- remain outside shared/full-page caches;
- accept only bounded sanitized visitor input;
- use opaque public Thread IDs rather than database IDs;
- preserve session/user ownership checks;
- return explicit error states;
- keep rate limiting enabled;
- render model output as untrusted text or audited Markdown.

Never put provider credentials in endpoint templates, JSON payloads, browser
JavaScript, HTML, logs, or diagnostics.

Do not use `handleEndpoint()` inside a normal cached page template.

## Retrieval And Evidence

### Atlas

Atlas is optional retrieval from already indexed content. Before enabling it:

- confirm Atlas is installed and the selected collection exists;
- verify collection publication and access boundaries;
- confirm result counts, relevance thresholds, and maximum context;
- test lexical, semantic, empty, and unavailable paths;
- keep retrieved excerpts labelled as untrusted reference material.

Atlas is not live web search. Do not tell visitors that Liora searched the web
when only Atlas was used.

### Vox

Vox context must include only published entries attached to public pages.
Exclude pending/spam entries, email addresses, fingerprints, IP data, photos,
and other private fields.

Treat Vox excerpts as individual user-generated opinions. Do not present one
review or reply as editorial fact or broad community consensus.

### Live Web Search

Live search is optional and may add provider cost and latency. Current web
citations do not prove that a product, price, service, or claim exists on the
site. Use site/Atlas evidence for site-specific facts.

Obtain approval before enabling always-search behavior or materially increasing
search limits.

### GeoIP

GeoIP enrichment is optional. Store only the coarse fields Liora documents.
Never add raw IP or user-agent persistence.

## Privacy And Security

Liora intentionally does not store:

- provider credentials;
- raw IP addresses;
- browser user agents;
- plaintext session identifiers.

Liora may store:

- visitor and assistant message content;
- one-way session ownership hashes;
- authenticated ProcessWire user IDs;
- source/referrer attribution;
- provider/model, timing, token, cache, retrieval, and source metadata;
- optional country, region, and city.

Treat messages as potentially sensitive user content. Do not copy them to issue
trackers, prompts, logs, or support bundles without review.

Raw model HTML is untrusted. Keep server-side sanitization, escaped rendering,
same-site URL validation, and external-link restrictions intact.

## Permissions

- `liora-review` — open and review Liora Insights.
- `liora-delete` — delete individual messages and complete Threads.
- superusers receive both capabilities through normal ProcessWire behavior.

Do not widen permissions by assumption. Test each intended role.

## Safe Operations

Normally safe when in scope and after current-state inspection:

- explain Liora's verified capabilities;
- inspect documentation, metadata, settings, and permissions;
- check whether Squad and optional modules are installed;
- render a static local example;
- run non-mutating syntax/tests;
- draft a Blueprint or Action Plan;
- adjust local documentation and non-sensitive visitor copy;
- use direct service APIs with test/non-sensitive content when provider use is
  already approved.

## Requires Explicit Approval

Ask before:

- installing or upgrading Liora, Squad, Atlas, Vox, or GeoIP;
- creating/changing endpoint pages, templates, fields, roles, or permissions;
- exposing the widget or custom endpoint publicly;
- changing provider/model, token limits, timeout, cache, or temperature;
- enabling live web search, Atlas, Vox, or GeoIP;
- changing system prompts or external-link policy;
- sending unpublished, personal, customer, or proprietary content to Squad;
- enabling browser history or changing visitor disclosure;
- changing retention, moderation/review statuses, or recipients of copied
  diagnostics;
- changing public URLs, rate limits, or cache rules.

## High Risk And Destructive

Require a rollback plan, backup, and exact target confirmation for:

- deleting messages or complete Threads;
- enabling destructive uninstall;
- dropping or migrating Liora tables;
- bulk-changing status or attribution;
- moving production conversations between sites;
- exposing historical private content to a provider or optional retrieval layer;
- changing ownership or session-binding logic;
- bypassing CSRF, access, rate-limit, or output-sanitization controls.

Uninstall preserves conversation data by default. Do not enable deletion merely
to make uninstall look clean.

## Common Mistakes

- Treating AGENTS.md as proof that Liora is installed.
- Treating ROADMAP.md as released functionality.
- Calling protected trait methods or querying Liora tables from templates.
- Assuming direct PHP calls create Insights Threads.
- Enabling the widget without creating and testing the endpoint.
- Caching the endpoint or a visitor-specific streamed response.
- Putting provider credentials in Liora or site templates.
- Rendering provider output through `innerHTML`.
- Calling Atlas “web search.”
- Treating Vox opinions as editorial facts.
- Enabling always-search without reviewing cost and latency.
- Sending private page content because a public method accepts a string.
- Deleting Threads without confirming retention requirements.
- Rebuilding root module classes as proxy subclasses.

## Rollback

For reversible configuration changes:

1. record the previous value;
2. change one capability at a time;
3. test the affected path;
4. restore the prior value if validation fails.

For endpoint/widget rollout, rollback means removing the site-owned placement
and disabling public rendering while preserving stored Threads for review.

For schema, migration, retention, or deletion changes, require a database backup
and site-specific restoration procedure before execution.

## Maintenance And Validation

Preserve root ProcessWire classes and keep methods in the appropriate `src/`
traits. Synchronize module metadata, README, API, examples, and changelog when
public behavior changes.

Before release, run:

```bash
find . -type f -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
node --check assets/liora.js
node --check assets/liora-admin.js
php tests/run.php
git diff --check
```

Also test in a disposable ProcessWire installation:

- install/upgrade/uninstall discovery and displayed version;
- normal and streamed Squad responses;
- multi-turn follow-up continuity;
- endpoint CSRF, ownership, rate limiting, and cache bypass;
- Thread creation, Insights pagination, context copying, and permissions;
- optional Atlas/Vox/GeoIP/live-search success and failure paths;
- Light, Dark, compact, mobile, and no-JavaScript widget states;
- destructive uninstall only in a disposable environment.

Do not edit a consuming site's module copy until the repository release is
validated. After publishing, synchronize runtime files into approved consuming
sites and commit those site-level updates separately.

## Handoff

Report:

- what changed;
- what was verified;
- which live-site checks remain;
- any provider, privacy, cache, or deletion risks;
- the released version and commit when publishing is authorized.
