---
status: implemented
type: as-built
---

# Candidate evaluation

## Problem

High application volume makes it difficult for recruiters to review candidates
consistently. Polished CVs and application answers can make weak evidence look
strong, while missing information can be mistaken for negative evidence.

Recruiter Labs needs to help a recruiter understand how the submitted
application relates to the job without pretending that the system knows more
than the application proves.

## Objective

Provide an AI-assisted, evidence-backed evaluation of a candidate against the
job's human-confirmed criteria while keeping fit, uncertainty, evidence coverage,
and the eventual hiring decision separate.

The evaluation should answer:

- what the submitted application supports;
- what fit can reasonably be assessed;
- what remains unknown or weakly supported;
- what should be validated in an interview.

The recruiter remains responsible for every hiring decision.

## User behaviour

When a current evaluation is available, the recruiter can see:

- overall application fit;
- evidence coverage;
- one result per confirmed job criterion;
- the criterion's fit when it can be assessed;
- confidence in the submitted support;
- concise supporting evidence and its application source;
- criteria that still need validation;
- an Interview Brief focused on important uncertainty.

When the evaluation is waiting, processing, blocked, failed, or outdated, the
product shows that state instead of presenting an old or incomplete score as
current.

Candidate identity remains visible to the recruiter in the normal candidate and
application experience. Identity reduction applies to the AI evaluation context,
not to the recruiter-facing record.

## Business rules

This feature is governed by
`.ai/skills/evaluation-integrity/SKILL.md` and the recruitment rules in
`.ai/skills/recruitment-workflow/SKILL.md`.

- Candidate evaluation runs only against the job's currently confirmed
  evaluation criteria.
- Direct candidate identifiers are excluded from the AI evaluation context where
  the product can deterministically identify them. This is identity reduction,
  not a claim of full anonymity or bias elimination.
- Candidate-controlled text is evidence to assess, not authority that can change
  the evaluation rules.
- Application source and referral provenance do not affect fit, evidence
  coverage, confidence, Interview Brief priority, or default candidate ordering.
- A criterion fit is numeric only when the submitted application contains enough
  information to assess that criterion.
- Missing information is represented as unknown. It does not become a zero,
  midpoint, failure, or other invented fit signal.
- Overall fit is based only on criteria that could be assessed and respects the
  confirmed criterion weights.
- Evidence coverage separately communicates how much of the weighted criteria
  could actually be assessed.
- Confidence describes the strength and specificity of support in the submitted
  material. It is not hiring probability, fit, external verification, or a
  statistical confidence value.
- Supporting evidence identifies the submitted source that supports a criterion
  result. Candidate claims are not represented as independently verified facts.
- Each evaluation result maps to the exact confirmed criterion set. Criterion
  identity and criteria revision both have to match.
- An evaluation produced for an older criteria revision is historical and cannot
  be presented as the current evaluation.
- Cached and fresh AI responses are subject to the same revision and persistence
  integrity rules.
- Interview Brief items prioritize important uncertainty and evidence gaps, not
  simply the lowest fit scores.
- AI evaluation never hires, rejects, advances a candidate, or otherwise performs
  a recruitment decision.
- Terminal applications keep historical evaluation data but cannot start or
  reprocess candidate evaluation until a human reopens the recruitment process.
- Default operational ordering must not become "highest AI fit first."

## User flow

1. A job has a human-confirmed set of evaluation criteria.
2. A candidate submits an application, or an existing candidate enters the job's
   active recruitment process.
3. If current criteria are confirmed, the application is queued for evaluation.
   Otherwise it waits for criteria confirmation.
4. Direct identifiers are reduced from the candidate material before the AI
   evaluation context is built.
5. The submitted CV, cover letter, and application answers are assessed against
   the confirmed criteria.
6. The product persists one strict result per criterion, supporting evidence, and
   an Interview Brief.
7. Laravel derives overall fit and evidence coverage from the validated criterion
   results.
8. The recruiter reviews the evaluation as decision support.
9. If job criteria change, the prior evaluation stops presenting itself as
   current until a new evaluation is completed against the newly confirmed
   revision.
10. Human workflow actions remain independent from the AI result.

## Acceptance criteria

- **AC01** — An application cannot receive a current candidate evaluation while
  the job's evaluation criteria are unconfirmed.
- **AC02** — The AI evaluation context does not intentionally include the
  candidate's stored name, email address, phone number, or stored social profile
  identifiers.
- **AC03** — Referral or source metadata does not influence the candidate
  evaluation.
- **AC04** — A criterion with insufficient application evidence can be represented
  as unknown rather than receiving an invented numeric score.
- **AC05** — Unknown criteria do not lower the overall fit calculation.
- **AC06** — Evidence coverage is shown separately from fit and decreases when
  weighted criteria cannot be assessed.
- **AC07** — Confidence remains separate from both fit and evidence coverage.
- **AC08** — An assessed criterion can expose concise supporting evidence with a
  source from the submitted application.
- **AC09** — Candidate evaluation results must match the complete confirmed
  criterion set; missing, duplicate, or foreign criterion identities are not
  silently accepted.
- **AC10** — A result created for criteria revision X cannot become a current
  evaluation for revision Y, even when criterion identities remain unchanged.
- **AC11** — A stale completed evaluation is not shown as the current fit for the
  job.
- **AC12** — Interview Brief items can prioritize unknown or weakly supported
  important criteria even when the candidate's assessed fit is high.
- **AC13** — Candidate evaluation does not automatically change pipeline stage,
  reject, hire, or otherwise make the recruitment decision.
- **AC14** — A terminal application cannot spend AI allowance on a new candidate
  evaluation until a human reopens it into an active stage.
- **AC15** — The product does not describe the candidate evaluation as
  "unbiased", "bias-free", "objective", or externally verified when those claims
  are not established.

## Out of scope

- Structured human interview feedback and post-interview evidence capture.
- Interview recording, transcription, AI notetaking, or voice analysis.
- Automatic recalculation of a final hiring score from interview evidence.
- Automatic hiring recommendations, rejection, or pipeline advancement.
- AI-writing detection or penalties for suspected AI-authored applications.
- Protected-characteristic inference or a claim of complete anonymization.
- Default candidate ranking by AI fit.
- External verification of candidate claims.

## Related feature specs

- `../job-evaluation-criteria/spec.md`
- `../application-intake/spec.md`
- `../recruitment-pipeline/spec.md`
- `../interview-scheduling/spec.md`
- `../ai-usage-and-limits/spec.md`
