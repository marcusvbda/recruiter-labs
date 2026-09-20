---
status: implemented
type: as-built
---

# Job evaluation criteria

## Problem

Candidate evaluation is only defensible when the hiring team owns the criteria
used to assess applicants. AI can prepare a useful set of criteria from a job,
but the recruiter must always be able to see that the criteria are AI-generated,
edit them, and rebuild them.

Making a recruiter click a routine "confirm" button before the product can do its
normal work is not human judgment; it is friction. Human ownership is preserved
through visible provenance, immediate editing rights and an explicit rebuild
action, not through a gate.

Criteria can also change over time. Candidate evaluations must not remain
current when they were produced against a superseded definition of the role.

## Objective

Prepare evaluation criteria automatically from substantive role context, make
them the job's current criteria as soon as preparation succeeds, and keep the
recruiter in control by letting them edit, rebuild or recover the criteria at any
time.

Treat the current criteria set as a versioned product contract for the job.

## User behaviour

The recruiter can:

- receive an initial AI-generated criteria set automatically when a newly created
  job has substantive role context;
- add substantive role context to an initially incomplete job and receive that
  first set automatically;
- see the criteria clearly identified as AI-generated, with their weights;
- edit criterion wording, add or remove criteria and change weights; a saved
  edit is authoritative immediately;
- explicitly rebuild the criteria when a fresh AI proposal is wanted (replacing
  the current set requires an explicit confirmation of the replacement, not of
  the criteria);
- retry after a failed or allowance-blocked preparation;
- see an actionable state when the job lacks the context needed to prepare
  criteria.

There is no separate "Confirm criteria" step. Applications wait instead of being
evaluated only while the job has no current criteria (preparation has not
finished, failed, is blocked, or lacks context).

## Business rules

This feature is governed by
`.claude/skills/evaluation-integrity/SKILL.md`.

- Successful AI extraction makes the extracted criteria the job's current
  criteria at once. The activation is recorded against the exact criteria
  revision (generation) and time, attributed to the system rather than a user.
- Preparation is only started from substantive role context. When context is
  missing, no generic criteria are invented and the job shows an actionable
  missing-context state.
- When preparation fails or is blocked by AI allowance, the failure is visible,
  retry remains available, and no criteria are activated; applications keep
  waiting and are never given fabricated evaluations.
- Candidate evaluation can run only while the job has current criteria for the
  current revision.
- A saved human edit to the criteria (or an evaluation-relevant change to the job
  definition, application questions or cover-letter context) advances the
  criteria revision. Existing criteria are kept, not regenerated or overwritten,
  and the new revision is current immediately.
- Operational job changes that do not alter what a candidate is evaluated against
  (campaign dates, paused intake, hiring target and similar) do not advance the
  revision.
- Evaluations produced against an older revision stop presenting themselves as
  current. Eligible in-process applications, and those still waiting for
  criteria, are re-evaluated automatically through the normal evaluation path.
- Terminal applications preserve their historical evaluation and are not
  re-evaluated, and consume no AI allowance, when criteria change.
- An in-flight extraction or evaluation produced for a superseded revision is
  never persisted as current; the current revision wins.
- A candidate evaluation records the exact criteria revision it used.
- Rerunning AI extraction (Rebuild) does not silently overwrite the meaning of a
  currently running evaluation; revision integrity still applies.
- The separate Job Review advisory card is no longer part of the recruiter
  experience. The extraction may still produce review alerts, but nothing shows
  them as advisory content, and they never affect candidate evaluation.
- Job revisions stored before automatic activation and left awaiting human review
  remain a legacy state until the criteria are rebuilt.

## User flow

1. A recruiter creates a job with substantive role context, or adds that context
   to an otherwise untouched initial job.
2. The system automatically starts the first AI criteria preparation.
3. On success the criteria become current, identified as AI-generated and
   editable; waiting eligible applications are released into the normal
   candidate evaluation path.
4. The recruiter may edit the criteria; the saved edit is current immediately and
   eligible active applications are re-evaluated against it.
5. If an evaluation-relevant job input changes later, the criteria revision
   advances, prior evaluations stop presenting as current and eligible active
   applications are re-evaluated; the recruiter's criteria are kept.
6. If preparation fails, lacks context or is blocked by allowance, the job shows
   why and offers Generate / Retry; nothing is activated meanwhile.
7. The recruiter may rebuild the criteria at any time to get a fresh AI set.

Automatic first preparation is idempotent from the recruiter's perspective. It
does not run for title-only jobs, copied or existing criteria, a repeated save,
or a page refresh. Explicit regeneration remains available for recovery and for
requesting a new set; it is not the normal first-generation path.

## Acceptance criteria

- **AC01** — Finishing AI criteria extraction makes the extracted criteria the
  job's current criteria without a separate confirmation action.
- **AC02** — Current criteria are identified as AI-generated and remain editable
  by an authorized recruiter.
- **AC03** — A saved human edit is authoritative immediately, with no second
  confirmation action.
- **AC04** — The product can distinguish the current criteria revision from the
  revision an evaluation was produced against.
- **AC05** — Applications waiting for criteria remain unevaluated until the job
  has current criteria, and are released automatically when it does.
- **AC06** — Activating or changing criteria releases eligible in-process
  applications through the existing candidate-evaluation workflow rather than a
  separate scoring path.
- **AC07** — Changing the meaning or weight of criteria advances the revision, so
  the previous evaluations are no longer current.
- **AC08** — Evaluation-relevant job changes advance the revision, while
  unrelated operational changes do not merely because the job was saved.
- **AC09** — An application evaluation produced against an older criteria
  revision cannot be presented as current after the revision changes, including
  when a stale in-flight result finishes late.
- **AC10** — Changing criteria refreshes active stale evaluations without
  spending AI allowance on terminal applications.
- **AC11** — Missing role context produces an actionable state and no invented
  criteria; a failed or blocked preparation activates no criteria and offers
  recovery.
- **AC12** — Later job edits never silently replace recruiter-edited criteria.
- **AC13** — Explicit Rebuild criteria remains available.
- **AC14** — The system never claims that legacy AI-generated criteria were
  historically reviewed by a human when no such review exists.

## Out of scope

- Legal or regulatory certification of a job description.
- A full visual history/diff viewer for every criteria revision.
- Structured interview feedback.
- Automatic criteria optimization based on hiring outcomes.
- Automatic changes to pipeline stages or hiring decisions.
- Autonomous rewriting of the job description.

## Related feature specs

- `../candidate-evaluation/spec.md`
- `../application-intake/spec.md`
- `../job-workspace/spec.md`
- `../core-recruiting-productivity/spec.md`
