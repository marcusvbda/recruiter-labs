---
status: implemented
type: as-built
---

# AI-native recruiting operations foundation

> **Superseded in part by `../core-recruiting-productivity/spec.md`.**
> That spec removes routine criteria confirmation. Wherever this document says
> criteria must be "confirmed" by a human, are "awaiting review/confirmation",
> raise a "Criteria ready for review" attention item, or that automatic
> preparation "does not confirm" them, the delivered behaviour is instead:
> successful automatic preparation makes the criteria the job's **current**
> criteria immediately (AI-generated, editable; a saved edit is authoritative
> at once; explicit Rebuild remains). There is no confirmation step and no
> confirmation-based attention item; only failed / blocked / missing-context
> preparation asks for human action. Read "confirmed criteria" below as
> "current criteria". Revision integrity, terminal-application protection,
> sourcing readiness, allowance and idempotency rules still apply, and the
> human-only decisions (rejecting, hiring, moving candidates, contacting,
> billing, permissions) are unchanged. The standalone Job Review advisory card
> is no longer part of the recruiter experience. See
> `../job-evaluation-criteria/spec.md` for the current criteria lifecycle.

## Problem

Recruiter Labs already uses AI in meaningful parts of the recruitment process.

The product can:

- propose job evaluation criteria;
- evaluate applications against human-confirmed criteria;
- derive evidence, fit, coverage, confidence, and interview validation needs;
- search an existing talent pool against a job;
- prepare an Interview Brief;
- preserve uncertainty rather than inventing negative evidence.

However, parts of the product still follow the interaction model of traditional
software with AI added on top:

Human opens a screen
→ human asks the system to perform work
→ AI produces output
→ human reads the output
→ human discovers and starts the next operation.

This creates unnecessary operational work for recruiters.

In several situations Recruiter Labs already has enough trusted state to know
that useful work can be performed without another human instruction.

Examples include:

- a newly created job already contains enough context to prepare initial hiring
  criteria suggestions;
- a newly submitted eligible application already has everything required for its
  evaluation to begin;
- confirming job criteria already means waiting eligible applications can proceed;
- confirmed criteria plus an existing talent pool means sourcing is ready to be
  performed, even when explicit authorization is still appropriate because the
  operation may be large or expensive;
- completed sourcing results may be waiting for recruiter review without the
  recruiter needing to remember that a background process finished;
- outdated sourcing can already be detected when criteria, candidate materials,
  or the talent pool change.

Recruiter Labs should not make the recruiter operate the software merely because
traditional software required a button.

At the same time, becoming AI-native must not mean making consequential hiring
decisions automatically, spending unpredictable AI resources without the
workspace's awareness, contacting people unexpectedly, or hiding why the system
acted.

The product needs a clear operating model for:

- what Recruiter Labs should do automatically;
- what Recruiter Labs should prepare but wait for human authorization to execute;
- what must remain a human decision.

This feature establishes that operating model and applies it to the existing
recruitment workflow before more product surfaces are built.

---

## Objective

Move Recruiter Labs from primarily:

Human action
→ AI insight
→ human action

toward:

Recruitment event
→ Recruiter Labs performs safe work automatically
→ Recruiter Labs prepares the next meaningful step
→ human intervenes only where judgment, authorization, cost, or consequence
  requires them.

The first version must:

1. establish a product-wide distinction between automatic work, prepared
   approval-required work, and human-only decisions;
2. automatically prepare initial AI criteria for a newly created job when enough
   job context exists;
3. preserve and standardize the existing automatic candidate-evaluation flow;
4. remove unnecessary discovery/navigation around internal sourcing by
   proactively surfacing when sourcing is ready, outdated, or has results awaiting
   review;
5. make approval-required sourcing executable directly from the relevant
   attention surface without first making the recruiter navigate to another page;
6. distinguish system-initiated AI work from explicit user-requested AI work in
   the product's AI execution history;
7. evolve Recruitment Attention toward representing meaningful human gates rather
   than work the system could safely perform itself;
8. establish design rules that future Recruiter Labs features must follow when
   deciding whether to request human input.

The feature must preserve the core principle:

> Recruiter Labs performs the recruitment work it is safe and appropriate to
> perform. Humans remain responsible for hiring judgment, consequential
> recruitment decisions, and explicit approval where required.

---

## Product context and source-of-truth boundary

This is a planned product specification.

It changes observable behaviour across existing recruitment features but does
not replace their existing integrity rules.

The existing contracts that remain authoritative include:

| Existing feature | Behaviour preserved or extended |
| --- | --- |
| Job evaluation criteria | AI proposes criteria; a human confirms the criteria that govern evaluation. |
| Candidate evaluation | Eligible applications are evaluated against the exact human-confirmed criteria revision. Fit, coverage, confidence, evidence, and uncertainty remain separate. |
| Application intake | Public application submission remains the source of candidate-supplied application evidence and already enters the normal evaluation path. |
| Active candidate sourcing foundation | Sourcing remains job-specific, evidence-backed, workspace-private, and separate from Application. |
| Talent pool import and materials | Independent candidate materials remain talent-pool evidence and do not silently become application evidence. |
| Recruitment attention | Attention remains deterministic, explainable, bounded, and derived from persisted state. |
| AI usage and limits | Every automatic or human-requested AI execution continues to obey provider, plan, allowance, usage, caching, and revision-integrity rules. |
| Recruitment pipeline | AI does not silently become the authority for candidate workflow decisions. |
| Structured interview feedback | Human interview evidence remains human evidence and is not rewritten into an AI hiring decision. |

This feature must not weaken the guarantees in
`.claude/skills/evaluation-integrity/SKILL.md` or
`.claude/skills/recruitment-workflow/SKILL.md`.

Where this feature changes observable behaviour described by an existing
as-built specification, that specification must be reconciled when
implementation is completed.

No existing specification should remain factually contradictory after this
feature is closed.

---

## Product principle

AI-generated output is not, by itself, the product outcome.

The product outcome is completed recruiting work.

For every recruitment workflow the product should ask:

1. What information does Recruiter Labs already know?
2. What safe work can Recruiter Labs perform from that information without
   asking the recruiter again?
3. What can Recruiter Labs prepare in advance?
4. What requires explicit human authorization?
5. What decision must remain human?

A recruiter should not be asked to:

- press a button merely to start safe, bounded processing the system already
  knows should occur;
- re-enter information Recruiter Labs already possesses;
- navigate through several screens merely to discover the next required action;
- monitor background AI work manually;
- remember to revisit a job after a background operation finishes.

The product should reduce human operation of the ATS.

It must not reduce meaningful human judgment.

---

## Operating model

Recruiter Labs operations are conceptually divided into three categories.

### Automatic work

Automatic work can start from trusted persisted product state without a new
human instruction when all required product gates are already satisfied.

Automatic work must be:

- non-decisional;
- bounded enough that starting it does not create unreasonable or unpredictable
  cost;
- reversible or safely replaceable when it produces derived information;
- subject to normal authorization, tenant, revision, provider, and allowance
  rules;
- visible through an appropriate operational state;
- incapable of contacting a candidate or changing a hiring outcome merely
  because AI produced a result.

Examples that belong in this category include:

- preparing initial suggested job criteria;
- evaluating a newly received eligible application;
- releasing waiting eligible evaluations after criteria confirmation;
- deriving deterministic attention state;
- preparing Interview Brief content as part of candidate evaluation.

Automatic does not mean ungoverned.

Every automatic AI operation continues to obey the same provider, quota,
revision, stale-response, cache, and tenant rules as the same operation would
obey if a human explicitly started it.

---

### Prepared work requiring approval

Some work can be recognized and prepared automatically but should not start
until a human explicitly authorizes it.

Reasons may include:

- cost varies significantly with the amount of data involved;
- the action communicates externally;
- the action changes formal candidate participation;
- the action changes recruitment workflow state;
- the action acts on behalf of the recruiter in a consequential way.

Recruiter Labs should surface these operations at the moment they become useful
instead of forcing the recruiter to discover them manually.

The first version explicitly treats a full internal sourcing sweep as
approval-required work.

A sourcing sweep may inspect many candidates and may create AI usage proportional
to the size of the talent pool.

Therefore this feature does not automatically start a full sourcing sweep merely
because criteria were confirmed or because the talent pool changed.

Instead Recruiter Labs prepares the action and makes it directly available to
the recruiter.

Future architectures may change the cost characteristics of sourcing. That alone
does not authorize future automatic sourcing without an explicit product
decision.

---

### Human-only decisions

Some decisions are intentionally outside AI authority.

This feature does not allow AI to autonomously:

- confirm hiring criteria;
- reject a candidate;
- hire a candidate;
- disqualify a candidate as a final recruitment outcome;
- override human interview evidence;
- fabricate evidence;
- convert missing information into negative evidence;
- change tenant ownership or permissions;
- change billing or plans;
- create policy governing its own authority.

Human confirmation of evaluation criteria remains mandatory.

The AI may propose.

The human owns the hiring contract.

---

## Automatic initial criteria preparation

### Normal new-job behaviour

When a recruiter finishes creating a new job and the job contains enough
meaningful evaluation context to support the existing criteria-extraction
process, Recruiter Labs should automatically begin preparing the first AI
criteria suggestion.

The recruiter should not normally have to:

Create job
→ open AI Criteria
→ click Generate.

The normal path becomes:

Create job
→ Recruiter Labs prepares criteria
→ recruiter reviews
→ recruiter edits when necessary
→ recruiter confirms.

The resulting criteria remain AI suggestions.

Automatic generation does not confirm them.

---

### Minimum context

Recruiter Labs must not attempt to generate meaningful hiring criteria from
identity fields alone.

A job must contain enough substantive role information to use the existing
criteria-extraction contract.

At minimum, a job must have a meaningful role description in addition to its
basic job identity.

If sufficient context does not exist:

- no fake or generic criteria are produced;
- the job remains usable as a draft;
- the criteria experience explains what information is needed;
- the recruiter can provide the missing role context and explicitly or
  automatically trigger preparation later according to the resulting state.

Do not invent requirements merely to force AI generation.

---

### Human ownership remains unchanged

Automatically prepared criteria must enter the same review-required state as
criteria explicitly generated by a recruiter.

AI preparation alone must never:

- mark criteria as confirmed;
- release candidate evaluation as if the recruiter approved the criteria;
- publish the job;
- start contacting candidates;
- alter the pipeline;
- become hiring policy.

The recruiter must still review and explicitly confirm the exact criteria
revision.

---

### No repeated automatic generation

Automatic criteria preparation is event-driven, not page-driven.

Opening or refreshing a page must not repeatedly spend AI allowance.

Repeated saves that do not change relevant job context must not repeatedly
schedule the same automatic preparation.

Duplicate lifecycle events must not create competing current criteria
generations.

If an operation is already pending or processing for the current relevant
revision, another equivalent automatic trigger must not create another
meaningful execution.

---

### Job changes during preparation

If evaluation-relevant job context changes while an automatically started
criteria preparation is in progress, the older result must not silently become
the authoritative current suggestion.

Existing criteria-generation and stale-response integrity continues to apply.

The product must prefer an honest outdated/needs-review state over pretending
that an older AI response describes the new job definition.

---

### Later job changes

This feature does not automatically overwrite recruiter-edited or previously
confirmed criteria every time the job changes.

Evaluation-relevant changes continue to invalidate the previous confirmation
according to the existing criteria contract.

Once human-edited or previously confirmed criteria exist, Recruiter Labs must not
silently replace their meaning with a newly generated AI suggestion.

Explicit regeneration remains available.

A future feature may introduce a richer proposed-revision workflow.

This feature does not introduce one.

---

### Duplicated jobs

Duplicating a job must not create a false claim that copied criteria were
confirmed for the new job.

If the duplicated job already contains criteria copied from the source job,
automatic initial extraction must not overwrite those criteria simply because a
new job record was created.

The new job still requires its own human confirmation.

The recruiter may explicitly request fresh AI criteria if desired.

---

## Candidate evaluation as automatic recruiting work

Candidate evaluation already follows the desired AI-native pattern in the normal
application flow.

This feature makes that behaviour an explicit product principle.

For an application containing eligible application evidence:

Application becomes eligible
→ confirmed criteria exist
→ evaluation begins through the normal AI path
→ fit, coverage, confidence, evidence, and Interview Brief become available
→ recruiter reviews them.

The recruiter must not be required to press an ordinary "Evaluate candidate"
button as part of the normal happy path.

Explicit retry or refresh actions may remain available when a real operational
reason exists.

---

### Applications waiting for criteria

If an application exists before current criteria are confirmed:

- the application waits;
- no evaluation is fabricated;
- confirming the current criteria automatically releases eligible active
  applications through the existing evaluation workflow.

The recruiter must not need to return to every waiting application and manually
request evaluation after criteria confirmation.

---

### Criteria revision changes

When a new criteria revision is confirmed:

- active applications whose evaluation is missing or outdated may automatically
  re-enter the normal evaluation path;
- terminal applications retain their historical evaluation and do not consume
  new AI allowance merely because criteria changed.

This remains derived evaluation work.

It is not a recruitment decision.

---

### Candidate added manually or through sourcing

Creating an Application by explicitly adding an existing Candidate to a Job must
not silently treat talent-pool material as application-submitted evidence.

Independent candidate materials remain governed by their existing sourcing
semantics.

If the new Application has no evidence eligible under the candidate-evaluation
contract:

- Recruiter Labs must not perform an empty AI evaluation merely for the sake of
  automation;
- the sourcing analysis, when one exists, remains separate;
- the product must not copy Potential Match into Application Fit;
- the product must not claim that a formal application evaluation exists.

Future candidate outreach or application-completion workflows may acquire new
application-specific evidence and then allow the normal automatic evaluation
path to proceed.

---

## Sourcing becomes proactively prepared work

Internal sourcing remains an explicit recruiter-authorized operation in this
version.

However, the recruiter should no longer have to discover manually that sourcing
is available.

Recruiter Labs already knows when:

- a job has current human-confirmed criteria;
- the workspace has eligible candidates;
- no sourcing search has been run;
- a previous search predates the current talent pool;
- a previous search belongs to an older criteria revision;
- completed sourcing matches remain waiting for human review.

Those states should proactively produce appropriate recruiter-facing work.

---

## Sourcing ready

When all of the following are true:

- the job has current human-confirmed criteria;
- the workspace has at least one eligible candidate for internal sourcing;
- no current sourcing analysis exists for the current relevant state;
- no equivalent sourcing operation is already running;

Recruiter Labs should surface that sourcing is ready.

The recruiter should be able to authorize the sourcing search directly from the
attention/action surface.

The normal path should not require:

Attention says sourcing may be useful
→ open job
→ open Sourcing tab
→ locate Find matches
→ click Find matches.

Instead:

Sourcing ready
→ Find matches.

The job Sourcing surface remains available for context, results, history, and
manual refresh.

---

## Sourcing refresh ready

When Recruiter Labs can determine that existing sourcing results are no longer
current because:

- the confirmed criteria revision changed;
- the talent pool changed;
- relevant candidate material changed;

the product should surface a refresh action when current criteria are again
confirmed and a refresh is meaningful.

The system must preserve the distinction between:

- criteria requiring human confirmation;
- sourcing that merely predates the current talent pool;
- sourcing analysis that is outdated;
- a sourcing operation currently running.

If criteria are awaiting human confirmation, the criteria review is the more
specific human gate.

The product must not simultaneously tell the recruiter to refresh sourcing
against criteria that are not yet authoritative.

---

## Sourcing results awaiting review

A completed sourcing search may produce suggested matches that the recruiter has
not reviewed.

Recruiter Labs should surface a job-level human gate such as:

> 12 potential matches are ready for review.

This is not a candidate-quality alert.

It means Recruiter Labs completed preparatory work and the next meaningful step
requires human review.

The signal should remain aggregated rather than generating one global attention
item per candidate.

It should disappear or change as the underlying state changes.

For example, candidates explicitly:

- saved;
- dismissed;
- added to the job;
- made otherwise non-actionable;

should no longer count as untouched suggested matches.

---

## Recruitment Attention evolves into the human-gate queue

Recruitment Attention remains deterministic.

This feature does not ask a language model to decide what the recruiter should do
next.

Instead, Attention increasingly represents:

> Work Recruiter Labs cannot or should not finish without the recruiter.

The product should not create an attention item for a safe operation that it can
perform automatically and reliably by itself.

Examples:

Bad:

> You have a new application. Click here to evaluate it.

The product can already evaluate an eligible application itself.

Good:

> Evaluation blocked — AI allowance reached.

Human intervention may be required.

Bad:

> Criteria can be generated for this new job.

When sufficient context exists, Recruiter Labs can prepare them itself.

Good:

> Criteria are ready for your review.

Confirmation requires the recruiter.

---

## New attention conditions

This feature may add deterministic human-gate signals for the following states.

### Criteria ready for review

AI criteria preparation completed and the current criteria revision awaits human
review/confirmation.

The action goes directly to criteria review.

### Criteria preparation failed or is operationally blocked

The automatic preparation could not complete and the recruiter must either:

- inspect/retry the criteria operation;
- resolve the relevant AI configuration/allowance problem;
- provide missing job context.

The message must describe the actual operational state.

It must not imply that the job or candidates are low quality.

### Internal sourcing ready

Current criteria are confirmed, an eligible talent pool exists, and no current
sourcing sweep exists.

The recruiter can explicitly start the sourcing operation directly.

### Internal sourcing refresh ready

A previous sourcing result is no longer current and the prerequisites for a
fresh search are satisfied.

The recruiter can explicitly authorize refresh directly.

### Sourcing results ready for review

A completed search has untouched suggested matches requiring human review.

The action opens the relevant sourcing results.

---

## Attention deduplication

Existing attention deduplication principles continue to apply.

One underlying decision should not appear as several competing items.

Examples:

- criteria awaiting confirmation suppress a sourcing-refresh prompt that cannot
  yet be acted on;
- a sourcing search already running suppresses "start sourcing";
- failed sourcing should surface its actual failed/blocked state rather than also
  saying the search is ready;
- zero eligible candidates does not create a useless "start sourcing" action;
- a completed sourcing search with suggested matches should surface one
  aggregated job-level review action rather than one global item per candidate.

Attention severity communicates operational urgency.

It does not rank candidates.

---

## AI-operation provenance

As Recruiter Labs performs more work automatically, the workspace must be able
to distinguish system-initiated AI work from work explicitly requested by a
human.

The existing AI execution history should communicate, at an appropriate level:

- the operation performed;
- whether it was automatically initiated by Recruiter Labs or explicitly
  requested by a user;
- the meaningful trigger/reason when available;
- provider/model and usage information already supported by the product;
- success, failure, or blocked state.

Examples of meaningful reasons include:

- Job created;
- Application submitted;
- Criteria confirmed;
- Recruiter requested criteria regeneration;
- Recruiter requested sourcing.

Exact copy is not part of the product contract.

The distinction must be understandable.

Automatic execution does not mean anonymous execution.

---

## Automatic operations and AI allowance

System-initiated AI work uses the same resource governance as explicitly
requested AI work.

Automatic AI work must:

- respect the effective provider configuration;
- respect platform allowance where applicable;
- respect plan-gated own-key behaviour;
- record real usage;
- record failures honestly;
- obey existing cache and stale-response rules.

Automatic work must not bypass allowance because "the system chose to run it."

If an automatic operation cannot run because allowance or provider state blocks
it:

- the operation must not generate fake output;
- candidate fit must not change;
- missing analysis must not become negative evidence;
- the relevant human gate or operational state may surface through Attention.

---

## No surprise bulk AI spending

AI-native does not mean automatically performing every possible AI operation.

Operations whose cost can scale significantly with dataset size require explicit
product treatment.

In this version, a full internal sourcing sweep remains one such operation.

The product may automatically:

- determine that sourcing is ready;
- calculate deterministic eligibility/count context;
- explain why refresh is needed;
- place the action in front of the recruiter.

It does not automatically run a potentially large sourcing sweep solely because:

- criteria were confirmed;
- a candidate was imported;
- a candidate CV changed;
- the talent pool revision increased.

The human authorization to run the sweep remains meaningful.

---

## Manual actions as recovery, not ceremony

Removing unnecessary clicks does not require removing every manual action.

Manual actions may remain where useful for:

- retry;
- explicit refresh;
- recovery after failure;
- requesting a new suggestion;
- overriding when the product cannot infer intent safely.

A manual button must not remain mandatory merely because the original product
flow had one.

If the system can safely perform the normal operation automatically, the manual
control becomes an exception/recovery mechanism rather than the happy path.

---

## Explainability of automated behaviour

A recruiter should not be surprised by significant automated work.

When Recruiter Labs performs visible AI work automatically, the resulting surface
should make the reason understandable.

Examples:

> Criteria draft prepared from this job.

> Candidate evaluation started after application submission.

> Evaluation refreshed after the new criteria revision was confirmed.

The product does not need to narrate every internal technical event.

It must make meaningful automated behaviour understandable when the user
encounters its result or history.

---

## Human control and recruitment decisions

This feature changes who starts operational work.

It does not transfer hiring authority to AI.

Recruiter Labs may automatically:

- prepare AI criteria suggestions;
- evaluate eligible candidate-submitted application evidence;
- identify uncertainty;
- prepare an Interview Brief;
- detect that sourcing is ready or stale;
- derive operational attention state;
- prepare future actions when future feature specifications authorize them.

Recruiter Labs must not automatically through this feature:

- confirm criteria;
- create a formal Application from a sourcing suggestion;
- save or dismiss a sourcing match on behalf of a recruiter;
- contact a candidate;
- send outreach;
- schedule an interview;
- move an Application through the pipeline;
- reject;
- hire;
- infer or write candidate facts not supported by evidence.

---

## Future-feature design contract

This feature establishes a product-design rule for future Recruiter Labs
development.

For every future feature that introduces recruiter work, the specification should
explicitly consider:

### Human input

What information is the human being asked to provide?

Does Recruiter Labs already possess that information or can it derive it safely?

If yes, do not require redundant input.

### Automatic work

What processing can safely happen when an existing product event makes the work
eligible?

Do not add a "Generate with AI" button as the mandatory normal path when the
system already knows when generation should occur and the operation is safe and
bounded.

### Prepared work

What useful next step can Recruiter Labs prepare before the recruiter reaches the
screen?

Preparation should reduce operational work without silently performing
consequential actions.

### Human gate

Why is human involvement required?

Valid reasons include:

- hiring judgment;
- external communication;
- consequential workflow changes;
- variable/unbounded cost;
- explicit consent or authorization;
- unresolved ambiguity.

"Because the previous UI had a button" is not a valid reason.

### Human-only boundary

Every feature must preserve the distinction between AI assistance/operation and
the human hiring decision.

This contract guides future feature design.

It does not automatically expand the authority of existing features.

---

## User flows

### Flow A — New job with sufficient context

1. Recruiter creates a job with meaningful role context.
2. Job creation succeeds normally.
3. Recruiter Labs automatically starts preparing initial AI criteria.
4. The job communicates that criteria preparation is in progress.
5. AI preparation completes.
6. Criteria remain unconfirmed.
7. "Criteria ready for review" becomes actionable.
8. Recruiter reviews and edits the suggested criteria.
9. Recruiter explicitly confirms them.
10. Waiting eligible candidate evaluations are released through the normal
    evaluation path.
11. If an internal talent pool exists, Recruiter Labs can surface that sourcing
    is ready.
12. The recruiter may explicitly authorize sourcing.

---

### Flow B — New job without enough context

1. Recruiter creates a minimally described job.
2. Recruiter Labs does not fabricate generic criteria.
3. The criteria experience explains that additional job context is needed.
4. Recruiter adds meaningful role information.
5. Once the product has enough context and no conflicting criteria state exists,
   initial criteria preparation can proceed.
6. Human confirmation remains required.

---

### Flow C — Public application

1. Candidate submits an eligible application.
2. Recruiter Labs accepts the application through the normal intake workflow.
3. If current criteria are confirmed, candidate evaluation starts without a
   recruiter instruction.
4. If criteria are not confirmed, the application waits.
5. Recruiter later confirms the criteria.
6. Recruiter Labs releases the waiting evaluation automatically.
7. Recruiter sees the completed evidence-backed evaluation and Interview Brief.

---

### Flow D — Criteria change

1. Evaluation-relevant job context changes.
2. Existing criteria confirmation becomes stale according to the current
   criteria rules.
3. Existing current candidate/sourcing analysis must not pretend to describe a
   superseded contract.
4. Human reviews the appropriate criteria revision.
5. Human confirms it.
6. Eligible active application evaluations can refresh automatically.
7. Sourcing does not automatically consume a large new batch of AI calls.
8. Recruiter Labs surfaces that sourcing refresh is ready.
9. Recruiter explicitly authorizes the refresh when desired.

---

### Flow E — Talent pool changes

1. New candidates or relevant candidate materials enter the workspace.
2. Existing sourcing freshness reflects that the previous search predates the
   current pool/material state.
3. Recruiter Labs does not automatically rescan the entire pool.
4. For jobs where a refresh is meaningful and criteria are current, an aggregated
   refresh action becomes available.
5. Recruiter explicitly authorizes sourcing refresh.

---

### Flow F — Sourcing completes while recruiter is elsewhere

1. Recruiter explicitly starts internal sourcing.
2. Recruiter leaves the Sourcing screen.
3. Search completes asynchronously.
4. Suggested matches exist.
5. Recruiter Labs surfaces one job-level item that matches are ready for review.
6. Recruiter opens the results.
7. Recruiter saves, dismisses, inspects, or adds candidates according to existing
   sourcing rules.
8. The derived attention state updates as untouched suggestions disappear.

---

## Business rules

### AI proposes; humans establish hiring policy

Automatic criteria generation does not weaken criteria confirmation.

The authoritative evaluation criteria remain explicitly human-confirmed.

### AI may perform work but not invent authority

Starting an operation automatically does not grant its AI output additional
authority.

Automatically produced evaluation data has exactly the same meaning and
limitations as the same evaluation explicitly requested by a user.

### Unknown remains unknown

Automation must never transform absent information into negative evidence merely
to complete a workflow automatically.

### Derived analysis remains revision-bound

Automatically triggered analysis must obey all current criteria, generation,
material, pool, and stale-response protections relevant to that analysis.

### Automated work remains tenant-scoped

A system-triggered operation has no broader data authority than the workspace
event that caused it.

Automation must never become a route around tenant isolation.

### Candidate-controlled content remains untrusted

Automatically starting AI processing does not turn candidate-provided content
into instructions.

Candidate text remains evidence to assess.

### Operational state is not candidate quality

Failure, quota exhaustion, stale analysis, missing evidence, or blocked
automation must never become a negative candidate signal.

### Human actions remain human actions

Save, dismiss, criteria confirmation, candidate stage changes, rejection, hiring,
and other existing human decisions must not be rewritten as automated AI actions
by this feature.

### Automatic work must be idempotent from the user's perspective

Duplicate application events, repeated saves, refreshed pages, or duplicate
system delivery must not produce multiple current results or repeated meaningful
side effects for the same eligible operation.

### Background work must expose honest state

The product must distinguish relevant states such as:

- waiting;
- preparing;
- ready for human review;
- blocked;
- failed;
- outdated;
- completed.

A partially completed or failed automatic operation must not be displayed as if
the system successfully finished the work.

### Attention remains deterministic

Recruitment Attention continues to derive from persisted domain state.

This feature does not turn Attention into an LLM-generated task list.

---

## Acceptance criteria

- **AC01** — A newly created job with sufficient meaningful role context can
  automatically begin initial AI criteria preparation without requiring the
  recruiter to click a Generate action.

- **AC02** — Automatically prepared criteria remain AI suggestions and cannot
  become authoritative until an authenticated workspace human explicitly
  confirms the exact current revision.

- **AC03** — A job without sufficient role context does not receive fabricated
  generic criteria merely to satisfy automatic processing.

- **AC04** — Page refreshes and repeated saves that do not represent a new
  relevant state do not repeatedly initiate equivalent automatic criteria
  preparation.

- **AC05** — A superseded automatic criteria response cannot silently become the
  current criteria suggestion after relevant job context changed while it was
  being produced.

- **AC06** — A duplicated job does not inherit the source job's human
  confirmation as confirmation of the duplicate.

- **AC07** — Existing copied or human-edited criteria are not silently
  overwritten merely because Recruiter Labs supports automatic initial criteria
  preparation.

- **AC08** — An eligible public application with current confirmed criteria
  proceeds through candidate evaluation without requiring the recruiter to start
  the normal evaluation manually.

- **AC09** — An application received before criteria confirmation waits rather
  than being evaluated against unconfirmed criteria.

- **AC10** — Confirming current criteria automatically releases eligible active
  applications that were waiting for those criteria.

- **AC11** — Confirming a new criteria revision may refresh eligible active stale
  evaluations without automatically re-evaluating terminal applications.

- **AC12** — Automatically initiated candidate evaluation preserves the existing
  fit, Evidence Coverage, Confidence, evidence provenance, unknown-information,
  Interview Brief, and human-decision semantics.

- **AC13** — Adding an existing Candidate to a Job does not automatically convert
  independent talent-pool material into formal Application evidence.

- **AC14** — The product does not copy a sourcing Potential Match into
  Application Fit when a sourced candidate enters a job.

- **AC15** — The product does not perform a meaningless empty AI candidate
  evaluation merely because an Application record exists without eligible
  application evidence.

- **AC16** — When a job has current confirmed criteria, an eligible internal
  talent pool, and no current sourcing search, Recruiter Labs can surface that
  internal sourcing is ready without requiring the recruiter to discover that
  fact manually.

- **AC17** — Starting a full internal sourcing sweep remains an explicit human
  authorization in this version.

- **AC18** — A recruiter can start an eligible sourcing sweep directly from the
  relevant attention/action item without first navigating through the job
  workspace to locate the normal Find matches control.

- **AC19** — A talent-pool or relevant material change can cause Recruiter Labs to
  surface that sourcing refresh is ready without automatically spending the AI
  resources required for the full refresh.

- **AC20** — Criteria waiting for confirmation suppress a sourcing-refresh action
  that cannot yet produce current results.

- **AC21** — A sourcing search already running does not simultaneously produce a
  duplicate start-sourcing action.

- **AC22** — A completed sourcing search with untouched suggested matches can
  produce one aggregated job-level human-gate item indicating that potential
  matches are ready for review.

- **AC23** — The sourcing-review attention count reflects actionable Suggested
  matches rather than generating one global attention item per candidate.

- **AC24** — Save, dismiss, restore, Add to job, and other existing sourcing
  decisions remain explicit human actions.

- **AC25** — Recruitment Attention remains derived and deterministic rather than
  becoming a separately authored AI task list.

- **AC26** — Work that Recruiter Labs can safely complete automatically should
  not create an attention item whose only purpose is asking the recruiter to
  start that same safe operation.

- **AC27** — When an automatic AI operation is blocked or fails persistently, the
  product can surface the real operational problem rather than silently doing
  nothing or inventing output.

- **AC28** — AI allowance exhaustion during automatic work does not reduce
  candidate fit, create negative evidence, or generate placeholder output.

- **AC29** — System-initiated AI executions continue to obey the same effective
  provider, plan, allowance, usage-recording, cache, and revision-integrity rules
  as user-initiated executions.

- **AC30** — AI execution history allows an authorized workspace user to
  distinguish an automatically initiated operation from an explicitly
  user-requested operation.

- **AC31** — Where product context provides a meaningful trigger, automatic AI
  history communicates that trigger at an understandable level without exposing
  candidate evidence unnecessarily.

- **AC32** — Automatic processing cannot confirm job criteria, contact a
  candidate, create a sourcing Application, change pipeline stage, reject, hire,
  or otherwise make the recruitment decision through this feature.

- **AC33** — Duplicate events or equivalent repeated triggers do not produce
  multiple current analyses or repeated meaningful side effects for one logical
  operation.

- **AC34** — An automatic operation that becomes stale before completion does not
  become the current result merely because AI resources were consumed.

- **AC35** — Automated work remains strictly workspace-scoped and does not gain
  access to other tenants' recruitment data.

- **AC36** — Candidate-provided text processed automatically remains untrusted
  evidence and cannot grant authority over product rules or actions.

- **AC37** — Existing explicit retry, regeneration, or refresh actions may remain
  available for recovery without remaining mandatory ceremony in the normal
  automatic path.

- **AC38** — No new candidate ranking, hiring recommendation, automatic
  rejection, or automatic hiring behaviour is introduced by this feature.

---

## Product edge cases

### New job created with only a title

Do not invent meaningful criteria from the job title alone.

The criteria state should explain that additional role context is required.

### AI unavailable when job is created

The job is still created.

The failure of automatic criteria preparation does not make the job invalid.

The operational state explains that criteria preparation could not complete and
provides the appropriate recovery path.

### AI allowance exhausted when job is created

No placeholder criteria are generated.

The workspace can resolve the allowance/provider condition and retry.

### Job edited while criteria preparation is running

A response describing superseded job context must not become current silently.

### Recruiter manually starts criteria generation while automatic preparation is
already running

The product must avoid presenting two competing current generations as valid.

### Job already has criteria

Do not automatically replace them merely because the job is later edited or
loaded under the new feature.

### Existing jobs after deployment

Deployment itself must not trigger bulk AI criteria generation across all
existing jobs.

Automatic work begins from meaningful product events, not from the feature being
installed.

### Application arrives during criteria preparation

The application waits for human-confirmed criteria.

Automatically generated criteria are insufficient until confirmation.

### Application evaluation fails

The candidate does not receive a negative fit signal.

The real failed state remains visible and may become an attention item according
to existing rules.

### Candidate is terminal when criteria are reconfirmed

Historical analysis remains historical.

The system does not automatically spend AI allowance to re-evaluate a recruitment
process that has already ended.

### Candidate is added from sourcing without application evidence

Do not reuse Potential Match as Application Fit.

Do not silently promote independent talent-pool CV material into application
evidence.

### Workspace has no talent pool

Confirmed criteria do not generate a meaningless "Find matches" action when no
eligible internal candidates exist.

### Talent pool contains thousands of candidates

The system can state that sourcing is ready.

It does not automatically authorize a potentially large AI spend.

### Sourcing is already processing

Do not offer another equivalent start action.

### Sourcing criteria become stale while search runs

Results from a superseded criteria revision must not become current.

### Talent pool changes after search completed

The existing result can remain historically understandable, but the product can
surface that a refresh is available.

### Search completes with zero suggested matches

Do not create a "matches ready for review" attention item.

### Search completes with many suggested matches

Create an aggregated job-level review gate rather than flooding the global
attention queue.

### Recruiter reviews all suggested matches

The derived "matches ready for review" item disappears naturally from current
state.

### Candidate becomes part of the job through another path

The sourcing experience and associated human-gate counts must reflect that the
candidate is no longer an unapplied sourcing opportunity for that job.

---

## Out of scope

This feature deliberately does not introduce:

- a generic AI agent that controls the whole product;
- a chat/copilot interface;
- natural-language product navigation;
- an autonomous recruiter;
- a user-configurable workflow builder;
- arbitrary automation rules;
- a visual automation builder;
- generic approval-chain infrastructure;
- automatic full internal sourcing scans;
- external candidate sourcing;
- LinkedIn, Indeed, GitHub, or people-data sourcing;
- candidate enrichment;
- automatic outreach;
- automatic email sending;
- automatic follow-up communication;
- candidate-reply interpretation;
- automatic interview scheduling;
- interview recording or transcription;
- automatic pipeline movement;
- automatic rejection;
- automatic hiring;
- automatic criteria confirmation;
- automatic replacement of human-edited criteria;
- learning new recruitment policy from recruiter outcomes;
- automatic changes to billing, plans, permissions, or workspace ownership;
- a new candidate-ranking model;
- a generic persistent task-management system;
- AI-generated attention prioritization;
- an autonomous background loop that repeatedly spends AI allowance until it
  achieves an outcome.

Those capabilities require their own deliberate product specifications when
needed.

---

## Expected product change

Before:

Recruiter creates job
→ recruiter asks AI for criteria
→ recruiter confirms
→ applications arrive/evaluate
→ recruiter remembers sourcing exists
→ recruiter enters Sourcing
→ recruiter starts search
→ recruiter returns later
→ recruiter reviews results.

After:

Recruiter creates job
→ Recruiter Labs prepares criteria
→ recruiter is asked only when criteria require human review
→ recruiter confirms
→ Recruiter Labs automatically handles eligible application evaluation
→ Recruiter Labs surfaces when sourcing is ready
→ recruiter authorizes the potentially expensive search
→ Recruiter Labs performs it
→ recruiter is surfaced when matches require human review.

The recruiter increasingly handles:

- judgment;
- ambiguity;
- authorization;
- exceptions;
- consequential decisions.

Recruiter Labs increasingly handles:

- preparation;
- processing;
- monitoring;
- derived operational state;
- remembering what should happen next.

---

## Success criteria

The feature is successful when a recruiter can create and operate a normal job
while performing fewer procedural actions without losing meaningful human
control.

Specifically:

- creating a sufficiently described job no longer normally requires a separate
  "generate criteria" ceremony;
- candidate evaluation remains automatic wherever its existing evidence and
  criteria gates allow it;
- the recruiter does not have to remember that sourcing has become available or
  stale;
- the recruiter does not have to monitor a sourcing background run to know that
  results are ready;
- high-cost or consequential operations still require deliberate authorization;
- human hiring decisions remain human;
- automatic AI work remains transparent, bounded, revision-safe, tenant-safe,
  and usage-governed.

---

## Related feature specs

- `../job-evaluation-criteria/spec.md`
- `../candidate-evaluation/spec.md`
- `../application-intake/spec.md`
- `../active-candidate-sourcing-foundation/spec.md`
- `../talent-pool-import-and-materials/spec.md`
- `../recruitment-attention/spec.md`
- `../ai-usage-and-limits/spec.md`
- `../recruitment-pipeline/spec.md`
- `../structured-interview-feedback/spec.md`
