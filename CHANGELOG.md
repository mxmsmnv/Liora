# Changelog

## 1.5.1 - 2026-07-24

- Clarified that the widget setting controls the ready-made chat UI rather than the whole Liora service.
- Added integration examples directly to the module settings.
- Documented ready-made widget, Inputfield, server API and custom frontend integrations.
- Explained which integration paths create tracked Insights Threads.

## 1.5.0 - 2026-07-23

- Paginated Liora Insights conversations and limited message loading to the current page.
- Collapsed every conversation by default so the dashboard remains easy to scan.
- Remembered open conversations and the scroll position separately for each filter and page.
- Kept the selected conversation anchored in the viewport while opening or hiding it.

## 1.4.1 - 2026-07-23

- Added a configurable stay-on-site policy and editable prompt instruction.
- Added deterministic server-side filtering for external absolute URLs and Markdown links.
- Preserved same-site links and verified Atlas source links.
- Buffered provider deltas while the policy is enabled so unchecked external URLs are never exposed mid-stream.

## 1.4.0 - 2026-07-23

- Added configurable copy actions to visitor chat messages.
- Added configurable response-time and provider token-usage metadata to Liora answers.
- Persisted answer metadata in LocalStorage so it remains visible in restored conversations.
- Replaced the submit-button ellipsis with a localized animated “Liora is thinking” message inside the conversation.
- Kept real provider streaming enabled and replaced the thinking state as soon as the first delta arrives.

## 1.3.3 - 2026-07-23

- Added permission-controlled deletion of an entire conversation from Liora Insights.
- Deleting a Thread permanently removes all of its messages through the existing database cascade.
- Added a destructive-action confirmation and responsive thread-footer control.

## 1.3.2 - 2026-07-23

- Redesigned Liora Insights conversations as a clearer chronological message timeline.
- Moved the active model and settings action into a persistent footer toolbar.
- Added a prominent configuration warning and setup button when Squad has no usable provider/model.
- Improved thread context, location, source, status and message metadata hierarchy on desktop and mobile.

## 1.3.1 - 2026-07-23

- Added per-message deletion to Liora Insights with confirmation and CSRF validation.
- Added a separate `liora-delete` permission while retaining automatic access for superusers.
- Recalculate conversation message counts and timestamps after a message is removed.

## 1.3.0 - 2026-07-23

- Added ProcessWire multi-language fields for every visitor-facing widget text.
- Added one-click English, German, French, Spanish, Italian, Dutch, Polish and Russian text presets.
- Localized JavaScript labels, conversation controls, source headings and fallback errors from the active page language.
- Made the default AI prompt answer in the visitor's language.

## 1.2.2 - 2026-07-23

- Added inline conversation-title editing with Save and Cancel controls.
- Saved renamed titles in LocalStorage and synchronized them to an owned server Thread without calling the AI provider or consuming the question rate limit.

## 1.2.1 - 2026-07-23

- Named conversations from the first visitor question instead of the shared source-page title.
- Added concise word-boundary truncation without an extra AI provider request.
- Migrated legacy page-based server titles on upgrade and old LocalStorage titles when history is opened.

## 1.2.0 - 2026-07-23

- Added a configurable welcome message for empty conversations, with a separate enable/disable setting.
- Added optional Atlas retrieval with collection, result-count, relevance and context-size settings.
- Re-resolved Atlas page sources against current public access before including excerpts.
- Added deterministic same-site source links to regular and streamed answers, including restored LocalStorage conversations.
- Kept Atlas failures and empty results on a safe fallback path through the normal Squad answer.

## 1.1.1 - 2026-07-23

- Kept the beginning of a newly generated long answer in view instead of forcing the conversation to its final line on every streamed delta.
- Made the conversation height responsive and added an expand/compact control for reading the full thread without an inner scrollbar.
- Made restored conversations reliably reveal the beginning of their latest message after layout.
- Moved widget design tokens into selectable JSON theme files under `themes/`.

## 1.1.0 - 2026-07-23

- Replaced flat request records with conversation threads and chronological messages.
- Migrated existing `liora_queries` rows into the new thread/message model.
- Added correct same-site source page resolution and separate browser referrer tracking.
- Added optional country and city enrichment through an installed GeoIP module.
- Added visitor-controlled LocalStorage history with previous/new conversation controls.
- Added configurable quality-review disclosure text.
- Added true streamed answer rendering through Squad provider deltas.
- Grouped each conversation in Liora Insights and highlighted Liora answers as quoted code.

## 1.0.0 - 2026-07-23

- Renamed the LQRS AI facade from LqrsAi to Liora.
- Added configurable active provider/model selection through Squad.
- Added prompt, response, cache, timeout, rate-limit and CTA settings.
- Added the reusable InputfieldLiora and safe frontend widget.
- Added privacy-conscious question and unmet-search tracking.
- Added ProcessLiora demand analytics and editorial review statuses.
- Added migration of historical rows from the legacy `ai` field.
