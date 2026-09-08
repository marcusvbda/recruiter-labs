---
status: implemented
type: as-built
---

# Active Candidate Sourcing Foundation

## Problem

Recruiter Labs currently operates primarily as an inbound recruitment system.

A recruiter creates a job, publishes it, receives applications, evaluates those
applications, moves candidates through the pipeline, interviews them, and makes
the final hiring decision.

This works well for candidates who actively apply, but it leaves a significant
part of the hiring opportunity unused.

Over time, a workspace may accumulate hundreds or thousands of candidates from
previous recruitment processes. Many of those candidates may have been
unsuitable for the role they originally applied to while being highly relevant
for a different role later.

Without active sourcing, recruiters must manually remember, search, and inspect
those candidates one by one.

This creates several problems:

- valuable candidates become effectively invisible after their original process;
- recruiters repeatedly search outside the company before using talent they
  already know;
- a rejection or unsuccessful application can incorrectly become the end of the
  relationship with that candidate;
- historical CVs and application materials are difficult to reuse in a
  structured way;
- recruiters cannot start from a job and ask which known candidates may deserve
  attention for it;
- Recruiter Labs depends too heavily on passive candidate acquisition.

Recruiter Labs needs a sourcing capability that turns the workspace's existing
candidate history into a reusable private talent pool.

The system should help answer:

> "Who do we already know that may be worth reviewing for this role, and why?"

It must do this without treating an AI-generated match as a hiring decision or
pretending that missing information is negative evidence.

---

## Objective

Introduce the foundation for active candidate sourcing by allowing recruiters to
discover existing workspace candidates who may match a job's current
human-confirmed hiring criteria.

The feature should:

- search the workspace's existing candidate pool from the context of a job;
- evaluate available candidate-submitted information against that job's current
  confirmed criteria;
- surface potential matches with explainable evidence;
- distinguish potential match, evidence coverage, and confidence;
- clearly expose what is known and what remains unknown;
- preserve the provenance and age of the information being assessed;
- let recruiters save or dismiss sourcing matches;
- let recruiters explicitly add a matched candidate to the job;
- keep sourcing separate from formal applications and recruitment decisions.

This feature establishes the internal sourcing domain that future sourcing
features can extend with external providers, imported talent pools, and external
prospects.

The first version deliberately focuses on **talent rediscovery inside the
workspace**.

---

## Product principle

Active sourcing extends the existing Recruiter Labs product loop.

The current inbound loop is:

Job
→ Confirmed criteria
→ Application
→ Evidence
→ Uncertainty
→ Interview
→ Human evidence
→ Human decision

This feature introduces a sourcing loop before application:

Job
→ Confirmed criteria
→ Existing talent pool
→ Potential matches
→ Evidence and uncertainty
→ Human review
→ Add to job
→ Application
→ Existing recruitment workflow

Recruiter Labs does not decide who should be hired.

It helps recruiters discover people worth reviewing and explains why they may be
relevant.

---

## Terminology

### Candidate

A person already known to the workspace.

A Candidate may have:

- one or more previous applications;
- candidate-submitted CVs or documents;
- application answers;
- contact information;
- previous recruitment history.

A Candidate exists independently from a specific job.

### Sourcing Match

A job-specific relationship between an existing Candidate and a Job indicating
that Recruiter Labs found enough relevant information to consider the candidate
worth reviewing for that job.

A Sourcing Match is not an Application.

It represents:

> "This existing candidate may be relevant to this job based on the information
> currently available."

The same Candidate may have different Sourcing Matches for different jobs.

### Application

A formal participation of a Candidate in a Job's recruitment process.

A Candidate becomes an Application for the current job only after an explicit
human action adds them to the job or they enter through an existing application
flow.

### Prospect

A person discovered outside the workspace who is not yet a Candidate.

Prospects belong to future external sourcing work and are not introduced by this
feature.

---

## User behaviour

### Sourcing within a job

A recruiter working inside a job can access a dedicated **Sourcing** section.

The Sourcing section belongs to the job workspace alongside the other
job-specific operational areas.

It answers:

> "Which people already in our talent pool may be worth reviewing for this job?"

The recruiter does not have to manually create a sourcing query from scratch.

The job's current confirmed hiring criteria are the primary definition of what
the product searches for.

---

## Sourcing availability

Sourcing requires the job to have a current human-confirmed criteria revision.

If criteria:

- do not exist;
- are still being generated;
- are awaiting review;
- became stale because the job definition changed;

the product must not produce a current sourcing analysis.

Instead, the recruiter is told that the job criteria must be confirmed before
candidate sourcing can run.

Publishing the job publicly is not required for internal sourcing.

Recruiters may source candidates while preparing a hiring process before the job
is publicly advertised.

---

## Finding matches

When current criteria are confirmed, the recruiter can explicitly request:

**Find matches**

Recruiter Labs searches eligible existing Candidates in that workspace.

The recruiter does not need to construct Boolean search expressions or manually
translate the job description into a separate search definition.

The confirmed criteria already define the role Recruiter Labs is trying to
match.

The system examines the available candidate information and identifies
candidates for whom enough relevant information exists to justify recruiter
attention.

The system must not imply that candidates without enough information are poor
matches.

They are simply insufficiently known.

---

## Search progress

A sourcing search may require meaningful processing time for a large talent
pool.

The product therefore exposes clear operational states such as:

- not searched yet;
- searching;
- completed;
- failed;
- blocked;
- outdated.

A recruiter should never have to interpret a partially processed result set as a
completed search.

When a search cannot run because AI availability or workspace allowance prevents
it, the product shows that operational condition rather than generating fake
results or negative candidate signals.

---

## Search summary

After a sourcing run, the recruiter can understand what happened at a high level.

Useful context includes:

- how many existing candidates were considered;
- how many potential matches were surfaced;
- how many candidates could not be meaningfully assessed because available
  information was insufficient.

A candidate with insufficient evidence must not be described as having failed
the search.

For example:

> 1,248 candidates reviewed  
> 36 potential matches  
> 417 candidates had insufficient information for meaningful matching

Exact presentation may vary, but the distinction between **not enough
information** and **poor match** must remain clear.

---

## Potential match results

Each surfaced candidate provides enough information for the recruiter to decide
whether the person deserves further review.

A result should expose, when available:

- candidate identity;
- Potential Match;
- Evidence Coverage;
- Confidence;
- criterion-level support;
- important unknowns;
- evidence provenance;
- source age or historical context where relevant;
- previous recruitment interactions as separate context.

Example:

> Marcos Souza
>
> Potential Match: 86%
> Evidence Coverage: 72%
> Confidence: High
>
> Strong evidence
>
> - Laravel
> - PostgreSQL
> - AWS
>
> Needs validation
>
> - Distributed systems
> - English
>
> Previous interaction
> Backend Engineer — February 2026
> Reached Technical Interview

Previous interaction context must not be presented as proof that the candidate
fits the new role.

---

## Potential Match

Potential Match describes how the available assessable information relates to
the current job's confirmed criteria.

It is:

- job-specific;
- criteria-revision-specific;
- based only on criteria for which meaningful information is available;
- decision support for sourcing.

It is not:

- a hiring probability;
- an application score;
- a final candidate score;
- a prediction that the person will accept the job;
- a prediction that the person will perform well;
- evidence that the candidate is objectively better than another human being.

A Potential Match produced for one job cannot be reused as the Potential Match
for another job.

---

## Evidence Coverage

Evidence Coverage communicates how much of the weighted job criteria Recruiter
Labs could meaningfully assess using the candidate information available to the
workspace.

Missing information reduces coverage.

Missing information does not automatically reduce Potential Match.

For example:

Candidate A:

Potential Match: 92%
Evidence Coverage: 28%

Candidate B:

Potential Match: 84%
Evidence Coverage: 91%

The product must make it obvious that Candidate A is much less understood.

A high Potential Match with low Evidence Coverage must not be presented as a
high-certainty recommendation.

---

## Confidence

Confidence represents the strength, specificity, consistency, and relevant
freshness of the available support for the sourcing analysis.

Confidence remains separate from:

- Potential Match;
- Evidence Coverage;
- hiring probability;
- external verification.

Older information may still be useful evidence, but the product must preserve
enough source context for the recruiter to understand that it may no longer
describe the candidate's current situation.

---

## Criterion results

The sourcing analysis should remain explainable at criterion level.

For each relevant criterion, the recruiter can understand whether the available
candidate material provides:

- meaningful support;
- weak or partial support;
- conflicting information;
- insufficient information.

A criterion with insufficient information remains unknown.

Unknown does not become:

- zero;
- failure;
- negative evidence;
- an invented midpoint.

Criterion results must map to the exact currently confirmed criterion set for
the job.

---

## Evidence sources

This first sourcing version may use information the workspace already
legitimately holds for the Candidate.

Relevant candidate-submitted source material can include:

- CVs from previous applications;
- cover letters from previous applications;
- application answers;
- other candidate-submitted application documents;
- candidate profile information that is relevant to the criterion.

The product may reassess those source materials against the criteria of the new
job.

It must not simply reuse an old application's fit result as if it were valid for
the new role.

For example:

A candidate may previously have received:

> Application fit: 91% for Senior PHP Developer

That 91% has no authority over sourcing for:

> Engineering Manager

The underlying candidate-submitted information may be useful.

The previous score is not.

---

## Previous AI evaluations

Previous application-level:

- overall fit;
- criterion scores;
- evidence coverage;
- confidence;
- Interview Brief priority;

must not be copied into the new Sourcing Match as the current analysis.

The sourcing analysis must be contextual to the current job and its current
confirmed criteria.

Historical application evaluations may remain visible in the candidate's
historical recruitment record, but they are not the new job's sourcing result.

---

## Previous interview evidence

Structured interview feedback belongs to the hiring process and job in which it
was recorded.

Human interview evidence from a previous job must not silently become sourcing
evidence for another job.

Recruiter Labs may show that a previous recruitment interaction occurred, for
example:

- candidate previously applied;
- candidate reached interview;
- candidate was a finalist;
- candidate was hired, rejected, withdrawn, or otherwise reached a historical
  workflow outcome;

but that operational history remains separate from the current Potential Match.

A future feature may deliberately design a safe cross-role human evidence model
if product evidence justifies it.

This feature does not introduce one.

---

## Candidate identity and AI context

Candidate identity remains visible to authorized recruiters.

Where Recruiter Labs sends candidate material into AI-assisted sourcing
analysis, directly identifying information that the product can
deterministically remove should not be intentionally included merely because it
exists.

Examples include:

- candidate name;
- email address;
- phone number;
- stored social identifiers.

Identity reduction applies to the AI analysis context.

It does not hide the candidate's identity from the recruiter.

It is not a claim of complete anonymization or bias elimination.

---

## Recruitment history

Previous recruitment history may help the recruiter understand their existing
relationship with a candidate.

Useful historical context may include:

- previous jobs;
- dates of previous applications;
- previous workflow progress;
- last known recruitment interaction.

This history is contextual information.

It must not automatically increase or decrease Potential Match.

For example:

- having been referred does not increase fit;
- having previously reached a final interview does not increase fit;
- having previously been rejected does not decrease fit;
- having withdrawn from another role does not decrease fit.

Different jobs can legitimately produce very different outcomes for the same
person.

---

## Default result ordering

Unlike the operational recruitment pipeline, sourcing is explicitly a discovery
surface.

Its default ordering may prioritize candidates who appear most useful to review
for the current role.

However, ranking must not present sparse evidence as strong certainty.

Potential Match must therefore never be interpreted without Evidence Coverage
and Confidence.

The product must avoid a situation where a candidate with extremely little
assessable information is presented as obviously superior solely because the
few criteria that could be assessed happened to fit well.

Exact ranking mechanics are not part of the product contract, but the observable
result must preserve the distinction between fit and certainty.

Changing sourcing ordering must never affect:

- pipeline stage;
- application evaluation;
- hiring status;
- recruiter decisions.

---

## Match lifecycle

A current Sourcing Match can have a recruiter-facing state such as:

### Suggested

Recruiter Labs surfaced the candidate for review.

No human sourcing decision has yet been recorded.

### Saved

A recruiter explicitly marked the candidate as worth keeping for this job.

Saving does not create an Application.

### Dismissed

A recruiter explicitly decided that this candidate should no longer appear in
the active sourcing list for this job.

Dismissal:

- belongs only to this job;
- does not delete the Candidate;
- does not reject an Application;
- does not affect the Candidate in other jobs;
- is not training data that automatically changes future AI behaviour.

Dismissed candidates remain recoverable through the sourcing experience rather
than being permanently lost.

### Added to job

A recruiter explicitly added the Candidate to the job's recruitment process.

The existing recruitment workflow then becomes authoritative.

The sourcing system no longer treats that person as an unapplied sourcing
opportunity for the job.

---

## Adding a matched candidate to the job

A recruiter can explicitly choose **Add to job** from a sourcing result.

This must reuse the existing product semantics for adding an existing workspace
candidate to a job.

The action:

- creates no duplicate Candidate;
- creates no duplicate Application;
- respects workspace and job application constraints;
- places the Candidate into the job's normal recruitment workflow;
- uses the job's initial configured pipeline stage;
- allows the existing application/evaluation workflow to proceed normally.

Sourcing does not create a special alternative type of Application.

After entering the job, the person is handled by the same recruitment workflow
as any other candidate in that job.

---

## Human control

Recruiter Labs may:

- find potential matches;
- explain evidence;
- expose uncertainty;
- order sourcing results;
- suggest who may deserve attention.

Recruiter Labs must not automatically:

- create an Application;
- contact the candidate;
- move the candidate through a pipeline;
- reject the candidate;
- hire the candidate;
- schedule an interview;
- change job criteria;
- change candidate data based on an inferred fact.

Those remain explicit human or separately defined workflow actions.

---

## Refreshing sourcing results

A sourcing result belongs to the exact confirmed criteria revision used to
produce it.

If evaluation-relevant job criteria change:

- existing sourcing analysis becomes outdated;
- the previous analysis remains recognizable as historical/outdated;
- it must not present itself as current;
- a new sourcing analysis must use the newly confirmed criteria revision.

Criteria must be confirmed again before current sourcing can resume when the
existing job-criteria rules require reconfirmation.

A refresh must not create duplicate sourcing relationships for the same
Candidate and Job.

---

## Saved and dismissed candidates after refresh

Recruiter sourcing decisions are distinct from AI analysis.

If a recruiter previously saved or dismissed a candidate for the job, refreshing
the AI match does not silently erase that human action.

A saved candidate remains saved.

A dismissed candidate remains available in the dismissed state and is not
silently returned to the active suggestion list.

The recruiter may explicitly restore a dismissed candidate if they want to
reconsider the person.

If the candidate has entered the job through another path, the sourcing
experience reflects that they are already part of the recruitment process.

---

## Re-running a search

Recruiters may refresh sourcing when they want updated results.

Re-running sourcing may:

- discover newly created Candidates;
- reassess Candidates with newly available candidate-submitted information;
- update current Potential Match analysis;
- update Evidence Coverage and Confidence;
- identify different candidates as worth reviewing.

It must not:

- duplicate Candidates;
- duplicate Applications;
- silently undo human save/dismiss decisions;
- overwrite historical application evaluations;
- change pipeline state.

---

## Insufficient information

Some existing Candidates may have little or no reusable candidate-submitted
material.

For example, a Candidate may contain only:

- a name;
- an email address;
- minimal contact data.

Recruiter Labs must not fabricate a Potential Match from identity or contact
information.

If there is not enough meaningful job-relevant information to assess the person,
the candidate is treated as insufficiently known.

The search summary may communicate this limitation.

Insufficient information is not negative candidate evidence.

---

## Stale candidate information

Historical candidate information may no longer reflect the person's current
situation.

A CV from three years ago may still establish that a person had a certain
experience at that time.

It does not necessarily establish:

- their current employer;
- their current location;
- their current seniority;
- their current availability;
- their current interests.

When source age materially affects interpretation, the product should preserve
that context rather than presenting old information as newly verified fact.

---

## Workspace isolation

Internal sourcing is strictly workspace-scoped.

A workspace may search only Candidates that belong to that workspace.

Candidate information from one workspace must never become available as sourcing
data for another workspace merely because Recruiter Labs hosts both companies.

This feature does not introduce a global Recruiter Labs candidate marketplace.

A Candidate known to Company A is not automatically discoverable by Company B.

---

## AI usage and availability

AI-assisted sourcing must respect the workspace's existing AI availability and
usage rules.

If a sourcing analysis cannot run because:

- the workspace's applicable AI allowance is exhausted;
- the configured provider is unavailable;
- the configured credentials cannot be used;
- another existing AI operational restriction applies;

the product exposes an operationally blocked or failed state.

It does not:

- generate placeholder match scores;
- reduce candidate fit;
- mark candidates as poor matches;
- create negative evidence.

Previously valid current sourcing results may remain visible with their proper
state and provenance rather than being replaced by fabricated output.

---

## User flow

### 1. Recruiter opens a job

The recruiter opens the existing job workspace.

The job has a dedicated Sourcing section.

### 2. Product checks criteria readiness

If the current job criteria are not human-confirmed, sourcing explains that the
criteria must first be reviewed and confirmed.

No current sourcing analysis runs.

### 3. Recruiter requests matches

The recruiter selects **Find matches**.

Recruiter Labs begins reviewing eligible existing workspace Candidates against
the current confirmed criteria.

### 4. Search completes

The product presents a search summary and a set of potential matches.

Candidates with insufficient meaningful information are not treated as failed
matches.

### 5. Recruiter reviews a potential match

The recruiter can inspect:

- Potential Match;
- Evidence Coverage;
- Confidence;
- criterion-level support;
- important unknowns;
- source provenance;
- historical interaction context.

### 6. Recruiter chooses what to do

For a suggested candidate, the recruiter may:

- save;
- dismiss;
- open the candidate record;
- add the candidate to the job.

No workflow action occurs merely because the candidate appeared in sourcing.

### 7. Candidate is added to the job

If the recruiter selects **Add to job**, Recruiter Labs uses the existing
candidate-to-job workflow.

The Candidate receives a normal Application in the job's initial pipeline stage.

The standard application evaluation and recruitment workflow then apply.

### 8. Job criteria later change

If the job's evaluation contract changes, sourcing results based on the old
criteria revision become outdated.

Once the new criteria revision is human-confirmed, the recruiter can refresh
sourcing against the new definition.

---

## Business rules

This feature is governed by the existing recruitment workflow and evaluation
integrity principles.

### Confirmed criteria are authoritative

Sourcing can produce a current match only against the job's current
human-confirmed criteria revision.

AI-generated criteria suggestions alone are not sufficient.

### Potential Match is job-specific

A sourcing match for Job A cannot become the sourcing match for Job B.

Each role must be assessed in its own confirmed criteria context.

### Unknown is not failure

Missing information reduces Evidence Coverage.

It does not automatically reduce Potential Match.

### Confidence is separate

Confidence does not replace Potential Match or Evidence Coverage.

### Evidence must remain traceable

Supporting evidence must preserve enough source context for the recruiter to
understand where the information came from.

### Historical scores are not current scores

Previous Application fit, criterion scores, confidence, or coverage must not be
reused as if they were the new job's sourcing analysis.

### Historical human interview evidence remains job-bound

Structured interview feedback from another hiring process must not silently
become evidence for the current sourcing match.

### Source does not affect qualification

Referral status, UTM source, application channel, or similar acquisition
provenance must not increase or decrease Potential Match.

### Previous workflow outcome does not determine qualification

A previous rejection, withdrawal, final-stage outcome, or other historical
pipeline state must not automatically increase or decrease Potential Match.

### AI never owns the recruitment decision

Sourcing suggestions do not create recruitment outcomes.

### Sourcing is private to the workspace

The feature does not create a cross-company candidate database.

### Candidate-controlled content is untrusted input

Candidate-submitted documents and text are evidence to assess, not instructions
that can alter Recruiter Labs' evaluation rules.

### Human sourcing actions are preserved

Save and dismiss decisions are not silently overwritten by later AI refreshes.

---

## Acceptance criteria

- **AC01** — A job exposes a dedicated Sourcing experience from its job
  workspace.

- **AC02** — Current sourcing cannot run until the job's current evaluation
  criteria revision has been explicitly human-confirmed.

- **AC03** — Public job publication is not required for internal talent
  rediscovery.

- **AC04** — A recruiter can explicitly request Recruiter Labs to find potential
  matches from existing Candidates belonging to the current workspace.

- **AC05** — Internal sourcing never searches Candidates belonging to another
  workspace.

- **AC06** — A sourcing result represents a relationship between an existing
  Candidate and the current Job and does not itself create an Application.

- **AC07** — Potential Match is calculated in the context of the current job and
  cannot simply reuse a fit score produced for another job.

- **AC08** — Sourcing results distinguish Potential Match from Evidence Coverage.

- **AC09** — Sourcing results distinguish Confidence from both Potential Match
  and Evidence Coverage.

- **AC10** — Missing candidate information can remain unknown rather than
  becoming a zero, failure, midpoint, or invented negative signal.

- **AC11** — A candidate with insufficient meaningful information is not
  represented as a poor match merely because the workspace lacks evidence.

- **AC12** — Criterion-level sourcing analysis maps to the current job's exact
  confirmed criteria set.

- **AC13** — Supporting sourcing evidence preserves enough provenance for the
  recruiter to understand which candidate-submitted source supports the result.

- **AC14** — Previous application fit, criterion scores, evidence coverage,
  confidence, or Interview Brief results are not copied into the current job as
  the current sourcing analysis.

- **AC15** — Structured interview feedback recorded for another job does not
  silently become sourcing evidence for the current job.

- **AC16** — Previous application history can be displayed as contextual
  recruitment history without automatically influencing Potential Match.

- **AC17** — Referral status and application acquisition source do not increase
  or decrease Potential Match.

- **AC18** — Previous rejection, withdrawal, finalist status, or other workflow
  outcome does not automatically increase or decrease Potential Match.

- **AC19** — Direct candidate identifiers that can be deterministically removed
  are not intentionally included in the AI sourcing-analysis context merely
  because they exist in the recruiter-facing Candidate record.

- **AC20** — A recruiter can explicitly save a suggested candidate for the
  current job without creating an Application.

- **AC21** — A recruiter can explicitly dismiss a suggested candidate for the
  current job without deleting the Candidate or affecting that person in other
  jobs.

- **AC22** — A dismissed sourcing match remains recoverable for explicit human
  reconsideration.

- **AC23** — A recruiter can explicitly add a sourcing match to the current job
  when existing application rules allow it.

- **AC24** — Adding a sourcing match to the job reuses the normal recruitment
  workflow and creates no special sourcing-only Application type.

- **AC25** — Adding a candidate to the job does not create a duplicate Candidate
  or duplicate Application.

- **AC26** — A Candidate added from sourcing enters the job's normal initial
  pipeline stage according to existing recruitment workflow rules.

- **AC27** — Surfacing, saving, or ranking a sourcing match never automatically
  advances, rejects, hires, contacts, or schedules the candidate.

- **AC28** — Changing the job to a new evaluation-relevant criteria revision
  prevents sourcing analysis from the previous revision from presenting itself
  as current.

- **AC29** — A sourcing refresh uses the newly current confirmed criteria and
  does not create duplicate Candidate/Job sourcing relationships.

- **AC30** — Refreshing sourcing does not silently remove recruiter save or
  dismiss decisions.

- **AC31** — A Candidate who already has an Application for the current Job
  cannot be added to that Job again through sourcing.

- **AC32** — The sourcing experience clearly distinguishes searching, completed,
  failed, blocked, and outdated states where those states occur.

- **AC33** — AI allowance or provider failure does not create fake match scores
  or negative candidate evidence.

- **AC34** — The search summary can communicate that some candidates were
  insufficiently known without categorizing them as failed candidates.

- **AC35** — Information age remains discoverable when historical source context
  matters to understanding a sourcing result.

- **AC36** — Default sourcing discovery may prioritize promising candidates, but
  sparse evidence must not be presented as equivalent to strong certainty and
  Potential Match remains visibly accompanied by Evidence Coverage and
  Confidence.

- **AC37** — Sourcing ordering never changes the operational ordering or state of
  existing Applications in the recruitment pipeline.

- **AC38** — Candidate-controlled text used by sourcing remains evidence to
  assess and cannot grant authority over product rules, recruitment actions,
  tenant state, permissions, or other system behaviour.

---

## Product edge cases

### Workspace has no existing Candidates

The Sourcing section explains that no internal talent pool exists yet.

It does not show a broken or empty ranking experience.

Future candidate imports and inbound applications can populate the talent pool.

### Criteria are waiting for confirmation

Sourcing remains blocked until a human confirms the current criteria revision.

### Candidate has only contact information

Name, email, phone number, or social identifiers alone are not enough to create
a meaningful Potential Match.

The candidate remains insufficiently known.

### Candidate has multiple historical applications

Relevant candidate-submitted source material may be assessed against the new
job.

Historical application scores remain attached to their original applications
and are not reused as the new job's result.

### Candidate was rejected from another role

The rejection is historical process context.

It is not negative sourcing evidence for the new role.

### Candidate withdrew from another role

The withdrawal does not automatically make the person a poor match for the new
role.

It may remain useful recruiter context.

### Candidate previously reached a final stage

The previous progress does not automatically increase Potential Match.

### Candidate has previous interview feedback

The interview feedback remains attached to the job and interview in which it was
recorded.

It does not silently become evidence for the new sourcing analysis.

### Candidate is already in the current job

The candidate cannot be added again.

The sourcing experience should reflect that the person already belongs to the
current recruitment process rather than presenting a duplicate action.

### Candidate enters the job while sourcing is running

Once the product recognizes that an Application now exists, the candidate must
not remain actionable as an unapplied sourcing match.

### Criteria change while sourcing is running

A result based on a superseded criteria revision must not become the current
sourcing result.

### Criteria change after candidates were saved

Saved state remains a human sourcing decision.

The associated match analysis becomes outdated until refreshed against the new
confirmed criteria.

### Dismissed candidate becomes stronger after new information appears

The product does not silently override the human dismissal.

The recruiter can explicitly revisit dismissed candidates.

### Candidate information is old

Historical evidence remains dated context.

The product must not present it as externally verified current information.

### AI allowance is exhausted

The sourcing operation is blocked according to existing AI usage rules.

Candidates do not receive negative signals because the workspace could not run
AI.

### AI operation fails

The search exposes a failure state and permits appropriate recovery.

Partial or fabricated results must not be represented as a completed analysis.

### Candidate is removed from the workspace

A removed Candidate must no longer remain actionable as an active sourcing
candidate.

Historical records, retention behaviour, and deletion semantics remain governed
by the product's data-lifecycle rules.

---

## Out of scope

This feature deliberately does not include:

- LinkedIn sourcing;
- Indeed sourcing;
- GitHub sourcing;
- external people-data providers;
- open-web candidate discovery;
- web crawling or scraping;
- external Prospect records;
- cross-company candidate discovery;
- a global Recruiter Labs candidate marketplace;
- candidate enrichment from external profiles;
- automatic external identity resolution;
- importing candidates from CSV or another ATS;
- Manatal migration tooling;
- automatic candidate outreach;
- email sequences;
- automatic invitations to apply;
- automated LinkedIn messages;
- candidate availability prediction;
- salary prediction;
- protected-characteristic inference;
- automatic hiring recommendations;
- automatic rejection;
- automatic pipeline movement;
- automatic interview scheduling;
- automatic creation of Applications from AI results;
- reuse of previous interview feedback as evidence for a different job;
- a sourcing CRM;
- a general Boolean-search language;
- a LinkedIn Recruiter replacement;
- a job-board marketplace;
- changes to the public careers experience;
- changes to billing or subscription plans.

External sourcing will build on this foundation in a separate feature.

---

## Future extension points

The product model introduced here should allow future features to extend sourcing
without changing the meaning of the internal workflow.

Expected future capabilities include:

### Talent pool import and migration

Existing candidate databases can be imported into the workspace and then become
eligible for internal rediscovery.

### External candidate sourcing

Recruiter Labs can query approved external sourcing providers and normalize
external people into a common sourcing experience.

### Prospect management

Externally discovered people can exist as Prospects before the recruiter decides
to add them to the workspace's Candidate pool.

### Candidate profile enrichment

Recruiter Labs can discover possible external professional profiles and allow a
human to confirm identity before incorporating new information.

### Candidate outreach

Recruiters can contact sourced candidates and invite them into the formal
application process.

These capabilities must extend the sourcing foundation rather than create
parallel recruitment systems.

---

## Related feature specs

- `../job-workspace/spec.md`
- `../job-evaluation-criteria/spec.md`
- `../candidate-evaluation/spec.md`
- `../application-intake/spec.md`
- `../recruitment-pipeline/spec.md`
- `../structured-interview-feedback/spec.md`
- `../ai-usage-and-limits/spec.md`
- `../workspace-team-access/spec.md`
