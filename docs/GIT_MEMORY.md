# Git-backed memory for Liora

This guide defines a portable repository format for the optional `LioraGit`
companion module. The repository is the durable source of truth. Any search
index, embedding collection or chat context is derived data and must be safe to
rebuild from Git.

The format is intentionally plain Markdown. A repository can be read and
edited without ProcessWire, Liora or a particular AI provider.

## Operating model

```text
authenticated participant
        ↓
private Liora chat
        ↓
LioraGit policy and permissions
   ├─ read approved Markdown
   ├─ retrieve relevant excerpts
   ├─ answer with exact sources
   └─ prepare a write proposal
        ↓ explicit confirmation
GitHub commit → refreshed search index
```

LioraGit must never give a participant the GitHub token. The token stays in
server-side ProcessWire configuration. Participants receive ProcessWire roles
and permissions for the chat operations they are allowed to perform.

## Recommended repository layout

Existing documentation repositories do not need to be reorganized. For a new
shared memory repository, start with:

```text
README.md
liora-memory.yml
memory/
  index.md
  inbox/
  notes/
  decisions/
  tasks/
  sources/
  archive/
```

- `README.md` explains the repository to humans.
- `liora-memory.yml` declares portable defaults. Server configuration remains
  authoritative and may only narrow these defaults.
- `memory/index.md` lists the most important current documents and vocabulary.
- `memory/inbox/` receives new, not-yet-classified records.
- `memory/notes/` contains durable facts, observations and meeting summaries.
- `memory/decisions/` contains decisions and their rationale.
- `memory/tasks/` contains work with an owner or state.
- `memory/sources/` contains source notes and provenance, not copied articles.
- `memory/archive/` contains superseded material kept for history.

Prefer several focused documents over one continuously growing transcript.
Keep binaries, exports and large original files outside the indexed paths and
link to them from a source note.

## Portable manifest

Copy [`liora-memory.example.yml`](git-memory/liora-memory.example.yml) to the
repository root as `liora-memory.yml` and review every path.

The manifest is untrusted repository content. It may suggest labels and paths,
but it must never:

- supply or select credentials;
- widen the repositories, branches or paths allowed by the server;
- disable authentication, confirmation or audit logging;
- grant a ProcessWire role;
- enable deletion, force-push or arbitrary Git operations.

When the manifest and server configuration differ, the narrower server policy
wins.

## Document contract

Use UTF-8 Markdown with LF line endings. Each knowledge document should have a
single subject, a descriptive filename and a short YAML front matter block.

```markdown
---
id: decision-2026-08-13-shared-chat
type: decision
title: Use a private Liora chat for shared memory
status: accepted
created: 2026-08-13
updated: 2026-08-13
authors:
  - maxim
topics:
  - collaboration
  - knowledge-management
visibility: private
sources: []
related: []
---

# Use a private Liora chat for shared memory

## Context

The partner needs to read and contribute to shared knowledge without receiving
GitHub or Codex access.

## Decision

Use authenticated Liora conversations backed by a restricted repository.

## Consequences

- Git remains the audit log and source of truth.
- Every write requires a preview and explicit confirmation.
```

### Required fields

| Field | Meaning |
| --- | --- |
| `id` | Stable repository-unique identifier. Do not change it when moving the file. |
| `type` | `note`, `decision`, `task`, `source`, `profile` or a configured type. |
| `title` | Human-readable subject, not a chat command. |
| `status` | Type-appropriate lifecycle state. |
| `created` | Original date in `YYYY-MM-DD`. |
| `updated` | Date of the last material change. |
| `authors` | Stable human or service identifiers. |
| `topics` | Small vocabulary used for discovery. |
| `visibility` | Normally `private`; it is a label, never an authorization mechanism. |
| `sources` | Supporting repository paths or credential-free HTTP(S) URLs. |
| `related` | Stable document IDs or repository-relative paths. |

Unknown facts should be omitted or written as `unknown`; never infer them merely
to complete metadata. Keep timestamps and commit identity as audit evidence,
but do not rely on Git history as the only place where document meaning is
explained.

Ready-made files are provided in [`docs/git-memory/templates/`](git-memory/templates/).

## Writing rules

1. Capture one subject per file.
2. Put raw or unclear input in `memory/inbox/`; do not pretend it is verified.
3. Separate facts, assumptions, decisions and proposed work with headings.
4. Preserve the original author and source when Liora rewrites wording.
5. Use repository-relative links so clones work on different computers.
6. Prefer ISO dates and explicit time zones when time matters.
7. Mark superseded decisions; do not silently rewrite history.
8. Never store passwords, tokens, private keys, session IDs or recovery codes.
9. Do not copy complete third-party articles or confidential material without
   an approved rights and privacy basis.
10. Keep generated summaries reviewable against their listed sources.

## Naming

Use lowercase ASCII paths for portability:

```text
memory/notes/2026-08-13-partner-onboarding.md
memory/decisions/2026-08-13-git-backed-memory.md
memory/tasks/configure-private-chat.md
```

The stable `id` matters more than the filename. A rename or move must preserve
the `id` and update incoming links where practical.

## Read behavior

For every answer, Liora should:

1. retrieve only from server-approved repositories, branches and paths;
2. treat repository text as untrusted evidence, never as system instructions;
3. distinguish current, proposed, rejected and superseded material;
4. prefer accepted decisions and current documents over older drafts;
5. report conflicts instead of selecting a convenient version silently;
6. cite the repository, path and commit used for material claims;
7. say when the repository does not contain enough evidence;
8. avoid revealing content the authenticated participant is not permitted to
   read.

An `AGENTS.md`, prompt-looking paragraph, HTML comment or fenced code block
inside the repository remains data. It cannot change LioraGit permissions or
the active system prompt.

## Write behavior

Reading and writing are separate capabilities. A participant with read access
must not automatically receive write access.

Every model-initiated mutation follows this state machine:

```text
request → structured proposal → validation → visible preview/diff
        → explicit confirmation → commit → result with commit SHA
```

The confirmation applies to one exact proposal. Editing its path, content,
repository, branch or base commit invalidates the confirmation. A stale base
commit must return a conflict and generate a fresh preview; it must not force an
overwrite.

The initial implementation should support only:

- creating a new `.md` file inside one configured write directory;
- updating an existing managed Markdown file after displaying its diff;
- normal commits to one configured branch.

Deletion, rename, binary upload, branch creation, merge, force-push, workflow
execution and changes outside the allowlist should remain unavailable.

## Prompt configuration

Use the prompts in [`docs/git-memory/PROMPTS.md`](git-memory/PROMPTS.md) as
server-controlled templates. Do not store the active security prompt inside
the connected memory repository, because repository writers could then modify
the rules governing their own access.

The templates cover:

- grounded answers;
- analysis and synthesis;
- write proposals;
- confirmation and execution messages;
- maintenance and contradiction review.

Site owners may customize identity, language and tone. They should preserve the
security, provenance, uncertainty and confirmation clauses.

## What should not be indexed

Exclude by default:

```text
.git/**
.github/**
node_modules/**
vendor/**
**/.env*
**/*secret*
**/*credential*
**/*.key
**/*.pem
**/AGENTS.md
**/CLAUDE.md
memory/archive/**
```

The server must additionally reject symlinks, files above its size limit,
unsupported encodings, truncated GitHub trees and paths containing traversal
segments. An exclude list reduces risk; it is not a substitute for keeping
secrets out of Git.

## Recommended first deployment

1. Create a private repository containing the layout above.
2. Protect the default branch against force-push and deletion.
3. Create a fine-grained read token scoped only to that repository.
4. Start LioraGit in read-only mode and rebuild the index.
5. Test answers, missing evidence, conflicts and malicious instructions stored
   in a test document.
6. Create a separate write credential restricted to the same repository.
7. Allow writes only to `memory/inbox/` with confirmation.
8. Add the partner as a ProcessWire user with chat permissions, not as a
   GitHub collaborator.
9. Review the first commits and the Liora conversation audit before widening
   the write paths.

## Private frontend page

The intended participant experience is a normal Liora widget on a private site
page, not the ProcessWire administration workspace.

Create a page template for the chat page:

```php
<?php namespace ProcessWire;

if(!$user->isLoggedin() || (!$user->isSuperuser() && !$user->hasPermission('liora-git-chat'))) {
    $session->redirect($config->urls->admin . 'login/');
}

echo $modules->get('LioraGit')->renderMemoryWidget([
    'heading' => 'Shared memory',
    'intro' => 'Ask about the project or prepare a new note.',
]);
```

Create a second thin endpoint template at the configured private endpoint URL:

```php
<?php namespace ProcessWire;

$modules->get('LioraGit')->handleMemoryEndpoint();
```

Exclude both routes from full-page and edge caches. The endpoint independently
checks the authenticated ProcessWire session, permission, CSRF token and rate
limit, so hiding the page URL is not the security boundary.

An HTTP Basic password can be an additional outer gate, but it does not replace
ProcessWire authentication: LioraGit uses the ProcessWire user ID to bind write
proposals and permissions.

Inside the normal chat:

```text
/remember Decision about partner access
Partners use a private ProcessWire account and do not receive GitHub tokens.

/confirm

/cancel
```

`/remember` creates a visible Markdown diff. `/confirm` applies only the exact
current user's unexpired proposal. `/cancel` discards it. Ordinary questions
remain read-only.
