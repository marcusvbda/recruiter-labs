---
name: execute-feature
description: Orchestration workflow for implementing a feature that already has docs/features/<feature>/spec.md, plan.md and tasks.md. Drives task-by-task delegation, deterministic verification, independent code review against acceptance criteria, bounded correction loops, resumable state in .ai/state/, and a final integrated feature review. Load when asked to execute, implement or continue a documented feature.
---

# Execute feature

Use this skill when a feature already has:

```text
docs/features/<feature>/spec.md
docs/features/<feature>/plan.md
docs/features/<feature>/tasks.md
```

If any of those is missing, stop and produce the missing document first (see
`docs/features/README.md`) — do not improvise a feature from a one-line request.

You are the **orchestrator**. You do not implement the whole feature yourself.
You scope one task at a time, delegate it, verify it deterministically, have it
reviewed against its acceptance criteria, and move on.

Global rules in `AGENTS.md` always win: English only, no branches/commits/pushes
unless explicitly asked in that message, no new or modified tests unless
explicitly asked in that message.

## 0. Start or resume

1. Read `tasks.md` in full (it is the shortest document and the task list).
2. Read the *table of contents / headings* of `spec.md` and `plan.md` — not the
   whole text — so you know where to look later.
3. Read `.ai/state/<feature>.md`. If it does not exist, create it (create
   `.ai/state/` if missing; it is git-ignored):

   ```text
   Feature: <feature>
   Source: docs/features/<feature>/

   T01 PENDING
   T02 PENDING
   T03 PENDING
   ```

4. Resume at the first task that is not `DONE`. Never redo a `DONE` task.
5. Load the domain skill(s) the feature touches, once, at the start:
   - recruiter surfaces → `.ai/skills/recruitment-workflow/SKILL.md`
   - AI / candidate evaluation → `.ai/skills/evaluation-integrity/SKILL.md`

## 1. Per-task loop

For each pending task, in order:

### 1.1 Scope

- Read the task and its linked acceptance criteria.
- Read **only** the sections of `spec.md` and `plan.md` the task references or
  clearly depends on.
- Identify the implementation domain(s) from the files the task will touch.

### 1.2 Delegate

Choose the implementation role by scope. A tool with native subagents delegates
to it (Claude Code: the `Agent` tool, whose agents load these same role files);
a tool without them reads the role file and adopts it in-thread.

| Task scope | Role (`.ai/roles/`) |
| --- | --- |
| Migration, model, service, action, job, policy, validation, backend business rule | `laravel-backend.md` |
| Filament resource, page, action, widget, table/form/infolist, relation manager | `filament-admin.md` |
| Inertia/React page or component, Tailwind, React Query | `inertia-frontend.md` |

Rules:

- Reuse these existing roles. Do not create a generic implementer role.
- A task that crosses domains runs the roles **sequentially inside one task
  boundary** — it stays one task, one review, one state entry.
- Give the worker: the task text, its acceptance criteria, the relevant
  spec/plan excerpts, the invariants from the loaded domain skill that apply,
  and the explicit instruction to implement **only this task's scope**.
- No scope creep: unrelated improvements spotted along the way are reported,
  not implemented.

### 1.3 Deterministic verification

Run the checks that match the files actually changed, using the real commands
listed in `.ai/guidelines/project-core.md`:

- PHP changed → `vendor/bin/pint --dirty --format agent`, `composer types:check`,
  and the relevant existing tests (`php artisan test --compact --filter=…`).
- Frontend changed → `npm run types:check`, `npm run lint:check`, and
  `npm run format:check`; `npm run build` only when the bundle is genuinely at
  risk.

If a check fails, return the failure to the implementing role and fix it
**before** invoking the reviewer — an LLM is not needed to run a command that
already reported the problem. The one exception: the failure is genuinely
puzzling and reviewer/`qa-tester` judgement is the fastest way to diagnose it.

Do not create or modify tests to make a check pass. If existing tests fail
because the spec deliberately changed behaviour, that is a blocker to surface,
not a test to rewrite (unless the user explicitly authorised test changes).

### 1.4 Review

Collect the diff for this task only (`git diff` / `git diff --stat`, scoped to
the touched paths — never a whole-repository review) and hand it to the
`code-reviewer` role with:

- the current task and its acceptance criteria;
- the relevant spec requirements and plan constraints;
- the diff;
- the deterministic verification results.

Expect the reviewer's verdict format (see `.ai/roles/code-reviewer.md`):
per-criterion PASS/FAIL, blocking findings, non-blocking findings, and
`Verdict: APPROVED` or `Verdict: CHANGES_REQUIRED`.

A task is done only when the behaviour is right — not because the diff looks
clean.

### 1.5 Close or correct

- **APPROVED** → mark `T## DONE` in `.ai/state/<feature>.md`, clear the
  correction counter, continue to the next task automatically. Do not ask
  "should I continue?" after a successful task.
- **CHANGES_REQUIRED** → run the bounded correction loop below.

Non-blocking findings are recorded in the final report; they do not stop the
task and are not silently implemented as extra scope.

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

Each fix round addresses only the blocking findings, handled by the same
domain role, and records `Correction round: N` in the state file.

After the second failed re-review, stop applying patches and re-evaluate the
task against `spec.md` and `plan.md` to determine which of these it is:

1. the implementation strategy is wrong → change the approach, reset the task
   (still one task), and note the change in `plan.md`;
2. the task is underspecified → rewrite the task in `tasks.md` (splitting it if
   needed) and restart it;
3. the plan is incorrect → correct `plan.md`, then re-derive the affected tasks;
4. the spec is ambiguous → surface the ambiguity to the user as a blocker, with
   the options and your recommendation, and stop that task.

Never keep patching blindly, and never mark a task `DONE` to escape the loop.

## 3. Final feature review

Every task being `DONE` is not the feature being done. When the last task
closes, run one integrated review before declaring completion:

1. Read `spec.md` and `plan.md` in full (now, not per task).
2. Produce the full feature diff (all tasks combined).
3. Run the broader deterministic gate for what the feature touched —
   `composer test` and/or the `npm` checks, or `composer ci:check` when the
   feature spans both sides.
4. Run `code-reviewer` once with the complete spec acceptance criteria, the
   plan's constraints and verification strategy, and the full diff.

The final review specifically looks for what task-by-task review cannot see:

- acceptance criteria that no single task fully owns;
- requirements satisfied in one task and broken by a later one;
- inconsistent naming, duplicated logic or dead scaffolding across tasks;
- product invariants from the loaded domain skills violated by the integration;
- flows that only work when the tasks are read in isolation.

If the final review fails, the same bounded correction rules apply, at feature
scope.

## 4. Report

When the feature passes the final review, report concisely:

- tasks completed;
- acceptance criteria and their status;
- deterministic checks run and their results;
- non-blocking findings left open;
- anything intentionally out of scope or blocked.

Then update `docs/features/<feature>/` if the implementation legitimately
diverged from the plan — that documentation is persistent and must reflect
reality. Delete `.ai/state/<feature>.md` only if the user asks; it is
git-ignored either way. **Never** delete anything under `docs/features/**`.

Do not commit, branch or push unless the user explicitly asks in that message.
