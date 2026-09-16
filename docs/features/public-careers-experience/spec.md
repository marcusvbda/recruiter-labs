# Public careers experience

## Problem

Recruiter Labs already supports the operational recruitment workflow after a
candidate reaches a job.

Today the product can:

- create and publish jobs;
- expose an individual public job page;
- collect structured applications;
- collect CVs, cover letters and application answers;
- preserve referral and UTM attribution;
- create or reuse workspace candidates;
- automatically evaluate eligible applications against human-confirmed criteria;
- place applications into the configured recruitment pipeline.

However, the public acquisition experience is incomplete.

A candidate can currently reach an individual job when somebody gives them its
direct public URL, but a company does not have a public destination where people
can:

- discover the company;
- see all currently open roles;
- navigate between those roles;
- understand that the jobs belong to the same employer;
- reach the existing application flow from one coherent public careers
  experience.

This leaves Recruiter Labs dependent on externally distributed individual job
links.

It also creates an important product gap for customers migrating from another
ATS: a company using Recruiter Labs cannot yet use Recruiter Labs itself as its
basic public careers destination.

The product needs a simple first-party careers page.

It should not become a website builder.

---

## Objective

Give each workspace an optional public careers page that provides a coherent,
branded entry point into its currently open jobs while reusing the existing job
and application-intake infrastructure.

The first version must:

1. provide one public careers URL per workspace;
2. allow the workspace to explicitly enable or disable the public careers page;
3. provide basic employer identity through the existing workspace name plus an
   optional careers description and logo;
4. automatically list jobs that are genuinely open for public applications;
5. provide a public careers-scoped job experience that reuses the existing job
   application contract;
6. provide honest states when a job is no longer accepting applications;
7. preserve referral and UTM attribution;
8. distinguish applications originating from the careers experience from direct
   job-link applications;
9. provide basic SEO, canonical and Open Graph metadata;
10. provide a usable responsive public experience;
11. allow recruiters to open and copy the careers URL from the admin product;
12. require no manual synchronization between published jobs and the careers
    page.

The core product principle is:

> Recruiters maintain the hiring process. Recruiter Labs keeps the public careers
> experience synchronized with the authoritative job state.

---

## Product context and source-of-truth boundary

This is a planned product specification.

It extends existing recruitment features rather than replacing them.

The following existing contracts remain authoritative:

| Existing feature                           | Behaviour preserved or extended                                                                                                                                    |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Job workspace                              | Jobs remain workspace-owned hiring processes.                                                                                                                      |
| Application intake                         | Existing validation, document handling, identity resolution, duplicate handling, application limits, pipeline entry and automatic evaluation remain authoritative. |
| Job evaluation criteria                    | Careers visibility does not confirm, modify or generate hiring criteria.                                                                                           |
| Candidate evaluation                       | Applications arriving through Careers follow the same automatic evidence-backed evaluation flow as other public applications.                                      |
| Referrals                                  | Valid referral attribution remains more specific than generic careers-page attribution.                                                                            |
| Recruitment pipeline                       | A careers application enters the existing configured pipeline exactly like the current public application flow.                                                    |
| AI-native recruiting operations foundation | The recruiter should not need to manually trigger evaluation or synchronize the careers page after publication.                                                    |

The existing individual public job route remains supported.

This feature must not create a second implementation of application intake.

---

## Existing product baseline

The product already has an individual public job route:

`/job/{key}`

It also already has a public application endpoint associated with that job.

The existing public job flow already supports:

- job description;
- company name;
- configured application questions;
- CV upload;
- cover-letter configuration;
- application locale;
- referral attribution;
- UTM attribution;
- application availability validation;
- automatic candidate evaluation when eligible.

This feature should reuse those capabilities.

It must not fork them into a parallel application system.

---

## Plugin evaluation

The Filament plugin catalogue was reviewed before defining this feature.

### Rankbeam SEO

Classification: **reference-only / reject for direct use**

Relevant capabilities:

- Filament 5 support;
- SEO metadata editing;
- canonical URLs;
- Open Graph;
- social preview;
- robots configuration.

Why it is not selected:

- V1 careers SEO is deterministic rather than editor-driven;
- Recruiter Labs does not currently need per-page SEO administration;
- introducing another SEO storage model and dependency would increase the
  implementation surface without solving a product problem in this feature.

### Smart SEO

Classification: **reference-only / reject for direct use**

Relevant capabilities:

- generic SEO metadata;
- Open Graph image support;
- SERP preview;
- optional AI-generated SEO content.

Why it is not selected:

- Careers V1 does not need AI-generated SEO metadata;
- it introduces polymorphic SEO storage and editor behaviour beyond the product
  requirement;
- deterministic metadata from Company and Job is sufficient.

### Vpress / Atelier

Classification: **reject**

These products provide CMS/page-building functionality.

That directly conflicts with the intentional V1 boundary:

Recruiter Labs is providing a careers page, not a general-purpose company website
builder.

### Decision

Implement the feature natively using the existing Laravel, Inertia, React and
Filament architecture.

No new dependency is required or approved by this specification.

---

## Public URL model

Each workspace may expose its careers experience at:

`/careers/{company-slug}`

Example:

`/careers/acme`

The existing Company slug is used.

The careers URL therefore follows the same workspace identity already used by
Recruiter Labs.

A separate careers slug is not introduced in V1.

Changing the workspace slug changes the careers URL just as it already changes
the workspace's tenant URL.

Slug-history redirects are out of scope.

---

## Careers page activation

The careers page is explicitly controlled by the workspace.

Introduce a careers-page enabled state.

Default for existing and newly created workspaces:

**disabled**

This avoids silently creating a new discoverable public company index merely
because Recruiter Labs deployed the feature.

When disabled:

- the careers URL is not publicly available;
- it returns a normal not-found response;
- jobs may continue to use their existing individual public URLs according to
  existing rules;
- no job publication state is changed.

When enabled:

- the careers page becomes publicly accessible;
- qualifying jobs are derived automatically from current Job state;
- no separate synchronization or republishing process is required.

Enabling Careers is a workspace presentation decision.

It does not publish jobs that are currently unpublished.

---

## Careers settings

The existing Workspace Settings area should gain a focused Careers section.

V1 supports:

### Careers page enabled

Boolean.

Controls whether the public workspace careers index exists.

### Company careers description

Optional.

Purpose:

Give candidates a short employer-level introduction before the list of roles.

This is not a general CMS field.

Requirements:

- optional;
- bounded length;
- safe public rendering;
- no arbitrary scripts or embeds;
- no page-builder semantics.

Plain text or equivalently constrained rich content is sufficient.

The implementation should prefer the simpler safe representation.

### Company careers logo

Optional.

Purpose:

Provide basic customer branding independent of Recruiter Labs branding.

Requirements:

- common web image format;
- bounded file size;
- publicly renderable;
- safe image validation;
- reasonable dimensions/responsive rendering;
- company name used as accessible fallback identity.

If no logo exists:

- the page remains fully usable;
- company name becomes the primary identity;
- no generic fake logo is required.

### Careers URL

The settings surface should show the effective public URL.

The recruiter can:

- open it;
- copy it.

When Careers is disabled, the settings UI should make that state clear.

Copying/opening the URL does not implicitly enable the page.

---

## Careers settings authorization

Careers settings follow the existing workspace-update authorization model.

This feature does not introduce new RBAC roles or permissions.

A user who cannot update the workspace cannot change its Careers settings.

Public visitors require no authentication.

---

## Careers index

The public careers page represents one workspace.

Its primary structure should be:

Company identity
→ employer description
→ open roles
→ role selection.

The page should include:

- optional company logo;
- company name;
- optional careers description;
- clear careers/jobs heading;
- list of current open jobs;
- link/action to view each job;
- empty state when no roles are open.

The experience should be intentionally simple.

It should look like a credible public careers surface rather than an internal
Filament screen.

It must not expose internal recruitment data.

---

## Job listing eligibility

The Careers index must derive job visibility from authoritative persisted Job
state.

A job belongs in the open-role listing only when it is meaningfully available
for public applications.

At minimum:

- it belongs to the workspace represented by the careers page;
- it is published;
- its start date has arrived, if configured;
- its end date has not passed, if configured;
- applications are not paused;
- its configured per-job application cap has not already been reached.

The implementation should centralize this meaning instead of scattering a new
slightly different definition of "open job" across controllers and pages.

Workspace-level billing/plan failures must not become public candidate-facing
job metadata.

A plan-limit problem is an internal workspace operational issue.

It must not appear publicly as:

"this company reached its Recruiter Labs plan limit."

---

## No manual careers synchronization

A recruiter does not separately add published jobs to the careers page.

Once:

- Careers is enabled;
- a Job is published;
- the Job is currently open for applications;

it appears automatically.

Similarly:

- pausing applications removes it from the open-role list;
- reaching its end date removes it;
- reaching its configured application cap removes it;
- unpublishing removes it;
- reopening or making it eligible again makes it available again.

This is derived product state.

There is no:

"Sync careers page"

button.

There is no:

"Publish to Recruiter Labs Careers"

step separate from the existing publication contract in V1.

---

## Job-list content

The current Job model does not contain structured:

- department;
- employment type;
- workplace arrangement;
- location.

This feature deliberately does not invent those fields merely to make the careers
cards look richer.

V1 job cards may use information already owned by the Job, such as:

- job title;
- concise description excerpt when useful;
- application/campaign availability when useful;
- clear View role / Apply action.

Introducing location, department, employment type or remote/hybrid taxonomy is a
separate product decision and is out of scope for this feature.

Do not infer those attributes from free-text job descriptions.

Do not ask AI to fabricate them.

---

## Careers-scoped job experience

Careers should provide a coherent path from the company careers page to the
existing public job experience.

The preferred public shape is:

`/careers/{company-slug}/jobs/{job-key}`

This route represents the job in the context of the employer's careers
experience.

It must:

- verify that the job belongs to the company in the route;
- reuse the existing public application presentation and intake contract;
- show the same authoritative job content and application questions;
- show the company identity;
- allow navigation back to the company's careers page;
- retain the Job's configured application locale behaviour.

This is not a second job model or second application form.

The same underlying Job is being presented through the careers context.

---

## Existing direct job URLs

Existing URLs such as:

`/job/{key}`

must remain functional according to their current contract.

Existing shared links must not break because Careers exists.

Recruiter Labs may establish the careers-scoped URL as the canonical public URL
when:

- Careers is enabled;
- the job belongs to that company;
- the job is publicly available.

But the legacy/direct route remains supported.

This feature must not require customers to redistribute every existing job URL.

---

## Public job states

The candidate experience must distinguish important public states honestly.

### Open

The job is published and accepting applications.

The application flow is available.

### Applications paused

The job remains published but intake is temporarily paused.

If a candidate follows an existing direct link:

- the job may remain understandable;
- the application form must not imply submission is available;
- the page should explain that applications are currently paused/unavailable;
- Careers index does not list it as an open role.

### Application cap reached

If the configured job-specific application cap is reached:

- Careers index no longer advertises it as open;
- an already-open job page must not pretend submission remains possible;
- a submission race is still resolved by the authoritative application-intake
  transaction.

### Ended

A published job whose configured end date has passed is not listed as open.

A careers-scoped URL that can still safely identify the published role may show
a clear "no longer accepting applications" state with a link back to the company
Careers page.

It must not show an active application form.

### Not yet open

A job whose start date has not arrived is not listed.

V1 does not need to advertise "coming soon" jobs.

### Unpublished

An unpublished job is not publicly exposed through Careers.

A public Careers request for an unpublished job behaves as not found.

Draft/private job data must not become discoverable merely because somebody
knows or guesses its identifier.

---

## No-open-jobs state

An enabled careers page with no qualifying open jobs is still a valid public
page.

It returns a normal page rather than a 404.

The candidate sees:

- company identity;
- employer description when configured;
- a clear message that there are currently no open roles.

Do not create fake jobs, waitlists or email capture in V1.

---

## Application source attribution

Recruiter Labs already distinguishes direct applications from referrals.

This feature introduces a new meaningful source:

`career_page`

The purpose is acquisition attribution.

It must not affect hiring evaluation.

### Source semantics

An application reached through the first-party Careers experience should be
stored as:

`Career Page`

when no more specific source exists.

A direct application through the existing individual job URL remains:

`Direct`

A valid referral remains:

`Referral`

even if the candidate eventually reaches the application form through a Careers
surface.

Referral is more specific attribution than generic careers discovery.

Conceptually:

valid referral
→ Referral

else careers-originated flow
→ Career Page

else
→ Direct

---

## Source integrity

Career-page source attribution should be produced from the trusted public flow,
not blindly trusted as arbitrary candidate evidence.

The implementation should preserve an honest distinction between:

- direct job URL;
- careers navigation;
- referral.

This metadata is operational attribution.

It is not candidate evidence.

It must never influence:

- criterion scores;
- Potential Match;
- Application Fit;
- Evidence Coverage;
- Confidence;
- Interview Brief;
- recommendation language.

---

## UTM attribution

Existing UTM behaviour remains supported.

UTM parameters and Application Source represent different things.

Example:

Application Source:
`Career Page`

UTM:
`utm_source=linkedin`
`utm_campaign=engineering_hiring`

Both may exist.

Careers must not remove, overwrite or collapse existing UTM tracking.

UTM values do not influence candidate evaluation.

---

## Referral compatibility

The existing referral flow remains authoritative.

A valid referral reaching a job must continue to:

- validate against the same Job/workspace;
- persist its Referral relationship;
- receive Application Source = Referral;
- preserve the existing referral analytics/workflow semantics.

Careers must not turn referrals into ordinary Career Page applications.

---

## Application intake reuse

Applications submitted from Careers must use the existing application-intake
domain path.

They must preserve existing guarantees including:

- server-side availability checks;
- tenant/job association;
- application questions;
- CV validation;
- cover-letter validation;
- duplicate-application prevention;
- normalized candidate identity;
- candidate reuse when appropriate;
- referral validation;
- UTM persistence;
- initial pipeline status;
- document storage;
- application limits;
- automatic candidate evaluation;
- transaction integrity.

Do not create a Careers-specific `Application` creation path that bypasses
`SubmitJobApplication`.

---

## AI-native behaviour

This feature should follow the AI-native operating model established by
`ai-native-recruiting-operations-foundation`.

Careers itself does not need artificial AI functionality.

The valuable automation is operational.

### No redundant recruiter operation

Recruiters should not:

- manually copy a published job into Careers;
- manually remove a paused/closed job from Careers;
- manually start candidate evaluation after a Careers application arrives;
- manually synchronize job changes into a second careers database.

Recruiter Labs already knows those states.

### Application arrival

Career Page
→ candidate applies
→ normal Application is created
→ if current criteria are confirmed and evidence is eligible
→ Recruiter Labs automatically begins candidate evaluation.

This feature does not add another human "Evaluate" ceremony.

### AI boundaries

Careers does not automatically:

- generate or rewrite job descriptions;
- invent employer marketing copy;
- generate company claims;
- publish jobs;
- confirm hiring criteria;
- rank careers visitors;
- reject candidates;
- contact candidates;
- make hiring decisions.

A future feature may support AI-assisted job creation or employer copy.

That is not part of Careers V1.

---

## Basic company branding

V1 branding consists only of:

- company/workspace name;
- optional logo;
- optional careers description.

The public experience may use Recruiter Labs' neutral layout and typography.

It does not support per-company:

- fonts;
- arbitrary colors;
- CSS;
- layouts;
- content blocks;
- hero builders;
- background images;
- videos;
- navigation builders;
- custom components.

The purpose is credible employer identity without creating a website builder.

---

## Recruiter Labs branding

This feature must not depend on the future Recruiter Labs rebranding project.

The Careers experience should use a neutral implementation that can survive the
later product rebrand.

Do not embed major hard-coded Recruiter Labs marketing language into customer
careers pages.

A minimal "Powered by Recruiter Labs" treatment may exist if desired by the
current product convention, but building configurable white-label behaviour is
out of scope.

---

## SEO

The public Careers experience needs basic technical SEO.

It does not need an SEO CMS.

### Careers index metadata

The page should provide:

- meaningful `<title>`;
- meta description;
- canonical URL;
- Open Graph title;
- Open Graph description;
- Open Graph URL;
- appropriate social image when a usable company logo exists.

Example title:

`Careers at Acme`

The company careers description may provide the meta-description source.

When none exists, use a safe deterministic fallback.

Do not ask an LLM to generate metadata.

---

## Job metadata

The careers-scoped job page should provide:

- job title;
- company name;
- meaningful meta description;
- canonical URL;
- Open Graph title;
- Open Graph description;
- Open Graph URL;
- optional company logo as social image when appropriate.

Existing direct job URLs should not create harmful duplicate-indexing behaviour.

The implementation should emit an appropriate canonical URL.

---

## Robots behaviour

Public enabled Careers pages may be indexable.

Open public jobs may be indexable.

The following must not be indexable as public job opportunities:

- admin previews;
- unpublished jobs;
- private drafts.

Unavailable/ended job pages that remain accessible only to explain that the role
closed should not compete with active roles in search results.

A sensible noindex/follow treatment is acceptable for such states.

---

## Structured data

Basic JobPosting structured data may be introduced only when the product has
enough trustworthy structured fields to represent it correctly.

The current Job model does not have several attributes normally expected by rich
JobPosting schema.

Therefore:

**JobPosting JSON-LD is not required in this V1.**

Do not infer structured location, salary, employment type or workplace mode from
free text merely to populate schema.org fields.

Incomplete honest SEO is preferable to fabricated structured data.

---

## Open Graph

The page must remain shareable even without a custom company logo.

Fallback order should conceptually be:

company logo
→ safe application default.

Do not create AI-generated social images.

---

## Public design requirements

The Careers experience should be:

- responsive;
- usable on mobile;
- accessible by keyboard;
- readable without authentication;
- visually coherent with the existing public job application flow;
- clearly separated from the internal admin product.

The page should prioritize content over visual customization.

The hierarchy should be obvious:

Company
→ Open roles
→ Job
→ Apply.

---

## Accessibility

Public Careers UI must use semantic public-web behaviour.

At minimum:

- correct heading hierarchy;
- usable keyboard navigation;
- visible focus behaviour;
- accessible links/buttons;
- logo alt/fallback based on company identity;
- form semantics inherited from the existing application flow;
- status messages not communicated only by color.

This feature should not reduce accessibility already present in the application
flow.

---

## Public data boundary

The Careers page may expose only data intentionally belonging to the public
candidate experience.

It must not expose:

- internal criteria;
- criteria weights;
- AI review alerts;
- candidate evaluations;
- sourcing matches;
- internal pipeline stages;
- recruiter identities unless intentionally part of existing public content;
- team membership;
- interview data;
- AI usage;
- plan information;
- internal candidate counts;
- application counts;
- hiring targets unless explicitly made public by a future feature;
- workspace settings unrelated to Careers.

---

## Tenant isolation

A Careers request for Company A must never expose jobs from Company B.

The company slug and job key must be validated as belonging to the same
workspace.

Knowing another workspace's job key must not allow it to appear under a different
company careers URL.

Public routes do not weaken tenant ownership.

---

## Public identifiers

The careers index uses the existing Company slug.

Individual jobs continue to use the existing non-sequential public job key.

Do not expose internal database IDs in public URLs merely for this feature.

---

## Company slug changes

The Company slug already identifies the workspace in product URLs.

Changing it also changes:

`/careers/{company-slug}`

The Careers settings surface should always show the current effective URL.

Automatic historical slug redirects are not part of V1.

---

## Careers description safety

Public company description content is workspace-controlled.

It must still be rendered safely.

No arbitrary JavaScript.

No unsafe iframe/embed behaviour.

No candidate-controlled content should enter this field.

---

## Logo lifecycle

Replacing a careers logo must not leave the Company referencing an unavailable
file.

Removing the logo returns the page to name-based branding.

File lifecycle should follow the project's existing storage conventions.

No candidate document storage should be reused for branding assets.

Public employer branding and private candidate documents have different privacy
requirements.

---

## Internal admin experience

The admin product should make Careers discoverable without creating a new major
navigation area.

V1 should live naturally in Workspace Settings.

The recruiter should be able to understand:

- whether the careers page is enabled;
- its current URL;
- its company description;
- its logo;
- how to open it;
- how to copy its URL.

Do not introduce a full Careers CMS resource.

---

## Job workspace integration

A recruiter looking at a published Job should be able to reach its effective
public URL without reconstructing it manually.

Where the current product already exposes "Open public page", that action may use
the careers-scoped URL when the Careers page is enabled.

When Careers is disabled, the existing direct job URL continues to work
according to its current contract.

Do not remove useful existing public-link behaviour.

---

## User flows

### Flow A — Enable careers page

1. Recruiter opens Workspace Settings.
2. Recruiter opens the Careers section.
3. Recruiter optionally adds:
    - company careers description;
    - company logo.
4. Recruiter enables Careers.
5. Recruiter saves.
6. Recruiter sees the effective public URL.
7. Recruiter opens or copies it.
8. Public candidates can access the page.

No jobs are published as a side effect.

---

### Flow B — Enabled page with open jobs

1. Workspace Careers is enabled.
2. Workspace has three published jobs.
3. Two are currently accepting applications.
4. One is paused or outside its campaign window.
5. Careers index shows the two open jobs.
6. Candidate chooses one.
7. Candidate reaches the careers-scoped job page.
8. Candidate applies using the existing intake flow.
9. Application Source is Career Page unless a valid Referral is more specific.
10. Existing UTM data is preserved.
11. If confirmed criteria exist, Recruiter Labs automatically begins evaluation.
12. Recruiter sees the application through the normal recruitment workflow.

---

### Flow C — No open jobs

1. Careers is enabled.
2. No Job is currently eligible for the open-role list.
3. Public Careers URL remains valid.
4. Company identity and description remain visible.
5. Candidate sees that there are currently no open roles.

---

### Flow D — Job becomes open

1. Careers is enabled.
2. Job is currently unpublished.
3. Recruiter publishes it.
4. Its campaign window allows applications.
5. It automatically appears on Careers.
6. No Careers-specific publish action is needed.

---

### Flow E — Applications paused

1. Open Job appears on Careers.
2. Recruiter pauses applications.
3. Job stops appearing in the open-role list.
4. Existing shared job links do not accept new applications.
5. Re-enabling applications makes the job eligible to appear again.

---

### Flow F — Job ends

1. Open Job is listed.
2. Its end date passes.
3. It disappears from the open-role listing.
4. A candidate following an existing Careers job link can receive an honest
   closed-role state when supported by the public contract.
5. No application form is presented as available.

---

### Flow G — Application cap reached

1. Job has an application cap.
2. The final available application is accepted.
3. Subsequent Careers views no longer present the role as open.
4. A concurrent final submission race is still decided by the authoritative
   application transaction.
5. No extra application is accepted because the Careers list was momentarily
   stale.

---

### Flow H — Direct job link

1. Recruiter shares `/job/{key}` directly.
2. Candidate applies through that direct flow.
3. Application Source remains Direct unless Referral applies.
4. Careers being enabled does not rewrite that history as Career Page.

---

### Flow I — Referral through careers context

1. Candidate has a valid referral.
2. Candidate reaches the job through a careers-related route.
3. Referral validates against the Job.
4. Application is stored as Referral.
5. Career-page discovery does not erase the more specific referral attribution.

---

### Flow J — Workspace disables Careers

1. Recruiter disables Careers.
2. `/careers/{slug}` becomes unavailable publicly.
3. Careers-scoped discovery is disabled.
4. Existing Jobs and Applications remain untouched.
5. Existing direct public job routes retain their existing behaviour.
6. No historical application source is changed.

---

## Business rules

### Careers is workspace-owned

One workspace has at most one first-party Careers page in V1.

### Careers activation is explicit

Creating a workspace does not silently create a discoverable careers index.

### Publication remains job-owned

Careers does not publish a Job.

It reflects publication.

### Careers visibility is derived

Qualifying jobs appear without an additional careers synchronization step.

### Open means open

The Careers list must not intentionally advertise jobs that Recruiter Labs knows
are not accepting applications under job-level availability rules.

### Public visibility does not imply hiring validity

A published job appearing publicly does not mean its criteria have been
confirmed.

Job publication and AI evaluation criteria are separate concepts.

Candidates may apply while criteria are still awaiting recruiter confirmation.

Their evaluation waits according to the existing candidate-evaluation contract.

### Source is attribution, not evidence

Career Page source cannot affect candidate assessment.

### Referral wins over generic source

A valid Referral is more specific attribution than Career Page or Direct.

### UTM is independent

UTM parameters supplement rather than replace source attribution.

### No duplicated intake implementation

Careers uses the existing application submission domain path.

### No hidden cross-tenant joins

Company and Job association must be validated on every careers-scoped job
request.

### Public pages contain public data only

Careers must not become an accidental window into internal ATS state.

---

## Acceptance criteria

- **AC01** — A workspace has an explicit Careers enabled/disabled setting.

- **AC02** — Existing and newly created workspaces default to Careers disabled
  unless another explicitly approved migration decision says otherwise.

- **AC03** — When Careers is disabled, `/careers/{company-slug}` is not publicly
  accessible.

- **AC04** — Enabling Careers does not publish, unpublish or otherwise modify any
  Job.

- **AC05** — An enabled workspace has a public Careers URL based on its existing
  Company slug.

- **AC06** — Workspace Settings displays the effective Careers URL.

- **AC07** — An authorized workspace member can copy/open the Careers URL from the
  admin experience.

- **AC08** — A workspace may provide an optional public careers description.

- **AC09** — A workspace may provide an optional careers logo.

- **AC10** — The Careers page works correctly when no logo exists.

- **AC11** — Public description/logo rendering cannot execute arbitrary scripts
  or expose private files.

- **AC12** — Careers lists only Jobs belonging to the requested workspace.

- **AC13** — Unpublished Jobs never appear in Careers.

- **AC14** — Jobs whose configured start date has not arrived do not appear as
  open roles.

- **AC15** — Jobs whose configured end date has passed do not appear as open
  roles.

- **AC16** — Jobs with paused applications do not appear as open roles.

- **AC17** — Jobs whose configured per-job application cap has been reached do
  not appear as open roles.

- **AC18** — Workspace billing/plan state is not exposed publicly as careers/job
  metadata.

- **AC19** — Eligible Jobs automatically appear/disappear from Careers when their
  authoritative state changes; no careers synchronization action is required.

- **AC20** — An enabled Careers page with zero open jobs remains a valid public
  page and shows a clear no-open-jobs state.

- **AC21** — A candidate can navigate from Careers to a job-specific public
  experience.

- **AC22** — The careers-scoped job route validates that Company and Job belong
  together.

- **AC23** — Knowing a valid Job key from another workspace cannot expose that Job
  under the wrong Company's Careers URL.

- **AC24** — An unpublished Job requested through Careers is not publicly exposed.

- **AC25** — A published but no-longer-available role does not present an active
  application form as if submissions were still accepted.

- **AC26** — Existing `/job/{key}` public links remain supported.

- **AC27** — Careers reuses the existing Job/Application intake domain rather than
  introducing an independent application creation implementation.

- **AC28** — A Careers application retains all current CV, cover-letter,
  application-question, duplicate, candidate identity and pipeline semantics.

- **AC29** — An application originating through Careers can be stored with
  Application Source = Career Page.

- **AC30** — A normal existing direct job-link application remains Source =
  Direct.

- **AC31** — A valid referral remains Source = Referral even when the candidate
  passed through the Careers experience.

- **AC32** — Existing UTM attribution is preserved for Careers applications.

- **AC33** — Application Source and UTM values do not affect AI evaluation,
  criterion scores, Evidence Coverage, Confidence or Interview Brief.

- **AC34** — Careers applications automatically enter the existing candidate
  evaluation workflow when existing eligibility gates allow it.

- **AC35** — Recruiters do not need to manually trigger evaluation merely because
  the application originated on Careers.

- **AC36** — Careers does not automatically confirm criteria, publish jobs,
  reject candidates, move pipeline stages or make hiring decisions.

- **AC37** — Careers index provides deterministic title/meta-description and
  canonical metadata.

- **AC38** — Careers job pages provide deterministic job/company SEO metadata.

- **AC39** — Public Careers and open Job pages provide appropriate basic Open
  Graph metadata.

- **AC40** — The product does not require a generic SEO plugin or new SEO CMS to
  satisfy V1.

- **AC41** — The feature does not fabricate JobPosting structured data from
  information Recruiter Labs does not possess.

- **AC42** — Careers is usable on mobile.

- **AC43** — Primary Careers navigation and role links are keyboard accessible.

- **AC44** — Public branding has an accessible fallback when no company logo
  exists.

- **AC45** — Careers does not expose internal hiring criteria, AI analysis,
  candidate data, pipeline state, sourcing state, recruiter team data or plan
  information.

- **AC46** — Changing the workspace slug changes the effective Careers URL and
  the admin reflects the new URL.

- **AC47** — Disabling Careers does not delete Jobs, Applications, application
  attribution or company branding data.

- **AC48** — No page-builder, theme builder, custom-domain system or candidate
  portal is introduced.

---

## Product edge cases

### Careers disabled with published jobs

Individual public job URLs continue under the existing contract.

The careers index remains unavailable.

### Careers enabled with no jobs

Show the employer page and a no-open-roles message.

Do not 404.

### Company has no careers description

Do not show empty placeholder copy as if the company provided it.

Use concise deterministic metadata where required.

### Company has no logo

Use the company name as the public identity.

### Logo removed

Public page immediately returns to name-based presentation.

### Job is unpublished while candidate has its page open

Submission still passes through authoritative availability validation.

A stale browser page cannot force acceptance.

### Job becomes paused while candidate has its page open

Submission is rejected by the authoritative intake rules.

Do not accept based on stale frontend state.

### Job reaches application limit during concurrent submissions

The existing locked application-availability path remains authoritative.

Careers UI does not bypass it.

### Workspace changes slug

Old Careers URL is not guaranteed to redirect in V1.

The new URL is shown in Workspace Settings.

### Wrong Company + valid Job key

Return not found.

Do not redirect to the Job's real company because doing so would confirm a
cross-tenant relationship unnecessarily.

### Direct job route while Careers is enabled

It remains supported.

Canonical metadata may point to the Careers-scoped job URL where appropriate.

### Referral + Career Page

Referral remains the persisted Application Source.

UTM may still record acquisition campaign information independently.

### Mixed job application locales

Each Job retains its own application locale.

The Careers page must not rewrite Job-authored content into another language.

V1 does not introduce automatic translation of employer/job content.

### Public page crawler

The crawler receives useful deterministic metadata without requiring
authentication, JavaScript-generated AI content or a CMS.

---

## Out of scope

This feature deliberately does not introduce:

- custom careers domains;
- domain verification;
- white-label domain routing;
- careers themes;
- arbitrary company colors;
- custom fonts;
- custom CSS;
- visual page builder;
- block-based CMS;
- careers navigation builder;
- multiple careers pages per workspace;
- department pages;
- location pages;
- employment-type taxonomy;
- remote/hybrid/on-site taxonomy;
- salary fields;
- compensation disclosure;
- JobPosting JSON-LD requiring fabricated missing data;
- automatic translation of job content;
- AI-generated employer description;
- AI-generated job descriptions;
- AI SEO generation;
- candidate accounts;
- candidate portal;
- saved jobs;
- job alerts;
- talent community / speculative applications;
- newsletter capture;
- employee testimonials;
- team pages;
- sophisticated Careers analytics;
- funnel analytics beyond existing source/UTM data;
- job-board syndication;
- Google Jobs integration as a separate distribution product;
- LinkedIn/Indeed publishing;
- external candidate sourcing;
- custom privacy/legal content builder;
- per-job careers listing overrides separate from normal publication;
- historical Company-slug redirects;
- configurable "Powered by" white-label plans.

Those require separate product decisions.

---

## Expected product change

Before:

Recruiter publishes Job
→ Recruiter Labs creates an individual public URL
→ recruiter distributes that exact URL somewhere else
→ candidate applies
→ application enters Recruiter Labs.

There is no first-party place to discover the company's other roles.

After:

Recruiter enables Careers once
→ Recruiter Labs exposes `/careers/{company}`
→ every currently open published Job appears automatically
→ candidate discovers a role
→ candidate opens it
→ candidate applies
→ application source is preserved
→ Recruiter Labs automatically continues the existing evaluation workflow.

The recruiter manages recruitment state.

Recruiter Labs operates the public projection of that state.

---

## Commercial value

This feature closes the passive acquisition side of the Recruiter Labs product
story.

The product can then demonstrate two complete entry paths:

### Passive acquisition

Careers Page
→ Job
→ Application
→ automatic evaluation
→ Pipeline
→ Interview
→ Human decision.

### Active internal acquisition

Talent Pool
→ Internal Sourcing
→ recruiter authorization/review
→ Add to Job
→ Pipeline
→ Interview
→ Human decision.

That makes Recruiter Labs materially closer to being usable as the primary ATS
for a small internal recruiting team rather than only an intelligence layer over
candidate data.

---

## Success criteria

The feature is successful when a workspace can use Recruiter Labs itself as a
credible basic public careers destination without operating a second publishing
workflow.

Specifically:

- a recruiter can configure basic employer identity and enable Careers;
- the workspace gets one shareable public Careers URL;
- current open roles appear automatically;
- closed/paused/unavailable roles do not masquerade as open;
- candidates can discover and apply to a role from that experience;
- the application enters exactly the same trusted internal workflow as existing
  public applications;
- Career Page acquisition is attributable without affecting candidate quality;
- referral and UTM attribution remain intact;
- no private ATS information leaks into the public experience;
- no generic CMS, page builder or SEO subsystem is introduced.

---

## Related feature specs

- `../application-intake/spec.md`
- `../job-workspace/spec.md`
- `../job-evaluation-criteria/spec.md`
- `../candidate-evaluation/spec.md`
- `../recruitment-pipeline/spec.md`
- `../referrals/spec.md`
- `../ai-native-recruiting-operations-foundation/spec.md`
- `../first-workspace-activation/spec.md`
