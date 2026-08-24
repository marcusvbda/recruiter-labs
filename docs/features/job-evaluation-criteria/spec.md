---
status: implemented
type: as-built
---

# Job evaluation criteria

## Problem

Candidate evaluation is only defensible when the hiring team owns the criteria
used to assess applicants. AI can help extract a useful starting point from a
job, but an AI-generated list must not silently become the company's hiring
policy.

Criteria can also change over time. Candidate evaluations must not remain
current when they were produced against a superseded definition of the role.

## Objective

Let AI propose evaluation criteria and job-review guidance while requiring a
human workspace member to review and explicitly confirm the criteria that govern
candidate evaluation.

Treat the confirmed criteria set as a versioned product contract for the job.

## User behaviour

The recruiter can:

- request AI-assisted criteria extraction for a job;
- review the suggested criteria and their weights;
- review separate Job Review guidance;
- edit the criteria before they become authoritative;
- explicitly confirm the criteria that will be used for candidate evaluation;
- rerun extraction when a new suggestion is wanted;
- see when criteria require review again after an evaluation-relevant change.

Applications wait instead of being evaluated while the current criteria revision
has not been confirmed.

## Business rules

This feature is governed by
`.ai/skills/evaluation-integrity/SKILL.md`.

- AI extraction produces suggestions, not approved hiring criteria.
- A completed extraction enters a review state and does not release candidate
  evaluation by itself.
- A persisted, authenticated user who belongs to the job's workspace must perform
  confirmation.
- Confirmation applies to one exact criteria revision.
- Candidate evaluation can run only while the current criteria revision is the
  confirmed revision.
- Evaluation-relevant changes invalidate the previous confirmation and require a
  new human review.
- Operational job changes that do not alter what a candidate is evaluated
  against must not unnecessarily invalidate criteria confirmation.
- Changes to the job definition, application questions, cover-letter context, or
  the criteria themselves may make the confirmed evaluation contract stale.
- Existing in-process applications that are waiting for criteria, or whose
  evaluation belongs to an older criteria revision, can be released through the
  normal evaluation path after reconfirmation.
- Terminal applications preserve historical evaluations and are not
  automatically re-evaluated when criteria change.
- A candidate evaluation records the exact confirmed criteria revision it used.
- Job Review guidance is advisory recruiting guidance. It is not candidate
  scoring, legal review, or an automated hiring decision.
- Rerunning AI extraction does not silently overwrite the meaning of a currently
  running evaluation; revision integrity still applies.

## User flow

1. A recruiter creates or edits a job.
2. The recruiter requests AI-assisted criteria extraction.
3. The system produces suggested criteria plus Job Review guidance.
4. The criteria remain in a review-required state.
5. The recruiter inspects and may edit criterion wording and weights.
6. The recruiter confirms the current criteria revision.
7. Waiting eligible applications can proceed through the normal candidate
   evaluation path.
8. If an evaluation-relevant job input changes later, the criteria contract is
   marked as needing review again.
9. Existing evaluations against the previous revision stop presenting themselves
   as current.
10. After a human confirms the new revision, eligible active applications can be
    evaluated against it.

## Acceptance criteria

- **AC01** — Finishing AI criteria extraction does not, by itself, authorize
  candidate evaluation.
- **AC02** — Suggested criteria are available for human review and editing before
  confirmation.
- **AC03** — Confirmation requires a real user who belongs to the job's
  workspace.
- **AC04** — The product can distinguish the current criteria revision from the
  revision that was last confirmed.
- **AC05** — Applications waiting for criteria remain unevaluated until the
  current criteria revision is confirmed.
- **AC06** — Confirming current criteria releases eligible in-process
  applications through the existing candidate-evaluation workflow rather than a
  separate scoring path.
- **AC07** — Changing the meaning or weight of confirmed evaluation criteria
  invalidates the previous confirmation.
- **AC08** — Evaluation-relevant job changes can require criteria review, while
  unrelated operational changes do not invalidate confirmation merely because
  the job was saved.
- **AC09** — An application evaluation produced against an older criteria
  revision cannot be presented as current after the job contract changes.
- **AC10** — Reconfirming a new revision can refresh active stale evaluations
  without automatically spending AI allowance on terminal applications.
- **AC11** — Job Review guidance remains separate from candidate evaluation and
  does not directly change candidate fit.
- **AC12** — The system never has to pretend that legacy AI-generated criteria
  were historically human-confirmed when no such confirmation exists.

## Out of scope

- Automatic approval of AI-generated hiring criteria.
- Legal or regulatory certification of a job description.
- A full visual history/diff viewer for every criteria revision.
- Structured interview feedback.
- Automatic criteria optimization based on hiring outcomes.
- Automatic changes to pipeline stages or hiring decisions.

## Related feature specs

- `../candidate-evaluation/spec.md`
- `../application-intake/spec.md`
- `../job-workspace/spec.md`
