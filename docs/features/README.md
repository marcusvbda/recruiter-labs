# Feature documentation

Persistent, versioned source of truth for each feature, shared by humans and by
every AI tool (Claude Code, Codex). One directory per feature:

```text
docs/features/<feature>/
├── spec.md    # WHAT and WHY  — product source of truth
├── plan.md    # HOW           — technical design
└── tasks.md   # executable implementation units
```

`<feature>` is a short kebab-case name (`interview-scheduling`,
`candidate-evaluation-revisions`).

These documents are **not** temporary agent plans. They are never deleted
automatically, never deleted as part of "finishing" a feature, and are updated
when the implementation legitimately diverges from them. Temporary session
plans live outside `docs/features/**` and are deleted after execution
(`AGENTS.md` → Documentation).

Execution of a documented feature follows
`.ai/skills/execute-feature/SKILL.md`. Progress state lives in
`.ai/state/<feature>.md`, which is local and git-ignored — no product or
technical decision may live there.

## `spec.md` — product source of truth

Describes primarily **what** and **why**. Sections:

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
- **Out of scope** — what this feature deliberately does not do.

No implementation detail here.

## `plan.md` — technical design

Describes primarily **how**. Sections:

- **Architecture** — the approach and why it fits the existing monolith.
- **Affected modules** — concrete paths/classes that will change.
- **Entities and data changes** — migrations, columns, enums, indexes.
- **Services, actions, jobs, events** — new or changed behaviour and where it
  lives.
- **UI and components** — Filament resources/pages/widgets, Inertia pages,
  components.
- **Integration points** — queues, calendar, AI agents, external services.
- **Technical constraints** — invariants, performance, tenancy, idempotency,
  authorization.
- **Verification strategy** — the deterministic checks that prove each part
  works, using the real commands in `.ai/guidelines/project-core.md`.
- **Implementation sequencing** — the order the work must happen in and why.

## `tasks.md` — executable units

An ordered list of tasks, each:

- identified (`T01`, `T02`, …) and ordered when dependencies exist;
- small enough for one agent to execute reliably in one pass, large enough to
  be a coherent unit of work;
- scoped to a domain where possible (backend / Filament / Inertia), noting when
  it deliberately crosses domains;
- linked to the acceptance criteria it satisfies;
- explicit about what "done" means for that task.

Suggested shape:

```markdown
## T03 — Persist the scheduling request key

**Domain:** backend
**Depends on:** T02
**Covers:** AC02, AC05

What to do, in two or three sentences, with the files or classes involved.

**Done when:** the observable outcome, plus the deterministic check that
demonstrates it.
```

Keep the list flat and readable — it is read on every execution pass.
