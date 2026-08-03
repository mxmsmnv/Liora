# Changelog

All notable changes to Liora will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
