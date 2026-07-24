# Liora agent guide

Liora is an LQRS-owned ProcessWire module for AI assistance and measurable
content demand. Squad remains the only credential/provider transport layer.

## Invariants

- Never store API keys or plaintext session identifiers.
- Do not record raw IP addresses or user agents.
- Treat a conversation as one thread with chronological messages; never flatten
  follow-up questions into unrelated dashboard rows.
- Browser history must remain opt-in to restore, stay in LocalStorage, and never
  contain provider credentials or server session identifiers.
- Keep widget behavior in JavaScript, structural styling in CSS, and
  repository-owned visual tokens in allowlisted `themes/*.json` files.
- Keep public output escaped and treat model responses as untrusted text.
- All admin mutations must use POST and ProcessWire CSRF validation.
- Preserve tracked questions on uninstall unless the owner explicitly enables
  the destructive uninstall setting.
- Keep templates thin; endpoint, widget and tracking behavior belongs here.

## Validation

- Lint every PHP file.
- Run `php tests/run.php`.
- Install `Liora`, `InputfieldLiora`, and `ProcessLiora` in a real ProcessWire
  site and verify the storage table.
- Exercise one real Squad request and verify that it appears in Liora Insights.
- Verify the public widget with JavaScript enabled and no console errors.
