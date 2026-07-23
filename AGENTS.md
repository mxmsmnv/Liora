# LqrsAi agent guide

`LqrsAi` is an LQRS-specific application facade over Squad.

- Keep provider credentials and default model selection in Squad.
- Do not add API-key fields or persist credentials in this module.
- Preserve the public response shape used by LQRS templates and CLI scripts:
  `success`, `status`, `error`, optional `content`, and optional `data`.
- Keep public error messages generic; provider details belong in protected logs.
- Use `chat()` for OpenAI-style message arrays and `ask()`/`complete()` for
  one-shot prompts.
- Site-specific prompts remain in LQRS templates or task code, not module core.
- Update the module version and changelog for behavior changes.
- Validate with `php -l LqrsAi.module.php`, a real ProcessWire installation,
  and a minimal provider request only when provider use has been authorized.
