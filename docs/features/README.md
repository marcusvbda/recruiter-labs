# Feature documentation

Persistent, versioned source of truth for each feature, shared by humans and by
every AI tool (Claude Code, Codex). One directory per feature.

For an existing / as-built feature:

```text
docs/features/<feature>/
└── spec.md    # WHAT and WHY — current product behaviour
```

For a new / planned feature:

```text
docs/features/<feature>/
├── spec.md          # required: WHAT and WHY — product source of truth
└── tech-design.md?  # optional: HOW — binding technical source of truth
```

`<feature>` is a short kebab-case name (`interview-scheduling`,
`candidate-evaluation-revisions`).

These documents are **not** temporary agent plans. They are never deleted
automatically or as part of "finishing" a feature. An as-built feature is valid
with only `spec.md`; do not create a retrospective technical design merely to
satisfy the planned-feature convention. Temporary session plans live outside
`docs/features/**` and are deleted after execution (`AGENTS.md` →
Documentation).

Execution of a documented feature follows
`.ai/skills/execute-feature/SKILL.md`. A planned feature is executable with a
complete `spec.md`; `tech-design.md` is optional. The orchestrator inspects the
current repository and its history, then derives an AI-appropriate task graph
in `.ai/state/<feature>.md`, which is local and git-ignored. It never creates a
persistent `tasks.md`.

## `spec.md` — product source of truth

Describes **what** is being built and **why**. It must be understandable without
knowledge of the frameworks, database, services, jobs or libraries used to
implement it. Product or business rules belong here when they describe
observable behaviour or a product invariant. Typical sections:

- **Problem** — what is broken or missing today, for whom.
- **Objective** — the outcome the feature must produce.
- **User behaviour** — what the recruiter (or candidate) sees and does.
- **Business rules** — the rules the product must enforce, including the
  existing invariants the feature must respect (link the relevant
  `.ai/skills/*/SKILL.md` rules instead of restating them).
- **User flow** — the sequence through the product, including alternate and
  terminal branches.
- **Acceptance criteria** — numbered `AC01`, `AC02`, … Each one observable and
  independently checkable; they are what the reviewer validates.
- **Product edge cases** — exceptional cases meaningful to the user or product.
- **Out of scope** — what this feature deliberately does not do.

Do not put implementation choices here. Do not mention classes, migrations,
APIs, libraries, queues, framework components, database structure, cache
implementation, React Query or Filament internals unless that technology is
itself part of the product requirement.

## `tech-design.md` — optional, binding technical source of truth

Describes **how** the current codebase should satisfy the approved `spec.md`.
It must be based on inspection of the actual repository rather than a generic
hypothetical architecture. It may cover:

- **Architecture** — the approach and why it fits the existing monolith.
- **Current repository context** — existing implementation and domain patterns
  that must be reused.
- **Affected modules** — concrete paths/classes that will change.
- **Entities and data changes** — migrations, columns, enums, indexes.
- **Services, actions, jobs, events** — new or changed behaviour and where it
  lives.
- **UI and components** — Filament resources/pages/widgets, Inertia pages,
  components, client-side data fetching and cache choices when relevant.
- **Integration points** — APIs, queues, calendar, AI agents, external services.
- **User-state implementation** — loading, error and empty states, pagination
  and responsiveness.
- **Technical constraints** — authentication, authorization, tenant isolation,
  security, performance, idempotency, concurrency and technical invariants.
- **Verification strategy** — the deterministic checks that prove each part
  works, using the real commands in `.ai/guidelines/project-core.md`.
- **Compatibility** — relevant as-built feature specs and domain-skill
  invariants that the implementation must preserve.

Product decisions must not be invented here. If `spec.md` is ambiguous, surface
the ambiguity for a product decision instead of silently turning it into a
technical choice.

When this file exists, the implementation must follow it exactly. Repository
inspection validates that the prescribed design is feasible; it does not grant
permission to replace, improve or silently correct the design. If any deviation
is required — including a stale path, unavailable component, internal conflict
or a cleaner alternative — stop and surface the blocker. Do not edit
`tech-design.md` during execution unless the user first approves a revised
design.

When this file is absent, `spec.md` remains sufficient. The orchestrator uses
the actual current repository, its history, existing patterns and relevant
domain skills to choose the best technical approach. That derived approach is
runtime execution state, not a missing persistent artifact.

## Runtime execution state

Humans do not pre-decompose planned features into persistent implementation
tasks. When execution starts, the orchestrator reads `spec.md` and, when
present, `tech-design.md`; loads the relevant domain skills; inspects the
repository and history; and derives the task graph best suited to the current
codebase and an AI agent. Every task maps to the acceptance criteria it covers,
and coverage is checked before implementation begins.

The graph, progress, correction counters, `spec.md` fingerprint and either the
`tech-design.md` fingerprint or an explicit `ABSENT` marker live only in
`.ai/state/<feature>.md`. When no design exists, state also records the derived
technical approach. Pending tasks may be split, merged, reordered or refined
after repository discovery as long as product scope does not change and any
binding technical design is followed exactly.

On resume, unchanged input identities allow the existing state to continue. A
change to `spec.md`, or the creation, modification or removal of
`tech-design.md`, requires impact inspection and reconciliation or rebuilding
of pending tasks. Completed work is not blindly repeated, but is revalidated
when the changed contract affects it.
