---
status: implemented
type: as-built
---

# Referrals

## Problem

Recruiting teams need to know when an application came through a referral and
which referral link produced it. That provenance is useful for sourcing and
traffic analysis, but it must not be confused with evidence that a candidate is
more qualified.

Referral entry points also need their own availability controls so old,
unpublished, expired, or exhausted links cannot keep accepting applications.

## Objective

Provide bounded referral links tied to a job and workspace user, preserve
referral provenance and clicks/applications, and keep referral status completely
outside candidate evaluation and default ranking.

## User behaviour

A recruiter can manage referral records associated with a job and share the
public referral entry point.

A referral can be:

- published or unavailable;
- optionally time-limited;
- limited to a configured maximum number of applications.

Candidates entering through an available referral see the associated job
application experience. A successful application is visibly attributable to the
referral rather than being treated as direct sourcing.

## Business rules

The evaluation rules in `.ai/skills/evaluation-integrity/SKILL.md` and
recruitment workflow rules in `.ai/skills/recruitment-workflow/SKILL.md` apply.

- A referral belongs to one company, one job, and one workspace user.
- A public referral is usable only while it is published, not expired, associated
  with an active job, and below its application capacity.
- A referral submitted with an application must belong to the same company and
  job being applied to.
- An invalid or unavailable referral cannot be used to create a referred
  application.
- Applications created through a valid referral preserve both referral identity
  and referral source.
- Direct applications remain direct.
- Referral is sourcing provenance only.
- Referral metadata must not be supplied as candidate evidence to the AI
  evaluation.
- Referral does not change criterion scores, fit, confidence, evidence coverage,
  Interview Brief priority, or default candidate ordering.
- Referral click/application activity may support sourcing analytics without
  becoming candidate quality evidence.

## User flow

1. A workspace user creates or receives a referral link for a job.
2. The referral is published and may have expiration/application-capacity
   constraints.
3. A candidate opens the referral entry point.
4. The product verifies that the referral and associated job are still available.
5. The candidate submits the normal job application.
6. The application records referral provenance and enters the same recruitment
   workflow as any other candidate.
7. Candidate evaluation uses the candidate's submitted evidence and confirmed
   job criteria, not referral provenance.
8. Recruiters can still see that the application arrived by referral.

## Acceptance criteria

- **AC01** — A referral is scoped to one company, job, and workspace user.
- **AC02** — An unpublished referral cannot be used as an available public
  referral entry point.
- **AC03** — An expired referral is unavailable.
- **AC04** — A referral that has reached its configured application capacity is
  unavailable for another referred application.
- **AC05** — A referral cannot be applied to a different job or company from the
  one it belongs to.
- **AC06** — A successful application through a valid referral is recorded with
  referral source/provenance.
- **AC07** — A normal application without a valid referral remains direct.
- **AC08** — Referral provenance remains visible to recruiters.
- **AC09** — Referral does not alter candidate fit, evidence coverage,
  confidence, criterion evidence, Interview Brief priority, or default ordering.
- **AC10** — Referral traffic/application activity may be measured without being
  represented as evidence of candidate qualification.

## Out of scope

- Referral rewards, bonuses, payouts, or commissions.
- Employee referral approval workflows.
- Referral-based candidate scoring.
- Preferential ranking for referred candidates.
- Agency/client referral CRM.
- External sourcing marketplaces.

## Related feature specs

- `../application-intake/spec.md`
- `../candidate-evaluation/spec.md`
- `../job-workspace/spec.md`
