---
status: planned
type: feature
---

# First workspace activation

## Problem

A newly created RecruiterLabs workspace exposes several valid areas of the
product before the user necessarily understands what they should do first.

A user may see jobs, candidates, interviews, settings, team management and
integrations without a clear path toward the first moment where RecruiterLabs
delivers its core value.

This creates an avoidable:

> "What should I do now?"

state.

The product should guide a new workspace toward its first useful recruitment
workflow without forcing the user through a generic product tour or blocking
access to the application.

The activation experience must reflect real product activity rather than an
independent checklist that can drift away from what actually happened inside
the workspace.

---

# Objective

Guide a newly created workspace from creation to its first evidence-backed
candidate evaluation.

RecruiterLabs should make the next useful action obvious while allowing the
user to explore the product freely.

The experience introduces two distinct product milestones:

- **Setup complete**
- **Workspace activated**

Setup complete means the workspace has established the minimum hiring structure
needed to begin receiving and evaluating applications.

Workspace activated means the workspace has actually completed the core
RecruiterLabs value loop for at least one application.

---

# Product principles

## Activation represents real product activity

Onboarding completion must not depend on users manually ticking checkboxes.

Steps should complete because the corresponding action actually happened in the
workspace.

Visiting a page alone must not count as completing a recruitment milestone.

## Onboarding guides but does not gate

The product remains usable while onboarding is incomplete.

Users may:

- navigate the product;
- create recruitment data;
- use existing authorized functionality;
- leave the onboarding experience and return later.

No onboarding step may block otherwise authorized RecruiterLabs functionality.

## One recruitment workflow, not two

Onboarding must direct users toward existing RecruiterLabs actions.

It must not introduce onboarding-specific versions of:

- job creation;
- criteria confirmation;
- candidate creation;
- applications;
- evaluations;
- team management;
- integrations.

The user should learn the actual product by using the actual product.

## Progress belongs to the workspace

Activation describes the progress of a workspace, not the learning progress of
an individual user.

All currently authorized members of the same workspace should observe the same
core activation progress.

A user who belongs to multiple workspaces may therefore see different
activation states for each workspace.

## Existing work receives credit

A workspace must never be asked to repeat work that has already satisfied an
activation milestone.

This applies to:

- work completed outside the onboarding UI;
- workspaces created before this feature existed;
- work completed by another authorized member.

## Activation progress should not regress

The activation journey represents first-time product milestones.

Once a real workspace action has completed an onboarding milestone, that
milestone remains completed for onboarding purposes even if the underlying
record is later:

- archived;
- closed;
- removed where existing product rules allow removal;
- otherwise changed after the milestone occurred.

Onboarding measures whether the workspace has reached a milestone before.

It is not a live operational-readiness checklist.

---

# Activation milestones

## Setup complete

A workspace reaches **Setup complete** after:

1. at least one job has been created; and
2. hiring criteria have been confirmed for at least one workspace job.

This means RecruiterLabs has received structured hiring intent from the team.

Setup completion is a milestone.

It does not mean the workspace has yet experienced the core candidate
evaluation value of RecruiterLabs.

## Workspace activated

A workspace reaches **Workspace activated** after:

1. the workspace has reached Setup complete;
2. at least one application exists for a workspace job; and
3. at least one application has successfully completed an evaluation.

The first successfully completed application evaluation is the primary product
activation event.

Once reached, Workspace activated remains true for onboarding and product
analytics purposes.

---

# Primary activation journey

The primary journey contains five steps.

These steps should be presented in a short, understandable order.

---

## Step 1 — Workspace created

### Completion condition

The workspace exists.

This step completes automatically.

A newly created workspace therefore begins the onboarding journey with visible
progress already made.

### User experience

This step should communicate that the user's workspace is ready and that the
next actions will prepare its first hiring process.

No action is required from the user.

---

## Step 2 — Create your first job

### Completion condition

The workspace has created its first job.

Completion must be caused by real job creation.

### Call to action

The onboarding experience should direct the user to the existing job creation
flow.

It must not create a separate onboarding-specific job form.

### User-facing intent

The step should communicate that a job establishes what the team is hiring for.

---

## Step 3 — Confirm hiring criteria

### Completion condition

Hiring criteria have been confirmed for at least one workspace job.

Completion must use the existing criteria-confirmation semantics.

### Call to action

The onboarding experience should direct the user toward the existing criteria
confirmation flow for an appropriate job.

### Product milestone

Completing this step after the first-job milestone marks the workspace as:

**Setup complete**

### User-facing intent

The step should communicate that confirmed criteria allow RecruiterLabs to
evaluate candidates consistently against hiring intent.

---

## Step 4 — Add your first application

### Completion condition

At least one application exists for a workspace job.

The exact user-facing CTA may use the terminology already established by the
existing candidate/application flow.

The completion source of truth is a real application attached to a workspace
job.

### Call to action

The onboarding experience should direct the user toward the existing product
path for receiving or adding the first relevant candidate/application.

It must not create a duplicate application workflow.

### User-facing intent

The step should communicate that RecruiterLabs needs a real candidate
application before it can demonstrate its evaluation value.

---

## Step 5 — Evaluate your first application

### Completion condition

At least one workspace application has successfully completed an evaluation.

The evaluation must have actually succeeded according to the existing
RecruiterLabs evaluation lifecycle.

Merely opening an application or requesting an evaluation is not sufficient.

### Product milestone

Completing this step marks the workspace as:

**Workspace activated**

### User-facing intent

The user should understand that RecruiterLabs has now turned hiring criteria and
candidate information into an evidence-backed evaluation.

---

# Optional setup

Optional setup should be visually separate from the primary activation journey.

Optional actions may include:

- Invite a teammate
- Connect calendar
- Connect email

Optional setup must not contribute to:

- Setup complete
- Workspace activated
- primary onboarding percentage where doing so would make optional work appear
  required

The user must be able to reach Workspace activated without completing any
optional setup.

---

# Optional step authorization

Optional actions must respect existing RecruiterLabs authorization.

For example:

## Invite a teammate

Only a workspace Owner may manage workspace membership.

A Member who cannot invite users must not be presented with an actionable
invitation CTA.

The onboarding layer must not create a second authorization path.

## Connect calendar or email

These actions must respect the existing integration behavior and authorization
rules.

Onboarding must not bypass integration ownership, workspace access or provider
authorization.

---

# Onboarding surfaces

The experience should be lightweight but difficult to miss.

It should use a small number of complementary surfaces rather than creating a
new major product area.

---

## Welcome experience

An eligible workspace should receive a short welcome experience during an early
visit.

The welcome experience should:

- introduce the purpose of RecruiterLabs;
- explain that only a few steps are needed to reach the first evaluation;
- show current activation progress;
- expose one clear primary next action;
- allow the user to continue later;
- never block access to the product.

The welcome experience should not become a multi-page wizard.

### Dismissal

A user may dismiss or postpone the welcome presentation.

Dismissing the welcome experience:

- does not complete any activation step;
- does not change workspace activation state;
- does not prevent real product actions from completing milestones.

Presentation-level dismissal may be personal to the user even though activation
progress itself belongs to the workspace.

---

## Dashboard checklist

Until the workspace becomes activated, the Dashboard should expose a compact
activation checklist.

The checklist should communicate:

- overall progress;
- completed primary steps;
- remaining primary steps;
- the next useful action;
- optional setup separately where appropriate.

The checklist should not dominate the entire Dashboard.

It exists to orient the user, not replace the Dashboard.

---

## Floating checklist access

While the workspace is not activated, users should have lightweight access to
the activation journey while navigating the workspace.

A compact floating launcher/checklist may provide this access.

It should:

- show progress;
- expose remaining steps;
- link to the appropriate existing product actions;
- remain visually secondary to recruitment work;
- be hideable or postponable without changing activation state.

The launcher must not obscure important product controls.

---

## Progress page

A dedicated onboarding progress page may exist as a secondary surface when
useful.

If present:

- it must not become a primary permanent navigation destination;
- it must show the same workspace progress as all other onboarding surfaces;
- it must not introduce different completion rules.

The Dashboard checklist and floating checklist should remain sufficient for
normal onboarding use.

---

# After activation

Once the workspace becomes activated:

- first-workspace onboarding should disappear from the normal Dashboard
  experience;
- the floating activation checklist should no longer be shown as an unfinished
  journey;
- the workspace should enter the regular RecruiterLabs product experience;
- activation UI should no longer consume primary workspace attention.

The product should not interrupt recruitment work with a mandatory celebration
screen.

A lightweight success acknowledgement is acceptable if it does not block the
user.

---

# Existing workspaces

When this feature is introduced, existing workspaces must receive credit for
work they have already completed.

Activation must not assume every existing workspace is new.

The product should reconcile existing workspace state with the journey.

Examples:

## Workspace with no jobs

Completed:

- Workspace created

Remaining:

- Create your first job
- Confirm hiring criteria
- Add your first application
- Evaluate your first application

## Workspace with an existing job

Completed:

- Workspace created
- Create your first job

Remaining steps depend on existing criteria, applications and evaluations.

## Workspace with confirmed job criteria

The relevant job and criteria milestones should already be completed.

If the required first-job milestone also exists, the workspace has reached
Setup complete.

## Workspace with applications but no completed evaluation

Application-related progress should receive credit, but the workspace is not
yet activated.

## Workspace with a completed evaluation

If the prerequisite hiring-intent milestones have also been reached, the
workspace should already be considered activated.

It must not be forced through first-workspace onboarding.

---

# Multi-user behavior

Core activation progress is shared by the workspace.

For example:

1. the Owner creates the first job;
2. another authorized Member confirms the criteria;
3. another Member adds or receives the first application;
4. another authorized Member triggers or reaches the first successful
   evaluation.

All authorized workspace members should then observe the corresponding shared
progress.

Onboarding must not require the same individual person to perform every step.

Individual authorship of the underlying product activity remains unchanged.

Activation does not create a separate authorship or attribution model.

---

# Joining an existing workspace

## Joining an unactivated workspace

A newly invited Member should see the workspace's current activation progress.

They must not begin a separate activation journey from zero.

## Joining an activated workspace

A newly invited Member must not restart first-workspace onboarding.

Workspace activation remains complete.

A new Member may still need to learn the product, but personal product education
is outside the scope of this feature.

---

# Multi-workspace behavior

Each workspace has independent activation progress.

A single user may simultaneously belong to:

- an activated workspace;
- a setup-complete workspace;
- a new workspace.

Changing the active workspace must display the activation state of the selected
workspace only.

Progress from one workspace must never:

- complete steps in another workspace;
- expose data from another workspace;
- leak recruitment information across tenant boundaries.

---

# Workspace access

Existing workspace-access authorization remains authoritative.

A user whose workspace access is disabled must not be able to use onboarding
surfaces to:

- enter the workspace;
- inspect its onboarding progress;
- follow onboarding URLs;
- read recruitment data;
- perform onboarding-linked actions.

Re-enabling workspace access should reveal the current shared progress of that
workspace.

It must not create a new personal activation journey.

---

# Product analytics

The activation funnel should be observable.

RecruiterLabs should be able to identify the first occurrence of the following
workspace milestones:

- `workspace_created`
- `first_job_created`
- `first_criteria_confirmed`
- `first_application_created`
- `first_application_evaluated`
- `workspace_setup_completed`
- `workspace_activated`

These names describe product events and do not require adoption of a specific
analytics vendor.

---

# Milestone semantics

Milestone events represent meaningful first-time workspace transitions.

They must be:

- workspace-scoped;
- idempotent;
- emitted or persisted only when the real corresponding state transition has
  occurred.

Repeated:

- page loads;
- Dashboard rendering;
- checklist rendering;
- condition checks;
- application views;

must not generate duplicate first-time milestones.

Once the first-time milestone has occurred, later changes to the original
entity must not rewrite product history.

---

# Activation measurement

The primary activation metric is:

**First successfully evaluated application**

This is more important than:

- account registration;
- opening the Dashboard;
- visiting Jobs;
- dismissing onboarding;
- inviting a teammate;
- connecting an integration.

The feature should make it possible to measure the funnel between workspace
creation and this first evaluation.

---

# Navigation and information architecture

Activation should reduce navigation ambiguity rather than introduce another
large navigation area.

The onboarding experience should reinforce:

- which workspace is currently active;
- that the displayed progress belongs to that workspace;
- what has already been completed;
- what the next useful action is.

The onboarding feature must not perform a broad navigation redesign.

---

# Copy and product language

Onboarding copy should be short, practical and related to the user's hiring
goal.

It should avoid:

- generic tutorial language;
- excessive AI marketing language;
- unexplained technical terminology;
- gamification language;
- patronizing instructions.

The experience should explain the value of the next action, not merely tell the
user which button to press.

For example, criteria confirmation should be framed around consistent candidate
evaluation rather than around completing a setup task.

---

# Localization

Onboarding must follow the application's existing localization model.

User-facing onboarding copy should be available in the product's currently
supported interface languages.

Adding onboarding must not create a separate localization mechanism.

Changing language should not create separate activation progress.

---

# Evaluation integrity

This feature observes the recruitment workflow.

It does not alter it.

Activation must not change:

- evaluation inputs;
- evaluation prompts;
- fit score calculation;
- coverage calculation;
- confidence;
- evidence provenance;
- unknown-criterion behavior;
- job-criterion semantics;
- AI provider behavior;
- interview feedback;
- application status;
- hiring decisions.

The onboarding system may observe that a successful evaluation exists.

It must never influence the contents or result of that evaluation.

---

# Authorization

The onboarding layer must respect all existing server-side authorization.

Hiding an action from the checklist is not sufficient protection.

Every onboarding CTA points to functionality that must independently authorize
the current user.

Onboarding must not become an alternate route around:

- tenant isolation;
- workspace access;
- Owner-only team management;
- integration authorization;
- recruitment business rules.

---

# Failure behavior

Onboarding progress must represent successfully completed product activity.

If an action fails, the corresponding milestone must not be completed.

Examples include:

- job creation fails;
- criteria confirmation fails;
- application creation fails;
- evaluation fails;
- evaluation is cancelled or discarded before successful completion.

The onboarding layer must not convert an attempted action into a completed
milestone.

---

# Edge cases

## User dismisses onboarding

The product remains usable.

Real workspace activity continues to update activation progress.

## User completes a step outside onboarding

The corresponding onboarding milestone updates automatically.

## Another team member completes a step

All authorized Members observe the updated shared workspace progress.

## Existing workspace is already activated

First-workspace onboarding is not shown as unfinished.

## User joins an activated workspace

Activation is not restarted.

## User belongs to several workspaces

Each workspace shows only its own progress.

## Original first job later becomes archived or otherwise inactive

The historical first-job onboarding milestone remains completed.

## Original first application later closes or changes status

Previously achieved first-application progress remains completed.

## First evaluated application later changes workflow state

The workspace remains historically activated.

## Optional integration is never configured

The workspace can still activate.

## User without Owner permission sees optional team setup

The product must not present an actionable invitation control they cannot
perform.

## Workspace access is disabled

The user cannot access onboarding or recruitment information for that
workspace.

---

# Onboarding presentation boundaries

The initial implementation should remain focused.

The product does not need multiple competing onboarding journeys.

The primary first-workspace activation experience should be concise.

The following presentation mechanisms are not required for this feature:

- guided spotlight tours;
- videos;
- mandatory walkthroughs;
- large tutorial sequences;
- gamified achievements.

The existence of technical support for those experiences does not make them
product requirements.

---

# Plugin and implementation boundary

The onboarding experience may be implemented with an existing Filament
onboarding package or with the application's native UI infrastructure.

The product requirements in this spec remain authoritative regardless of the
implementation mechanism.

An onboarding package must not redefine:

- activation semantics;
- tenant ownership of progress;
- authorization;
- recruitment workflows;
- evaluation behavior.

The implementation must preserve workspace-scoped shared activation progress.

A package whose progress model cannot support this requirement without
distorting the existing tenant architecture must not be forced into the
product.

Dependency changes require explicit user approval according to the repository's
existing dependency rules.

---

# Out of scope

This feature does not introduce:

- mandatory product tours;
- guided spotlight tours as a product requirement;
- mandatory tutorial videos;
- certification or training progress;
- gamification;
- achievements;
- user-level activation as the source of truth;
- blocking onboarding wizards;
- a second job creation workflow;
- job templates;
- sample recruitment data;
- automatic job creation;
- automatic criteria confirmation;
- automatic candidate creation;
- automatic application creation;
- automatic evaluation;
- changes to Team roles or workspace access;
- new RBAC;
- billing activation;
- subscription trials;
- lifecycle email campaigns;
- marketing automation;
- a third-party analytics platform requirement;
- candidate evaluation changes;
- a broad Dashboard redesign;
- a broad navigation redesign;
- per-customer configurable onboarding journeys;
- onboarding management exposed to normal workspace users.

---

# Acceptance criteria

## AC01

A newly created workspace has a first-workspace activation journey available
without blocking normal product use.

## AC02

Workspace creation automatically completes the first onboarding milestone.

## AC03

Creating the first real workspace job automatically completes the first-job
milestone.

## AC04

Confirming hiring criteria for a workspace job automatically completes the
criteria milestone.

## AC05

A workspace reaches Setup complete after the first-job and criteria-confirmation
milestones have been completed.

## AC06

Creating or receiving the first real application for a workspace job
automatically completes the first-application milestone.

## AC07

Only a successfully completed application evaluation completes the first
evaluation milestone.

## AC08

Completing the first successful evaluation after the required prerequisite
milestones marks the workspace as activated.

## AC09

Primary activation progress does not require users to manually tick checklist
items.

## AC10

Completing a product action outside the onboarding UI produces the same
activation progress as following the onboarding CTA.

## AC11

Activation milestones do not regress when the original records later change
state.

## AC12

The same workspace activation progress is visible to all currently authorized
workspace members.

## AC13

Different workspaces maintain independent activation progress.

## AC14

Switching workspaces shows onboarding progress only for the selected workspace.

## AC15

Existing workspaces receive credit for relevant recruitment activity that
already exists when the feature is introduced.

## AC16

An existing workspace that has already satisfied activation requirements is not
presented as an unfinished new workspace.

## AC17

A newly invited Member joining an activated workspace does not restart
first-workspace activation.

## AC18

A newly invited Member joining an unactivated workspace sees the workspace's
existing shared progress rather than starting from zero.

## AC19

An eligible unactivated workspace receives a short, dismissible welcome
experience.

## AC20

Dismissing or postponing onboarding does not alter activation progress.

## AC21

An unactivated workspace exposes a compact activation checklist on the
Dashboard.

## AC22

An unactivated workspace provides lightweight access to its checklist while the
user navigates the product.

## AC23

The activation experience clearly identifies the next useful incomplete primary
step.

## AC24

Onboarding CTAs use existing RecruiterLabs workflows rather than duplicate
onboarding-specific forms.

## AC25

Invite teammate, calendar connection and email connection remain optional and
do not prevent Setup complete or Workspace activated.

## AC26

Optional onboarding actions respect existing authorization and are only
actionable when the current user can perform the corresponding product action.

## AC27

After Workspace activated, unfinished first-workspace guidance no longer
occupies the normal Dashboard and persistent workspace experience.

## AC28

Onboarding cannot bypass workspace access or tenant authorization.

## AC29

A user whose workspace access is disabled cannot inspect or manipulate that
workspace's activation journey.

## AC30

Onboarding progress and presentation follow the application's existing locale
selection without creating separate progress per language.

## AC31

The feature exposes or persists idempotent first-time milestones for workspace
creation, first job, first criteria confirmation, first application, first
evaluation, setup completion and activation.

## AC32

Repeated rendering or repeated condition checks do not create duplicate
first-time milestone events.

## AC33

Failed product actions do not incorrectly complete onboarding milestones.

## AC34

The onboarding feature does not modify candidate evaluation, fit scoring,
coverage, confidence, evidence semantics, interview feedback or hiring
decisions.

## AC35

The feature does not introduce mandatory tours, videos, gamification or blocking
setup wizards.

## AC36

The implementation remains workspace-scoped and tenant-safe for users belonging
to multiple workspaces.

## AC37

The activation journey remains concise and focused on reaching the first
evidence-backed candidate evaluation.

## AC38

The resulting experience materially reduces the "what should I do next?" state
for a new workspace without creating a second product workflow.
