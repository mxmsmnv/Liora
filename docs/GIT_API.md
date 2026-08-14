# LioraGit public API

This API belongs to the optional `LioraGit` companion included with Liora
1.15.0. Verify that LioraGit, Liora, Atlas and Squad are installed and
configured before calling it.

All methods enforce the current ProcessWire user permission. CLI calls are
allowed for reviewed maintenance automation.

## `sync(): array`

Requires `liora-git-sync`. Reads the configured repository and branch, indexes
approved Markdown into the private Atlas collection, skips unchanged Git blobs
and removes indexed documents that no longer exist after a complete tree read.

```php
$summary = $modules->get('LioraGit')->sync();
```

The result contains `documents`, `indexed`, `unchanged`, `deleted`, `chunks`
and the indexed `commit`. A truncated GitHub tree, size-limit violation,
provider failure or storage error aborts the call explicitly.

Synchronization is incremental per document, not one transaction spanning the
whole repository. If a later GitHub or embedding request fails, documents
already replaced during that run remain valid and are retried or skipped by
blob SHA on the next run. Stale-document deletion happens only after the full
configured tree has been processed successfully.

## `askMemory(string $question, array $history = []): array`

Requires `liora-git-chat`. Retrieves private repository evidence from Atlas
and sends a bounded conversation through Liora/Squad. Live web search and
provider caching are disabled for this call.

```php
$result = $modules->get('LioraGit')->askMemory(
    'What have we decided about partner access?',
    $boundedHistory
);
```

The normalized Liora result additionally contains `sources` with labels,
titles, GitHub URLs, repository paths and commit SHAs. The call is read-only
and does not create a normal public Liora Insights Thread.

## `renderMemoryWidget(array $options = []): string`

Requires `liora-git-chat`. Renders the normal Liora frontend widget but points
it to LioraGit's private endpoint. Streaming is disabled because repository
commands may return deterministic previews or commit results instead of a
provider stream. The private widget remains available when the normal public
Liora widget setting is disabled.

```php
echo $modules->get('LioraGit')->renderMemoryWidget([
    'heading' => 'Shared project memory',
    'intro' => 'Ask what we know, decided or still need to resolve.',
]);
```

## `handleMemoryEndpoint(): void`

Serves the authenticated JSON endpoint used by `renderMemoryWidget()`. Use it
only from a thin, uncached ProcessWire template. It enforces POST, CSRF, a
bounded session rate limit, `liora-git-chat`, and `liora-git-write` for memory
commands.

```php
<?php namespace ProcessWire;

if(!$user->isLoggedin()) {
    http_response_code(403);
    return;
}

$modules->get('LioraGit')->handleMemoryEndpoint();
```

## `proposeCreate(string $title, string $content): array`

Requires `liora-git-write`. Creates an expiring server-side proposal for one
new Markdown note inside the configured write directory. It does not call
GitHub's write API.

```php
$proposal = $modules->get('LioraGit')->proposeCreate(
    'Partner onboarding',
    'Give the partner a ProcessWire chat role without GitHub access.'
);
```

The proposal is bound to the current ProcessWire user and exact repository,
branch, path, base commit and content hash. It expires after one hour.

The bundled admin chat exposes the same operation deterministically:

```text
/remember Partner onboarding
Give the partner a ProcessWire chat role without GitHub access.
```

This prepares the preview; it does not commit. LioraGit deliberately uses an
explicit command instead of allowing the model to infer write authorization
from ordinary conversation.

## `confirmProposal(string $publicId): array`

Requires `liora-git-write`. Commits one owned, unexpired proposal after
revalidating its content hash, target policy and exact repository head.

```php
$commit = $modules->get('LioraGit')->confirmProposal($proposal['public_id']);
```

If the branch advanced after preview, nothing is written and the proposal is
marked as a conflict. A successful result contains `repository`, `branch`,
`path`, `commit_sha` and `url` from GitHub.

## `cancelProposal(string $publicId): bool`

Requires `liora-git-write`. Cancels one current user's pending proposal without
writing anything.

## Current limitations

- One repository and branch per LioraGit installation.
- GitHub REST API transport only.
- Markdown reads and new-note creation only.
- Writes only inside one configured directory.
- Admin chat history is bounded to the current server session; Git is the
  durable shared memory. The frontend widget keeps its normal bounded browser
  history and sends only recent turns to the endpoint.
- No update, delete, rename, binary upload, branch creation, merge, workflow
  execution or force-push API.
- No webhook or scheduled sync yet.
