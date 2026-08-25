---
name: execute-feature
description: Use when asked to execute, implement or continue a planned feature documented under docs/features/<feature>/.
---

# Execute feature

## Execution boundary

```text
PRODUCT
spec.md
    ↓
OPTIONAL TECHNICAL DESIGN
tech-design.md present: binding exactly
tech-design.md absent: approach derived after boundary
    ↓
-----------------------------
EXECUTION BOUNDARY
-----------------------------
audit current repository and history
    ↓
follow binding design or derive best approach
    ↓
derive AI-optimized task graph
    ↓
.ai/state/<feature>.md
    ↓
implement → verify → review → correct
    ↓
integrated feature review
```

This skill starts **below** the execution boundary. It never invents product
requirements or makes a product decision on the user's behalf. Product
behaviour comes from `spec.md`. When `tech-design.md` exists, its implementation
direction is binding exactly. When it is absent, the orchestrator derives the
best technical approach from the repository, its history and loaded domain
skills.

## Entry gate

A new / planned feature is executable when this required file exists and
satisfies the document contract in `docs/features/README.md`:

```text
docs/features/<feature>/spec.md
```

Missing acceptance criteria, an empty file, placeholders or obviously
unfinished sections make `spec.md` incomplete. If it is missing or incomplete:

```text
STOP.
Report exactly which artifact is missing or incomplete.
Do not create or infer it.
Do not start implementation.
```

`tech-design.md` is optional. Do not create it to satisfy the entry gate or
retrospectively document an as-built feature. If it exists, it must itself be
complete and grounded in the current repository; an incomplete design is a
blocker because exact execution is not possible.

The presence of `tech-design.md` means the user requires that design to be
implemented exactly. Repository inspection validates feasibility but never
authorizes replacing, improving, reinterpreting or silently correcting it. If
any deviation is required, stop and surface the blocker before implementation.

The orchestrator never creates `tasks.md`. Task decomposition is an execution
decision recorded only in `.ai/state/<feature>.md`.

## Your role

You are the **orchestrator**. You inspect the current repository, derive the
implementation task graph, scope and delegate one task at a time, verify it
deterministically, have it reviewed against its acceptance criteria, and move
on.

Global rules in `AGENTS.md` always win: English only, no branches, commits or
pushes unless explicitly asked in that message, and no new or modified tests
unless explicitly asked in that message.

### Decisions you may make, and decisions you may not

When `tech-design.md` is absent, decide the technical approach from repository
context and history while preserving `spec.md`. When it exists, make only
implementation-level choices it does not prescribe and that cannot alter or
deviate from it. In either case, decide freely about:

- task boundaries, dependencies and execution order;
- method, variable and class naming;
- small internal extraction or inlining;
- exact placement of a helper within the approved structure;
- minor sequencing inside a task.

Never decide silently — stop and surface it instead:

- a new business rule or changed user-visible behaviour;
- anything that expands or reduces the feature's scope;
- a new product requirement the spec does not state;
- a product ambiguity that implementation cannot resolve;
- any required deviation from an existing `tech-design.md`, including a stale
  path, unavailable component, internal conflict or preferred alternative.

## 0. Start or resume

Only once the entry gate has passed:

1. Read `spec.md` in full. If `tech-design.md` exists, read it in full.
2. Load the domain skill(s) the feature touches:
   - recruiter surfaces → `.ai/skills/recruitment-workflow/SKILL.md`;
   - AI / candidate evaluation →
     `.ai/skills/evaluation-integrity/SKILL.md`.
3. Inspect the actual current repository and its relevant history:
   implementation, data model, established patterns, dependencies and
   verification commands. With a design, use inspection to validate exact
   feasibility. Without one, use it to choose the best technical approach.
4. Compute a deterministic SHA-256 identity for `spec.md`. Record either the
   SHA-256 identity of `tech-design.md` or the literal `ABSENT`, then read
   `.ai/state/<feature>.md` if it exists. Repository inspection also checks
   whether code relevant to recorded completed work has drifted since its
   completion evidence was captured.

### New execution state

If no state exists and `tech-design.md` is absent, first derive the best
technical approach from the inspected repository and history. If the design is
present, adopt it exactly. Then derive the task graph most appropriate for AI
execution within that technical basis. Tasks may have dependencies and may
cross domains when that is the smallest coherent unit. Every task must state
what it does, its domain, dependencies, acceptance criteria, completion
evidence and status.

Before implementation starts, enumerate every acceptance criterion from
`spec.md`, map each one to one or more tasks, and verify that none is uncovered.
If a criterion cannot be implemented from the approved documents, stop and
surface the blocker.

Create `.ai/state/<feature>.md` (and `.ai/state/` if needed) with this minimum
shape:

```markdown
# Execution state: <feature>

Source: docs/features/<feature>/
spec.md SHA-256: <hash>
tech-design.md: <SHA-256 hash | ABSENT>
Technical basis: <BINDING_DESIGN | REPOSITORY_DERIVED>

## Derived technical approach

<Required only when Technical basis is REPOSITORY_DERIVED; summarize the
repository/history evidence and chosen approach.>

## Acceptance-criteria coverage

- AC01: T01
- AC02: T01, T03

## Tasks

### T01 — <outcome>

Status: PENDING
Domain: <role/domain>
Depends on: none
Covers: AC01, AC02
Completion evidence: <observable result and deterministic checks>
Correction round: 0

<implementation scope derived from the selected technical basis>
```

Product decisions and any binding technical design stay in persistent source
documents. When no design exists, the repository-derived technical approach is
an execution decision stored with task decomposition, progress and evidence in
state.

### Existing execution state

- An unresolved `BLOCKED` status takes precedence over matching identities and
  pending tasks. Resume only after the user decision or source-document change
  that resolves the blocker has been reconciled.
- First return any interrupted `IN_PROGRESS` task to `PENDING`, whether or not
  the source identities match, so reconciliation starts from an explicit safe
  state.
- If both recorded identities match, reconcile relevant repository drift with
  recorded completion evidence and resume at the next dependency-ready
  `PENDING` task. Do not redo a `DONE` task that remains valid.
- If `spec.md` changed, or `tech-design.md` was created, changed or removed, do
  not blindly resume the old pending graph. Re-read every changed or newly
  present document, inspect its impact on the repository and tasks, then
  reconcile or rebuild the technical basis, pending graph and
  acceptance-criteria map. A newly present design becomes binding; a removed
  design requires a fresh repository-derived approach.
- Preserve completed work that remains valid. Revalidate any `DONE` task whose
  contract, technical assumptions or relevant implementation changed by
  re-running its recorded deterministic completion evidence. Update that
  evidence with the result, returning the task to `PENDING` only when further
  work is actually required.
- Record the new identities and a reconciliation note listing changed inputs,
  impacted criteria/tasks, revalidated completed tasks and task-graph changes
  before continuing.

If reconciliation exposes a product or binding-design blocker, record the
current identities, partial reconciliation findings and `BLOCKED` status before
stopping. The blocker suspends implementation and unrelated technical changes
until the user resolves it.

When a new or changed `tech-design.md` affects `DONE` work, deterministic checks
alone are insufficient: run `code-reviewer` against that work and the binding
design. Work that is product-correct but technically different from the design
is no longer validly `DONE`; reopen or supersede it before any implementation
continues. When a design is removed, derive the new approach using valid
completed work as repository context and preserve it unless a concrete
correctness or coherence issue requires change.

Pending tasks may be split, merged, reordered or refined whenever repository
discovery reveals a better execution strategy. Product scope must not change;
when `tech-design.md` exists, every revision must continue to follow it exactly.
Keep task IDs and coverage mappings traceable when revising the graph.

## 1. Per-task loop

For each pending task in dependency order:

### 1.1 Scope

- Read the task and its mapped acceptance criteria.
- Revisit only the sections of `spec.md` and any present `tech-design.md` it
  depends on, plus the derived technical approach when no design exists.
- Identify the implementation domain(s) from the files it will touch.
- Mark the task `IN_PROGRESS` before delegation so interrupted work is explicit
  on resume.

### 1.2 Delegate

Choose the implementation role by scope. A tool with native subagents delegates
to it; a tool without them reads the role file and adopts it in-thread.

| Task scope | Role (`.ai/roles/`) |
| --- | --- |
| Migration, model, service, action, job, policy, validation, backend business rule | `laravel-backend.md` |
| Filament resource, page, action, widget, table/form/infolist, relation manager | `filament-admin.md` |
| Inertia/React page or component, Tailwind, React Query | `inertia-frontend.md` |

Rules:

- Reuse these existing roles. Do not create a generic implementer role.
- A task that crosses domains runs the roles sequentially inside one task
  boundary — it stays one task, one review and one state entry.
- Give the worker the task, its acceptance criteria, relevant source-document
  excerpts, applicable domain-skill invariants, and an explicit instruction to
  implement only that scope.
- Unrelated improvements spotted along the way are reported, not implemented.

### 1.3 Deterministic verification

Run the checks matching the files actually changed, using the real commands in
`.ai/guidelines/project-core.md`:

- PHP changed → `vendor/bin/pint --dirty --format agent`,
  `composer types:check`, and relevant existing tests
  (`php artisan test --compact --filter=…`).
- Frontend changed → `npm run types:check`, `npm run lint:check`, and
  `npm run format:check`; use `npm run build` only when the bundle is genuinely
  at risk.

If a check fails, return the failure to the implementing role and fix it before
invoking the reviewer. The exception is a genuinely puzzling failure where
reviewer or `qa-tester` judgement is the fastest diagnosis.

Do not create or modify tests to make a check pass. If existing tests fail
because the spec deliberately changed behaviour, surface the blocker rather
than rewriting tests unless the user explicitly authorised test changes.

### 1.4 Review

Collect the diff for this task only (`git diff` / `git diff --stat`, scoped to
the touched paths) and give the `code-reviewer` role:

- the current task and its acceptance criteria;
- the relevant product requirements and either binding technical-design
  constraints or the repository-derived approach from state;
- the diff;
- deterministic verification results.

Expect the reviewer format from `.ai/roles/code-reviewer.md`: per-criterion
PASS/FAIL, blocking findings, non-blocking findings, and `Verdict: APPROVED` or
`Verdict: CHANGES_REQUIRED`.

A task is done only when its behaviour is right, not merely when the diff is
clean.

### 1.5 Close or correct

- **APPROVED** → mark the task `DONE` in `.ai/state/<feature>.md`, record its
  completion evidence, reset the correction counter and continue automatically.
- **CHANGES_REQUIRED** → run the bounded correction loop below.

Record non-blocking findings for the final report; do not silently implement
them as extra scope.

## 2. Correction loop (circuit breaker)

Maximum **2** focused correction rounds per task:

```text
review FAIL
  → fix round 1 → deterministic verification → re-review
review FAIL
  → fix round 2 → deterministic verification → re-review
review FAIL
  → STOP patching
```

Each round addresses only blocking findings, uses the same domain role, and
updates `Correction round: N` in the state file.

After the second failed re-review, stop patching and re-evaluate against
`spec.md` and the selected technical basis.

**Continue automatically** when `tech-design.md` is absent and the correction
remains below the execution boundary:

- change a faulty repository-derived implementation strategy while preserving
  approved product behaviour, and update the derived approach in state;
- split, merge, reorder or refine pending tasks in the state file without
  changing product scope.

When `tech-design.md` exists, continue automatically only by correcting the
implementation to match it exactly or by adjusting task decomposition without
deviating from it. Never edit the design during execution.

**Stop and surface to the user** when the feature itself needs a decision:

- `spec.md` is ambiguous or desired product behaviour is unclear;
- acceptance criteria conflict with each other or an existing technical design;
- the correction changes feature scope;
- an existing `tech-design.md` is stale, incorrect, internally inconsistent,
  infeasible or would need any deviation;
- a major technical decision is missing from an existing `tech-design.md` and
  exact implementation therefore cannot be determined.

For a blocker found before state exists, stop without creating state. Otherwise
report the blocker, options and recommendation; mark the task `BLOCKED` in the
state file and wait. Never edit acceptance criteria to match the code, invent
product behaviour, or alter `tech-design.md` without prior user approval.

## 3. Final feature review

Every task being `DONE` is not the feature being done. When the last task
closes, run one integrated review:

1. Re-read `spec.md` and any present `tech-design.md` in full and confirm the
   recorded hash/`ABSENT` identities still match state.
2. Produce the full feature diff across all tasks.
3. Run the broader deterministic gate for the domains touched — `composer test`
   and/or the npm checks, or `composer ci:check` when the feature spans both.
4. Run `code-reviewer` with all acceptance criteria, the binding design or
   repository-derived approach, verification results and the full diff.

The integrated review checks for:

- acceptance criteria that no task fully owns;
- requirements satisfied in one task and broken later;
- inconsistent naming, duplicated logic or dead scaffolding across tasks;
- applicable product invariants violated by integration;
- flows that work only when tasks are considered in isolation.

If the final review fails, the same bounded correction rules apply at feature
scope.

## 4. Report

When the feature passes final review, report concisely:

- tasks completed;
- acceptance criteria and status;
- deterministic checks and results;
- non-blocking findings left open;
- anything intentionally out of scope or blocked.

Treat `spec.md` as product truth and an existing `tech-design.md` as binding:
surface proposed changes instead of editing either to match implementation.
Retain `.ai/state/<feature>.md` unless the user asks to delete it; it is
git-ignored. Never delete anything under `docs/features/**`.

Do not commit, branch or push unless the user explicitly asks in that message.
