# Changelog

All notable changes to Liora will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.15.0] - 2026-08-13

### Added

- Documented the portable repository layout, Markdown metadata contract,
  permissions, confirmation flow and rollout guidance for the planned optional
  LioraGit companion.
- Added server-controlled prompt templates and reusable note, decision, task
  and source documents for Git-backed shared memory.
- Added the optional `LioraGit` and `ProcessLioraGit` companion modules with a
  private authenticated chat, incremental Markdown indexing through Atlas,
  exact GitHub source provenance, separate read/write/sync permissions, and
  separately configured GitHub credentials.
- Added persistent one-hour write proposals with a visible diff, participant
  ownership, content integrity hash, exact base-commit validation, explicit
  confirmation, conflict refusal and returned commit SHA.

### Security

- Repository content remains untrusted evidence and cannot alter the trusted
  LioraGit prompt, credentials, permissions or write policy.
- Initial writes are restricted to new Markdown files inside one configured
  directory; deletion, rename, branch, merge, workflow and force-push are not
  exposed.

## [1.14.1] - 2026-08-09

### Changed

- Replaced the custom conversation status filter with the native UIkit
  `uk-subnav uk-subnav-pill` pattern used by Ichiban and the ProcessWire design
  system reference.
- Aligned Font Awesome button icons with their text baseline.

## [1.14.0] - 2026-08-08

### Changed

- Reworked the Insights dashboard around the conversation review queue, with a
  clearer introduction, prioritized operational metrics, stable status pills,
  compact conversation actions, and a responsive empty state.
- Moved repeated demand below the review queue and collapsed lower-priority
  rows while preserving access to the full bounded report.
- Improved tablet and mobile hierarchy, table readability, focus states, and
  overflow behavior throughout the dashboard.

## [1.13.0] - 2026-08-08

### Added

- Added the bounded `retrievalQuery` widget and endpoint option so consuming
  sites can pass a canonical spelling hint without changing the visitor's
  visible question or recorded original query.

### Changed

- Atlas uses the canonical hint for a new conversation and combines it with
  follow-up context in an existing conversation.
- Search-context prompting now treats corrected spellings as hints and avoids
  claiming matching catalogue records are absent when supplied site evidence
  confirms them.

## [1.0.0] - 2026-08-02

First public release of Liora. The module has not been published before.

### Added

- Reusable conversational widget and `InputfieldLiora` for ProcessWire pages
  and forms.
- Normal and streamed Squad responses with configurable provider, model,
  generation, cache, timeout, and adaptive live-search options.
- Tracked conversation Threads with chronological messages, follow-up context,
  browser-restored local history, starter questions, and localized interface
  presets.
- Optional Atlas retrieval, Vox community context, GeoIP enrichment, and
  normalized public web-search citations.
- Safe Markdown rendering, same-site source links, configurable external-link
  restrictions, responsive themes, and visitor-facing privacy notices.
- Setup → Liora Insights dashboard with demand summaries, filters, pagination,
  message metadata, diagnostics, copyable context, review statuses, and
  permission-controlled deletion.
- Public PHP API, tracked JSON endpoint, custom-frontend integration path, and
  integration documentation.
- Privacy-conscious storage that excludes provider credentials, raw IP
  addresses, user agents, and plaintext session identifiers.
- Migration support for historical Liora/LQRS query data while preserving
  tracked conversations on uninstall by default.
- Responsibility-based source layout under `src/AI`, `src/Admin`,
  `src/Config`, `src/Conversation`, `src/Core`, `src/Http`,
  `src/Localization`, `src/Retrieval`, `src/Storage`, `src/Support`,
  and `src/Widget`.
- README illustration, sponsorship metadata, MIT license, public API reference,
  examples, and Olivia-compatible agent guidance.

### Security

- Tracked endpoint mutations require POST and ProcessWire CSRF validation.
- Thread ownership is bound to a one-way session hash or authenticated user.
- Provider responses and retrieved content are treated as untrusted input.
- Stored technical metadata is allowlisted and excludes prompts, credentials,
  request bodies, visitor identifiers, and private Vox fields.
- Destructive uninstall remains opt-in.
