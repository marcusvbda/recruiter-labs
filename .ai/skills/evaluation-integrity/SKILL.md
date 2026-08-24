---
name: evaluation-integrity
description: Rules for everything AI-facing in the product — App\Ai\Agents conventions (structured output, TOON context, token efficiency), criteria extraction and human confirmation, candidate evaluation and its revisions, stale evaluations, context sanitization, evidence provenance, fit/coverage/confidence semantics, interview brief, referral neutrality and AI cache/schema versioning. Load before touching any of them.
---

# AI agents and evaluation integrity

Load this skill when the task touches any of:

- `App\Ai\Agents\*`, `App\Ai\Concerns\*` or any Laravel AI SDK usage;
- job criteria extraction, review or confirmation
  (`ExtractJobCriteria`, `ConfirmJobCriteria`, `RequireJobCriteriaReview`);
- candidate evaluation / fit analysis
  (`ScheduleApplicationFitAnalysis`, `AnalyzeApplicationFit`,
  `ReplaceApplicationFitAnalysis`, `Application::hasCurrentEvaluation()`);
- interview brief generation;
- AI context sanitization (`CandidateEvaluationContextSanitizer`);
- AI usage, quota/BYOK, token optimisation or `AiUsageRecord`;
- AI response caching or `CACHE_SCHEMA_VERSION`;
- any copy that describes what the AI evaluation does.

These are integrity and safety invariants: AI proposes, Laravel computes, a
human decides. Never weaken them for convenience, and never let product copy
claim more than the system knows. If a task appears to require breaking one,
surface the conflict instead of resolving it silently.

Related:

- `.ai/skills/recruitment-workflow/SKILL.md` — where evaluation output is
  displayed (Kanban, application workspace, Overview) and the workflow rules
  that own those surfaces.
- `AGENTS.md` — global rules (language, Git, testing, documentation).

## AI agents

Every agent (`App\Ai\Agents\*`, using the Laravel AI SDK) must follow these conventions, established while building `ExtractJobCriteria`, to keep token consumption down without sacrificing output reliability.

**Structured output stays structured.** Always implement `HasStructuredOutput` with a `schema()` method for the response. Never abandon it in favor of asking the model to reply in a custom format (e.g. TOON) and hand-parsing the result — that trades away the provider's schema-enforcement guarantee for a small token saving on the output side, which is rarely worth it. Token efficiency work belongs on the request/context side, where structure isn't load-bearing for reliability.

**Encode the context payload as TOON, not JSON.** Use the `helgesverre/toon` package with `EncodeOptions::compact()`. TOON removes braces, key quoting, and repeated keys in uniform arrays (tabular format), which meaningfully cuts input tokens with no loss of information.

**Reuse `App\Ai\Concerns\BuildsCompactAgentContext`.** Any new agent that builds a context payload should `use` this trait instead of reimplementing the same logic:

- `compactContext(array $data): string` — filters out `null`, `''`, and `[]` values before TOON-encoding, since empty optional fields cost tokens without adding meaning.
- `plainText(?string $html): ?string` — strips HTML from rich-text/`RichEditor` fields. Block boundaries (`</p>`, `</li>`, headings, `<br>`) are replaced with a **space**, not a newline: TOON must escape a raw newline inside a quoted string as a literal `\n`, which costs tokens for zero semantic benefit.

**Curate the context; don't serialize the model.** Only include fields that plausibly inform the agent's specific task. Don't dump a full Eloquent model's attributes into the prompt — e.g. scheduling metadata or file-format constraints that have nothing to do with the task should be left out, not merely reformatted.

**Bound free-text output fields in the schema.** Cap long string fields (e.g. `$schema->string()->max(220)`) and ask for concise phrasing (e.g. "one-sentence reason") in the instructions. This reduces output tokens without touching the structured-output mechanism.

**Keep instructions short.** The system prompt is sent on every call — trim it to the essential task description, format note (if the context encoding needs explaining), and constraints. Avoid restating things the schema already enforces.

**Verify token savings with a real call.** After any token-efficiency change, dispatch a real request against the actual provider (not `Agent::fake()`) and compare the recorded `input_tokens`/`output_tokens`/`total_tokens` (via `AiUsageRecord`) against a baseline. Faked responses don't exercise the real tokenizer, so they can't confirm a token-reduction claim — only a live call can.

## Evaluation integrity

The product's thesis is that a recruiter's judgement, supported by application
evidence, beats a model's opinion. Everything below exists so the product cannot
quietly claim more than it knows. AI proposes; Laravel computes; a human decides.

### Invariants

- **AI proposes evaluation criteria; a human confirms the ones that govern
  candidate evaluation.** A finished extraction lands in
  `JobCriteriaProcessingStatus::AwaitingReview` — editable, clearly labelled as
  an AI suggestion, and authoritative for nothing. `ConfirmJobCriteria` is the
  only path to `Completed`, and it records `criteria_confirmed_generation`,
  `criteria_confirmed_at` and `criteria_confirmed_by_id`. Extraction finishing is
  not criteria being approved.
- **Candidate evaluation cannot run against unconfirmed criteria.** The single
  gate is `Job::hasConfirmedCriteria()` — status *and* confirmed revision — and
  both `ScheduleApplicationFitAnalysis` and `AnalyzeApplicationFit` check it, the
  second because criteria can change between queueing and running. Until then the
  application waits in `ApplicationAnalysisStatus::AwaitingCriteria`, and
  confirming releases it through the existing queue, quota and BYOK path. Never
  bypass those actions.
- **An evaluation belongs to two exact revisions.** The Application analysis
  generation identifies the evaluation request, and the confirmed Job criteria
  generation identifies the semantic criteria snapshot used to build it. Capture
  both before the AI request, then revalidate both inside the persistence
  transaction while locking the Application and Job. A response produced for
  criteria revision X can never be persisted as revision Y, including on a cache
  hit or when every criterion ID is unchanged: IDs guarantee mapping identity;
  the generation guarantees semantic revision identity.
- **Terminal Applications cannot schedule candidate evaluation.** Hired,
  rejected, withdrawn and disqualified Applications keep their historical
  evaluation but expose no start or reprocess action and dispatch no evaluation
  job. A human must first reopen the Application into an active stage.
- **The stored criteria revision identifies the evaluation that produced it.**
  `applications.analysis_criteria_generation` records it, and
  `Application::hasCurrentEvaluation()` is the only way to ask whether a stored
  fit still describes this hiring process. `criteria_generation` doubles as the
  criteria revision: `RequireJobCriteriaReview` advances it, which invalidates an
  in-flight extraction and the recorded confirmation in one write.
- **A stale evaluation is never presented as current.** Scores are not deleted —
  they stop being shown as the answer. Every surface that displays fit (header,
  summary, evaluation tab, Kanban card, candidate view) goes through
  `hasCurrentEvaluation()`, and the evaluation tab renders an explicit
  `outdated` state instead. Reconfirming reschedules in-process applications;
  terminal ones keep their historical evaluation.
- **Only evaluation-relevant job changes cost the confirmation.** Title,
  description, application locale, application questions and cover-letter
  configuration feed the criteria, as do the criteria themselves. Campaign dates,
  paused intake, hiring target and other operational metadata do not.
  `EditJob` snapshots those inputs around the save and only then calls
  `RequireJobCriteriaReview` — never increment on every edit, or recruiters learn
  to click through the confirmation without reading it.
- **Direct candidate identifiers are removed from the AI evaluation context.**
  `CandidateEvaluationContextSanitizer` deterministically redacts the stored
  name (and its word-bounded parts), email, phone and social profiles, plus any
  email address, `+`-prefixed or parenthesised phone number, and known
  personal-profile URL, into `[redacted-name]` / `[redacted-email]` /
  `[redacted-phone]` / `[redacted-profile]`. `candidate_name` is not in the
  payload at all, and neither is referral or any other sourcing metadata.
- **Identity reduction is never marketed as anonymity.** Employers,
  institutions, technologies, projects, titles, dates, tenure, qualifications and
  numbers stay — they are the evidence. Never build a protected-characteristic
  detector or infer sensitive attributes. Copy may say only that direct candidate
  identifiers are excluded from the AI evaluation context; never "anonymous",
  "unbiased", "bias-free", "objective" or "legally compliant". Candidate identity
  stays fully visible to the human recruiter everywhere else.
- **Unknown evidence produces a null fit, never an invented penalty.**
  `application_criterion_scores.score` is nullable: null means the application
  did not support a judgement, and it is not 0, not 50, not a failure. Nothing
  may substitute a number after the model returned unknown, and a null score is
  normalised to low confidence.
- **Overall fit excludes unknown criteria.** It is the weighted average over
  criteria with a non-null score only — unknowns are in neither numerator nor
  denominator. When every criterion carries zero weight each counts once.
- **Evidence coverage is separate from fit, and confidence from both.**
  `applications.analysis_coverage` is assessed weight over total weight, 0-100.
  Display fit and coverage as two figures; never merge them into a
  "confidence-adjusted fit", never colour fit as pass/fail, and never turn
  coverage into a second ranking metric.
- **Confidence means strength of support in the submitted material** — not
  probability of a good hire, not external verification, not statistical
  certainty. The product verifies nothing against the outside world, so copy uses
  "supported by application evidence", never "verified" or "proven".
- **Each criterion result carries structured provenance.**
  `application_criterion_scores.evidence` holds at most three
  `{source, detail}` items, `source` being a `CriterionEvidenceSource`
  (`resume`, `cover_letter`, `application_answer`). Evidence comes only from the
  sanitized context, is dropped when the criterion could not be assessed, and is
  never an external-verification claim.
- **AI output maps to `JobCriterion` by ID.** The context carries
  `criterion_id`; `ReplaceApplicationFitAnalysis` reads the authoritative
  criterion text and weight from the current record, validates that every current
  criterion appears exactly once with nothing unknown, missing or duplicated and
  nothing from another tenant, and fails the execution otherwise. There is no
  fallback weight and no string matching — both silently invent an assessment.
  Interview-brief items reference criteria by ID too.
- **Interview Brief prioritises important uncertainty, not low scores.** Weight,
  unknown scores, low or medium confidence and weak or conflicting evidence drive
  priority. Fit 95 with low confidence can outrank fit 35 with high confidence.
  Max six items, practical and non-leading, never about protected
  characteristics.
- **Referral is sourcing metadata and nothing else.** It never reaches the
  evaluation agent, never enters fit or coverage, carries no bonus, and never
  changes candidate order. It stays visible as sourcing context.
- **AI fit is never a default operational priority.** The Kanban orders by
  `status_entered_at` ascending — longest waiting first, which within a column
  (one status, one threshold) puts the genuinely overdue at the top — then
  `created_at`, then `id`. Ordering candidates by `analysis_score` is an
  automated hiring recommendation wearing a layout's clothes. Do not add a
  sort-by-fit control.
- **Candidate Evaluation, Interview Brief and per-criterion evidence are one AI
  execution.** Do not add an agent, an execution, or a separate anonymisation
  call — redaction is deterministic Laravel code. When the request or response
  contract changes, bump the agent's `CACHE_SCHEMA_VERSION` so old cached
  responses cannot be consumed, and fingerprint the sanitized context, never the
  identity-bearing original.
- **Next-action guidance is workflow-safe.** "No interview exists" is not
  evidence that an interview is the next step: workspaces run their own stages
  (Applied, Screening, Assessment, Manager Review), so the generic fallback is
  `review_candidate`, with stage movement leading. A terminal application offers
  no interview scheduling at all, primary or overflow — reopening it into an
  active stage is what makes recruiting actions available again.

