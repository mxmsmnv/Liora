# Liora roadmap

Liora is a reusable ProcessWire conversation and demand-intelligence module.
It helps a visitor when a page, catalogue or search result does not answer a
question, then turns that conversation into evidence that site owners can use
to improve their content, data and product experience.

This roadmap describes direction, not fixed release dates. Features move into
a release only after their storage, privacy, migration and extension contracts
are clear.

## Product principles

### Remain useful outside LQRS

- Core features must not depend on LQRS templates, fields, URLs or taxonomies.
- Public copy, site identity, prompts, reasons, statuses and action labels must
  be configurable and localizable.
- Squad remains the provider and credential transport layer. Liora must not
  duplicate provider secrets or HTTP adapters.
- Atlas, Vox and GeoIP are optional integrations. A site without them must keep
  a complete working conversation and Insights experience.
- Product cards, editorial tasks and host-site actions must use documented
  hooks or adapters rather than assumptions about a particular data model.
- LQRS may use first-party adapters in its site repository, but those adapters
  must not become requirements of the community module.

### Improve the host site instead of replacing it

- Liora is a fallback for missing answers, not a substitute for structured
  content, search, navigation or editorial review.
- Repeated visitor demand should become an actionable improvement queue.
- Generated text must never be published automatically.
- Editorial, community, retrieved and external-web evidence must remain
  distinguishable.

### Privacy and trust by default

- Never store provider keys, raw IP addresses, user agents or plaintext session
  identifiers.
- Store only the conversation and metadata needed for the enabled features.
- Make retention, deletion, export and review disclosure explicit.
- Treat model output, retrieved content and visitor input as untrusted.
- Require POST, permission checks and ProcessWire CSRF validation for mutations.

### Operable by a small team

- Prefer deterministic local analysis before additional model calls.
- Make expensive clustering, web search and reports schedulable and bounded.
- Provide useful defaults while allowing advanced integrations through hooks.
- Every feature needs migrations, tests, empty states and failure fallbacks.

## Current foundation

Liora already provides:

- tracked multi-turn Threads with chronological messages;
- streamed Squad responses and configurable provider/model selection;
- optional Atlas, Vox, GeoIP and live-web-search context;
- LocalStorage conversation restoration controlled by the visitor;
- localized welcome text, starter questions and interface labels;
- adaptive light/dark JSON themes;
- safe Markdown, same-site links and source attribution;
- response time, token usage and copy controls;
- paginated Insights review, statuses, editing and deletion;
- privacy-conscious session ownership and configurable disclosures.

The next releases build a measurable improvement loop on this foundation.

## Phase 0 — Community hardening

Goal: make a clean installation feel product-neutral and make integrations
predictable for third-party ProcessWire sites.

- [ ] Replace product-specific default copy with neutral, configurable defaults
      while preserving existing customized values during upgrade.
- [ ] Add configurable assistant name, site name and editorial terminology.
- [ ] Document every public PHP method, endpoint payload and response shape.
- [ ] Document hooks for context providers, source normalization, result cards,
      actions, feedback reasons and editorial exports.
- [ ] Add a generic WireWall/custom-firewall integration example for the JSON
      endpoint without weakening unrelated routes.
- [ ] Add clean-install, upgrade and uninstall test fixtures.
- [ ] Verify operation with Squad alone and with each optional integration
      independently unavailable.
- [ ] Add a community release checklist covering packaging, screenshots,
      compatibility, migrations and security review.

## Phase 1 — Answer feedback and outcomes

Goal: measure whether an answer helped instead of counting conversations alone.

- [ ] Optional “Helpful / Not helpful” controls on assistant answers.
- [ ] Configurable and localizable negative-feedback reasons.
- [ ] Optional free-text feedback with a clear privacy notice.
- [ ] One current feedback outcome per answer, with an audit timestamp.
- [ ] Aggregate helpfulness by page, context, model and intent.
- [ ] Insights filters for unanswered, unhelpful and corrected conversations.
- [ ] A host-site hook emitted after feedback is stored.
- [ ] Rate limiting and ownership checks that prevent arbitrary feedback writes.

Feedback must remain optional. Disabling it must remove the public controls
without deleting historical outcomes.

## Phase 2 — Intent classification and demand clustering

Goal: reveal repeated unmet needs without reading every Thread manually.

- [ ] A neutral default intent taxonomy: discovery, comparison, availability,
      price, factual detail, how-to, recommendation, review, correction and
      other.
- [ ] Configurable taxonomies and labels for different sites.
- [ ] Deterministic keyword/rule classification as the zero-cost baseline.
- [ ] Optional Squad-assisted classification for ambiguous conversations.
- [ ] Scheduled clustering of semantically similar questions.
- [ ] Manual merge, split, rename and dismiss controls for clusters.
- [ ] Cluster metrics: conversation count, unique sessions, affected pages,
      languages, countries, first seen and last seen.
- [ ] Exclude deleted, failed or opted-out data from derived analysis.
- [ ] Store classifier version so clusters can be rebuilt safely.

Clustering must run in bounded CLI/cron batches and never delay a visitor
response.

## Phase 3 — Editorial improvement queue

Goal: convert verified demand clusters into work that improves the host site.

- [ ] Create a task from a Thread or cluster.
- [ ] Generic task types: improve page, add structured data, create guide,
      create FAQ, fix incorrect information, improve search and investigate.
- [ ] Workflow states: proposed, accepted, in progress, published, measured and
      dismissed.
- [ ] Optional owner, due date, notes and priority.
- [ ] Nullable ProcessWire page ID plus a portable canonical URL/reference.
- [ ] Link each task back to its supporting Threads without duplicating them.
- [ ] Record what changed and when it was published.
- [ ] Measure whether the same demand decreases after publication.
- [ ] CSV/JSON export and hooks for GitHub, Jira or another task system.
- [ ] No automatic page creation or publishing in the core module.

LQRS-specific mappings such as product fields, collections or editorial
templates belong in a separate adapter.

## Phase 4 — Structured results and host actions

Goal: let Liora provide useful next steps without asking the model to invent UI.

- [ ] A documented, provider-neutral result-card schema.
- [ ] Generic cards for pages, products/content items, comparisons and recipes.
- [ ] A registry where the host site resolves IDs into current public data.
- [ ] An action registry for safe operations such as open, save, compare or add
      to a collection.
- [ ] Server-side validation and permission checks for every action.
- [ ] Accessible HTML rendering with text fallback for custom frontends.
- [ ] Hooks that let sites add card and action types without patching Liora.
- [ ] Never let arbitrary model output define executable URLs or mutations.

The language model may explain results, but the database and host adapters must
provide identifiers, facts, URLs and availability.

## Phase 5 — Evidence and answer quality

Goal: make it clear what supports an answer and where review is required.

- [ ] Source badges for host content, retrieval adapters, community content and
      external web results.
- [ ] Coverage indicators based on available evidence, not model self-reported
      confidence.
- [ ] Flag unsupported product claims, prices, availability and dates.
- [ ] Detect contradictions between structured host data and retrieved text.
- [ ] Allow editors to mark an answer correct, incorrect or superseded.
- [ ] Maintain an evaluation set from reviewed Threads without exposing visitor
      identifiers.
- [ ] Compare answer quality, latency and cost across configured models.
- [ ] Provide graceful “not enough evidence” responses and escalation hooks.

## Phase 6 — Privacy lifecycle

Goal: give site owners and visitors predictable control over conversation data.

- [ ] Configurable retention periods for Threads, messages, feedback and derived
      clusters.
- [ ] Scheduled deletion or anonymization with a dry-run report.
- [ ] Optional detection and masking of email addresses, phone numbers and
      similar personal details before persistence.
- [ ] Visitor-facing deletion of browser history and server-owned Threads when
      ownership can be proven safely.
- [ ] Admin export and deletion tools with permissions and CSRF protection.
- [ ] Consent-aware hooks for sites with an existing privacy platform.
- [ ] Document data categories and processor responsibilities for installers.

PII detection must be presented as risk reduction, not a guarantee.

## Phase 7 — Adaptive retrieval, routing and cost controls

Goal: use slower or paid capabilities only when they materially improve an
answer.

- [x] Detect freshness-sensitive requests such as current price, availability,
      news, releases, awards and events.
- [x] Use live web search only when enabled and relevant.
- [ ] Configurable routing by intent, context, language or complexity.
- [ ] Provider/model fallback chains delegated through Squad.
- [ ] Per-request and daily token/cost budgets with safe fallback copy.
- [ ] Cache policies separated by static, personalized and freshness-sensitive
      answers.
- [ ] Latency budgets and timeout telemetry for retrieval and generation stages.
- [ ] Admin reporting for tokens, estimated cost, cache use and response time.

## Phase 8 — Reports and improvement measurement

Goal: provide a short operational view of what visitors need and what changed.

- [ ] Weekly and monthly dashboard summaries.
- [ ] Top unresolved clusters and pages with the most unmet demand.
- [ ] Helpful-answer rate, failed-answer rate and median response time.
- [ ] Model, retrieval and web-search usage.
- [ ] Tasks created, published and awaiting measurement.
- [ ] Before/after demand for completed editorial work.
- [ ] Configurable email delivery through a host-provided mailer.
- [ ] JSON export and hooks for external analytics.

Reports must show the measurement period and underlying counts. They must not
present model-generated recommendations as verified facts.

## Later, after evidence of demand

- [ ] Consent-based preference profiles for returning visitors.
- [ ] Site-defined experiments for welcome text and starter questions.
- [ ] Human handoff adapters for support or sales teams.
- [ ] Additional frontend renderers maintained separately from the core widget.
- [ ] Import/export formats for anonymized evaluation datasets.

These features should not precede feedback, clustering and the editorial queue.

## Extension contracts

Community-facing integrations should converge on small contracts:

1. **Context provider** — accepts the current page and conversation, then
   returns bounded excerpts and normalized sources.
2. **Result resolver** — accepts safe identifiers and returns current cards
   owned by the host application.
3. **Action handler** — declares a visible action and validates its execution
   server-side.
4. **Classifier** — assigns versioned intents or cluster candidates.
5. **Editorial exporter** — sends an approved task to an external system.
6. **Report delivery adapter** — delivers an already-rendered report.

Adapters must fail independently. A failed optional adapter must not prevent a
normal Squad answer or corrupt a Thread.

## Release criteria

Every roadmap feature must include:

- a schema and reversible migration plan;
- permissions and CSRF protection for mutations;
- localization for new public and admin copy;
- accessible light and dark presentation;
- deterministic behavior without optional AI calls where practical;
- unit/smoke coverage plus a real ProcessWire integration check;
- documented hooks, configuration and failure behavior;
- privacy and retention impact notes;
- changelog and SemVer decision;
- upgrade verification with existing Threads preserved.

## Recommended delivery order

1. Community hardening.
2. Answer feedback and outcomes.
3. Intent classification and demand clustering.
4. Editorial improvement queue.
5. Structured results and host actions.
6. Evidence and answer quality.
7. Privacy lifecycle.
8. Adaptive routing and cost controls.
9. Reports and improvement measurement.

The first complete product loop is:

> visitor question → answer → feedback → demand cluster → editorial task →
> published improvement → measured reduction in unmet demand
