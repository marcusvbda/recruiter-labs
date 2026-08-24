---
status: implemented
type: as-built
---

# Recruitment attention

## Problem

Recruiters can have many active jobs and applications, but most records do not
need action at the same time. A dashboard of raw counts does not answer the more
important question: what in the recruiting process currently needs human
attention?

The product also needs to avoid noisy alerts. A normal early-stage job must not
be called stalled, and one underlying problem should not appear as several
competing warnings.

## Objective

Derive a bounded, explainable work queue from recruitment state so recruiters
can identify operational blockers and go directly to the place where a human
action is needed.

Attention is deterministic workflow guidance, not AI task generation.

## User behaviour

The recruiter sees "Needs your attention" in the overview and contextual
attention in a job workspace.

Each attention item communicates:

- what happened;
- why it matters;
- relevant job/candidate context when applicable;
- one destination/action that helps the recruiter address it.

The queue may include signals for:

- declined interviews;
- interview calendar failures;
- calendar reauthorization required;
- failed candidate evaluation;
- evaluation blocked by AI allowance;
- candidate overdue in stage;
- finalist awaiting a decision;
- genuinely stalled job;
- job ending without finalists;
- hiring target reached;
- hiring target nearly reached.

## Business rules

This feature is governed by
`.ai/skills/recruitment-workflow/SKILL.md`.

- Attention is derived from persisted recruitment state. It is not a separate
  user-created task entity.
- Attention rules are deterministic and explainable; an AI model is not asked
  what the recruiter should do next.
- Every signal must correspond to an observable state and have an actionable
  destination.
- Candidate/application signals are scoped to active recruitment where the work
  is still relevant.
- Stage overdue depends on the current stage's configured waiting threshold.
- A finalist waiting for a decision is more specific than a generic overdue
  signal for the same application, so the more specific signal wins.
- A job is not "stalled" merely because applications exist and no interview has
  happened yet. Actual overdue waiting/progress evidence is required.
- A job ending without finalists is more specific than generic job-stalled
  messaging when both would describe the same underlying situation.
- Calendar connection/sync problems are operational risks and do not become
  candidate-quality signals.
- AI quota/evaluation failure signals describe missing decision support, not
  negative evidence about a candidate.
- Hiring-target signals are advisory. Reaching a target does not automatically
  pause, unpublish, or close a job.
- Lists are bounded so one signal category cannot consume the whole surface;
  hidden counts remain discoverable.
- Severity communicates operational urgency, not candidate ranking.
- Attention must not automatically change a pipeline stage or make a hiring
  decision.

## User flow

1. Recruitment activity changes persisted state: a candidate waits too long, an
   interview is declined, calendar sync fails, a target is reached, or another
   supported condition becomes true.
2. The attention service derives the current set of applicable signals.
3. Overlapping generic signals are suppressed when a more specific signal
   already explains the same required decision.
4. The overview presents a bounded cross-job queue.
5. A job workspace can present the subset relevant to that hiring process.
6. The recruiter follows an attention item to the affected application, job,
   calendar settings, AI settings, or pipeline context.
7. Once the underlying persisted condition changes, the derived attention signal
   disappears or changes accordingly.

## Acceptance criteria

- **AC01** — Attention items are derived from current persisted state and are not
  stored as independent recruiter tasks.
- **AC02** — Every attention item can explain the condition that caused it and
  points to an actionable destination.
- **AC03** — A declined upcoming interview can surface as an attention item.
- **AC04** — A failed interview calendar synchronization can surface as an
  attention item without becoming a candidate-evaluation signal.
- **AC05** — A calendar account requiring reauthorization can surface when
  upcoming interview commitments depend on it.
- **AC06** — A failed or quota-blocked candidate evaluation can surface as an
  operational AI problem without lowering candidate fit.
- **AC07** — An active application becomes stage-overdue only according to its
  configured stage threshold.
- **AC08** — A final-stage candidate awaiting a decision is not simultaneously
  shown as a duplicate generic stage-overdue decision.
- **AC09** — A newly opened job with applicants is not called stalled solely
  because no candidate has interviewed yet.
- **AC10** — Job-stalled requires real overdue waiting/progress evidence.
- **AC11** — A job ending soon without finalists can suppress a less-specific
  stalled signal for the same job.
- **AC12** — Hiring-target reached/near signals never close or pause the job
  automatically.
- **AC13** — The attention surface remains bounded and can communicate that
  additional items are hidden.
- **AC14** — No attention signal automatically changes candidate stage or makes a
  hiring decision.

## Out of scope

- User-created to-do lists or task assignment.
- Arbitrary reminders.
- AI-generated prioritization.
- Automatic corrective actions.
- Generic notification-rule builders.
- Candidate ranking based on attention severity.

## Related feature specs

- `../recruitment-pipeline/spec.md`
- `../interview-scheduling/spec.md`
- `../calendar-integration/spec.md`
- `../candidate-evaluation/spec.md`
- `../job-workspace/spec.md`
- `../ai-usage-and-limits/spec.md`
