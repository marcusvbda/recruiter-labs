---
name: recruitment-workflow
description: Product invariants for the recruiter-facing product — information architecture, navigation, Overview work queue and its presentation, Jobs list, job/application/candidate workspaces, Calendar, hiring workflow (pipeline) semantics, Kanban, application stage behaviour, and the attention/progress services. Load before changing any of those surfaces.
---

# Recruitment workflow and information architecture

Load this skill when the task touches any of:

- the Overview (`App\Filament\Pages\Dashboard`) or its widgets/view;
- the Jobs list, job workspace (`ViewJob`) or the Kanban board;
- the application workspace (`ViewApplication`), application stages/statuses or
  next-action guidance;
- the candidate workspace (`ViewCandidate`);
- the Calendar / agenda and interview scheduling surfaces;
- hiring workflows (`PipelineResource`, `Status`, workflow provisioning);
- recruiter navigation, the Settings cluster or navigation badges;
- `RecruitmentAttentionService`, `RecruitmentAttentionQueue`,
  `RecruitmentProgressService` or `RecruiterAgendaPreview`.

Everything below is a product invariant, not a style preference. Move rules
when a surface moves, but never quietly relax or redesign them; if a task
appears to require breaking one, surface the conflict instead of resolving it
silently.

Related:

- `.ai/skills/evaluation-integrity/SKILL.md` — AI agents, candidate evaluation,
  fit/coverage/confidence and everything AI-facing.
- `AGENTS.md` — global rules (language, Git, testing, documentation).

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

