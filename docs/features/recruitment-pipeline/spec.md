---
status: implemented
type: as-built
---

# Recruitment pipeline

## Problem

Recruiting teams need a clear, configurable workflow for moving candidates from
entry to an outcome. The product must preserve the company's process rather than
letting AI scores or generic assumptions decide what stage comes next.

Operational delay also matters: recruiters need to see who has been waiting,
where the process is blocked, and which outcomes are terminal.

## Objective

Provide a human-controlled per-job recruitment pipeline with explicit stage
semantics, stage-age visibility, safe terminal outcomes, and operational
ordering that helps recruiters work the process without turning AI fit into an
automatic ranking or decision engine.

## User behaviour

Recruiters can:

- configure reusable recruitment pipelines and their statuses;
- assign a pipeline to a job before candidates enter it;
- see job applications grouped by status on the job pipeline board;
- move a candidate to another status in the same job workflow;
- see how long a candidate has been in the current stage;
- see operational signals such as overdue waiting, interview state, and
  evaluation state;
- see fit only as supporting context when a current evaluation exists.

Terminal outcomes remain visible as history but stop offering normal active
recruitment actions until the recruiter deliberately reopens the application.

## Business rules

This feature is governed by
`.ai/skills/recruitment-workflow/SKILL.md`.

- A job's pipeline is fixed once applications exist. Changing it after candidate
  entry would make existing status assignments ambiguous.
- A new application enters the first configured status of the job's pipeline.
  There is no fallback to an unrelated company status.
- Every application status belongs to the same company and pipeline as the job.
- Stage movement is a human action. AI fit, confidence, referral source, and
  attention signals do not automatically move a candidate.
- Statuses can carry distinct semantics for terminal outcome, hired outcome, and
  final-stage decision context.
- Entering a status starts or resets the application's stage-age clock.
- A non-terminal status can define how long an application may remain there
  before it is considered overdue.
- Configured communication associated with entering a status follows the same
  human-controlled stage transition.
- The default pipeline board order is operational: candidates waiting longer in
  the stage appear before newer arrivals. AI fit is not a default priority key.
- A current fit may be shown on a card as context, but an outdated fit must not
  be presented as current.
- Terminal applications stop offering actions that imply active recruiting, such
  as scheduling or reprocessing candidate evaluation, until a human reopens them.
- Hired is a terminal outcome, not merely another in-progress step.
- Final-stage status indicates that the process is close to a human decision; it
  is not itself a hiring recommendation.

## User flow

1. A workspace configures a pipeline with ordered statuses.
2. A job is created with one pipeline.
3. A candidate enters the job and starts in the first pipeline status.
4. The recruiter reviews the candidate in the pipeline or application view.
5. The recruiter explicitly moves the application when the hiring process
   advances or closes.
6. Each move updates the current stage and the time-in-stage clock and may trigger
   the configured stage communication.
7. Operational surfaces highlight candidates waiting beyond the stage's
   configured expectation.
8. If the application enters a terminal status, active recruiting actions stop.
9. A recruiter may deliberately move a terminal application back to an active
   stage when reopening the process is appropriate.

## Acceptance criteria

- **AC01** — A job with existing applications cannot be switched to a different
  pipeline without an explicit migration capability that the current product
  does not provide.
- **AC02** — A new application enters the first configured status of the job's
  pipeline.
- **AC03** — An application cannot be moved to a status from another company or
  another pipeline.
- **AC04** — Moving an application is always initiated by a human-authorized
  workflow action.
- **AC05** — The product distinguishes active, final-stage, hired, and other
  terminal status semantics.
- **AC06** — Time spent in the current stage is measured from the application's
  latest stage entry.
- **AC07** — A stage with an attention threshold can cause an active candidate to
  become operationally overdue after that threshold is exceeded.
- **AC08** — The default Kanban ordering is not based on candidate AI fit.
- **AC09** — Current AI fit can be displayed as context without becoming the
  board's default ranking or automatic stage decision.
- **AC10** — A terminal application does not offer normal active recruiting
  actions until a human reopens it.
- **AC11** — Hired and rejected/closed outcomes remain distinguishable.
- **AC12** — A configured status-entry communication is coupled to the same
  validated stage transition rather than a separate unverified status write.

## Out of scope

- Automatic pipeline advancement based on AI.
- Automatic rejection or hiring.
- Arbitrary workflow automation rules.
- Mapping/migrating existing candidates when changing a job's pipeline.
- A generic task-management system.
- Enterprise workflow builders or approval engines.

## Related feature specs

- `../application-intake/spec.md`
- `../candidate-evaluation/spec.md`
- `../recruitment-attention/spec.md`
- `../interview-scheduling/spec.md`
- `../job-workspace/spec.md`
