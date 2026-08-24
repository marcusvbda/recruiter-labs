---
status: implemented
type: as-built
---

# Application intake

## Problem

A public job application must collect usable candidate information, respect the
job's configured requirements and capacity, and enter the correct tenant and
recruitment workflow safely.

The intake path also needs to preserve source attribution and candidate evidence
without letting a public request bypass job availability, plan limits, or
workflow integrity.

## Objective

Provide a public, localized application flow that validates job availability,
collects the configured candidate material, prevents duplicate applications to
the same job, records provenance, and creates the application in the correct
initial recruitment stage.

## User behaviour

A candidate can view a published job and submit:

- name and contact information;
- a CV;
- a cover letter when the job supports or requires one;
- answers to the job's configured application questions.

The public flow supports the job's application locale and can preserve referral
and campaign attribution.

After successful submission, the application enters the job's recruitment
process. Candidate evaluation is scheduled only when the job has current
human-confirmed criteria; otherwise the application waits for that confirmation.

## Business rules

The recruitment workflow rules in
`.ai/skills/recruitment-workflow/SKILL.md` and evaluation rules in
`.ai/skills/evaluation-integrity/SKILL.md` apply after intake.

- A job can receive a public application only while it is published, not paused,
  and inside its configured application window.
- Workspace application allowance and the job's own application limit are
  enforced before accepting a new application.
- Public submission is rate-limited and validated server-side.
- Candidate identity is scoped to the company. An existing candidate with the
  same normalized email can be reused within that workspace rather than creating
  an unrelated duplicate identity.
- The same candidate cannot submit a second application to the same job.
- A new application enters the first status of the job's configured pipeline.
- Submitted answers may only target questions that belong to the current job.
- The question wording is preserved with the submitted answer so later edits to
  the job do not rewrite what the candidate originally answered.
- The submitted CV is part of the application record. A file-based cover letter
  is stored when that is the configured cover-letter mode.
- Source is either direct or referral according to a valid referral used for the
  job. Source remains provenance, not candidate evidence.
- Campaign/UTM parameters submitted with the application are preserved as
  acquisition attribution.
- Candidate-evaluation scheduling uses the normal confirmed-criteria gate. Intake
  does not create a separate or privileged AI path.
- Failure during the application transaction must not leave a partially accepted
  application that appears complete.

## User flow

1. A candidate opens a public job page or a valid referral entry point.
2. The page presents the job's configured application fields and requirements in
   the job's application locale.
3. The candidate submits identity/contact details, CV, optional or required
   cover-letter material, and configured answers.
4. The server revalidates that the job can still receive applications and has
   available workspace/job capacity.
5. The product resolves the candidate identity within the company and rejects a
   duplicate application to the same job.
6. A new application is created in the job pipeline's initial status with source
   and attribution preserved.
7. Documents and question answers are associated with that application.
8. Candidate evaluation is scheduled when current criteria are confirmed;
   otherwise the application waits for criteria confirmation.
9. The candidate receives a successful-submission response only after the intake
   transaction succeeds.

## Acceptance criteria

- **AC01** — A draft, paused, not-yet-open, or ended job cannot accept a normal
  public application.
- **AC02** — Workspace application limits and the job's configured application
  limit can block a submission before a new application is accepted.
- **AC03** — A public application submission is server-side validated and
  rate-limited.
- **AC04** — A candidate identity is resolved within the correct company and is
  not shared across tenants.
- **AC05** — The same candidate cannot have two applications for the same job
  through normal intake.
- **AC06** — A successful application starts in the first configured status of
  the job's pipeline.
- **AC07** — Submitted question answers can only belong to the job being applied
  to, and the submitted question context remains understandable after later job
  edits.
- **AC08** — The submitted CV is associated with the resulting application, and
  cover-letter material follows the job's configured mode.
- **AC09** — A valid referral is recorded as application provenance; a direct
  application remains direct.
- **AC10** — Referral/source attribution does not alter candidate evaluation.
- **AC11** — UTM/campaign attribution can be retained with the application.
- **AC12** — An application created before criteria confirmation waits rather
  than receiving an evaluation against unconfirmed criteria.
- **AC13** — A failed multi-step submission does not intentionally leave stored
  documents representing a completed application when the application itself was
  not accepted.

## Out of scope

- External job-board posting or application ingestion.
- Candidate sourcing or talent scraping.
- Multiple applications by the same candidate to the same job.
- Automatic rejection or ranking at submission time.
- Candidate account/login portal.
- Structured post-interview feedback.
- Referral rewards or compensation.

## Related feature specs

- `../job-evaluation-criteria/spec.md`
- `../candidate-evaluation/spec.md`
- `../recruitment-pipeline/spec.md`
- `../referrals/spec.md`
- `../ai-usage-and-limits/spec.md`
