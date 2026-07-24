# Changelog

## Unreleased

- Added a community-oriented roadmap for answer feedback, demand clustering,
  editorial tasks, structured results, quality controls, privacy lifecycle,
  adaptive routing and reporting.
- Defined portability rules that keep LQRS-specific data models and integrations
  outside the reusable Liora core.

## 1.9.5 - 2026-07-24

- Rendered the module-settings preview as a static, non-form widget so its
  empty question field can no longer block configuration saves with browser
  validation.
- Kept normal frontend widgets interactive and unchanged.

## 1.9.4 - 2026-07-24

- Added an optional live web-search setting with a configurable 1–10 result
  limit, delegated entirely to Squad's provider adapters.
- Kept Atlas and live web search conceptually separate: Atlas confirms LQRS
  catalogue facts while public web evidence supplements current information.
- Merged normalized provider citations into the widget source list while the
  existing stay-on-site policy continues to prevent outbound navigation.

## 1.9.3 - 2026-07-24

- Removed the vertical movement effect from starter-question hover states.

## 1.9.2 - 2026-07-24

- Added an explicit conversation-continuity instruction for follow-up turns.
- Made short replies inherit earlier constraints instead of restarting the topic, greeting again or repeating the same questions.
- Expanded Atlas follow-up retrieval from one preceding visitor message to the three most recent distinct visitor constraints.

## 1.9.1 - 2026-07-24

- Added a one-click admin action that copies a Thread’s metadata and complete chronological conversation for debugging or sharing with Codex.
- Made Atlas excerpts supplement reliable general model knowledge instead of forcing an “LQRS has no information” answer when retrieved material is irrelevant.
- Updated the default stay-on-site prompt to prohibit external destinations without unnecessarily restricting the model to site-only knowledge.
- Automatically replaces the previous unmodified stay-on-site default at runtime while preserving custom administrator wording.

## 1.9.0 - 2026-07-24

- Added up to three configurable, localizable starter-question buttons below the empty conversation.
- Made a starter question immediately begin the same tracked Thread flow as a typed question, including page attribution and optional Atlas/Vox context.
- Added a global visibility toggle plus per-widget `showSuggestedPrompts` and `suggestedPrompts` overrides.

## 1.8.0 - 2026-07-24

- Added optional Vox context from published reviews, questions, replies and discussions on the current or Atlas-retrieved public page.
- Added Vox entry-count and context-size controls, with community retrieval enabled by default when Vox is installed.
- Included stored review ratings and recommendation flags while excluding private Vox fields.
- Labelled Vox excerpts as untrusted user-generated opinions and prevented Liora from presenting individual comments as editorial facts or broad consensus.
- Added preceding-question context to retrieval so short follow-ups such as “what reviews does it have?” can resolve the referenced product.

## 1.7.0 - 2026-07-24

- Added low-latency lexical-first Atlas retrieval with automatic semantic fallback when local matches are insufficient.
- Added an Atlas fast-retrieval setting, enabled by default.
- Streamed provider deltas immediately even when the stay-on-site policy is enabled; external links remain inert while streaming and the final server-filtered answer replaces the draft.

## 1.6.1 - 2026-07-24

- Made short visitor messages render as compact content-sized bubbles.
- Added safe structured Markdown rendering for headings, paragraphs, lists and code.
- Styled numbered Atlas citations and matched them to the rendered source list.
- Rendered validated same-site Markdown links while leaving external links inert.
- Hardened widget sizing against surrounding-site box-model and overflow rules.

## 1.6.0 - 2026-07-24

- Added an adaptive widget theme that follows the visitor's light/dark operating-system preference.
- Added explicit Light and Dark theme choices for sites that force a color scheme.
- Made live system-theme changes apply through `prefers-color-scheme` without reloading the page.
- Replaced hard-coded light widget colors with validated JSON theme tokens.

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
