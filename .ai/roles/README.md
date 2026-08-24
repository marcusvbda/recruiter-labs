# Roles

Canonical, tool-neutral definitions of the specialised workers this project
uses. One file per role; each describes what the worker is responsible for and
how it behaves.

| Role | Responsible for |
| --- | --- |
| [laravel-backend.md](laravel-backend.md) | Models, migrations, services, actions, jobs, policies, backend business rules |
| [filament-admin.md](filament-admin.md) | Filament resources, pages, actions, widgets, tables/forms/infolists |
| [inertia-frontend.md](inertia-frontend.md) | Inertia/React pages and components, Tailwind, React Query |
| [code-reviewer.md](code-reviewer.md) | Reviewing a diff against acceptance criteria; never edits files |
| [qa-tester.md](qa-tester.md) | Running existing tests, diagnosing failures and regressions |

How a tool consumes them:

- **Claude Code** exposes them as native subagents. `.claude/agents/<role>.md`
  is a thin adapter that carries only Claude's registration metadata and points
  here; the instructions themselves live in this directory.
- **Codex** (and any tool without subagents) adopts the role in-thread: read the
  role file before doing that kind of work, then follow it.

Roles never restate global rules. `AGENTS.md` (language, Git, testing,
documentation) and `.ai/guidelines/project-core.md` (verification commands,
stack detail) apply to every role. Product invariants stay in
`.ai/skills/*/SKILL.md` and are loaded when the domain is relevant.
