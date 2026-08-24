# Project core guidelines

Shared engineering guidance for every AI tool in this repository. It supports
`AGENTS.md` and never replaces it: global rules (language, Git, testing,
documentation, orchestration) stay in `AGENTS.md`; this file holds the detail
that would otherwise bloat it.

Where each kind of instruction lives is documented once, in `AGENTS.md` →
"Where instructions live".

## Stack detail

The stack list itself is in `AGENTS.md`. Two expansions:

- **Dependency versioning.** Always install/use the latest stable version of
  each dependency at setup time (`composer require`/`npm install` without
  pinning old versions). When running `composer create-project`/`npm create`,
  confirm the resolved version before proceeding, since newer releases may exist
  beyond this assistant's knowledge. Do not change the application's
  dependencies without approval.
- **React Compiler.** It is enabled via `babel-plugin-react-compiler` (or the
  equivalent Vite config). Do not write manual `useMemo`/`useCallback`/
  `React.memo` speculatively — the compiler already handles that optimization;
  use them only for a real, identified performance issue.

## Deterministic verification commands

These are the commands that actually exist in this repository
(`composer.json` scripts, `package.json` scripts). Do not invent others. Prefer
the narrowest command that covers what changed, and run these **before** asking
an AI reviewer to look at anything.

### PHP changed

| Purpose | Command |
| --- | --- |
| Format changed files | `vendor/bin/pint --dirty --format agent` |
| Format whole project | `composer lint` (`pint --parallel`) |
| Format check only | `composer lint:check` |
| Static analysis | `composer types:check` (`phpstan analyse`) |
| Focused tests | `php artisan test --compact --filter=<name>` or with a path |
| Full PHP gate | `composer test` (clears config, lint:check, types:check, tests) |

### Frontend changed (`resources/js/**`, styles, Vite config)

| Purpose | Command |
| --- | --- |
| Types | `npm run types:check` (`tsc --noEmit`) |
| Lint (check) | `npm run lint:check` |
| Lint (fix) | `npm run lint` |
| Formatting | `npm run format:check` / `npm run format` |
| Build (only when the bundle is genuinely at risk) | `npm run build` |

### Everything changed / final gate

`composer ci:check` runs the frontend checks plus the PHP gate. It is the
heaviest option — use it for a final feature review, not per task.

Notes:

- Running existing tests is always allowed. Creating or modifying tests is not,
  unless the user explicitly asked in that message (see `AGENTS.md` → Testing).
- Do not use an LLM to do what a shell command verifies. If a deterministic
  check fails, fix the implementation before invoking a reviewer — unless the
  failure itself is what needs diagnosis.
- If a frontend change does not show up in the UI, the user may need to run
  `npm run build`, `npm run dev` or `composer run dev` — ask them.

## Tooling differences between Claude Code and Codex

Both tools read `AGENTS.md`, `.ai/**` and `docs/features/**`. They differ in
integrations:

- **Claude Code** has the Laravel Boost MCP server (`database-schema`,
  `database-query`, `search-docs`, `browser-logs`, `get-absolute-url`) and
  exposes `.ai/roles/*` as native subagents through the thin adapters in
  `.claude/agents/`. Prefer Boost tools over shell equivalents, and prefer
  `search-docs` over guessing framework APIs.
- **Codex** (or any tool without those integrations) uses plain shell and file
  reads, runs the same verification commands, and adopts the same `.ai/roles/*`
  definitions in-thread. Everything else — rules, skills, roles, feature
  documents, state files — is identical.

Nothing in `.ai/**` or `docs/features/**` may assume a tool-specific capability.

## Token discipline

- Load a skill only when its domain is relevant; never preload all of `.ai/skills`.
- Read the sections of `spec.md`/`plan.md` a task needs, not the whole document
  on every task.
- Review the task diff, not the repository.
- Run deterministic checks first; use AI review for judgement, not for running
  commands.
- Do not invoke `qa-tester` for trivial tasks; run the relevant tests directly.
- One comprehensive review at the end of a feature, not a full-repository review
  per task.
