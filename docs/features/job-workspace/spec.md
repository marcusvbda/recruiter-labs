---
status: implemented
type: as-built
---

# Job workspace

## Problem

A recruiter working one hiring process needs a single operational surface that
answers:

- what is the state of this job;
- how is hiring progressing;
- what needs attention;
- where are the candidates in the pipeline;
- how are applications and traffic distributed.

Splitting these answers across disconnected admin pages makes the recruiter hunt
for context and encourages dashboard/KPI clutter instead of action.

## Objective

Provide one primary per-job workspace that combines recruitment progress,
attention, pipeline operations, and lightweight job analytics while keeping
candidate movement and hiring decisions human-controlled.

## User behaviour

Opening a job presents a persistent summary of the hiring process and tabs for:

- **Overview** — distribution of applications across the job workflow and core
  job/process context;
- **Pipeline** — the operational Kanban where recruiters review and move
  applications;
- **Analytics** — lightweight traffic/acquisition context for the job.

The persistent workspace summary includes:

- job publication/paused state;
- selected recruitment pipeline;
- application, interviewing, finalist, and hired counts;
- hired progress against the job's hiring target;
- waiting/progress context;
- bounded job-specific attention items.

From the workspace, recruiters can also:

- edit/publish/unpublish the job;
- open the public job page;
- open the pipeline directly;
- add an existing workspace candidate to the job when allowed.

## Business rules

This feature is governed by
`.ai/skills/recruitment-workflow/SKILL.md`.

- The job workspace is the primary operating surface for one hiring process; it
  should not duplicate the global overview as a second card dashboard.
- Progress metrics and attention are different concepts. Metrics describe state;
  attention identifies work.
- Hiring progress distinguishes applications, interviewing candidates,
  finalists, hired candidates, and the configured hiring target.
- Hired progress is shown against the target so the same hired count has the
  right context for different jobs.
- Attention shown here is the job-scoped subset of deterministic recruitment
  attention.
- Pipeline movement remains human-controlled and follows the recruitment
  pipeline invariants.
- Pipeline default ordering remains operational rather than AI-fit-first.
- Adding an existing candidate to the job creates an application in the job's
  initial workflow stage only when workspace/application allowance permits it
  and the candidate is not already in that job.
- Job analytics are lightweight operational/acquisition analytics, not a general
  BI/report builder.
- Traffic/source analytics must not be interpreted as evidence that an individual
  candidate is more qualified.
- Reaching the hiring target remains advisory; the workspace does not
  automatically close or unpublish the job.
- The workspace links to specialized settings/resources when configuration
  belongs elsewhere rather than reimplementing those systems inline.

## User flow

1. A recruiter opens a job from the global recruiting overview or jobs list.
2. The workspace summary immediately shows job state, hiring progress, and
   current job-specific attention.
3. The recruiter uses **Overview** to understand stage distribution and process
   context.
4. The recruiter uses **Pipeline** for day-to-day candidate work, opening
   applications and explicitly moving candidates when appropriate.
5. The recruiter uses **Analytics** to understand job traffic and acquisition
   context such as campaign/UTM performance.
6. The recruiter may edit publication/configuration, open the public page, or add
   an existing workspace candidate when needed.
7. The product continues to derive metrics and attention from current recruitment
   state; it does not create a separate mutable copy of that state for the
   workspace.

## Acceptance criteria

- **AC01** — A job has one primary workspace that exposes summary context plus
  Overview, Pipeline, and Analytics sections.
- **AC02** — The persistent job summary distinguishes metrics from actionable
  attention.
- **AC03** — The summary exposes application, interviewing, finalist, and hired
  progress.
- **AC04** — Hired progress is shown in the context of the job's configured
  hiring target.
- **AC05** — Job-specific attention items use the same deterministic attention
  semantics as the global recruiting overview.
- **AC06** — The Pipeline section is the operational candidate-moving surface and
  does not default to AI-fit-first ordering.
- **AC07** — Opening a candidate from the workspace does not cause an automatic
  stage change or hiring decision.
- **AC08** — An existing workspace candidate can be added to the job only when
  they are not already an applicant and the workspace can receive another
  application.
- **AC09** — A manually added existing candidate enters the first configured
  stage of the job's pipeline.
- **AC10** — The Analytics section remains focused on job traffic/acquisition and
  does not become an individual candidate-quality score.
- **AC11** — Reaching a hiring target is visible but does not automatically close,
  pause, or unpublish the job.
- **AC12** — The workspace can link to the public job page and job/pipeline
  configuration without duplicating their source-of-truth behaviour.

## Out of scope

- General-purpose BI or custom report builders.
- Automatic hiring or pipeline movement.
- A sourcing CRM.
- Agency client/placement management.
- Structured interview feedback.
- Full team/RBAC administration.
- Global company settings that are not specific to the job workflow.

## Related feature specs

- `../recruitment-pipeline/spec.md`
- `../recruitment-attention/spec.md`
- `../candidate-evaluation/spec.md`
- `../application-intake/spec.md`
- `../referrals/spec.md`
