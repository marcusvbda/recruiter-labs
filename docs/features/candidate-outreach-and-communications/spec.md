---
status: implemented
type: as-built
---

# Candidate outreach and communications

## Problem

Recruiter Labs already performs a substantial part of the recruitment workflow.

The product can:

- receive candidates through public applications;
- maintain a workspace Talent Pool;
- rediscover existing candidates through internal sourcing;
- evaluate applications against recruiter-confirmed criteria;
- surface evidence, uncertainty, coverage and confidence;
- organize candidates inside recruitment pipelines;
- schedule interviews;
- send configured pipeline and interview emails;
- surface human gates through Recruitment Attention.

However, candidate communication is still fragmented.

A recruiter may discover a promising candidate inside Recruiter Labs, understand
why that candidate may fit a Job, and then need to leave the product to:

- understand what should be said;
- copy candidate/job context;
- write an outreach message;
- avoid making unsupported personalization claims;
- send the email;
- remember what was sent;
- later understand which communication belonged to which Job;
- reconstruct communication history when another recruiter takes over.

This is particularly visible in active sourcing.

Today Recruiter Labs can answer:

> "Who in the Talent Pool may fit this Job, and why?"

But it does not yet complete the next operational step:

> "Prepare a grounded message to this candidate and let the recruiter authorize
> sending it."

The product therefore still produces insight without completing enough of the
recruitment work that follows from that insight.

---

## Objective

Introduce first-party candidate outreach and communication history so Recruiter
Labs can prepare and execute recruiter-authorized candidate emails without
requiring the recruiter to reconstruct context outside the product.

V1 must:

1. allow a recruiter to contact a candidate about a Job without creating a fake
   Application;
2. allow communication from both sourced candidates and existing Applications;
3. prepare evidence-grounded candidate outreach with AI;
4. require explicit human authorization before AI-prepared outreach is sent;
5. allow the recruiter to edit AI-generated content before sending;
6. allow completely manual messages without requiring AI;
7. send through the workspace's existing configured email-provider
   infrastructure;
8. persist a candidate/job communication history;
9. include relevant existing Recruiter Labs recruitment emails in the
   communication history going forward;
10. track delivery state separately from recruitment state;
11. surface delivery failures that require human intervention;
12. prevent communication when the candidate has been explicitly marked
    do-not-contact;
13. preserve tenant isolation and existing recruitment decision boundaries;
14. avoid turning Recruiter Labs into a generic email client.

The product principle is:

> AI-generated text is not the feature.
> Completing safe, authorized candidate communication is the feature.

---

## AI-native operating model

This feature follows the operating model established by
`ai-native-recruiting-operations-foundation`.

### Automatic work

Recruiter Labs may automatically:

- assemble safe outreach context after the recruiter requests outreach;
- resolve candidate, Job and workspace context;
- identify candidate evidence that may safely ground personalization;
- prepare the composer context;
- persist communication and delivery state;
- update communication history after send completion;
- surface failed communication through deterministic Attention rules.

Automatic work must not itself send an AI-authored candidate message.

### Prepared work requiring approval

Recruiter Labs may:

- generate an outreach draft;
- generate a manual follow-up draft when explicitly requested;
- suggest a subject;
- suggest candidate-specific wording grounded in known evidence.

The recruiter must explicitly authorize the external send.

### Human gate

The recruiter controls:

- whether a candidate should be contacted;
- whether AI should prepare a message;
- the final message content;
- whether the message should actually be sent.

### Human-only / consequential boundaries

Recruiter Labs must not automatically:

- reject a candidate;
- hire a candidate;
- create a final hiring decision;
- move a candidate through the pipeline because of communication;
- infer that silence means rejection or lack of interest;
- infer that sending outreach means the candidate became an applicant;
- create an Application solely because a sourced candidate was contacted.

---

## Current product baseline

Recruiter Labs already contains email infrastructure that this feature must
extend rather than replace.

The existing product supports:

- workspace email-provider settings;
- Resend configuration;
- Gmail OAuth connection;
- a workspace default email provider;
- recruitment email sender abstraction;
- queued recruitment email sending;
- provider readiness checks;
- idempotency protections;
- pipeline-status candidate emails;
- interview scheduled emails;
- interview rescheduled emails;
- interview cancelled emails;
- recruiter-authored pipeline templates;
- a constrained email-template token catalog.

This feature must reuse those foundations where appropriate.

It must not introduce an unrelated second email-provider architecture.

---

## Existing email provider boundary

V1 supports sending through the email provider already selected by the
workspace.

The candidate communication domain should not care whether the configured
outbound provider is:

- Gmail;
- Resend;
- another future supported provider.

Provider-specific transport remains behind the existing provider abstraction.

If the workspace has no usable outbound email provider:

- the recruiter may still create/edit a draft;
- Send is unavailable;
- the UI explains that email configuration is required;
- the recruiter receives a direct path to Email Provider Settings.

Missing provider configuration does not mutate candidate or recruitment state.

---

## Plugin evaluation

The Filament plugin catalogue was reviewed before defining this feature.

### Fin Mail

Classification: **reference-only / reject for direct use**

Relevant capabilities include:

- email composer;
- templates;
- token replacement;
- email logging;
- send-email actions.

Why it is not selected:

- Recruiter Labs already owns its email-provider abstraction;
- Recruiter Labs already owns recruiter email templates;
- communication needs candidate/job/application/sourcing semantics;
- AI draft provenance and evidence grounding are product-specific;
- provider delivery state already has Recruiter Labs domain behaviour;
- adding a second email abstraction would create competing sources of truth.

### Inbox

Classification: **reject**

It provides an inbox-style internal messaging system.

The current product requirement is external candidate recruitment
communication, not user-to-user internal messaging.

### Mail Log / Mails

Classification: **reference-only / reject for direct use**

These plugins are useful generic outgoing-email audit tools.

The feature needs a domain communication history tied to:

- workspace;
- candidate;
- Job;
- optional Application;
- recruitment event;
- AI draft provenance;
- provider delivery state.

A generic Laravel mail log is not sufficient as the product source of truth.

### Decision

Implement natively using the existing Recruiter Labs email infrastructure.

No new Filament plugin dependency is approved by this specification.

---

# Communication domain

## Candidate communication thread

Recruiter Labs needs an explicit first-party representation of a candidate
conversation context.

A communication thread belongs to:

- one workspace;
- one candidate;
- optionally one Job;
- optionally one Application.

The common outreach context is:

Candidate + Job

An Application is not required.

This is important for active sourcing.

Example:

Talent Pool Candidate
→ Potential Match
→ recruiter chooses Contact
→ communication thread exists

This does **not** mean:

→ Application automatically exists.

Communication and application are separate concepts.

---

## Thread identity

V1 should avoid creating unnecessary duplicate conversation contexts.

Conceptually, one active communication context for:

workspace

- candidate
- Job

should be reused where appropriate.

If an Application for that Candidate + Job later exists, the communication
history may become associated with that Application without rewriting the
historical messages.

The implementation must not duplicate threads merely because the candidate
moves from:

sourced candidate
→ applicant.

---

## Jobless candidate communication

The primary V1 use case is recruitment communication about a Job.

Generic candidate messaging with no Job context is not a primary workflow.

The domain may technically permit a nullable Job if needed by existing
infrastructure, but the product should not introduce a general-purpose CRM email
system in this feature.

---

# Communication messages

A communication thread contains immutable historical communication entries.

A message needs enough product-level information to answer:

- who the communication concerned;
- which Job it concerned;
- who initiated it;
- whether AI helped prepare it;
- what final content was authorized;
- when sending was requested;
- what provider was used;
- whether delivery succeeded;
- whether sending failed.

Each message also has explicit provenance independent of AI assistance:

- recruiter message;
- pipeline status notification;
- interview scheduled notification;
- interview rescheduled notification;
- interview cancelled notification.

Only recruiter messages carry recruiter authorization attribution. Automated
notifications are immutable system-send snapshots, not recruiter-authorized
communications.

V1 messages may conceptually have states such as:

- draft;
- queued;
- sending;
- sent;
- failed;
- ambiguous.

Exact technical enum naming is an implementation concern.

---

## Drafts

A draft is not a sent message.

Drafts may be:

- manually authored;
- AI prepared and then edited by a recruiter.

The product should preserve that distinction.

A recruiter may:

- edit subject;
- edit body;
- discard a draft;
- regenerate an AI draft;
- send the final edited version.

Regenerating must not send anything.

---

## Sent-message immutability

Once an external email has been successfully sent:

- its historical subject/body must not silently change;
- later edits create a new draft/message;
- audit information continues to reflect what was actually authorized and sent.

The system must never render current candidate/job data as though it were the
historical contents of an old message.

---

# Entry points

## Sourcing

The active-sourcing experience should expose a candidate-level action such as:

`Contact candidate`

for a candidate the recruiter is currently reviewing.

The action:

1. establishes Candidate + Job context;
2. does not create an Application;
3. opens/prepares the communication composer;
4. allows AI preparation or manual writing;
5. sends only after human authorization.

Contacting a candidate does not implicitly:

- Save the sourcing match;
- Dismiss the sourcing match;
- Add the candidate to the Job;
- create an Application;
- move any pipeline stage.

Those remain separate human actions.

---

## Candidate profile

A Candidate profile should expose communication history.

The recruiter should be able to understand:

- which Jobs the candidate was contacted about;
- when messages were sent;
- whether they were AI-assisted;
- delivery state;
- recruiter who authorized the message.

Candidate communication must remain workspace-scoped.

---

## Application

An Application should expose communication relevant to that candidate and Job.

The recruiter should be able to:

- view communication history;
- send a manual candidate message;
- ask AI to prepare a message;
- see system recruitment emails sent for that Application.

This does not replace the Application's existing:

- evaluation;
- interview;
- pipeline;
- document;
- feedback

surfaces.

Communication is another dimension of the recruitment record.

---

## Job workspace

A Job may expose communication entry points where useful, but V1 does not need a
full job-wide inbox.

The product should prefer candidate-centric communication rather than adding a
large new messaging workspace.

---

# Composer

## Basic composer

The composer should contain at minimum:

- recipient;
- subject;
- body;
- sending identity/provider context;
- AI preparation action when eligible;
- Send action.

Recipient should be derived from the candidate's current valid email address.

V1 does not need:

- CC;
- BCC;
- arbitrary recipients;
- attachments;
- rich campaign layouts.

---

## Recipient integrity

A recruitment communication cannot be sent when:

- candidate has no valid email;
- candidate belongs to another workspace;
- candidate is marked do-not-contact;
- configured sending provider is unavailable.

The UI should explain the relevant reason.

Do not silently send to an alternative address guessed by AI.

---

# AI-prepared outreach

## Purpose

AI should reduce the recruiter work required to turn:

candidate evidence

- Job context

into a concise, credible recruitment message.

The AI is writing communication.

It is not evaluating whether the person should be hired.

---

## Initial outreach

From a sourcing candidate, the recruiter may choose:

`Prepare outreach`

Recruiter Labs then generates a draft using safe context.

The draft should normally contain:

- concise subject;
- candidate greeting;
- why the role may be relevant;
- one or more evidence-grounded reasons the recruiter is reaching out;
- brief Job/company context;
- low-pressure call to action.

Tone should be professional and concise.

Do not generate generic exaggerated recruiter language merely to sound
personalized.

---

## Grounding

Personalized claims must be grounded in information Recruiter Labs actually
possesses.

Potential grounding sources may include:

- candidate materials;
- resume/CV;
- structured candidate profile data;
- sourcing evidence;
- relevant application evidence when an Application exists;
- Job information.

Example of acceptable personalization:

> "Your experience building Laravel APIs and working with AWS looks relevant to
> this role."

only when those facts are actually supported by candidate material.

Unacceptable:

> "Your leadership of a 20-person engineering team stood out."

when no such evidence exists.

---

## No evidence

Insufficient evidence does not block communication.

If useful candidate-specific evidence is unavailable, AI should fall back to a
truthful role-oriented message.

It must not fabricate personalization to make the message sound better.

---

## Internal scores are not candidate-facing content

Candidate-facing messages must not expose internal assessment mechanisms such
as:

- Potential Match score;
- Application Fit;
- Evidence Coverage percentage;
- Confidence values;
- criterion weights;
- internal AI recommendation language.

The draft may use underlying supported facts.

It must not tell candidates:

> "You scored 87% for this Job."

---

## Sensitive information

AI outreach must not personalize based on sensitive/protected information,
whether explicit or inferred.

Do not generate outreach based on inferred:

- race;
- ethnicity;
- religion;
- disability;
- medical condition;
- political views;
- sexual orientation;
- pregnancy;
- family status;
- other protected/sensitive attributes.

Personalization should remain professionally relevant to the role.

---

## Candidate-controlled text is untrusted

Candidate material and application answers are untrusted model input.

They may contain prompt-injection-like text.

Candidate-controlled content must never be allowed to:

- redefine the AI task;
- reveal system prompts;
- request credentials;
- alter authorization;
- cause message sending;
- override evidence rules.

The existing AI-input-boundary principles remain binding.

---

# AI draft execution

## Human initiation

In V1, outreach generation begins from an explicit recruiter action.

Examples:

- Prepare outreach;
- Rewrite draft;
- Prepare follow-up.

This avoids surprise AI cost for every sourcing result.

Future policy-driven automatic draft preparation may be considered separately.

---

## AI usage accounting

Outreach generation must follow existing Recruiter Labs AI infrastructure.

It must respect:

- workspace provider configuration;
- AI allowance;
- tenant scope;
- usage recording;
- operation provenance;
- provider/model accounting.

Introduce an explicit AI operation identity for candidate communication
generation.

The history should distinguish this from:

- candidate evaluation;
- sourcing;
- criteria preparation.

The execution is user-requested in V1.

---

## AI allowance unavailable

AI allowance exhaustion must not prevent manual recruiter communication.

If AI cannot generate:

- explain why;
- preserve any existing manually written draft;
- allow the recruiter to continue manually;
- do not create placeholder AI text;
- do not send anything automatically.

---

# Recruiter editing and approval

AI-generated content is a draft.

The recruiter may freely edit it before sending.

The system sends the final content visible to the recruiter at authorization
time.

The audit record should preserve that final content.

A recruiter clicking:

`Send`

is the human approval gate.

No additional confirmation modal is mandatory if the composer itself clearly
shows:

- recipient;
- subject;
- body;
- sending identity;

and Send is an explicit action.

Avoid confirmation ceremony that does not improve safety.

---

# Manual communication

AI is optional.

A recruiter must be able to compose and send a message without asking AI to
generate anything.

This is important when:

- AI allowance is unavailable;
- the recruiter already knows what to say;
- the communication is highly contextual;
- AI is unnecessary.

Recruiter Labs should augment communication, not make email dependent on AI.

---

# Follow-up drafting

V1 may support:

`Prepare follow-up`

from an existing communication thread.

This is explicitly recruiter initiated.

AI may use:

- prior Recruiter Labs outbound messages from the thread;
- current Job context;
- candidate/job evidence already allowed for outreach.

The result remains a draft requiring human Send authorization.

V1 must **not** assume that a candidate failed to respond merely because
Recruiter Labs has no inbound message.

---

# Email sending

## Existing provider abstraction

Candidate communication should use the workspace's configured default
recruitment email provider.

Do not bypass:

- provider readiness;
- provider credentials;
- workspace ownership;
- Gmail connected identity;
- Resend sender configuration.

---

## Send execution

Sending should be asynchronous/queued when consistent with existing email
architecture.

The system should prevent accidental duplicate sends caused by:

- double click;
- queue retry;
- page refresh;
- network retry.

Use durable idempotency.

A retry must not produce a second candidate message merely because the first
request's response was lost.

---

## Sender identity

The recruiter should see which identity will send the message before
authorization.

Examples:

Gmail:
`recruiter@company.com`

Resend:
configured company sender address.

Do not imply that the email comes from an individual recruiter if the provider
actually sends from a shared company address.

---

# Communication history

## Purpose

Recruiter Labs should become the recruitment system of record for communication
it performs.

The history should answer:

> What did Recruiter Labs send this candidate about this hiring process?

It is not intended to replicate the entire Gmail mailbox.

---

## Timeline entry

A communication history entry should present useful information such as:

- direction;
- message type;
- subject;
- body/content;
- recruiter who authorized it;
- AI-assisted indicator when applicable;
- provider/sending identity;
- sent/failed state;
- sent/requested timestamp.

Do not make provider internals the primary UI.

---

## Existing pipeline emails

Existing recruiter-configured pipeline-status emails remain supported.

From implementation of this feature forward, they should be represented in the
same candidate communication history when enough domain context exists.

Their existing trigger behaviour must not change.

Example:

Application enters Interview Review
→ existing configured status email sends
→ communication history records the outbound candidate communication.

---

## Interview emails

Existing interview:

- scheduled;
- rescheduled;
- cancelled

candidate communications should likewise be visible in communication history
going forward.

This feature must not duplicate calendar invitations or send a second interview
email merely to populate history.

The existing interview flow remains authoritative.

---

## Historical backfill

Do not attempt to fabricate historical message bodies for emails sent before
communication-history tracking existed.

If reliable historical provider/message data is insufficient:

- do not backfill;
- start communication history from deployment of this feature.

Truthful incomplete history is preferable to invented history.

---

# Delivery state

Communication state and recruitment state are separate.

An email send failure must never:

- reject a candidate;
- change sourcing status;
- change Application status;
- alter candidate fit;
- move pipeline stage.

A delivery failure is an operational communication problem.

---

## Provider failure

If sending fails:

- preserve the message and final authorized contents;
- mark its delivery state truthfully;
- allow a bounded retry when safe;
- surface actionable failure information;
- do not create a duplicate message.

---

## Ambiguous delivery

If the provider outcome is ambiguous:

- do not blindly resend;
- preserve ambiguous state;
- surface it for human review where appropriate.

Avoid turning transport uncertainty into duplicate candidate outreach.

---

# Recruitment Attention

Attention remains deterministic.

The LLM must not decide whether a communication task exists.

V1 may introduce Attention signals for actual operational failures such as:

### Candidate communication failed

Meaning:

A recruiter authorized a message but Recruiter Labs could not deliver it.

Action:

Open the relevant communication thread/message.

### Email provider needs attention

Meaning:

A communication could not proceed because the configured provider is no longer
usable or requires reauthorization.

Action:

Open Email Provider Settings.

Do not create Attention items merely because:

- a recruiter has never contacted a sourcing match;
- a candidate has not replied;
- an AI draft exists that the recruiter intentionally abandoned.

Silence is not an inferred task in V1.

---

# Do-not-contact

Recruiter Labs needs a minimal operational safeguard against knowingly
contacting a candidate who should no longer receive recruitment outreach.

A candidate may be explicitly marked:

`Do not contact`

or equivalent.

When active:

- manual Send is blocked;
- AI may not be presented as a path to bypass the block;
- existing historical communication remains visible;
- the state is workspace-scoped.

Changing do-not-contact status is a human action.

This V1 does not attempt to replace the broader future candidate-data-privacy
feature.

---

## Existing process notifications and do-not-contact

Do-not-contact primarily governs discretionary recruiter outreach.

The implementation must carefully distinguish this from communications required
by an already-active recruitment process.

Do not silently change existing pipeline/interview email semantics unless the
current product contract explicitly requires it.

If ambiguity exists between:

- sourcing/marketing-like outreach;
- process-related candidate communication;

preserve existing process behaviour and keep the do-not-contact scope narrow in
this feature.

Broader communication-consent policy belongs in `candidate-data-privacy`.

---

# Sourcing interaction

## Potential Match remains Potential Match

Contacting a sourcing candidate must not convert Potential Match into
Application Fit.

It must not copy:

- sourcing score;
- sourcing evidence;
- sourcing confidence

into Application evaluation.

The sourcing/evaluation boundary remains unchanged.

---

## Contacting does not create an Application

This is a hard boundary.

Candidate:
Talent Pool

Sourcing Match:
Suggested

Recruiter:
Contact candidate

Result:

Communication thread/message

Not:

Application.

If the recruiter later chooses:

`Add to Job`

the existing human-controlled action creates/associates the Application
according to the current sourcing contract.

---

# Application interaction

A recruiter may manually communicate with an existing applicant regardless of
whether AI evaluation has completed.

Communication is not conditional on:

- Fit score;
- Evidence Coverage;
- Confidence.

AI may use supported evidence to help draft communication but must not convert
evaluation into hiring decisions.

---

# Terminal Applications

Terminal recruitment status does not erase communication history.

For a rejected/hired/closed Application:

- history remains readable;
- discretionary new communication should not be presented as an assumed next
  step.

If the recruiter deliberately needs to send a message from a terminal process,
the UI may allow a conscious manual action where existing authorization allows
it.

Do not generate automatic outreach after terminal closure.

---

# Multi-user behaviour

Communication belongs to the workspace, not privately to the recruiter who sent
it.

Authorized workspace members should be able to understand candidate
communication history.

The history records which recruiter authorized each recruiter-initiated send.

This reduces handover loss between recruiters.

---

# Tenant isolation

Every communication operation must preserve tenant isolation.

A user in Company A must never:

- view Company B communication;
- contact Company B candidate;
- use Company B provider credentials;
- associate a message with Company B Job;
- associate a message with Company B Application.

Candidate, Job, Application, thread and provider must belong to the same
workspace when applicable.

---

# Authorization

Communication should reuse existing candidate/application/job authorization
semantics.

This feature does not introduce a new RBAC system.

A user who cannot access/update the relevant recruitment record must not gain a
new path through Communications to perform that action.

---

# Localization

Recruiter Labs UI for communication must support the product's existing
languages:

- English;
- Portuguese (Brazil);
- Spanish.

Recruiter-authored message contents are not automatically translated in V1.

AI may prepare a draft in the recruiter-requested language where the product
provides an explicit language choice/context.

Do not infer a candidate's preferred language from:

- name;
- nationality;
- location;
- ethnicity.

---

# Message language

For sourced outreach, the composer may default to the workspace/user language or
another explicit product default.

The recruiter may choose/change language when requesting an AI draft.

For existing Applications, the Job's application locale may be used as useful
context but must not silently override a recruiter-selected language.

---

# Security

## No secrets in AI context

AI draft generation must never receive:

- email-provider API keys;
- OAuth tokens;
- internal credentials;
- private integration secrets.

---

## No arbitrary recipient control from AI

The model may generate:

- subject;
- body.

It must not decide:

- recipient email;
- sender identity;
- provider;
- authorization.

Those values come from trusted application state.

---

## HTML safety

Candidate-facing AI content must be safely rendered.

Do not allow model-generated HTML/scripts to execute.

Prefer plain text / constrained formatting for V1.

---

# Observability

A recruiter should be able to distinguish:

- AI generation failure;
- email provider unavailable;
- queued message;
- successful send;
- failed send;
- ambiguous delivery outcome.

Avoid one generic:

"Something went wrong"

state for operationally different problems.

---

# User flows

## Flow A — Outreach a sourced candidate

1. Recruiter opens Job Sourcing.
2. Recruiter reviews a Potential Match.
3. Recruiter chooses `Contact candidate`.
4. Recruiter Labs opens a Candidate + Job communication context.
5. Recruiter chooses `Prepare outreach`.
6. Recruiter Labs assembles safe evidence-grounded context.
7. AI creates subject/body draft.
8. Recruiter reviews and optionally edits it.
9. Recruiter sees recipient and sending identity.
10. Recruiter clicks Send.
11. Recruiter Labs queues/sends idempotently.
12. Communication history shows the authorized message and delivery state.
13. No Application is created.

---

## Flow B — Manual sourced outreach

1. Recruiter chooses Contact candidate.
2. Recruiter skips AI.
3. Recruiter writes subject/body.
4. Recruiter sends.
5. Communication history records the message.
6. No AI usage is consumed.
7. No Application is created.

---

## Flow C — Candidate has no email

1. Recruiter opens Contact candidate.
2. Recruiter Labs detects no valid candidate email.
3. Send is unavailable.
4. UI explains that the candidate needs a valid email address.
5. No AI send is attempted.
6. No fake address is generated.

---

## Flow D — Provider unavailable

1. Recruiter creates a draft.
2. Workspace has no usable email provider.
3. Draft remains preserved.
4. Send is unavailable.
5. Recruiter gets a direct route to Email Provider Settings.
6. Recruitment state is unchanged.

---

## Flow E — Communicate with applicant

1. Recruiter opens an Application.
2. Recruiter opens Communications.
3. Existing process communication history is visible.
4. Recruiter chooses New message.
5. Recruiter writes manually or requests an AI draft.
6. Recruiter authorizes Send.
7. Message is persisted in the Application's Candidate + Job communication
   context.

---

## Flow F — AI allowance exhausted

1. Recruiter requests Prepare outreach.
2. AI allowance is unavailable.
3. Recruiter Labs explains the AI operation cannot run.
4. Existing draft text is not destroyed.
5. Recruiter can compose manually.
6. Sending itself remains available if the email provider is ready.

---

## Flow G — Pipeline notification

1. Recruiter manually moves an Application into a configured pipeline stage.
2. Existing pipeline email behaviour runs.
3. No duplicate communication is introduced by this feature.
4. The resulting outbound candidate communication is visible in communication
   history going forward.

---

## Flow H — Interview scheduled

1. Recruiter schedules an interview.
2. Existing calendar/interview workflow remains authoritative.
3. Existing candidate communication is sent according to the current contract.
4. Communication history records the relevant outbound communication.
5. This feature does not create a second interview invitation.

---

## Flow I — Send failure

1. Recruiter authorizes a message.
2. Provider returns a definitive failure.
3. Message remains recorded.
4. Delivery becomes Failed.
5. Candidate recruitment state remains unchanged.
6. Attention surfaces the operational problem when recruiter action is needed.
7. Safe retry is available according to idempotency rules.

---

## Flow J — Ambiguous provider outcome

1. Recruiter authorizes message.
2. Network/provider result becomes ambiguous.
3. Message is not automatically sent again blindly.
4. Delivery remains Ambiguous.
5. Recruiter can inspect the issue.
6. System avoids duplicate outreach.

---

## Flow K — Do not contact

1. Recruiter marks Candidate as Do not contact.
2. Existing history remains.
3. New discretionary outreach Send actions are blocked.
4. AI cannot bypass the block.
5. No candidate/recruitment score changes.

---

## Flow L — Recruiter handover

1. Recruiter A contacted Candidate for Job.
2. Recruiter B later opens Candidate/Application.
3. Recruiter B sees the communication performed through Recruiter Labs.
4. Recruiter B does not need Recruiter A's personal notes to know what the
   system sent.

---

# Business rules

## BR01 — Communication does not equal application

Contacting a candidate never creates an Application implicitly.

## BR02 — Send is human-authorized in V1

AI-generated recruiter outreach cannot be sent without explicit recruiter
authorization.

## BR03 — AI is optional

Manual candidate messaging remains available when AI is unnecessary or
unavailable.

## BR04 — Personalization requires evidence

AI cannot invent candidate accomplishments to personalize outreach.

## BR05 — Scores remain internal

Fit, Potential Match, Coverage, Confidence and criterion weights are not
candidate-facing message content.

## BR06 — Outreach does not decide recruitment state

Sending or failing to send does not move pipeline stages.

## BR07 — Transport failure is operational

Email delivery failure does not become candidate evaluation evidence.

## BR08 — Existing provider architecture remains authoritative

Candidate communications use current workspace provider configuration.

## BR09 — Existing transactional email behaviour is preserved

Pipeline and interview communications are not reimplemented as separate
duplicate sends.

## BR10 — Historical messages are immutable

Sent content remains an accurate record of what was actually sent.

## BR11 — Do-not-contact cannot be bypassed by AI

An AI-generated draft is not authorization to contact.

## BR12 — Candidate text is untrusted AI input

Candidate material cannot override AI system/domain instructions.

## BR13 — No inferred candidate reply state

Absence of an inbound Recruiter Labs message is not interpreted as:

- no response;
- not interested;
- rejected;
- inactive.

## BR14 — No automatic follow-up in V1

Recruiter Labs does not send timed follow-ups based on assumed silence.

## BR15 — Workspace scope applies everywhere

Message, Candidate, Job, Application and sending provider must resolve to one
workspace.

---

# Acceptance criteria

- **AC01** — A recruiter can initiate communication with a candidate from an
  active-sourcing context.

- **AC02** — Contacting a sourcing candidate does not automatically create an
  Application.

- **AC03** — Contacting a sourcing candidate does not automatically Save, Dismiss
  or otherwise change the sourcing match.

- **AC04** — A Candidate + Job communication history can exist before an
  Application exists.

- **AC05** — When a matching Application later exists, communication history can
  remain associated with the same Candidate + Job context without rewriting
  history.

- **AC06** — A recruiter can access relevant communication history from an
  Application.

- **AC07** — A recruiter can access candidate communication history from the
  Candidate context.

- **AC08** — A recruiter can compose a completely manual candidate email.

- **AC09** — A recruiter can explicitly request an AI-prepared outreach draft.

- **AC10** — AI draft generation does not send the message.

- **AC11** — The recruiter can edit AI-generated subject/body before sending.

- **AC12** — The final sent content corresponds to the final recruiter-authorized
  content, not an earlier AI draft.

- **AC13** — AI personalization uses supported candidate/job evidence and does not
  fabricate unsupported achievements.

- **AC14** — AI does not expose Potential Match, Fit, Coverage, Confidence or
  criterion weights to the candidate.

- **AC15** — Candidate-controlled text cannot cause AI to send a message or
  override communication authorization.

- **AC16** — AI draft generation is recorded as a distinct AI operation using
  existing AI usage/provider infrastructure.

- **AC17** — AI usage records identify outreach generation as user-requested in
  V1.

- **AC18** — AI allowance exhaustion does not prevent manual drafting or email
  sending.

- **AC19** — A candidate without a valid email cannot be sent a recruitment
  email.

- **AC20** — AI cannot invent an email address for a candidate.

- **AC21** — Candidate email recipient is resolved from trusted Candidate state,
  not model output.

- **AC22** — Sender/provider is resolved from workspace email configuration, not
  model output.

- **AC23** — Gmail and Resend continue to use the existing provider abstraction.

- **AC24** — If the default provider is not usable, the draft remains available
  and the recruiter receives a path to Email Provider Settings.

- **AC25** — Sending is idempotent against double-click, queue retry and common
  duplicate execution paths.

- **AC26** — A successful send creates a durable communication-history record.

- **AC27** — Sent content cannot silently change afterward.

- **AC28** — Communication history identifies the recruiter who authorized a
  recruiter-initiated message.

- **AC29** — Communication history distinguishes AI-assisted from wholly manual
  recruiter-authored messages.

- **AC30** — Communication history presents meaningful delivery state.

- **AC31** — A definitive provider failure does not alter Application status,
  sourcing status or candidate evaluation.

- **AC32** — An ambiguous provider result does not trigger blind automatic
  resending.

- **AC33** — Existing pipeline-status email triggers remain behaviourally
  unchanged.

- **AC34** — Pipeline emails generated after the feature is deployed can appear
  in the relevant communication history without a duplicate email being sent.

- **AC35** — Existing interview scheduled/rescheduled/cancelled behaviour remains
  authoritative.

- **AC36** — Interview communications can appear in communication history without
  duplicating the actual invitation/notification.

- **AC37** — No fabricated historical email bodies are backfilled for older
  communication where reliable content does not exist.

- **AC38** — A recruiter may explicitly request an AI follow-up draft based on
  existing outbound thread context.

- **AC39** — Follow-up drafts require explicit Send authorization.

- **AC40** — V1 does not automatically send a follow-up after a duration of
  silence.

- **AC41** — V1 does not infer candidate interest from lack of an inbound
  Recruiter Labs message.

- **AC42** — Candidate may be explicitly marked Do not contact.

- **AC43** — Do-not-contact blocks new discretionary recruiter outreach.

- **AC44** — Do-not-contact does not erase historical communication.

- **AC45** — AI cannot bypass do-not-contact state.

- **AC46** — Failed authorized communications that need recruiter intervention
  can surface through deterministic Recruitment Attention.

- **AC47** — Attention does not create a task merely because a sourcing candidate
  has not been contacted.

- **AC48** — Attention does not create a no-response/follow-up task based on
  absence of inbound email.

- **AC49** — Communication from Company A cannot access Candidate, Job,
  Application, thread or provider data from Company B.

- **AC50** — Communication UI supports EN, PT-BR and ES product translations.

- **AC51** — AI does not infer candidate communication language from protected or
  sensitive personal attributes.

- **AC52** — No new generic email-provider architecture is introduced alongside
  the existing Recruiter Labs sender system.

- **AC53** — No new Filament email/inbox plugin dependency is introduced.

- **AC54** — Candidate communication does not automatically reject, hire or move a
  candidate.

- **AC55** — Contacting a Potential Match does not copy sourcing evaluation into
  Application evaluation.

---

# Edge cases

## Candidate email changes after a message was sent

Historical recipient remains the address used for that send.

New drafts use the candidate's current valid address.

Do not rewrite historical recipient information.

---

## Candidate belongs to Talent Pool but no Job

Do not turn Communications into a generic email CRM merely because a Candidate
record exists.

Primary action should require a recruitment context.

---

## Candidate is Suggested for multiple Jobs

Communication context must clearly identify which Job a message concerns.

Do not merge unrelated Job conversations solely because candidate email is the
same.

---

## Candidate later applies independently

Existing outreach history remains.

The new Application must not reinterpret outreach as application evidence.

---

## Candidate has existing Application and sourcing match

Do not create duplicate Application or duplicate communication context merely
because both domain relationships exist.

---

## Candidate material changed after AI draft generation

Existing draft remains the generated draft.

Regeneration may use current eligible context.

Do not silently rewrite a draft while recruiter is reviewing it.

---

## Criteria revision changes

Communication history is unaffected.

Future AI drafts may use current supported role context.

Do not invalidate sent outreach simply because hiring criteria changed.

---

## AI returns unsupported personalization

Validation/grounding policy should prevent unsupported claims from becoming an
automatically trusted final message.

The recruiter remains able to manually author their own text.

---

## User edits AI draft and introduces unsupported content

The final text is recruiter-authored/approved.

Recruiter Labs should not claim that all manually edited text remains
AI-evidence-grounded.

Preserve provenance honestly.

---

## Provider becomes disconnected after draft

Draft remains.

Send is blocked or fails safely according to provider state.

Do not discard recruiter work.

---

## Provider becomes disconnected after queueing

Delivery records actual outcome.

A provider/auth failure becomes an operational communication problem.

---

## Recruiter double-clicks Send

Only one external email may result for the same authorized send operation.

---

## Queue worker crashes during sending

Existing/extended idempotency and ambiguous-delivery semantics prevent blind
duplicate sends.

---

## Candidate is terminal in an Application

History remains readable.

Do not proactively prepare another outreach.

Manual exceptional communication remains an intentional recruiter action if
allowed by existing authorization.

---

## Candidate is do-not-contact

Composer can show history/context but discretionary Send is unavailable.

Do not hide why.

---

# Out of scope

This feature deliberately does not include:

- Gmail inbox replication;
- reading the user's entire Gmail mailbox;
- inbound email synchronization;
- automatic candidate reply classification;
- AI interpretation of candidate replies;
- automatic detection of interested/not interested;
- automatic interview scheduling from an email reply;
- automatic pipeline movement from communication;
- timed autonomous follow-up sequences;
- drip campaigns;
- bulk candidate campaigns;
- newsletters;
- marketing automation;
- arbitrary mailing lists;
- mass mail merge;
- CC/BCC;
- email attachments;
- custom HTML email builder;
- drag-and-drop email builder;
- candidate SMS;
- WhatsApp;
- LinkedIn messaging;
- LinkedIn automation;
- external sourcing providers;
- AI-generated hiring decisions;
- auto-rejection;
- auto-hiring;
- candidate portal;
- self-service unsubscribe/privacy portal;
- full GDPR/legal-basis management;
- historical mailbox import;
- importing Gmail conversation history;
- generic CRM functionality.

Inbound reply synchronization and policy-driven follow-up automation require a
separate product/technical decision because they expand provider permissions,
privacy exposure and integration behaviour.

---

# Expected product change

Before:

Potential Match
→ recruiter reviews evidence
→ leaves Recruiter Labs
→ reconstructs candidate/job context
→ writes external email
→ sends externally
→ communication history is fragmented.

After:

Potential Match
→ recruiter chooses Contact candidate
→ Recruiter Labs prepares grounded outreach
→ recruiter reviews/edits
→ recruiter authorizes Send
→ existing provider sends
→ communication becomes part of the candidate/job record.

For Applications:

Application
→ recruitment event / recruiter message
→ candidate communication
→ communication history remains visible alongside recruitment context.

The recruiter still owns judgment.

Recruiter Labs performs the safe operational work around that judgment.

---

# Product value

This feature changes active sourcing from:

> "Recruiter Labs found someone interesting."

to:

> "Recruiter Labs found someone interesting, explained why, prepared a credible
> approach, and is ready to execute it when the recruiter approves."

That closes an important gap between AI insight and completed recruiting work.

It also gives internal recruiting teams a shared communication record rather
than making candidate context depend on one recruiter's inbox.

---

# Success criteria

The feature is successful when:

- a recruiter can go from Potential Match to an authorized candidate email
  without leaving Recruiter Labs;
- that action does not falsely create an Application;
- AI can prepare a useful, evidence-grounded draft;
- recruiter can edit or ignore AI completely;
- no AI-authored outreach is sent without explicit recruiter authorization;
- workspace email infrastructure is reused rather than replaced;
- communication history survives recruiter handover;
- existing pipeline/interview email behaviour remains intact;
- send failure does not contaminate recruitment decision state;
- the system does not fabricate candidate personalization;
- the system does not introduce bulk messaging or inbox complexity;
- the feature materially reduces recruiter operational work without delegating
  hiring decisions.

---

# Future evolution

This V1 deliberately stops at safe outbound communication.

A later evolution may extend the same communication domain toward:

Candidate reply
→ provider-safe inbound synchronization
→ operational intent extraction
→ Attention
→ recruiter action

and eventually:

authorized policy
→ bounded follow-up
→ stop immediately on reply/opt-out
→ recruiter handles judgment and exceptions.

Those capabilities should extend the communication domain introduced here
rather than create a second messaging subsystem.

---

# Related feature specs

- `../active-candidate-sourcing-foundation/spec.md`
- `../talent-pool-import-and-materials/spec.md`
- `../candidate-evaluation/spec.md`
- `../application-intake/spec.md`
- `../recruitment-pipeline/spec.md`
- `../interview-scheduling/spec.md`
- `../recruitment-attention/spec.md`
- `../ai-native-recruiting-operations-foundation/spec.md`
- `../workspace-team-access/spec.md`
