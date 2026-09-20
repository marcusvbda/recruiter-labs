---
status: planned
type: product
---

# Core recruiting productivity

## Problem

Recruiter Labs has accumulated meaningful recruiting capabilities, but the core
recruiter experience is becoming harder to operate than the work it is supposed
to simplify.

The product can already create Jobs, maintain Candidates and Applications,
evaluate application evidence, generate interview guidance, source candidates,
manage pipelines, schedule interviews, communicate with candidates, track AI
usage, and surface operational attention.

The problem is no longer missing capability.

The problem is operational friction.

A recruiter handling tens or hundreds of Jobs and hundreds or thousands of
Candidates cannot afford to operate AI as a sequence of features, confirmations,
tabs, drafts, status screens, and repeated navigation.

If reviewing one candidate inside Recruiter Labs takes more effort than opening a
CV, making a judgment, and moving on, the product has failed even when every
individual feature is technically correct.

Several current behaviours contribute to that risk:

- one Application exposes too many separate surfaces for information that belongs
  to one review decision;
- the same uncertainty can appear in Evaluation, Interview Brief, and Interviews;
- communication has accumulated workflow concepts that are harder than simply
  sending the right message;
- safe AI work sometimes still requires the recruiter to start or confirm work
  that the system already knows how to perform;
- AI is present in the product but its work is not visible enough for the
  recruiter to perceive the operational leverage they are receiving;
- the Dashboard shows product state but does not clearly demonstrate how much
  recruiting work Recruiter Labs is completing on the user's behalf;
- existing AI output and operational concepts can dominate screens instead of
  helping the recruiter reach the next human decision quickly.

Recruiter Labs must become easier to use as it becomes more capable.

The product should feel like an additional recruiting operator working beside the
recruiter, not like an AI toolbox the recruiter has to operate.

---

## Objective

Reset the core recruiter experience around one outcome:

> Recruiter Labs should reduce the amount of human operation required to move a
> hiring process forward while keeping consequential recruitment decisions in
> human hands.

This feature must make Recruiter Labs materially faster and simpler to operate by:

1. simplifying the Job and Application workspaces around the recruiter's real
   workflow;
2. making safe, required AI processing automatic rather than button-driven;
3. removing routine criteria-confirmation ceremony from the normal flow;
4. making AI work visibly active, understandable, and measurable while it runs;
5. giving the recruiter a fast sequential candidate-review path instead of
   forcing repeated navigation;
6. removing duplicated or low-value information from the main decision surface;
7. replacing the current recruiter-message workflow with a simple reusable email
   template system plus a normal freeform composer;
8. preserving useful automatic pipeline-status emails through the same reusable
   template system;
9. using notifications only when they allow the recruiter to stop monitoring a
   background operation and continue working elsewhere;
10. reshaping the Dashboard around human attention, AI work, recruiting progress,
    and measurable productivity;
11. preserving evidence integrity, tenant isolation, candidate communication
    safety, and human control over hiring decisions.

This is intentionally a cross-cutting product reset.

It is not a request to add another layer of features on top of the current UX.
Existing behaviour should be removed, merged, demoted, or rewritten when doing so
makes the recruiter faster.

---

## Product thesis

The primary value proposition is not:

> Recruiter Labs gives you more recruiting information.

It is:

> Recruiter Labs performs safe recruiting work continuously, organizes the
> result, and brings the recruiter in only when human judgment or authorization
> is actually needed.

A recruiter should perceive three different kinds of product behaviour:

### Recruiter Labs is working

The system is performing safe background work such as preparing criteria,
evaluating application evidence, or processing an explicitly authorized sourcing
operation.

The recruiter does not need to supervise it.

### Recruiter Labs needs you

A human judgment, missing input, recovery action, or consequential decision is
required.

This belongs in Recruitment Attention or an equivalent contextual human gate.

### Recruiter Labs has completed work for you

The system finished useful work and the result is available.

Most routine completions should simply update the relevant surface and AI
Activity. Only completions worth interrupting the user should also generate a
notification.

These concepts must remain distinct.

---

## Core interaction rule

Every recurring recruiter interaction in the core flow must satisfy at least one
of these tests:

1. the recruiter is providing information Recruiter Labs does not already know;
2. the recruiter is making a human recruitment judgment;
3. the recruiter is authorizing an external, costly, or consequential action.

If an interaction does none of those things, it should normally be removed.

Examples of interactions that should disappear from the happy path:

- clicking Generate merely because a Job already contains enough information for
  required criteria preparation;
- clicking Evaluate candidate when the Application is already eligible for
  evaluation;
- confirming AI-generated criteria solely to allow the product's normal
  evaluation machinery to operate;
- asking the product to generate interview questions that can already be derived
  from the current evaluation;
- refreshing the page to discover whether background work completed;
- navigating through multiple tabs to find information that belongs to the same
  candidate-review decision.

Recovery and explicit reprocessing actions may still exist when something failed,
changed, or genuinely needs to be rerun.

---

# AI-native operating model

## AI is an operating layer, not a feature menu

Recruiter Labs should not present the normal AI workflow as:

Human asks AI
→ AI returns something
→ human starts the next AI task.

For safe and required derived work, the normal pattern becomes:

Recruitment state changes
→ Recruiter Labs recognizes useful work
→ work is scheduled automatically
→ work becomes visible in AI Activity
→ result appears in context
→ recruiter intervenes only where needed.

The presence of AI must remain explicit.

The recruiter should understand which content or work was AI-generated or
AI-assisted, but should not have to manually operate AI for normal processing.

---

## Automatic AI work

The following work belongs to the automatic happy path when its required inputs
exist and normal usage/provider rules permit execution:

- initial Job evaluation criteria preparation;
- normal Application evaluation against the current criteria;
- automatic reevaluation of eligible active Applications after the recruiter
  changes the authoritative criteria;
- Interview Brief preparation as part of the candidate evaluation flow;
- existing bounded derived recruiting work that is already defined as automatic
  by the product.

Automatic work must remain:

- tenant-safe;
- idempotent from the user's perspective;
- revision-aware;
- subject to AI allowance/provider rules;
- visible while queued, working, blocked, failed, or completed;
- incapable of hiring, rejecting, or automatically advancing a candidate.

---

## Explicit or pre-authorized work

Automation does not remove meaningful authorization.

The following remain explicit unless another approved product rule already
provides durable authorization:

- moving an Application to another pipeline stage;
- rejecting, hiring, or closing an Application;
- scheduling, rescheduling, or cancelling an interview;
- sending an ad-hoc candidate message;
- starting a potentially large or optional sourcing sweep;
- changing workspace configuration;
- changing billing, permissions, plans, or provider credentials.

A recruiter may provide durable authorization through configuration where the
purpose is clear.

Example:

> When an Application enters Hired, automatically send the selected Welcome
> template.

Once that rule is deliberately configured, the recruiter should not have to
confirm the same email again on every stage transition.

---

## AI suggestions versus authoritative product data

AI may safely populate derived data that exists specifically so Recruiter Labs can
perform its normal work.

It must not silently rewrite unrelated recruiter-authored business content merely
because it has an opinion about it.

For example:

- evaluation criteria can be prepared automatically because they are required by
  the candidate-evaluation feature;
- a candidate evaluation can be produced automatically because it is derived
  decision support;
- Recruiter Labs must not silently rewrite a Job description because AI believes
  another description would be better.

Standalone AI Job Review advisory cards are not part of the core recruiter
experience after this feature.

Recruiter Labs should not spend screen space or AI usage producing general
advisory content that does not clearly reduce recruiter work.

---

# Evaluation criteria without confirmation ceremony

## Normal first-generation behaviour

When a Job has enough substantive role context to support evaluation criteria,
Recruiter Labs automatically prepares its initial criteria.

When that generation completes successfully:

- the criteria become the Job's current evaluation criteria without a separate
  Confirm criteria action;
- the criteria are clearly identified as AI-generated;
- the recruiter can immediately review and edit them;
- eligible Applications can immediately enter the normal evaluation flow;
- the recruiter is not forced to visit the criteria screen before normal
  evaluation can begin.

The product should not treat routine confirmation as evidence of meaningful human
judgment when the recruiter is merely clicking a required button to unlock the
system.

---

## Human editing

The recruiter remains able to:

- edit criterion wording;
- add or remove criteria;
- change weights or importance where supported;
- explicitly rebuild criteria when a fresh AI proposal is wanted.

A saved human edit becomes authoritative immediately.

It does not require a second confirmation action.

When authoritative criteria change:

- existing evaluations against the prior revision stop presenting themselves as
  current;
- eligible active Applications automatically enter reevaluation;
- terminal Applications preserve their historical evaluation and do not consume
  new AI usage merely because the Job criteria changed.

The existing Fit, Coverage, Confidence, evidence, unknown, and revision-integrity
rules remain binding.

---

## Later Job changes

Later edits to a Job must not silently overwrite recruiter-edited criteria.

The product should distinguish:

- initial criteria preparation, which is automatic;
- recruiter edits to the criteria, which become authoritative immediately;
- an explicit Rebuild criteria action, which deliberately requests a new AI
  criteria set;
- Job edits that may make the current criteria worth reviewing.

A Job edit may surface that the current criteria deserve review, but it must not
silently replace human-edited criteria merely because the Job description
changed.

Routine Job editing must not reintroduce a global Confirm criteria gate.

---

## Criteria failure and missing context

If criteria cannot be prepared because the Job lacks substantive context:

- no generic criteria are invented;
- the recruiter receives a clear, actionable state explaining what is missing;
- candidate evaluation waits because there is no valid criteria set;
- the missing input may surface through Recruitment Attention when human action
  is required.

If criteria preparation fails:

- the failure is visible;
- retry remains available;
- no fake criteria are activated;
- Applications are not given fabricated evaluations.

---

# Candidate evaluation without AI operation ceremony

## Automatic evaluation

An eligible active Application with current criteria enters evaluation
automatically.

The recruiter should not normally encounter an Evaluate candidate or Generate
analysis step.

The system evaluates the submitted evidence, persists the result, and updates the
Application when complete.

Retry/reprocess remains a recovery action, not part of the happy path.

---

## Integrity remains unchanged

This simplification does not weaken evaluation integrity.

The existing evaluation-integrity rules remain authoritative, including:

- candidate-controlled text is evidence, not authority;
- unknown does not become zero or negative evidence;
- Fit, Coverage, and Confidence remain distinct concepts;
- source/referral does not improve candidate Fit;
- stale evaluation revisions cannot present as current;
- AI does not hire, reject, advance, or make the final recruitment decision;
- the default candidate order does not become highest-AI-score-first.

The recruiter experience may hide secondary complexity through progressive
disclosure, but the underlying semantic distinctions must remain correct.

---

# Core Job workspace simplification

## Job workspace purpose

The Job workspace is an operating surface, not another dashboard.

It should answer:

- who is in this hiring process;
- what needs human attention;
- where candidates are in the workflow;
- what optional sourcing work exists;
- what lightweight acquisition analytics are available.

It should not duplicate the Pipeline as an Overview visualization merely to have
another tab.

---

## Job navigation

For Jobs with Applications, the primary workspace sections become:

- **Pipeline**
- **Sourcing**
- **Analytics**

The existing permanent **Overview** tab is removed from the normal operational
workspace.

The Job header may retain a compact summary containing only information that
helps orient the recruiter, such as publication state, workflow, Application
count, interviewing count, finalist count, hired progress, and job-scoped
Attention.

Internal technical identifiers such as raw UUIDs must not appear in the normal
recruiter-facing header.

A Job with no Applications still opens into a useful empty Pipeline state with
clear existing next actions rather than requiring a separate permanent Overview
tab.

---

## Pipeline remains the operational center

The Pipeline remains human-controlled.

AI evaluation may be visible as supporting context but must not become the
board's default ordering or an automatic workflow decision.

Opening a candidate from Pipeline should create a clear review context that can
be continued to the next candidate without repeatedly rebuilding navigation.

---

# Application becomes the primary candidate-in-job decision surface

## Reduce the information architecture

The current Application experience is reduced to three primary sections:

- **Review**
- **Interviews**
- **Application**

The previous Summary and Evaluation concepts become one Review experience.

Documents become part of Application.

Communication does not become another permanent tab.

The recruiter should not need to understand the distinction between internal
product modules in order to answer a hiring question.

---

## Review

Review is the default Application surface.

Within a few seconds it should answer:

1. Where is this person in the process?
2. What does the submitted evidence currently support for this Job?
3. What important information is still unknown or weak?
4. What human action is available next?

Review should contain:

- candidate and Job identity;
- current pipeline stage;
- concise relevant process/interview state;
- current evaluation state;
- compact Fit and Evidence Coverage context when a current evaluation exists;
- criterion-level evidence/results in a scannable form;
- important unresolved areas;
- direct access to the candidate's primary submitted document when available;
- the primary human workflow actions.

Review must not require the recruiter to read multiple large cards that repeat the
same uncertainty in different language.

---

## Criterion presentation

Criterion presentation should preserve the domain semantics while reducing the
number of concepts the recruiter must process simultaneously.

A criterion that cannot currently be assessed should communicate an operational
state such as:

> Needs evidence

with a concise explanation.

An assessed criterion should show its result and supporting evidence without
requiring the recruiter to interpret several badges before understanding it.

Confidence and source detail may remain available through progressive disclosure
when they are not the primary thing the recruiter needs to decide.

The UI must not collapse unknown into low Fit or otherwise weaken the evaluation
integrity model for the sake of visual simplicity.

---

## Interview Brief belongs to Interviews

Interview Brief content must not be fully duplicated across Review and Interviews.

Review identifies what remains uncertain.

Interviews turns relevant remaining uncertainty into interview preparation.

The detailed Interview Brief lives in Interviews.

It is prepared automatically as part of the normal evaluation flow and should not
require a separate Generate interview questions action.

---

## Application section

Application contains the candidate-submitted record for this Job, including as
applicable:

- application answers;
- submitted contact/profile information;
- source/attribution context;
- cover letter;
- submitted documents and files.

A separate Documents tab is not required.

---

## Communication placement

The same communication-history block must not be rendered below every Application
section.

Sending a message is a contextual action.

The candidate's broader communication history belongs primarily to the global
Candidate record, where cross-Job history can be understood.

The Application may expose the Send message action in the current Job context
without turning communication into another mandatory review surface.

---

# Fast sequential candidate review

## Review queue context

Recruiter Labs must support the reality that recruiters review many candidates in
sequence.

Opening an Application from a Job Pipeline should preserve enough context to move
to the next relevant Application without returning to the Pipeline after every
candidate.

The recruiter should be able to:

- move to the previous or next candidate in the current review context;
- skip a candidate without mutating recruitment state;
- perform a stage move and continue to the next candidate through an explicit
  fast path such as Move & review next.

The exact wording may follow the existing product language, but the workflow must
reduce repeated navigation.

---

## Review order

Sequential review order must be deterministic and operational.

It must not silently become an AI ranking.

When the recruiter arrived from a particular Job stage or filtered Pipeline
context, next/previous navigation should preserve that context where possible.

If no meaningful review context exists, the product may fall back to the Job's
normal operational Application order.

---

# Candidate profile

The Candidate profile remains the global person-level record.

It should continue to provide the cross-Job view of:

- candidate identity/contact information;
- Applications;
- materials;
- communication history.

It should expose one clear **Send message** action.

Job-specific evidence and hiring decisions continue to belong to Application.

---

# Communication reset

## Product decision

The current recruiter-message workflow is replaced.

Recruiter Labs will not require the recruiter to understand message purposes such
as Initial outreach versus Follow-up in order to send an email.

The recruiter does not need a special AI outreach workflow for ordinary candidate
communication.

The new mental model is:

> Send message → optionally choose a template → edit if desired → Send.

This intentionally removes complexity even if existing code already supports
richer draft behaviour.

---

## Reusable email templates

A workspace can manage reusable candidate email templates in Settings.

Each template contains at minimum:

- a recruiter-facing template name;
- subject;
- body;
- workspace ownership;
- whether the template is currently available for use.

The editor clearly exposes the supported substitution variables so recruiters do
not have to memorize syntax.

At minimum, templates must support candidate name and Job/position name.

Existing safe template variables already supported by Recruiter Labs may remain
available when they are useful.

The template experience should make variables easy to insert or copy and should
provide a useful preview.

---

## Template resolution

When a recruiter selects a template from a candidate/application context:

- variables are resolved using the current candidate, Job, workspace, and
  Application context that actually exists;
- the resolved subject and body become visible editable composer content;
- the recruiter may freely edit the resolved text before sending;
- the final visible content is what is authorized and sent.

If a selected template requires context that is unavailable, the product must not
silently send broken or misleading content.

It should clearly identify the unresolved context and require the recruiter to
supply/select the missing context or remove the unresolved dependency before
Send becomes available.

---

## Freeform message

Templates are optional accelerators.

The recruiter can choose to write a message from scratch.

The composer then exposes normal editable Subject and Message fields.

A recruiter who wants to send:

> Hey, are you available for a quick conversation tomorrow?

should be able to do so without creating a template or invoking AI.

---

## Composer entry points

The primary action is consistently named **Send message**.

From an Application:

- Candidate and Job context are already known.

From a Candidate profile:

- the recruiter may select the relevant Job context when one is needed;
- when the action was opened from a Job-specific context, that Job should already
  be selected;
- a generic message may be sent without Job context when the template/content
  does not require Job variables.

Recipient and sending identity remain clearly visible before Send.

---

## Remove the current AI drafting workflow from the normal UX

The normal composer must not expose a required or prominent Prepare outreach,
Initial outreach, or Prepare follow-up workflow.

Recruiter Labs should not generate a second draft merely because another message
was sent.

There is no requirement in this feature to preserve AI-written outreach as a
visible product feature.

Existing successfully sent communication history must remain intact.

Existing unsent drafts must not be silently destroyed solely because the new
composer is simpler; they may be surfaced as recoverable legacy drafts without
reintroducing the old message-purpose workflow.

---

## Sending

Sending continues through the workspace's configured email provider.

The recruiter does not choose transport implementation details inside the
composer.

Send remains unavailable when the normal candidate-communication safety rules
prevent delivery, including invalid recipient, do-not-contact, tenant mismatch,
or unavailable configured provider.

The final sent subject/body remain immutable historical communication content.

A successful Send does not itself change pipeline stage or create a hiring
outcome.

---

# Pipeline status email automation

## Reuse the same templates

Pipeline stage communication remains useful, but its configuration becomes
simpler and uses the same reusable email templates.

A pipeline status may be configured with:

- Send email when candidate enters this status: on/off;
- Email template: one reusable template from the workspace.

The status itself should not require recruiters to maintain a separate duplicate
subject/body editor when the same content can be managed as a reusable template.

---

## Durable authorization

Configuring a status to send a particular template is the recruiter's durable
authorization for that automation.

When a human later moves an Application into that status:

- the normal validated stage transition occurs;
- the configured template is resolved for that candidate/Application;
- the email is sent automatically through the configured provider;
- another confirmation modal is not required.

The stage movement remains human-controlled.

The email automation does not grant AI authority to move the candidate.

---

## Existing configured status emails

Existing pipeline-status email content must not be silently lost when the product
moves to reusable templates.

The resulting user-visible behaviour must preserve equivalent configured email
content, whether it is converted into reusable templates or otherwise safely
carried forward during the transition.

A migration must not unexpectedly disable a previously configured stage email.

---

## Delivery failure

A candidate stage transition must not be rolled back merely because its configured
email failed to send.

Delivery failure is a communication/operational problem.

It should become visible through the appropriate communication history and, when
human intervention is useful, notification and/or Recruitment Attention.

It must not become candidate-quality evidence.

---

# Visible AI Activity

## Purpose

AI-native behaviour must be visible enough that the recruiter perceives the work
Recruiter Labs is doing on their behalf.

The product should create the feeling:

> Work is happening even while I am doing something else.

without fabricating activity or exposing internal engineering details.

---

## Persistent AI indicator

Authenticated recruiter pages expose a lightweight persistent RecruiterLabs AI
indicator in the top navigation area.

It should communicate a state such as:

- Working · 3
- Waiting · 2
- Up to date
- Blocked

without taking over the interface.

The indicator updates live while the user remains on the page.

Clicking it opens a compact AI Activity surface.

---

## AI Activity surface

The AI Activity surface shows real product work, not decorative animation.

It should expose:

### Working now

Current queued or active work, with understandable labels such as:

- **Criteria analyst** — Preparing criteria for Backend Engineer
- **Candidate reviewer** — Evaluating Sofia Martins for Engineering Team Lead
- **Talent matcher** — Reviewing the talent pool for Product Designer

Names should describe the recruiting job being performed.

Do not expose internal class names, queue names, prompt names, worker IDs, or
implementation architecture.

### Waiting / blocked

When useful, explain a real prerequisite or operational block in recruiter
language, such as:

- Waiting for Job criteria
- Waiting for AI allowance
- Could not complete candidate evaluation

Large groups should be aggregated where appropriate instead of creating one noisy
row per candidate.

### Recently completed

Show a bounded recent history of useful AI work, enough for the recruiter to see
that the system completed work while they were elsewhere.

Recent completion items may link directly to the relevant Job or Application.

---

## Truthfulness

AI Activity must be grounded in real operation state.

The product must not:

- show a fake agent as Working when no work exists;
- invent queue progress percentages it cannot actually measure;
- claim that work was completed when it failed or became stale;
- present a blocked operation as successful productivity.

If nothing is running, **Up to date** is a valid and useful state.

---

## AI-generated labels

Recruiter-facing content materially produced by AI should be identifiable as
AI-generated or AI-assisted at the appropriate section level.

The product should not add a distracting badge to every sentence.

Examples where visible provenance matters include:

- generated Job criteria;
- candidate evaluation;
- Interview Brief;
- sourcing analysis.

Human-authored interview evidence must remain visually and semantically distinct
from AI-generated evidence.

---

# Notifications

## Notification principle

Notifications exist so the recruiter does not have to babysit background work.

They are not a second Attention queue and should not fire for every AI operation.

A notification is appropriate when:

- the user could reasonably have left the originating screen;
- the event is worth knowing without reopening that screen manually;
- the message can provide a useful direct destination.

---

## Events that should notify

The first version should use notifications for a small set of meaningful events,
such as:

- candidate import completed when there is a useful result to review;
- candidate import failed;
- an explicitly started sourcing operation completed;
- sourcing failed or became blocked;
- candidate email delivery failed;
- interview declined or an equivalent meaningful candidate response/state change;
- a blocking AI operation failed in a way that requires human recovery.

Exact notification coverage may reuse existing domain events, but the resulting
experience must remain low-noise.

---

## Events that should not notify individually

Do not create one notification for every normal candidate evaluation completion.

At scale this would punish the recruiter for using the product successfully.

Routine high-volume completion belongs in AI Activity and in the updated
Application/Job state.

Notifications must never become candidate ranking or candidate-quality signals.

---

# Dashboard reset

## Dashboard purpose

The Dashboard should answer four questions in this order:

1. What needs my attention?
2. What is Recruiter Labs doing for me right now?
3. What useful work did Recruiter Labs complete for me?
4. Which hiring processes/interviews need normal operational awareness?

The Dashboard should not become a wall of disconnected statistics.

---

## Needs your attention

The existing deterministic Recruitment Attention model remains the primary human
work queue.

The Dashboard should give it strong visual priority.

Items that the system can now complete automatically should no longer remain as
human gates merely because the old workflow required a click.

In particular, routine criteria confirmation must disappear as an Attention task.

Attention should increasingly mean:

> Recruiter Labs cannot or should not finish this without you.

---

## AI work now

The Dashboard includes a compact RecruiterLabs AI section showing current work
and a direct path to the same AI Activity experience available from the top
navigation.

The Dashboard should not duplicate a long technical execution log.

---

## Productivity KPIs

The Dashboard should visibly demonstrate measured AI work that replaced recruiter
operation.

At minimum, for a clear recent period such as today or this week, it should be
able to show measured values such as:

- Applications evaluated automatically;
- AI work completed automatically;
- profiles analyzed by an explicitly run sourcing operation when applicable;
- current AI allowance usage/remaining capacity.

The copy should communicate completed work rather than model mechanics.

Prefer:

> 84 applications reviewed by RecruiterLabs AI this week

instead of:

> 184,233 tokens consumed.

Token/cost detail remains appropriate in AI Settings.

---

## Estimated manual review time avoided

Recruiter Labs may show an **Estimated review time saved** KPI only when the
workspace has an explicit manual-review baseline.

The baseline represents the workspace's own estimate of typical manual minutes
spent reviewing one Application.

The estimate must be transparent and calculated only from eligible completed
candidate evaluations that actually replaced normal manual-start evaluation
work.

If no workspace baseline exists:

- the product continues to show hard measured productivity counts;
- it must not fabricate a time-saved number;
- an unobtrusive path may allow an authorized workspace user to define the
  baseline.

The estimate must be labelled as an estimate, not measured fact.

Money-saved calculations are not part of this version.

---

## AI allowance visibility

AI capacity should be discoverable without forcing the recruiter to visit
Settings merely to learn whether automatic work can continue.

The normal interface should make current allowance/remaining capacity available
through the AI Activity/Dashboard experience.

Critical or exhausted allowance remains an operational warning.

Detailed provider/model/token accounting remains in AI Settings.

---

## Existing Dashboard content

Raw hiring counts may remain as compact supporting context, but they must not
crowd out:

- Attention;
- active AI work;
- demonstrated productivity;
- upcoming interviews / active hiring processes.

Duplicated or low-value cards should be removed rather than preserved simply
because they already exist.

First-workspace activation UI remains visible only while the workspace is truly
unactivated.

The activation model must be reconciled with the new criteria lifecycle so an
obsolete criteria-confirmation step does not keep a functioning workspace stuck
in onboarding.

A workspace that has already reached a valid candidate evaluation should not be
presented indefinitely as if it has not completed the core activation journey.

---

# Realtime and state freshness as product behaviour

Recruiters must not need to manually reload a page to see the result of an action
or a background operation.

The observable requirement is:

- actions performed by the current user update the affected UI immediately after
  success;
- relevant background work updates the open UI when its state changes;
- AI Activity and notification state update while the user remains in the
  product;
- the product does not rely on periodic visible staleness as part of the normal
  experience.

The existing realtime engineering convention remains the implementation
foundation, but this specification defines only the user-visible requirement.

---

# Human decision boundary

This feature intentionally increases automation while preserving the following
human boundaries.

Recruiter Labs must not automatically:

- reject a candidate;
- hire a candidate;
- advance or move a candidate to another pipeline stage based on AI evaluation;
- turn missing evidence into a negative candidate result;
- treat source/referral as candidate quality;
- send an ad-hoc recruiter message without explicit Send authorization;
- start large optional sourcing merely because it could;
- rewrite human interview evidence;
- merge AI evaluation and human interview evidence into a final automated hiring
  score;
- use protected/sensitive attributes as hiring criteria or message
  personalization.

The system should automate work around the human decision, not take ownership of
the decision.

---

# Success experience

## Normal Job-to-evaluation path

The desired happy path becomes:

Create Job with substantive role context
→ Recruiter Labs prepares criteria automatically
→ criteria become usable and visibly AI-generated
→ candidate applies
→ Recruiter Labs evaluates automatically
→ Review updates live
→ recruiter reads one decision surface
→ recruiter decides what to do
→ recruiter continues to the next candidate.

No routine AI-start button and no routine criteria-confirmation button are part of
this path.

---

## Normal candidate-review path

Job Pipeline
→ open candidate
→ Review
→ understand evidence / unknowns
→ Move stage, Schedule interview, Send message, Skip, or review next
→ next candidate.

The recruiter should not need to bounce among Summary, Evaluation, Documents,
Communication, and Interview Brief merely to decide what happens next.

---

## Normal messaging path

Send message
→ choose a template or write from scratch
→ review/edit final subject and body
→ Send.

No Initial outreach prerequisite.

No generated follow-up merely because an outreach was sent.

No second hidden draft workflow.

---

## Normal automated stage-email path

Configure reusable template once
→ assign template to pipeline status
→ human moves candidate into status
→ configured email sends automatically
→ delivery/history update without blocking the stage transition.

---

# Acceptance criteria

## Core productivity and simplification

- **AC01** — The primary recruiter workflow is evaluated against the rule that a
  recurring human interaction must provide missing information, make human
  judgment, or authorize an external/costly/consequential action; routine
  interactions that satisfy none of those purposes are removed from the happy
  path.
- **AC02** — A recruiter can review a candidate-in-job from one default Review
  surface without switching between separate Summary and Evaluation tabs.
- **AC03** — Application primary navigation contains no more than Review,
  Interviews, and Application as the normal core sections.
- **AC04** — Documents are available through Application and no separate
  Documents tab is required.
- **AC05** — Detailed Interview Brief content is not duplicated in both Review and
  Interviews.
- **AC06** — The same communication-history block is not rendered below every
  Application section.
- **AC07** — Internal technical identifiers such as raw Job UUIDs are absent from
  the normal recruiter-facing Job header.

## Criteria and automatic AI work

- **AC08** — A new Job with sufficient substantive role context automatically
  starts its initial criteria preparation without requiring a Generate action.
- **AC09** — Successful initial criteria preparation produces current usable
  criteria without requiring a separate Confirm criteria action.
- **AC10** — AI-generated criteria are visibly identified as AI-generated and
  remain editable by an authorized recruiter.
- **AC11** — Saving recruiter edits to the current criteria makes those edits
  authoritative without a second confirmation action.
- **AC12** — Changing authoritative criteria makes evaluations from the prior
  revision non-current and automatically schedules eligible active Applications
  for reevaluation.
- **AC13** — Terminal Applications are not automatically reevaluated merely
  because criteria changed.
- **AC14** — Missing substantive Job context does not produce invented generic
  criteria and instead exposes an actionable missing-context state.
- **AC15** — Criteria preparation failure does not activate fake criteria and has
  an explicit recovery path.
- **AC16** — Later Job edits do not silently overwrite recruiter-edited criteria.
- **AC17** — Explicit Rebuild criteria remains available when the recruiter wants
  a fresh AI-generated set.
- **AC18** — An eligible active Application with current criteria enters normal
  candidate evaluation automatically without an Evaluate candidate action.
- **AC19** — Interview Brief preparation occurs automatically as part of the
  supported evaluation flow and does not require a separate generate action.
- **AC20** — Retry/reprocess actions remain available for real recovery or stale
  state but are not required in the normal happy path.

## Evaluation integrity

- **AC21** — Simplified presentation does not collapse unknown evidence into low
  Fit or negative candidate evidence.
- **AC22** — Fit and Evidence Coverage remain semantically distinct, and
  Confidence remains available without being merged into either concept.
- **AC23** — Default candidate review/pipeline ordering does not become highest AI
  Fit first.
- **AC24** — AI evaluation does not automatically move, reject, hire, or close an
  Application.

## Job and Application workflow

- **AC25** — For a Job with Applications, Pipeline is the primary operational
  workspace and the permanent Overview tab is removed from the normal Job
  navigation.
- **AC26** — Job workspace still exposes Sourcing and Analytics without recreating
  Overview as a second card dashboard.
- **AC27** — A Job without Applications has a useful Pipeline empty state with
  relevant existing next actions.
- **AC28** — Review presents current stage, evaluation state, core evidence,
  important unresolved areas, and primary next actions in one scannable surface.
- **AC29** — Review provides direct access to the candidate's primary submitted
  document when one exists without requiring a dedicated Documents tab.
- **AC30** — Opening an Application from a Job review context allows the recruiter
  to navigate to the next/previous relevant Application without returning to the
  Pipeline after every candidate.
- **AC31** — Sequential review navigation preserves the originating Job/stage or
  equivalent operational context where possible and never implies an AI ranking.
- **AC32** — The recruiter can continue to the next candidate without changing
  the current candidate's state.
- **AC33** — A deliberate fast path allows a successful human stage move to be
  followed by review of the next candidate without reconstructing navigation.

## Communication

- **AC34** — Workspace Settings provide reusable candidate email templates with a
  name, subject, body, availability state, and visible supported variables.
- **AC35** — Template variables include at least candidate name and Job/position
  name.
- **AC36** — Selecting a template in a valid candidate context resolves its
  variables into visible editable subject/body content before Send.
- **AC37** — Missing context required by a selected template is visibly identified
  and cannot silently produce a broken outgoing message.
- **AC38** — A recruiter can send a freeform candidate message without selecting a
  template or invoking AI.
- **AC39** — The primary recruiter communication action is a simple Send message
  flow rather than a required Initial outreach / Follow-up workflow.
- **AC40** — The normal composer does not automatically create a second follow-up
  draft after a message is sent.
- **AC41** — The normal composer does not require AI draft generation in order to
  communicate with a candidate.
- **AC42** — Application messaging automatically has the current Job context;
  Candidate-profile messaging can select Job context when needed.
- **AC43** — Candidate messaging continues to use the workspace's configured
  outbound provider and existing send-safety rules.
- **AC44** — Existing successfully sent communication history is preserved and
  remains immutable.
- **AC45** — Existing unsent communication drafts are not silently destroyed by
  the UX reset.

## Pipeline email automation

- **AC46** — A pipeline status can be configured to send one selected reusable
  email template when an Application enters that status.
- **AC47** — A configured status-template mapping acts as durable authorization;
  no extra send confirmation is required after the human-authorized stage move.
- **AC48** — Existing configured pipeline-status email content is preserved
  through the transition to reusable templates.
- **AC49** — Failure to deliver a configured stage email does not roll back the
  candidate's validated stage transition.
- **AC50** — Delivery failure can surface as an operational communication problem
  without affecting candidate evaluation.

## AI Activity and visibility

- **AC51** — Authenticated recruiter pages expose a lightweight persistent
  RecruiterLabs AI indicator with a real current state such as working, waiting,
  up to date, or blocked.
- **AC52** — The AI indicator updates without a manual page refresh when relevant
  background state changes.
- **AC53** — Opening AI Activity shows bounded current work using recruiter-facing
  work names rather than internal queue/class/prompt names.
- **AC54** — AI Activity can distinguish meaningful queued/working,
  waiting/blocked, failed, and recently completed states without inventing
  progress it cannot measure.
- **AC55** — AI Activity items link to the relevant Job or Application when a
  meaningful destination exists.
- **AC56** — High-volume waiting/completion state may be aggregated to avoid one
  noisy activity item per candidate.
- **AC57** — The UI never displays fake working activity when no corresponding
  operation exists.
- **AC58** — Materially AI-generated recruiter-facing sections identify their AI
  provenance without adding a distracting badge to every individual sentence.

## Notifications

- **AC59** — Notifications are used for meaningful asynchronous completion or
  failure states that allow the recruiter to continue working elsewhere.
- **AC60** — Routine candidate-evaluation completion does not generate one
  notification per candidate.
- **AC61** — Candidate import completion/failure and explicitly started sourcing
  completion/failure can notify with a useful destination.
- **AC62** — Candidate email delivery failure and equivalent actionable
  communication failures can notify without becoming candidate-quality signals.

## Dashboard and measurable productivity

- **AC63** — The Dashboard gives Recruitment Attention stronger priority than
  non-actionable raw statistics.
- **AC64** — The Dashboard exposes current RecruiterLabs AI work without
  duplicating a technical execution log.
- **AC65** — The Dashboard shows a recent-period count of Applications evaluated
  automatically.
- **AC66** — The Dashboard shows a recent-period count of useful automatic AI work
  completed by Recruiter Labs.
- **AC67** — When applicable, the Dashboard can show profiles analyzed by an
  explicitly run sourcing operation as completed work rather than as a candidate
  ranking.
- **AC68** — Current AI allowance/remaining capacity is discoverable from the
  normal AI Activity/Dashboard experience, while detailed token/provider data
  remains in Settings.
- **AC69** — Estimated review time saved is shown only when a workspace-specific
  manual-review baseline exists and is visibly labelled as an estimate.
- **AC70** — Without a configured baseline, Recruiter Labs shows measured
  productivity counts and does not fabricate time-saved numbers.
- **AC71** — Existing raw hiring-count cards are reduced/demoted when they compete
  with Attention, AI work, productivity, interviews, or active hiring processes.
- **AC72** — First-workspace activation no longer depends on obsolete explicit
  criteria confirmation and does not remain persistently visible for a workspace
  that has already reached a valid candidate evaluation.

## State freshness and decision boundaries

- **AC73** — A successful user action updates the affected recruiter-facing state
  without requiring a manual browser refresh.
- **AC74** — Relevant background work updates open recruiter-facing state without
  requiring a manual browser refresh.
- **AC75** — No new automatic behaviour introduced by this feature can reject,
  hire, or advance a candidate based solely on AI output.
- **AC76** — No automatic AI or communication behaviour uses protected/sensitive
  attributes as candidate-quality evidence or personalization input.

## Source-of-truth reconciliation

- **AC77** — Existing as-built feature documentation that currently describes
  routine human criteria confirmation, the old candidate outreach workflow, or
  obsolete core navigation is reconciled with the delivered product behaviour so
  the repository does not finish with contradictory feature specifications.

---

# Product edge cases

## AI allowance exhausted during automatic evaluation

Automatic work may stop because the workspace reached its AI allowance.

The product must:

- preserve the candidate's prior valid state;
- show that the operation is blocked;
- avoid inventing an evaluation;
- surface recovery/upgrade/provider options through the existing AI usage model;
- never treat quota exhaustion as candidate evidence.

---

## Criteria edit while evaluations are running

Older in-flight results must not silently become current after a newer criteria
revision exists.

The current revision wins.

Automatic retry/reevaluation should converge on the current authoritative
criteria without presenting stale output as current.

---

## Candidate has no CV/document

Review still works from the evidence that actually exists.

The UI does not render a broken Open CV action.

Missing material remains missing evidence, not negative evidence.

---

## Candidate has multiple Applications

Global Candidate messaging/history remains person-centric.

Application Review remains Job-specific.

A message launched from an Application uses that Job context and does not
silently attach itself to another hiring process.

---

## Candidate has no Application but is in Talent Pool

The Candidate can still receive a recruiter-authored message when a valid
recipient/provider exists.

If a Job-specific template is selected, Job context must be selected/resolved.

Sending a message does not create a fake Application.

---

## Template removed after being assigned to a status

The product must not silently send different content.

The affected status configuration must become clearly invalid/incomplete until a
valid template is selected, or deletion must be prevented while the template is
in active use.

Exact implementation is a technical decision, but the recruiter must not lose
track of the automation.

---

## Stage email cannot resolve a variable

The stage transition remains valid.

The outgoing message must not send with broken unresolved content.

The communication failure/configuration problem becomes visible for human repair.

---

## Background operation becomes stale

AI Activity must reflect that the result did not become current when a newer
revision superseded it.

A stale operation is not counted as useful completed productivity merely because
provider usage occurred.

---

## Realtime connection unavailable

Temporary loss of live updates must not corrupt recruitment state.

When live connectivity returns or the user navigates/reloads normally, the
product must reconcile to current persisted state rather than assuming every
intermediate event was received.

---

# Out of scope

This feature deliberately does not add:

- Directed Evidence Resolution / Ask candidate;
- automatic candidate rejection;
- automatic hiring;
- automatic pipeline advancement based on AI;
- default AI-score candidate ranking;
- bulk AI-driven hiring decisions;
- a generic AI chat box;
- a generic agent builder;
- autonomous rewriting of Job descriptions;
- standalone Job Review advisory cards;
- inbound email synchronization or a full inbox;
- SMS, WhatsApp, LinkedIn, or other new communication channels;
- drip campaigns or marketing automation;
- arbitrary workflow-rule builders;
- a new sourcing architecture;
- automatic large sourcing sweeps;
- interview recording/transcription/notetaking;
- final AI hiring recommendations;
- money-saved ROI calculations;
- general-purpose BI/report building;
- redesign of Calendar, billing, referrals, team permissions, or provider
  settings beyond the changes explicitly required above.

These may be considered later only when the simplified core flow is proving
useful in real recruiter usage.

---

# Related existing feature specifications

Implementation must inspect and preserve compatible invariants from, and
reconcile conflicting observable behaviour in, at least:

- `../ai-native-recruiting-operations-foundation/spec.md`
- `../job-evaluation-criteria/spec.md`
- `../candidate-evaluation/spec.md`
- `../job-workspace/spec.md`
- `../recruitment-pipeline/spec.md`
- `../recruitment-attention/spec.md`
- `../candidate-outreach-and-communications/spec.md`
- `../structured-interview-feedback/spec.md`
- `../first-workspace-activation/spec.md`
- `../ai-usage-and-limits/spec.md`

The integrity rules in `.ai/skills/evaluation-integrity/SKILL.md` and
`.ai/skills/recruitment-workflow/SKILL.md` remain binding except where an
existing rule is specifically the obsolete routine criteria-confirmation product
behaviour replaced by this approved specification. Any such contradiction must be
surfaced and reconciled explicitly rather than silently ignored.
