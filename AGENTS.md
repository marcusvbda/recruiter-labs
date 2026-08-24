# recruiter-labs

## Language

All project text must be in English: code comments, commit messages, PR descriptions, `CLAUDE.md`/`AGENTS.md` files, subagent definitions, docs, and UI copy unless a specific feature explicitly requires localization. Do not write Portuguese in any file in this repository.

## Stack

- **Backend**: Laravel (v13+) + Filament PHP (v5+) — admin/CRUD
- **Frontend**: Inertia.js (v2+) + React (v19+, with **React Compiler** enabled) — pages outside Filament's scope
- **Styling**: Tailwind CSS (v4+)
- **Client-side data fetching**: TanStack React Query (v5+) — only when needed (polling, refetch, client-side cache); the default flow is Inertia props from the controller
- **Architecture**: single monolith (no separate API/SPA)

Versioning guideline: always install/use the latest stable version of each dependency at setup time (`composer require`/`npm install` without pinning old versions). When running `composer create-project`/`npm create`, confirm the resolved version before proceeding, since newer releases may exist beyond this assistant's knowledge.

React Compiler: enable via `babel-plugin-react-compiler` (or the equivalent Vite config) from the start of the frontend setup — do not write manual `useMemo`/`useCallback` speculatively, since the compiler already handles that optimization.

## Agent orchestration

This project uses specialized subagents defined in `.claude/agents/`. The main agent (you, on the root thread) acts as **orchestrator**: it breaks down the task, delegates to the right subagent via the `Agent` tool, and runs verification loops (implement → review → fix) before considering anything done.

Available subagents:

- `laravel-backend` — models, migrations, services, jobs, form requests, backend business rules.
- `filament-admin` — Filament resources, panels, actions, widgets.
- `inertia-frontend` — React/Inertia pages outside Filament's scope, Tailwind components, React Query integration.
- `code-reviewer` — reviews diffs before considering a task done.
- `qa-tester` — tests (Pest/PHPUnit), edge cases, regressions.

Usage rule: for any non-trivial task, delegate to the matching domain subagent instead of implementing everything on the main thread. Use `code-reviewer` as a verification step before finalizing relevant changes.

## Git rules

Never create branches, commit, or push unless explicitly asked to in that specific message. This applies to every agent (main thread and subagents) — do not commit as a side effect of "finishing" a task.

## Testing

Do not write or modify tests (Pest/PHPUnit) unless the user explicitly asks for them in that specific message. This overrides the Laravel Boost "Test Enforcement" rule below and the `qa-tester` subagent's default usage — implementing a feature or fix is done once it works and passes existing tests, not once new tests exist for it. This applies to every agent (main thread and subagents), including `code-reviewer`, which should not flag missing test coverage as a blocker.

## Plan documents

When a task involves generating an implementation plan `.md` file (e.g. via a planning skill) in any folder, delete that plan file once the plan has been fully executed. Plan files are working scaffolding for the session, not project documentation — they should not end up versioned in the repo.

## Conventions

- Keep Inertia page files focused on page-level rendering and component composition. Move reusable or substantial UI into `resources/js/components`.
- When a file needs small local React components, declare them as arrow functions. The main Inertia page component must be declared as a default exported function.

## Information architecture

The product is organised around the recruiter's real questions, not around the
order features were built in. Every new screen must land in the place that
answers the question it belongs to. Do not reintroduce a "Recruitment"
navigation group: the whole product is recruitment.

| Question | Where it is answered |
| --- | --- |
| What needs my attention? | Overview (`App\Filament\Pages\Dashboard`) |
| How are my processes progressing? | Jobs list |
| Where are candidates in *this* hiring process? | Job workspace (`ViewJob`) |
| What do I know about this person, and what happens next? | Application workspace (`ViewApplication`) |
| Who is this person, across processes? | Candidate view (`ViewCandidate`) |
| What are my commitments? | Calendar |
| How is my workspace configured? | Settings cluster |

### Invariants

- **Operational pages belong in primary navigation; configuration belongs in
  Settings.** Primary navigation is `Overview, Jobs, Candidates, Calendar,
  Referrals, Settings` and should stay roughly that short. Anything a recruiter
  configures once and then forgets goes into
  `App\Filament\Clusters\Settings` (account, workspace, hiring workflows,
  integrations, AI, plan) — never into the sidebar as a top-level item.
- **Reusable pipeline definitions are configuration; a job's Kanban is
  operational.** `PipelineResource` is clustered under Settings and is called
  *hiring workflow* in UI copy. The word *pipeline* is reserved for the live
  board of candidates inside a job. Never render the Kanban in Settings, and
  never manage stages from the job workspace.
- **Job progress must be visible without opening every job.** The jobs list
  shows applications / interviewing / finalists / hired per row via
  `JobProgressColumn`. Any new progress signal is added there and to
  `RecruitmentProgressService`, which is the single source of truth for those
  counts — do not recompute them ad hoc in a page or widget.
- **An active hiring process and a job accepting applications are different
  questions.** `Job::scopeCurrentlyActive()` means published and inside its
  campaign window; it deliberately ignores `applications_paused`, because a
  paused job still has candidates being interviewed. Only
  `Job::acceptsApplications()` — and copy that literally promises new
  submissions — respects the pause. Never label a `currentlyActive()` count as
  "open for applications".
- **Overview metrics all use the same active-job scope.** Active applications,
  interviewing, finalists and hired on the Overview count only applications
  belonging to currently active jobs, so a hire from a campaign that ended
  months ago cannot inflate "what is happening now". Historical figures belong
  on the job's own analytics, not there.
- **Interviewing means a pending commitment, not interview history.** An
  application is interviewing when it has a non-cancelled interview that has not
  finished yet (upcoming or running). An interview that already ended does not
  keep a candidate in the metric, and hired or otherwise terminal applications
  never count. The definitions live in `Application`'s scopes (`inProcess`,
  `interviewing`, `inFinalStage`, `hired`) and `Interview::scopeUpcoming()`;
  every surface — job relations, Overview, jobs table, filters — composes those
  instead of rewriting the condition.
- **Late-stage and closing stages are explicit, never inferred from a stage
  name.** `Status` carries three persisted flags: `is_final_stage` (close to a
  decision), `is_hired` (the definitive positive outcome) and `is_terminal` (the
  process ended, whether hired, rejected, withdrawn or disqualified). `is_hired`
  is always a terminal outcome. Only four combinations are valid — intermediate,
  late stage, hired, closed — and `Status` normalises on save to keep it that
  way (hired implies terminal; terminal is never a finalist stage). Do not
  pattern-match on names such as "Offer" or "Rejected" at runtime, do not grow
  this into a stage taxonomy, and configure the flags in workflow provisioning
  (`ProvisionDefaultPipeline`, seeders) instead.
- **An application's current stage must always be immediately visible.** It is
  the first thing in the application header, above fit, evidence and any
  interview. The evaluation's *processing* state is background-job status and
  must never be more prominent than the stage.
- **Next-action guidance stops at terminal outcomes.** The application summary
  suggests the recruiter's likely next step; for a hired application it shows
  the hire, for any other terminal stage it shows the process as closed. It must
  never propose scheduling an interview for a candidate who was rejected,
  withdrew or was disqualified. It stays guidance — never automate a
  recruitment decision.
- **Candidate Evaluation is contextual evidence, not the primary workflow
  state.** It informs the recruiter; the pipeline stage is what the product
  tracks. Keep the evaluation inside its own tab and summarise it, at most, as
  a score plus what still needs validation.
- **The Overview is operational, not a welcome page.** No greeting, clock,
  workspace identity or decorative hero. Every element answers "what is
  happening in recruiting right now?" and links into the page where the work
  happens.
- **Calendar is operational; calendar integration is configuration.** The
  agenda page stays in primary navigation; connecting Google Calendar stays in
  Settings → integrations. Do not merge them.
- **Overview interviews are personal; the calendar is operational.** The
  Overview's agenda shows only interviews owned by the authenticated recruiter's
  calendar account — never another recruiter's commitments presented as theirs.
  Company-wide visibility and recruiter filters stay on the Calendar page, under
  its existing authorization rules. The Overview agenda is a compact preview of
  the recruiter's next commitments (grouped by day, one timezone stated once);
  the Calendar page remains the full operational view. Do not grow the agenda
  into a second calendar, and do not present it as an admin table.
- **Interview scheduling is idempotent per scheduling request.** Each opened
  scheduling form carries a UUID `schedule_request_key`, persisted on the
  interview and unique in the database. Replaying that request (double click,
  retry) reuses the interview it already created — one interview, one calendar
  event, one Meet room, one notification — while a new request key still books
  an additional, intentional interview. Idempotency belongs to the request, not
  to the application, and must not weaken the existing deterministic calendar
  event ID, locking or sync-recovery behaviour.
- **Interview scheduling defaults to the agenda's timezone.** The scheduling
  form defaults to `session('agenda.timezone')` — the timezone the Calendar page
  resolved from the browser — and falls back to `config('app.timezone')`. The
  field stays explicit and editable, the selected timezone is what the chosen
  date and time mean, and future-time and IANA validation stay in the domain.
  Do not build a user timezone settings subsystem.
- **One canonical location per piece of information.** Contextual summaries
  that link to the canonical page are fine; the same block of facts repeated in
  a header, a tab and a card is not. When adding information, first check
  whether it already exists somewhere and link instead.
- **Application tab order follows recruiter decision-making**: Summary,
  Evaluation, Interviews, Application, Documents. Tabs use explicit `id()`/
  `key()` values so `?section=` deep links keep working; keep them stable.
- **Reuse the existing Filament vocabulary.** New surfaces use existing
  resources, widgets, sections and badges. Do not introduce a new design
  system, and do not turn every metric into a card. Dark mode stays disabled.

## Workflow-driven, not CRUD-driven

The product conducts the recruiter through the process; it does not merely store
recruitment data. Every screen should answer *what should I do next, and where do
I do it* — a page that only exposes an entity has not finished its job.

### Invariants

- **The Overview is a work queue, not a metrics dashboard.** Region order is the
  hierarchy: attention, then the recruiter's own commitments, then active-process
  health, then totals last. Metrics never outrank the queue.
- **Attention items are derived, never persisted.** They come from
  `RecruitmentAttentionService`, exist exactly as long as the state that produced
  them, and have no task table, no dismiss flag and no owner. Do not add a
  generic task/todo/reminder subsystem.
- **Every attention item is explainable and actionable.** It carries a concrete
  reason in the recruiter's terms ("waiting 6 days in Screening — this stage is
  configured for attention after 4 days") plus a primary action URL into the page
  that resolves it. No opaque labels, and no dead-end informational cards.
- **Attention never acts.** It suggests work: the recruiter advances stages,
  hires, rejects, schedules and closes jobs. Nothing is automated from a
  heuristic.
- **Attention must never rely on a new AI call.** Existing AI output (evaluation,
  confidence, interview brief, job review) may inform what is displayed;
  "what should the recruiter do?" is deterministic product state. AI never makes
  a hiring decision.
- **Prefer fewer reliable signals over many noisy ones.** A signal with no
  trustworthy data behind it is not implemented. Each signal contributes a
  bounded number of items and the remainder stays counted in
  `RecruitmentAttentionQueue::hiddenCount()` — never silently dropped.
- **A signal needs waiting evidence, and the most specific one wins.** A job is
  not stalled because candidates arrived, nobody has an interview yet, or it is
  new: "stalled" requires applications genuinely overdue against their stage's
  own threshold with nothing having moved forward. Two items must never ask for
  one decision — a finalist awaiting a decision raises `DecisionPending`, not
  also `StageOverdue`, and a campaign ending without finalists explains itself
  without a generic `JobStalled` beside it. Keep this as explicit conditions in
  `RecruitmentAttentionService`; do not grow a rule engine.
- **Attention scope is deliberate:** interview items are personal (the signed-in
  recruiter's calendar), application items cover every published job including
  ones whose campaign window closed, and job items cover currently active
  processes only.
- **Stage age uses `applications.status_entered_at`, never `updated_at`.** It is
  written on creation and by `MoveApplicationToStatus` only, so an evaluation
  finishing or an interview being booked cannot make a waiting candidate look
  freshly arrived. Re-entering the same status does not reset it.
- **"Waiting too long" is workflow configuration, never a hard-coded number of
  days.** A `Status` may declare `attention_after_days`; null means no age-based
  warning. Terminal stages never carry one (`Status` nulls it on save). Default
  workflows may ship conservative, clearly editable values — never a recruiting
  policy baked into application logic.
- **Hiring completion is measured against `job_postings.hiring_target`**
  (defaults to 1, minimum 1), and actual hires always come from the workflow's
  hired stages — never from a fit score, a final stage or an interview outcome.
  It is unrelated to `application_limit` and to plan limits.
- **Reaching the hiring target suggests, never acts.** The product states the
  objective was met and offers a recruiter-controlled next step; it must not
  pause intake, unpublish or close the job by itself.
- **The primary click on a job means "work on this hiring process".** The jobs
  table row navigates into the workspace — onto the board when the job has
  candidates. Entering a job must never require opening an action menu; Edit,
  Duplicate, Publish and the public URL stay secondary.
- **Kanban cards prioritise work signals over passive metadata.** Time in stage,
  overdue, upcoming interview, RSVP or calendar problems, evaluation failure,
  evidence still needing validation, decision-needed and fit. Answer and
  document counts do not belong there. Referral stays visible as sourcing
  metadata and never affects fit or ranking. Keep cards short.
- **Terminal outcomes are never rendered as future linear steps.** Hired and
  rejected are alternative branches, so no "stage 3 of 6" and no left-to-right
  chain through every stage. Show the current stage and how long the candidate
  has been in it.
- **Next-action guidance exposes the action.** The application summary renders
  the corresponding Filament action next to its recommendation, reusing the
  page's existing action builders (a distinct action *name* per copy, since
  Filament mounts by name) — never a duplicated rule in Blade. Terminal outcomes
  offer no recruiting action at all.
- **Header action prominence is state-dependent.** One likely action leads:
  join/manage a booked interview, decide in a late stage, schedule when nothing
  is booked, reprocess a failed evaluation. A terminal application never offers
  scheduling as primary. Demoted actions move into the overflow group, never
  disappear.
- **Navigation badges must be actionable.** No raw record totals in the sidebar,
  and no invented notification counts.
- **Every page keeps the sidebar pointing somewhere.** A resource may be
  registered without its own navigation entry — the application workspace is
  reached from inside a hiring process, not from the sidebar — but it must never
  leave the sidebar with nothing selected. Claim the page from the nav item it
  belongs under via `getNavigationItemActiveRoutePattern()`, matching the page's
  own breadcrumbs: application pages keep **Jobs** active. A test asserts no
  admin route is left unclaimed.
- **Normal plan and AI usage belong in Settings.** The persistent topbar is
  reserved for the exception — an AI allowance close to blocking candidate
  evaluations. Access to Plan and AI usage is never removed, only relocated.

### Overview presentation

The Overview is where the product is judged, so how it reads is part of the
feature. It is **one composed surface** (`App\Filament\Pages\Dashboard` with its
own view), never a stack of independent Filament widgets each bringing its own
section chrome, heading block and empty state.

- **Attention leads the layout, not just the reading order.** On desktop it takes
  the wide column with the personal agenda beside it; on narrow screens they
  stack with attention first.
- **Hierarchy comes from typography, spacing and composition** — not from cards,
  gradients, shadows or bigger icons. Fewer containers, restrained hairline
  borders, one quiet surface per region. Never a card inside a card.
- **Badges are for categorical states only** (`Paused`, `Target reached`,
  `Declined`). Severity is a coloured marker plus a screen-reader label, never a
  `Critical`/`Warning`/`Info` pill, and never a row of alarm badges. A state that
  colour alone conveys is also stated in words.
- **No redundant KPI cards.** Global totals appear once, as a restrained inline
  line of figures near the heading, because attention, the agenda and the process
  list already carry the same numbers with operational context.
- **Rows stay dense and every row leads somewhere.** Attention rows read
  "reason · context · action"; a light link is enough, a heavy button per row is
  not. Empty states are one compact line — an absence should save vertical space,
  not spend it.
- **Regions cap what they list and say what they left out.** Presentation caps
  live in the page (`AttentionLimit`, `AgendaLimit`, `ProcessLimit`) and the
  remainder is always counted in the region's note — never silently dropped.
- **Composition lives in the view; meaning stays in the services.** The page
  reads `RecruitmentAttentionService` and `RecruitmentProgressService` and shapes
  the agenda with `RecruiterAgendaPreview`; no recruitment query or rule is
  reimplemented in Blade.

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
- **An evaluation belongs to the criteria revision that produced it.**
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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- filament/filament (FILAMENT) - v5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v5
- phpunit/phpunit (PHPUNIT) - v13
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
