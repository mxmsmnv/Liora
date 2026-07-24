# Changelog

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
