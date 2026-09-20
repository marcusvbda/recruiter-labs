---
status: implemented
type: as-built
---

# Talent pool import and materials

## Problem

Recruiter Labs can already receive public applications, evaluate application
evidence, and rediscover candidates from previous recruitment processes.
However, a new customer may hold its existing candidate pool in spreadsheets
and folders rather than in Recruiter Labs applications.

The existing candidate profile stores contact details independently of a job.
Reusable CV evidence currently comes from previous applications. Consequently,
creating contact records alone does not make an imported talent pool useful for
evidence-based rediscovery.

Customers should not have to invent a historical job, create artificial
applications, ask every historical candidate to apply again, or manually
recreate their entire contact list just to start using their own candidate pool.

This feature supplies the missing entry point:

Existing contacts and candidate-provided CVs
→ reviewed import
→ workspace candidates and independent materials
→ explicit sourcing against a job's confirmed criteria
→ human review
→ the existing recruitment workflow.

## Objective

Allow an authorized workspace recruiter to:

- import candidates from a documented CSV template;
- associate historical CVs with the intended candidates before committing;
- understand and resolve duplicate identities and invalid rows;
- add and manage CVs directly on a candidate without requiring an application;
- preserve who supplied a material, its declared origin, and its known age;
- make eligible imported CV evidence available to existing internal sourcing;
- recover from interrupted or partially successful imports without duplication.

Success means a customer can bring a supported contact list and CV collection
into the workspace, inspect the result, and explicitly use the available
candidate-provided evidence in an existing sourcing search.

Import completion does not mean a candidate has applied, has consented to a
particular vacancy, has been evaluated, or is suitable for a job.

## Product context and source-of-truth boundary

This is a planned product specification, not a description of functionality
already implemented. It defines observable behaviour and acceptance criteria.

The document contract is defined in
[Feature documentation](../README.md). Project-wide rules remain in
[CLAUDE.md](../../../CLAUDE.md). The relevant product invariants remain in
[Recruitment workflow](../../../.claude/skills/recruitment-workflow/SKILL.md) and
[Evaluation integrity](../../../.claude/skills/evaluation-integrity/SKILL.md).

The existing feature contracts that matter are:

| Existing contract                                                                       | Behaviour preserved or extended                                                                                                                                             |
| --------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Active candidate sourcing foundation](../active-candidate-sourcing-foundation/spec.md) | Extend eligible source material with independent candidate CVs. Preserve job-specific analysis, human initiation, evidence semantics, and saved/dismissed decisions.        |
| [Application intake](../application-intake/spec.md)                                     | Preserve public submission, company-scoped identity, original application documents, questions, provenance, and limits. Import is a separate entry into the candidate pool. |
| [Candidate evaluation](../candidate-evaluation/spec.md)                                 | Preserve application-specific evidence and evaluation. Pool materials do not automatically become application evidence.                                                     |
| [Job evaluation criteria](../job-evaluation-criteria/spec.md)                           | Preserve current human-confirmed criteria as the gate for later sourcing and evaluation.                                                                                    |
| [Workspace team access](../workspace-team-access/spec.md)                               | Preserve normal recruitment access for enabled Owners and Members and immediate workspace-access revocation.                                                                |
| [First workspace activation](../first-workspace-activation/spec.md)                     | Preserve the requirement for a real application and a successful application evaluation. Imports do not complete those milestones.                                          |
| [AI usage and limits](../ai-usage-and-limits/spec.md)                                   | Import and document preparation do not consume AI-analysis allowance. Later sourcing continues to obey its existing allowance and credential rules.                         |
| [Job workspace](../job-workspace/spec.md)                                               | Keep recruitment work inside the relevant job; importing a pool does not create another job workspace or pipeline.                                                          |

The sourcing specification deliberately excluded CSV import from its original
scope and explicitly anticipated imported talent pools as an extension.
This specification supplies that extension. It also introduces material-aware
sourcing freshness so changes to the new materials cannot leave old results
presented as current.

Existing as-built documents must remain accurate until implementation changes
their observable behaviour. At implementation completion, update only the
affected descriptions and cross-references. This specification does not
authorize unrelated feature redesigns.

No separate technical design is supplied by this feature. Implementation
choices follow the repository's normal feature-execution workflow and the
documentation contract. Do not infer a requirement for a particular importer,
document library, infrastructure provider, or new architectural layer.

## Intended customer and first-version boundary

The initial customer is a small internal recruitment team bringing an existing
candidate pool from spreadsheets and folders.

Version one deliberately uses a standard CSV and explicitly matched PDF/DOCX
files. Assistance means a human helps the customer prepare those inputs and
uses the same authorized import flow. It does not mean a hidden operator bypass,
unreviewed production changes, or a bespoke migration for each customer.

The required entry points are:

1. **Import candidates** from the Candidates area.
2. **Import history** within the Candidates area.
3. **Add CV** from an existing candidate's profile.
4. **Materials** within the candidate profile for viewing and managing CVs.

Creating a candidate manually and then adding a CV remains supported. Bulk
CV-only identity extraction is not part of version one: a collection of files
without a CSV requires the recruiter to select or create the intended candidate
before attaching each file.

## Terminology

| Term                 | Meaning                                                                                                                          |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| Candidate            | A person known to one workspace, independently of participation in a vacancy.                                                    |
| Import batch         | One uploaded CSV, its optional CV files, the review decisions, and the resulting execution/report.                               |
| Import row           | One data record from the CSV. It represents at most one candidate identity and one optional CV.                                  |
| Candidate material   | A candidate-provided CV stored directly against the candidate rather than a particular application.                              |
| Application document | An existing document belonging to a particular application. It retains that ownership and history.                               |
| Source label         | Recruiter-supplied context such as "Historical ATS export" or "Recruitment inbox archive"; not verified evidence about a person. |
| Received date        | The date the workspace says it originally received the CV, when known.                                                           |
| Added date           | When the material actually became part of Recruiter Labs. It is never substituted for a missing historical received date.        |
| Available material   | A retained candidate CV that has not been archived or deleted. Its text readiness is a separate state.                           |
| Archived material    | A retained candidate CV deliberately excluded from future sourcing, while remaining inspectable by authorized workspace users.   |
| Deleted material     | A removed candidate CV whose file and derived content must no longer be accessible through this feature.                         |

## Product principles

### Review precedes candidate changes

Uploading, validating, preparing, or previewing an import must not create or
modify candidate records or candidate materials. Only explicit confirmation
starts changes to the candidate pool.

### Candidate identity is workspace-scoped

Identity resolution must never use another workspace's records. A matching
email in another workspace is neither a duplicate warning nor a reason to link
or refuse this workspace's candidate.

### Existing information is preserved

Import may reuse an existing candidate and add a CV. It must not overwrite
existing contact information, erase blanks into populated fields, merge people,
or replace historical applications.

### Import is not a recruitment decision

Import creates no job participation, score, stage, interview, message, hiring
outcome, or sourcing decision. Every later recruitment action remains explicit.

### Missing information stays missing

Unknown dates, unreadable CVs, absent contact fields, and insufficient sourcing
evidence must be described honestly. None becomes a negative candidate signal.

### Stored successfully and usable for sourcing are separate outcomes

A supported CV can be stored successfully while its text is still being
prepared or cannot be read. A contact can be imported successfully without any
CV. The product must show these distinctions.

## Authorization and workspace context

An enabled Owner or Member with the existing Candidates feature available may
start, review, confirm, inspect, and retry imports and manage candidate materials.
This remains normal recruitment access; do not introduce an import-specific
role, owner-only recruitment workflow, or granular permissions system.

All batch pages, row reports, previews, progress, material actions, file
downloads, and generated report downloads require current workspace access.
Remembered URLs and previously opened pages must not bypass that requirement.

The workspace is established by the authorized product context. No CSV field,
filename, URL parameter supplied as import data, or material metadata may choose
the destination workspace.

The upload and confirmation screens state the workspace name. Switching
workspaces does not move the draft or change its destination. A stale browser
page must not confirm a batch into a different workspace.

Import history is shared workspace recruitment information. Authorized members
can inspect a colleague's batch. A member may explicitly confirm an unconfirmed
draft or resume remaining work; the product records who actually confirmed or
resumed it rather than impersonating the original uploader.
Materials committed after a different member explicitly resumes identify that
member as the adding actor. Previously committed materials retain their
original attribution.

Current authorization and Candidates-feature availability are rechecked before
each not-yet-committed row. If the responsible user loses access or the feature
becomes unavailable, remaining work pauses. Successfully committed rows remain
intact. Another enabled member may explicitly review and resume remaining work
when the workspace is eligible again.

A row already committed before revocation is not undone. Work that has not
committed when revocation becomes effective must not subsequently be authorized
using the former access.

## Supported inputs and operational limits

These limits define version-one capacity, not new subscription packages.
They must be visible before upload and enforced on every input path.

| Input or operation                       | Required limit                                                                           |
| ---------------------------------------- | ---------------------------------------------------------------------------------------- |
| CSV files per batch                      | One                                                                                      |
| CSV encoding                             | UTF-8, with or without a UTF-8 byte-order mark                                           |
| CSV size                                 | At most 5 MiB                                                                            |
| Candidate data rows                      | At most 1,000, excluding the header and entirely empty records                           |
| CSV separators                           | Comma or semicolon, selected explicitly; comma is the default and the template separator |
| CV formats                               | PDF and DOCX only                                                                        |
| CV size                                  | At most 10 MiB per file                                                                  |
| CVs attached to one import batch         | At most 100                                                                              |
| Aggregate CV upload size per batch       | At most 250 MiB                                                                          |
| CV references per CSV row                | Zero or one                                                                              |
| Files per direct Add CV action           | One                                                                                      |
| Retained independent CVs per candidate   | At most 20, including archived CVs; application documents do not count                   |
| Unconfirmed import batches per workspace | At most three in Draft, Validating, or Ready for review combined                         |
| Executing imports per workspace          | One at a time                                                                            |

For size comparisons, one MiB is 1,048,576 bytes. Values exactly at a limit are
accepted when otherwise valid; values above it are refused with the relevant
limit explained.

When another import is executing, confirmation explains that condition and
links to its progress. The new batch stays Ready for review; it is not silently
scheduled to run later. After capacity is available, the user reviews current
conditions and confirms again. To create a fourth unconfirmed batch, finish or
discard one of the existing drafts first.

A customer with more than 100 CVs splits the collection into batches. Contact
rows without CVs may still use the full 1,000-row capacity. The UI must not claim
that selecting 1,000 contacts includes uploading 1,000 files in one operation.

Do not accept Excel workbooks, legacy DOC files, images, compressed archives,
email attachments fetched from an inbox, remote file URLs, or proprietary ATS
export formats through this version. Explain how to supply the supported CSV
and PDF/DOCX inputs instead.

## CSV template and field contract

The product provides a downloadable template and a concise field guide before
upload. Header identifiers remain English and stable in every interface
language. The explanatory UI follows the existing localization system.

The required headers are **name** and **email**. Optional headers may be omitted.
Headers may appear in any order; surrounding whitespace and letter case in
headers are ignored. Duplicate headers after normalization, unknown headers, or
missing required headers are file-level errors. They must not be silently
ignored or mapped to internal fields.

| Column       | Required value     | Validation and meaning                                                                                                                                                                                       |
| ------------ | ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| name         | Yes, for every row | Nonblank, at most 255 characters after outer whitespace is removed. Preserve accents, punctuation, and display capitalization.                                                                               |
| email        | Yes, for every row | One syntactically valid email address, at most 255 characters. Trim outer whitespace and compare case-insensitively. Do not rewrite aliases or provider-specific address forms.                              |
| phone        | No                 | International number beginning with + and containing 7–15 digits after removing spaces, parentheses, hyphens, and periods. The first digit after + must not be zero. No extensions or inferred country code. |
| linkedin_url | No                 | Absolute HTTPS personal-profile URL on linkedin.com or www.linkedin.com with a nonempty /in/ profile path. Store as a contact link; do not fetch or enrich it.                                               |
| cv_filename  | No                 | Exact filename of one CV selected for this batch, including extension, at most 255 characters. A filename is an association key, never a path or remote URL.                                                 |
| received_on  | No                 | Known date the workspace received the referenced CV, in YYYY-MM-DD form, with a real calendar date and no future date. Requires cv_filename.                                                                 |
| source_label | No                 | Row-level source label, at most 120 characters. When empty, use the required batch source label.                                                                                                             |

The batch source label is required, 1–120 characters after trimming. It
describes where the collection came from and has no effect on fit, coverage,
confidence, candidate order, or hiring outcomes.
The field guide asks for a collection/source description rather than candidate
names, email addresses, sensitive notes, or other personal details.

Email is required for CSV import even though the existing manual candidate flow
can retain candidates without email. This deliberate batch boundary enables
predictable identity matching. It does not make email mandatory for existing
manual candidates or for adding a CV directly to an already selected candidate.

Dates use the workspace's existing displayed timezone where available, otherwise
the product's application timezone, to decide whether a date is in the future.
Received dates are calendar dates, not invented midnight submission timestamps.
Unknown dates remain empty. Import must not infer a date from a filename, a CV
employment entry, a document's embedded properties, or the date of migration.

Blank optional cells mean "no information supplied". Phone and LinkedIn details
are validated when supplied even for rows that will reuse an existing candidate.
Numeric-looking values remain text; leading digits and the + prefix must not be
lost through spreadsheet-style number conversion.

Multiple-line quoted fields, quoted separators, escaped double quotes, LF and
CRLF line endings are supported. Invalid quoting or an inconsistent number of
fields is a file-level error. Entirely empty data records are ignored and
counted as ignored; they do not shift the original record identifiers in reports.

CSV data rows are numbered as logical records, with the header as record 1.
Quoted line breaks do not create additional candidate rows. Reports use this
same original record number throughout preview, execution, and recovery.

Example:

    name,email,phone,linkedin_url,cv_filename,received_on,source_label
    Alex Morgan,alex.morgan@example.com,+353871234567,https://www.linkedin.com/in/alex-example,alex-cv.pdf,2025-11-14,Historical candidate submission
    Jamie Taylor,jamie.taylor@example.com,,,,,Legacy contact list

The template uses fictional example identities and contains no real customer
information. Examples must be clearly identified as examples and must not be
automatically imported into a workspace.

## Candidate identity and duplicate rules

### Matching an existing candidate

The only automatic candidate identity key is normalized email within the
destination workspace.

- No match: propose creating a candidate.
- Exactly one match: propose reusing that candidate.
- Multiple existing matches after normalization: block the row as ambiguous.
  Do not choose one or repair historical records through import.

Do not match or merge by name, phone, LinkedIn URL, filename, CV contents, or
similarity. Do not treat two email aliases as equivalent. An existing candidate
without email is not automatically claimed by a row with a similar name.

For an exact email match, a name comparison that ignores letter case and
collapses whitespace is used only to identify a review warning. It is not a
second identity key.

If the names differ beyond that comparison, the row requires a deliberate
**Use existing candidate** decision or exclusion. Show the existing identity
and incoming name together. The default must not attach a CV to an identity
conflict without review.

### Preserving existing contact information

Reusing a candidate preserves all existing name, email, phone, and social
profile fields, including fields currently empty. This version is not an
update-or-overwrite contact importer.

Show that differing incoming contact values will not be applied. The recruiter
can edit the candidate through the normal candidate-editing flow separately.
An existing candidate with no new CV is an **Existing candidate — unchanged**
outcome, not an updated contact.

For a newly imported candidate, retain a small first-entry provenance record:
added through import, the effective row source label, the acting importer,
actual added date, and batch reference. Show this as origin context on the
candidate profile even when the candidate has no CV and the batch's temporary
row report has expired. It is not a historical application or a claim about
when the workspace first met the candidate.

Reusing a candidate must not replace its original first-entry provenance.
A newly attached CV keeps its own material provenance. An unchanged contact-only
row does not create a new candidate-origin event.

### Duplicate rows within a CSV

All rows sharing one normalized email are marked as a duplicate group, even
when their contents are identical. None is silently selected as the winner.

The reviewer may select exactly one row from the group and exclude the others,
or exclude the whole group. They must correct the CSV if information from more
than one row should be combined. Multiple CVs for a candidate can be added later
through the candidate profile.

### Changes after preview

Confirmation does not grant permission to resolve a new ambiguity silently.
Identity, selected candidate existence, file associations, relevant limits, and
permissions are checked again when each row is applied.

If a candidate appeared under the email after preview, disappeared, changed
email, or no longer matches the reviewed identity decision, that row needs
review and must not create a duplicate or attach a document elsewhere.
Unrelated rows may still complete.

Concurrent import, manual creation, and public intake must still produce at
most one candidate for the same normalized workspace email. Safe recovery must
not depend on these paths happening sequentially.

## CV association and validation

CSV filenames are matched against files explicitly uploaded into that batch.
Normalize Unicode filename representation consistently, trim outer whitespace,
and otherwise match the filename exactly, including case.

No association may be guessed from a person's name, an email embedded in a CV,
the order of selected files, or filename similarity.

Reject references containing path separators, traversal components, remote
URLs, or control characters. A supported-looking extension does not make a
remote URL an accepted filename.

The preview must identify:

- a row referencing a missing file;
- multiple uploaded files with the same association filename;
- one file referenced by multiple selected rows;
- identical file contents assigned to different candidate identities within the
  same batch;
- unsupported, oversized, empty, corrupt, password-protected, or otherwise
  unsafe-to-process files;
- uploaded files that no selected row references.

Ambiguous associations block the affected rows. The recruiter must fix the file
selection/reference, exclude the row, or explicitly choose **Import contact
only** for that row. No file is attached through an ambiguous association.

Import contact only removes that row's CV and received-date contribution from
the confirmed manifest; it does not claim that the referenced CV was imported.
Any remaining file-level ambiguity must be resolved before it can be used by
another row.

Unreferenced uploads are excluded, clearly counted, and later removed with
temporary import data. They must never become anonymous candidate materials.

File content and extension must agree. Files must not execute active content
during validation, preview, extraction, or download. DOCX is accepted as the
specified document format; this is not permission to accept arbitrary archives
or their nested contents.

A valid PDF containing only scanned pages may be retained, but it is explicitly
marked as having no readable text when preparation cannot extract text.
OCR is not required in version one.

Before confirmation, the importer acknowledges that the files are
candidate-provided CVs the workspace is authorized to hold and use for
recruitment. This is a recorded declaration by the importer, not evidence of
candidate consent, document authenticity, or external verification.

Recruiter notes, interview feedback, score sheets, generated biographies, and
AI summaries must not be offered as alternative material types. User-supplied
content must never be able to issue product instructions or authorize actions.

## Preview and confirmation

After validation, the reviewer sees:

- destination workspace and batch source label;
- uploader and preparation time;
- number of logical rows and ignored empty rows;
- proposed new candidates and reused candidates;
- rows needing identity review;
- invalid rows, excluded rows, and explicitly selected contact-only rows;
- CVs to add, already-retained CVs, invalid references, and unused files;
- clear notice that existing contact fields and recruitment history stay intact;
- clear notice that import does not evaluate or contact candidates.

Each row shows its original number, name, email, proposed identity action,
document association, relevant source date, validation messages, and selection.
Rows with problems can be filtered and inspected without reviewing only an
arbitrary first-page sample.

Supported review decisions are intentionally small:

1. Include an eligible row.
2. Exclude a row.
3. Confirm reuse of the shown existing candidate after a name conflict.
4. Import contact only when deliberately omitting the CV.
5. Choose one row from an intra-file duplicate group.

The product does not need a spreadsheet editor or general transformation UI.
Changing field values requires a corrected CSV and a new validation pass.
Changing files, separator, source label, or review decisions invalidates the
previous confirmation summary and requires a fresh review.

CSV structural errors and batch-capacity errors prevent all confirmation.
Validation problems with an individual CV affect the referencing rows and can
be resolved by replacing/omitting the file as described above; they do not block
unrelated selected rows. Row-level errors do not prevent
importing a reviewed valid subset: unresolved invalid rows are unselected, their
count is explicit, and the confirmation states how many rows will be excluded.
There must be at least one selected eligible row.

The final action states the exact selected row count and separately identifies
new candidates, reused candidates, and omitted rows. For example:

> Import 84 selected rows: create 60 candidates, reuse 24 candidates.
> 16 rows will not be imported. 42 CVs will be added.

The confirmed inputs and decisions are fixed for that execution. A second
browser tab with older decisions cannot silently replace them.

## Execution, progress, and row outcomes

The batch has understandable operational states:

| State                 | Meaning and allowed next step                                                                                                                                        |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Draft                 | Uploaded inputs are being assembled; no candidate changes have occurred. Review or discard.                                                                          |
| Validating            | Inputs are being checked; no candidate changes have occurred. Wait or discard when validation stops.                                                                 |
| Ready for review      | Proposed rows and problems are available. Resolve, exclude, confirm, or discard.                                                                                     |
| Processing            | Confirmed selected rows are being applied. Progress survives closing the page.                                                                                       |
| Paused                | Remaining work cannot continue because of access, feature availability, or a recoverable operational problem. Review the reason and explicitly resume when eligible. |
| Completed             | Every selected row was committed or was an intentional unchanged result. Excluded preview rows remain visible in the totals.                                         |
| Completed with issues | Some selected rows need correction/retry; successful rows remain committed.                                                                                          |
| Failed                | A batch-wide execution problem prevented useful completion. Show any committed progress honestly and permit eligible recovery.                                       |
| Discarded             | An unconfirmed draft was deliberately abandoned. No candidate changes occurred.                                                                                      |
| Expired               | An unfinished batch's input-retention window ended; remaining work requires a new upload. Historical successful effects are not undone.                              |

Completed and failed batches retain their final execution state after their
row-level reports expire, with a separate "Details expired" indication.
Expired is the terminal execution state for unfinished drafts or paused work,
not a replacement for a successfully completed history.

While processing, show selected total, processed total, remaining total,
successful new candidates, reused candidates, unchanged rows, newly retained
CVs, duplicate CVs skipped, and failed rows. Counters must not count attempts as
additional successes.

Import may finish before text preparation finishes. Report candidate/file
import completion separately from pending, ready, unreadable, or failed text
preparation. A background preparation failure cannot relabel an already
retained candidate as never imported.

An import that makes no row progress for 30 consecutive minutes must stop
presenting itself as healthy Processing. It becomes Paused with an operational
explanation and a recovery action. Late work from the interrupted execution must
still obey its current authorization, reviewed manifest, and replay rules.

For a row confirmed with a CV, creating/reusing the candidate and retaining the
intended CV must succeed as one observable row operation. A storage failure
must not report a new candidate with its required CV as successfully imported.
For a newly created candidate, failure must leave neither a new half-imported
candidate nor an orphan CV. Reusing an existing candidate must not damage it.

Document text preparation happens after file acceptance and is not part of that
row's import-success condition.

Terminal row outcomes distinguish:

- **Candidate created**, with or without a new CV;
- **Existing candidate reused**, with a new CV;
- **Existing candidate unchanged**, with no new CV or only an already-retained CV;
- **Excluded before import**, with the stated reason/decision;
- **Needs review**, because relevant state changed after confirmation;
- **Failed**, because the selected operation could not complete.

Contact and material outcomes are separate: one reused candidate can have one
new material; an unchanged row can have one duplicate material skipped.

## Recovery, replay, and history

The recruiter can leave the page and later return to the same progress/result.
Repeated confirmation, double clicks, refreshes, delayed responses, or retrying
a worker operation must not create additional candidates, CV versions, or
success counts.

**Retry remaining rows** applies only to eligible failed or not-yet-processed
rows from the same confirmed inputs. Already successful rows are never rerun.
Rows requiring a new identity decision must return to review before continuing.
Edited input data requires a new batch.

An existing candidate or material removed after a successful import must not be
recreated by replaying the old successful row. The history states that the
previous result is no longer available.

Uploading the same CSV as a new batch still performs identity matching and
material duplicate checks. This is a new review, not an instruction to repeat
old side effects or roll back subsequent human changes.

There is no whole-batch undo after confirmation and no deletion of all imported
candidates from a batch action. The ordinary candidate/material actions apply
to corrections after import. Discard is available only before confirmation.

Import history shows batch source, uploader, confirmer, relevant times, state,
summary counts, and a link to the review/result. Each retained material
identifies the actor who added it and its batch when applicable.

The result includes a private downloadable CSV report with original row number,
safe identity fields, row outcome, candidate reference when still accessible,
material outcome, and concise reasons for exclusions/failures.

A separate **Download rows needing correction** CSV contains only the original
supported template columns for failed/needs-review/excluded invalid rows, ready
for correction and re-upload. Exclusions deliberately chosen as duplicates or
unwanted records are omitted from this correction download. Reasons remain in
the companion report. No hidden extra columns may make the correction CSV fail
the importer's own header validation.

Spreadsheet downloads must render untrusted cells as inert text, not formulas,
commands, hyperlinks generated from untrusted markup, or executable content.
Escaping for spreadsheet safety must not change stored candidate values.

### Safe correction-file round-trip

The supported recovery workflow must preserve a valid value when another field
in the row is corrected. In particular, international + phone numbers and
literal text beginning with =, +, -, @, or an apostrophe must not acquire escape
characters, lose original characters, or change meaning after re-import.

Use the following user-visible correction-file convention:

- The correction CSV uses the same supported headers and normal CSV quoting.
- Every nonempty data cell receives exactly one additional leading apostrophe
  as a reversible text-protection marker. Empty cells remain empty. Header
  names do not receive this marker.
- A value that already begins with an apostrophe receives another one, so its
  original leading character remains distinguishable.
- The download instructions identify the file as a Recruiter Labs correction
  file and explain how to keep the single added marker when editing cells.
- Import provides an explicit **Recruiter Labs correction file** input mode,
  available directly from the correction action and in the normal upload flow.
  That mode removes exactly one leading protection apostrophe from each nonempty
  cell before normal field validation and filename association. A nonempty cell
  missing its required marker is reported for correction, not guessed.
- The ordinary CSV-template mode never strips a literal apostrophe. Selecting
  correction-file mode is a format choice, not additional authorization.
- Normal validation, identity review, and confirmation always occur after
  decoding. Protection markers do not count toward field-length limits.

For example, original +353871234567 is exported as '+353871234567 and
restored to +353871234567. An original name beginning with one apostrophe is
exported with two and restored with one. No expression is evaluated.

The UI must not promise that arbitrary spreadsheet transformations preserve
these values. It supplies the supported editing/re-upload instructions and
reports a broken convention clearly. The protected CSV can also be corrected
as plain text.

Correction downloads contain references, not the CV files themselves. A new
corrected batch requires uploading its referenced CVs again. Files in an old
batch must not be silently claimed by a new batch merely because names match.

## Independent candidate materials

The candidate profile gains a **Materials** region alongside existing profile
and recruitment history. It answers:

> What source documents do we hold for this person, where did they come from,
> and which ones are available for rediscovery?

An independent CV displays:

- original filename and file format;
- file size;
- active/archived lifecycle state;
- preparation state, including partial readable text when relevant;
- added date and the member who added it;
- source label and importer-declared candidate-provided origin;
- received date or **Original received date unknown**;
- import batch link when applicable;
- available view/download and lifecycle actions.

The list defaults to available materials ordered by added date, newest first,
with a stable tie-break. This is a display order, not a claim that the newest
upload contains the most recent career history. Archived materials are
accessible through an explicit filter and count.

Show CVs without requiring any application. A candidate with zero applications
and a retained CV is a valid state. Existing recruitment history remains
visible, unchanged, and linked to its canonical application pages.

Application-owned CVs may be summarized or linked from Materials under a
clearly separate **Application documents** grouping. They remain managed in
their existing application context. Do not copy every historical application
document into the independent library as a backfill.

### Add a CV directly

The recruiter first selects an existing candidate. They upload one supported
CV, provide a source label and optional received date, review the identity and
file, acknowledge candidate-provided origin, and save.

No email is required when the candidate has already been explicitly selected.
The upload does not infer or update name, email, phone, employer, skills, or
other profile fields from the document.

For a person not yet known to the workspace, the recruiter uses normal candidate
creation first. A bulk CV-only parser or a second candidate-creation wizard is
not required.

### Duplicate material

The same file contents retained against the same candidate must not produce
another independent CV simply because the file was renamed, re-uploaded, or
replayed through a new batch.

If the matching independent CV is available, show it as already retained and
leave its original provenance and dates unchanged.

If it is archived, show that state and require a separate explicit restoration
action. Re-import must not silently restore it or reset its age.

If a different file uses the same filename, it is a distinct version and may
be added. The original must not be overwritten.

A duplicate in an existing application does not transfer ownership away from
that application. An independently supplied candidate CV may coexist with an
application document containing the same text, but it must not cause duplicate
evidence or appear to be a fresh candidate submission.

### Archive and restore

Archiving removes an independent CV from future sourcing input while preserving
the original file and provenance for authorized inspection. It immediately
affects sourcing freshness as defined below.

Restoration is explicit, preserves the original dates and attribution, and
returns the CV to preparation/availability according to its existing readable
state. It does not execute AI or restore old sourcing scores as current.

Adding a new CV does not automatically archive another CV. The recruiter
decides whether older material should remain eligible. Multiple available CVs
can coexist; the product must not infer a definitive "current CV" from upload
order or an employment date.

### Correct metadata

An authorized member can correct the source label or declared received date.
The original added date and adding actor are immutable. Record who made the
correction and when; material age presented to the recruiter must reflect the
current declaration while retaining its status as recruiter-supplied metadata.

Changing the received date invalidates affected sourcing results. Changing only
an internal source label does not request AI work or alter scores.

### Delete a material

Deletion requires confirmation naming the candidate and file. State that this
removes the independent material and its derived content, does not delete the
candidate, and does not erase separately retained application documents.

After confirmation, the original, prepared text, accessible previews, and
material-derived content retained by this feature become unavailable immediately.
Physical cleanup may complete separately, but failures must remain visible for
recovery and must not restore file access.

Sourcing output based on the deleted material must not continue exposing its
content through evidence snippets, historical result views, or old links.
Invalidate those analyses and remove their derived content where necessary,
while preserving the recruiter's saved/dismissed relationship without scores.

This is deletion of one pool material, not a complete candidate-erasure
workflow. Independently retained application documents and their existing
evaluations remain governed by their own feature/data-lifecycle rules. The UI
must not promise erasure of every copy or deletion from already downloaded
customer files.

If an existing supported candidate-deletion path removes the candidate, it must
also make this feature's materials inaccessible and clean up their files,
prepared content, and identifying report links. Do not leave new orphan CVs.
This requirement does not redesign the existing candidate-deletion policy.

## Document preparation and readiness

Accepting a supported file prepares its readable text without a generative-AI
call. Preparation must not infer candidate attributes, generate a profile,
translate the document, summarize qualifications, or consume AI-analysis quota.

Preparation states are:

| State              | User-facing meaning                                                                                                               |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------- |
| Preparing          | The original has been stored; readable text is not ready yet.                                                                     |
| Ready              | Some readable text is available for the normal sourcing sufficiency checks. It is not proof that every criterion can be assessed. |
| No readable text   | The original is retained, but meaningful text could not be extracted, for example from a scanned PDF.                             |
| Preparation failed | An operational problem prevented preparation; retry is available.                                                                 |
| File unavailable   | The retained original cannot currently be retrieved; do not claim the material can be used.                                       |

Archived/deleted is a separate lifecycle dimension and must not be disguised as
an extraction failure.

A material that remains Preparing for 30 consecutive minutes without successful
completion must expose a retryable Preparation failed condition with a clear
explanation. It must not leave the recruiter waiting indefinitely or change the
successful import outcome. Late completions obey the latest retry and material
lifecycle state rather than overwriting a newer result.

When only part of a CV can be prepared within existing product limits, disclose
that the available text is partial. Keep the original downloadable where
authorized. Do not claim complete analysis of the whole document.

No-readable-text material is not eligible by itself to support a sourcing
assessment. Other valid historical material may still make the candidate
assessable. An authorized recruiter may retry preparation or upload a readable
replacement as another CV; OCR and manual editing of extracted evidence are
outside this version.

PDF viewing may use the existing authorized document-viewing experience.
DOCX requires a safe download; a new document-conversion or office-editor
experience is not required.

## Sourcing integration

The existing **Find matches** journey must be able to consider a candidate with
no applications when the candidate has sufficient available, readable,
candidate-provided CV material.

New independent CVs supplement the existing permitted historical application
sources. They do not replace those sources, import old scores, or grant
recruiter-written metadata the status of evidence.

The following are not sourcing evidence:

- CSV contact fields by themselves;
- source labels, import batch names, importer identity, or import order;
- the fact that a CV was imported successfully;
- previous scores, interview assessments, or hiring outcomes;
- public-profile content reached from an imported URL.

Preserve the existing identity-reduction and evidence-integrity requirements
when independent CV content reaches the later AI sourcing operation. Do not
send original filenames, source labels, or import metadata merely because they
are stored; they may reveal identity or imply irrelevant authority.

The recruiter must be able to identify the source behind an imported-CV
sourcing result: the candidate material, its declared received date when known,
its added date, and its importer-declared origin. Do not invent a historical job
or a candidate submission date to fit an existing result presentation.

Identical substantive CV content appearing in multiple available sources must
not be counted as multiple independent pieces of support. Removing duplication
must not relabel an old CV as newly received because one copy was imported
today. When an explicit received date is unknown, preserve that uncertainty;
the date of import is not evidence of freshness.

Available differing CV versions may provide conflicting information. Preserve
the available source/date distinctions; do not silently merge them into an
assertion about the candidate's present circumstances.

Contact-only candidates and candidates with no meaningful readable source
remain eligible to be counted as insufficiently known under existing sourcing
rules. Do not fabricate a match from identity fields, mark import as failed
because no fit exists, or lower the existing sufficiency requirement.

Import and preparation never start or refresh a sourcing search. The recruiter
explicitly requests one in the existing job workspace. That search still
requires current confirmed criteria and the normal AI allowance/provider rules.

## Material changes and sourcing freshness

A sourcing result must describe the materials actually available when it was
produced, not only the job's criteria at that time.

The following changes invalidate affected existing candidate/job sourcing
analyses:

- a new independent CV becomes readable and available;
- a contributing CV is archived, restored, deleted, or becomes unavailable;
- usable prepared text changes after a retry;
- a contributing CV's declared received date changes.

Only analyses whose candidate evidence is affected need become outdated.
Unrelated candidates' analyses need not be recomputed. A new contact or new
readable CV also makes a previously completed search's pool coverage older than
the current pool; the job's sourcing area must communicate that new/changed
materials are not included until the recruiter refreshes.

Outdated results are labeled, and old numeric analysis must not be presented as
the current potential match. Saved and dismissed decisions remain intact.
No new application, message, or pipeline change follows from invalidation.

If material changes while analysis is in progress, the old response cannot
become the current result afterward. A deletion or archive must also prevent a
pending preparation task from silently making that material available again.

Retries and reuse of previous prepared/analysis results obey the same
freshness requirements. Deleting and then uploading a file again is a new
material lifecycle, not permission to resurrect purged outputs.

Currentness of existing application evaluations does not change merely because
a pool material was added, archived, or deleted. Their application evidence has
not changed. This feature must not replace application documents or recalculate
historical fit through a pool-material action.

## Application and activation boundaries

Importing a candidate or adding a CV does not:

- create an application or increment application intake counts;
- consume a job slot or an application allowance;
- set a stage, application source, campaign attribution, or referral;
- schedule candidate evaluation or release an application waiting for criteria;
- send a message, invitation, interview event, or external integration request;
- complete First application created, First application evaluated, or Workspace
  activated milestones.

The existing explicit **Add to job** action remains available under its existing
rules. Selecting which pool materials become a new application's evidence,
requesting current application answers, and evaluating that new application are
the separate sourced-candidate-activation work identified in the product
roadmap. They are not silently bundled into this import feature.

Do not advertise that importing a CV immediately produces an application fit.
After completion, useful next actions are **View imported candidates**,
**Review materials needing attention**, or navigating to an existing job's
Sourcing section. The job's normal criteria/allowance gates still apply.

## Plan behaviour and capacity

Import and candidate-material management follow the existing Candidates-feature
entitlement. No new paid import add-on, candidate-count subscription limit,
seat pricing, storage subscription plan, or AI charge is introduced here.

A workspace whose AI allowance is exhausted may still import and prepare CV
text. It is later sourcing, not import, that may be blocked by AI allowance.
The same separation applies when a workspace owns provider credentials.

The explicit operational file/batch/material limits still apply to every
workspace. Import preview must identify candidates at the retained-CV limit and
allow deliberate contact-only import or exclusion instead of silently dropping
their referenced CV.

Archiving does not free a retained-file slot. Permanent removal does. A
duplicate upload does not consume another slot. Concurrent direct uploads and
imports must not exceed the cap.

## Temporary-data and report lifecycle

Temporary import data is not an indefinite second talent database.

- Draft inputs expire seven days after the latest actual upload or reviewer
  decision; simply viewing a page does not extend expiry.
- Confirmed batches retain temporary inputs and row-level reports for seven
  days after reaching Completed, Completed with issues, or Failed.
- Paused confirmed work expires after seven days without execution or an
  explicit eligible resume. The user is shown the deadline.
- An eligible retry within the window preserves already successful effects and
  can extend the window from its new terminal state.
- Discarded drafts revoke file access immediately and remove temporary files.
- Cleanup removes the original CSV, unused/rejected/uncommitted CV uploads,
  temporary copies of committed files, and row-level payloads/reports when
  their retention window ends.

Retained candidate CVs and their provenance are independent of the temporary
batch inputs. Expiring a batch must not delete successfully retained materials.
Conversely, deleting a retained CV must not leave a downloadable temporary copy
in an unexpired batch.

After row-level retention ends, keep only a minimal batch summary: workspace,
responsible actors, source label, times, final state, aggregate counts, and
non-sensitive operational error categories. Do not retain CSV identity values,
CV text, original file inventories, or row exports indefinitely.

Candidate/material erasure takes precedence over the remaining report window:
remove identifying row detail and any copied material content while retaining
honest aggregate import counts. Existing complete-candidate erasure rules, when
available, must also cover this feature's temporary data.

An erased identity/material must also be excluded from pending retries that
would recreate it from retained source data. Other rows may continue where
their reviewed inputs remain valid. If erasure makes the remaining manifest
incomplete, return those rows to review rather than reconstructing deleted data.

Private source files and reports must not be exposed through public links or
email attachments. Completion notification, if used, links to the authorized
batch result and does not contain candidate identities or CV contents.

Operational logs and error messages must not reproduce whole CSV rows,
candidate CV text, credentials, internal storage paths, or provider responses.

## Presentation, accessibility, and localization

Keep Candidates selected in navigation throughout import and material flows.
Do not add top-level Imports, Documents, or Migration sections, and do not
redesign the Overview, job pipeline, application tabs, or Settings.

Use the existing product presentation conventions. At narrow widths, identity,
row outcome, errors, and confirmation actions remain readable and usable.
The complete import/review flow must work with keyboard navigation, descriptive
field labels, focusable validation errors, and status text that does not depend
on colour alone.

Provide progress text and a durable result instead of requiring an indefinitely
open modal. Do not claim successful completion while selected rows are pending.

The Candidates list gains a concise independent-material availability signal
and filters for:

- candidates touched by a selected retained import batch;
- candidates with independent CVs;
- candidates with independent CVs needing preparation attention.

The batch filter may be unavailable after identifying row associations expire;
explain that limit rather than preserving identity payloads solely for a filter.
The permanent candidate profile still retains the material's provenance.

Avoid a new global candidate score, ranking, "quality" badge, candidate profile
builder, general tag taxonomy, or extensive migration dashboard.

New interface strings follow the product's currently supported locales.
Candidate data, filenames, and source labels are not automatically translated.
Dates/numbers use the existing display conventions; CSV dates remain YYYY-MM-DD.
Templates and reports must round-trip Unicode names without corrupting accents.

## Required user journeys

### A. New pool with matching CVs

1. The recruiter opens Candidates → Import candidates.
2. They download the template, prepare supported rows, and supply a batch source.
3. They upload the CSV and its explicitly named CVs.
4. They review identities, file associations, dates, and omissions.
5. They confirm the selected manifest and candidate-provided origin declaration.
6. Processing creates candidates and independent CVs with accurate row outcomes.
7. Text preparation becomes ready or exposes specific material problems.
8. The recruiter views the candidates and explicitly runs sourcing in a job.
9. Sourcing can assess sufficient imported evidence without any prior application.

### B. Contacts only

1. The recruiter uploads a valid CSV without CV references.
2. They review and confirm.
3. Contacts are created or reused without fabrication of qualifications.
4. The result explains that contact-only records have no imported CV evidence.
5. The recruiter can add a CV later from the candidate's profile.

### C. Existing candidates and conflicting identities

1. Some normalized emails match existing workspace candidates.
2. Identical identities are proposed for reuse; differing names require review.
3. The recruiter confirms shown reuse decisions or excludes rows.
4. Existing contact fields and recruitment history remain unchanged.
5. Only explicitly associated, nonduplicate CVs are added.

### D. Partial failure and recovery

1. A confirmed batch commits some rows and encounters a recoverable failure.
2. The result identifies successful, failed, and unprocessed rows.
3. The recruiter returns later within the retention window.
4. They retry eligible remaining rows or download the rows needing correction.
5. Successful earlier rows are not duplicated, replayed, or reverted.

### E. Material management without an import

1. The recruiter opens a known candidate, including one without email/applications.
2. They add a supported CV with declared origin and optional received date.
3. The original remains accessible while preparation progresses.
4. They may archive, restore, correct metadata, retry preparation, or delete it.
5. Those actions update sourcing freshness without rewriting application history.

### F. Access changes during processing

1. A member confirms an import.
2. Their workspace access is disabled or revoked before all rows commit.
3. Uncommitted work pauses and private links stop granting access.
4. Committed work remains attributable and intact.
5. Another authorized member explicitly reviews and resumes remaining work.

## Acceptance criteria

Each criterion describes observable behaviour. Verification should cover the
relevant normal, direct-request, concurrent, and failure paths rather than only
the appearance of a UI control. Project testing and execution rules remain in
CLAUDE.md and the existing execution workflow.

### Entry, authorization, and scope

- **AC01** — Candidates exposes Import candidates, import history, and candidate
  Materials without adding a top-level navigation area.
- **AC02** — Enabled Owners and Members with Candidates access can use the
  feature; it does not introduce an Owner-only import restriction.
- **AC03** — Users without current workspace access cannot read or modify batches,
  reports, previews, materials, or original files through direct requests.
- **AC04** — The destination workspace is visible at upload and confirmation and
  cannot be selected through CSV content or a stale workspace-switching context.
- **AC05** — Candidate identity checks reveal no other workspace's records or
  duplicate status, even for the same email or same file.
- **AC06** — A member can inspect shared import history; confirmation/resume
  attribution identifies the actual acting member.
- **AC07** — Revocation or feature loss pauses uncommitted work; another eligible
  member must explicitly review/resume it.

### Input contract

- **AC08** — A downloadable fictional-data template and field guide document all
  supported headers, limits, separator choices, and CV naming requirements.
- **AC09** — UTF-8 CSV with or without BOM, comma/semicolon separators, quoted
  fields, escaped quotes, and LF/CRLF is accepted according to the stated rules.
- **AC10** — Missing/duplicate/unknown headers, invalid encoding, malformed records,
  empty datasets, oversized CSVs, and batch-capacity violations cannot reach
  import confirmation. An omitted invalid CV does not block unrelated rows.
- **AC11** — Row/file/batch capacity limits accept the exact boundary and reject
  values above it with the relevant reason.
- **AC12** — Every CSV row requires a valid name and email; optional values follow
  the stated field constraints.
- **AC13** — Normalized email uses trimmed case-insensitive comparison without
  provider-specific alias rewriting.
- **AC14** — Phone values retain the international + form without inferred country
  codes, lost digits, or numeric conversion.
- **AC15** — LinkedIn values are validated contact links and are never fetched,
  enriched, or treated as candidate evidence.
- **AC16** — Source labels obey batch/row fallback and do not affect analysis.
- **AC17** — Received dates are valid nonfuture YYYY-MM-DD values; an unknown date
  stays unknown and a date without a CV reference is rejected.
- **AC18** — Reports preserve original logical row numbers, including when quoted
  values contain line breaks or entirely empty records are ignored.

### Identity resolution

- **AC19** — A row with no workspace email match proposes one new candidate.
- **AC20** — An exact existing email match proposes reuse, not another candidate.
- **AC21** — A differing incoming name requires explicit reuse confirmation or
  exclusion before its CV may be attached.
- **AC22** — Reuse preserves every existing contact field, including empty fields;
  incoming differences are disclosed rather than silently applied. New-candidate
  import provenance survives report expiry, while reuse preserves the existing
  candidate's original provenance.
- **AC23** — All within-file duplicate email rows require selecting at most one
  representative or excluding the group.
- **AC24** — Ambiguous historical email matches block the row without automatically
  merging or repairing candidates.
- **AC25** — Name/phone/profile/file similarity cannot automatically identify a
  candidate; manual email-less candidates retain their existing behaviour.
- **AC26** — Concurrent public intake, manual creation, and import cannot duplicate
  a normalized workspace email or attach evidence to an unreviewed new identity.
- **AC27** — Identity changes since preview produce Needs review without modifying
  unrelated successful rows.

### Files and associations

- **AC28** — Each imported CV belongs to the reviewed candidate through an explicit
  unambiguous filename association, never guessed ordering or identity extraction.
- **AC29** — Missing files, duplicate association filenames, multi-row references,
  and identical files assigned to different selected identities are identified.
- **AC30** — Import contact only deliberately removes the affected row's CV/date
  contribution and is separately visible in preview and results.
- **AC31** — Unreferenced uploaded files are counted, never attached, and cleaned up.
- **AC32** — Only matching-content PDF/DOCX files within the size limits are
  retained; empty, corrupt, encrypted, mismatched, oversized, and unsupported CVs
  fail safely if selected and may be deliberately omitted under the review rules.
- **AC33** — Filenames/URLs cannot select storage destinations or cause remote
  fetches; untrusted document content and metadata cannot execute instructions.
- **AC34** — A candidate-provided-origin/authority declaration is explicit and
  attributable without being displayed as verified candidate consent.

### Review and execution

- **AC35** — Uploading and previewing create no candidates or retained materials.
- **AC36** — Every row and issue is reviewable, with filters and exact summary counts;
  no unreviewable sample substitutes for the complete manifest.
- **AC37** — Only eligible selected rows are confirmed; omitted/invalid rows are
  explicit and at least one eligible row is required.
- **AC38** — Changes to inputs or decisions require refreshed validation and review;
  another tab cannot silently mutate an already confirmed execution.
- **AC39** — Progress and results survive leaving the page and distinguish all
  required batch states without displaying pending work as complete.
- **AC40** — A selected candidate-plus-CV row either retains its intended result
  or fails without creating a half-imported new candidate or orphan file.
- **AC41** — New/reused/unchanged candidate outcomes and added/duplicate CV outcomes
  have separate accurate counts.
- **AC42** — Selected row failures preserve prior committed rows and expose a
  specific retry or correction path.
- **AC43** — Repeated confirmation and execution retries create no additional
  candidates, material versions, or success counts.
- **AC44** — Retry remaining rows skips prior successes and requires review for
  newly ambiguous identities.
- **AC45** — A new upload of the same data still respects identity/material
  duplication and does not reset provenance or undo later human actions.
- **AC46** — Replaying a successful historical row cannot recreate a subsequently
  deleted candidate or material.
- **AC47** — Only one batch executes per workspace; waiting confirmation does not
  silently start against an obsolete preview.
- **AC48** — Draft discard performs no candidate changes; confirmed batches expose
  no destructive whole-batch rollback.

### Materials and preparation

- **AC49** — A candidate can retain an independent CV with zero applications.
- **AC50** — Direct Add CV works for an explicitly selected email-less candidate
  and never extracts identity fields into the profile.
- **AC51** — Material presentation shows origin, adding actor/date, known or unknown
  received date, lifecycle, preparation state, and authorized file actions.
- **AC52** — Original application documents remain owned by their applications and
  are not automatically copied, replaced, archived, or removed by pool actions.
- **AC53** — Same-candidate repeated file contents do not create another independent
  material, even under a different filename.
- **AC54** — A duplicate of an archived CV remains archived until explicitly
  restored; duplicate upload does not refresh its age or provenance.
- **AC55** — Different contents with the same filename retain separate versions;
  adding a version does not automatically archive another.
- **AC56** — Available/archived materials and preparation states remain distinct;
  the UI does not label import order as the candidate's current CV.
- **AC57** — Preparation makes no generative-AI call, incurs no AI-analysis
  allowance charge, and does not generate inferred profile attributes.
- **AC58** — Stored, Preparing, Ready, No readable text, Preparation failed, and
  File unavailable conditions are represented honestly where applicable.
- **AC59** — Textless scanned PDFs remain downloadable with a no-readable-text
  explanation and no fabricated extracted evidence.
- **AC60** — Partial prepared text is disclosed; the original remains accessible,
  and readiness is not described as complete qualification assessment.
- **AC61** — Eligible preparation retries preserve candidate/material identity and
  original provenance rather than making additional CVs. Preparation stalled
  beyond 30 minutes exposes a retryable failure instead of remaining Preparing.
- **AC62** — Source/date metadata correction preserves original added attribution
  and records the correcting actor/time.
- **AC63** — Retained-CV limits include archived independent files, exclude
  application documents, and remain safe under concurrent uploads/imports.

### Sourcing and recruitment integrity

- **AC64** — Explicit sourcing can assess sufficient independent CV evidence for a
  candidate who has never applied to any workspace job.
- **AC65** — Contact-only and unreadable-only cases remain insufficiently known
  when no other adequate source exists; no artificial score or penalty is produced.
- **AC66** — Sourcing continues to use current human-confirmed criteria and its
  normal provider, allowance, and human-initiation rules.
- **AC67** — Imported origin labels/contact metadata/history cannot become scoring
  evidence; existing identity-reduction rules cover the new material context.
- **AC68** — Imported evidence retains its material provenance and known/unknown
  historical age without inventing a job or submission timestamp.
- **AC69** — Duplicate substantive CV content does not count as independent repeated
  support, and migration time does not become evidence of freshness.
- **AC70** — Material availability/text/date changes invalidate affected sourcing
  analyses and preserve saved/dismissed human decisions.
- **AC71** — New/changed pool evidence is distinguishable from the coverage of a
  previously completed search until explicit refresh.
- **AC72** — An in-flight old analysis or delayed preparation result cannot become
  current after its material was changed, archived, or deleted.
- **AC73** — Cached/reused results obey the same currentness and deletion rules.
- **AC74** — Import/material actions never create an application, consume
  job/application allowances, change pipeline state, or send candidate messages.
- **AC75** — Import/material actions never complete application-based activation
  milestones, schedule application evaluation, or overwrite historical fit.
- **AC76** — Existing explicit Add to job behaviour is preserved without silently
  copying pool CVs/answers into the new application.
- **AC77** — Exhausted AI quota does not block candidate import or non-AI text
  preparation; later sourcing may still be blocked normally.

### Deletion, retention, and reports

- **AC78** — Confirmed material deletion immediately prevents access to originals,
  temporary duplicates, previews, prepared text, and derived content it removes.
- **AC79** — Deleted-material sourcing snippets/results cannot continue exposing
  removed content; human save/dismiss decisions remain without stale scores.
- **AC80** — Material deletion explains that separately retained application
  documents and the candidate record are outside that deletion operation.
- **AC81** — Existing supported candidate deletion cleans up this feature's new
  materials and identifying report references without leaving accessible orphans.
- **AC82** — Private result and correction CSV downloads require current workspace
  access and contain no executable spreadsheet payloads.
- **AC83** — The correction CSV uses only supported import headers and excludes
  deliberate duplicate/unwanted-row exclusions; the report retains reasons.
  Exporting, correcting another field, and re-importing in the documented mode
  preserves every otherwise unchanged value, including + phone numbers and
  literal formula-marker/apostrophe prefixes. Standard mode does not strip them.
- **AC84** — The stated seven-day deadlines are visible and cleanup removes
  temporary inputs and row payloads without deleting retained candidate materials.
- **AC85** — Expired histories retain only minimal summaries; retries requiring
  expired inputs request a new upload.
- **AC86** — Candidate/material erasure takes precedence over the report window
  and cannot be defeated through a batch copy or report link.
- **AC87** — Logs and optional completion notifications do not expose candidate
  contents, complete rows, original files, or private storage details.

### Presentation and completeness

- **AC88** — Import and material flows preserve Candidates navigation and existing
  job/application/Overview information architecture.
- **AC89** — Candidate lists can show material availability and the defined
  import/material-attention filters, including the report-retention boundary.
- **AC90** — Existing supported locales cover new UI strings; CSV headers stay
  stable and Unicode candidate data round-trips without corruption.
- **AC91** — Keyboard operation, accessible errors, non-colour status labels, and
  narrow-screen review/confirmation are usable throughout the required journeys.
- **AC92** — All six required user journeys have an operational end state and
  actionable recovery paths; no candidate creation/evaluation is simulated to
  make the feature appear complete.

## Product edge cases

| Situation                                             | Required outcome                                                                                                                          |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| The CSV contains a missing email and a readable CV    | The row needs correction or exclusion. No inferred email/identity. Direct upload remains possible after explicitly selecting a candidate. |
| Two people use the same supplied email                | The importer cannot distinguish them automatically. Select one intended identity or correct the input; do not merge the people.           |
| The CSV has the right email but another name          | Explicitly confirm the shown existing identity or exclude; never attach by an unreviewed guess.                                           |
| A row matches a candidate whose phone is empty        | Preserve that empty phone. Updating contacts is a separate deliberate edit.                                                               |
| A supplied phone looks like a spreadsheet number      | Reject invalid international format and explain the required text representation.                                                         |
| Someone uploads resume.pdf twice for different people | Filename/content association conflicts require correction or explicit contact-only import.                                                |
| One candidate has several historical CVs              | Import one through the row and add others through Materials; retain distinct versions and dates.                                          |
| The same CV exists in an old application              | Preserve application ownership; avoid double-counting substantive evidence or presenting today's import as a new submission.              |
| A received date is unknown                            | Show unknown historical receipt and the actual added date separately.                                                                     |
| A PDF is a scan                                       | Retain a valid original, show no readable text when appropriate, and do not claim sourcing readiness from it alone.                       |
| A file is encrypted or structurally corrupt           | Refuse that file; the reviewer can correct it, exclude the row, or deliberately import contact only.                                      |
| AI allowance is zero                                  | Import and text preparation can complete; explicit sourcing obeys its own allowance block.                                                |
| The candidate already has 20 independent CVs          | Do not silently drop another. Allow contact-only import or removal of an existing material before retry.                                  |
| A colleague creates the same email after preview      | Recheck and request review of the changed identity outcome; preserve other completed rows.                                                |
| A row succeeds but its response is lost               | Reopening/retry reveals the prior success without duplicating the candidate or CV.                                                        |
| A batch partially succeeds and the page closes        | Retain honest progress and permit remaining-row recovery within the stated window.                                                        |
| The confirming member loses workspace access          | Stop uncommitted work and private access; require another authorized member's explicit resume.                                            |
| The workspace loses Candidates access                 | Pause remaining import operations and deny feature access until eligibility is restored.                                                  |
| A material is archived while extraction is running    | A late completion cannot restore sourcing availability.                                                                                   |
| A material is deleted while sourcing is running       | No old response may expose the deleted content or become current afterward.                                                               |
| A previously saved sourcing match becomes stale       | Keep the saved relationship, show outdated evidence state, and require explicit refresh.                                                  |
| The last readable CV is removed                       | Future sourcing reports insufficient information unless another permitted source remains; no negative candidate score is generated.       |
| A candidate is deleted after a successful import      | Historical replay cannot recreate them; row links cease exposing identity details under the lifecycle rules.                              |
| The report-retention window ends                      | Remove row payloads/files; preserve minimal aggregate history and successfully retained CVs.                                              |
| A spreadsheet cell starts with a formula marker       | Treat it as data; any report export is inert and cannot execute it.                                                                       |

## Out of scope

This feature deliberately does not include:

- generic Excel/XLSX/XLS import or a spreadsheet editor;
- arbitrary column mapping, transformation recipes, or reusable ETL pipelines;
- ZIP archives, folder ingestion, OCR, legacy DOC, or image CV support;
- bulk candidate creation inferred from CV contents;
- AI resume summaries, inferred skills, inferred identity, or profile enrichment;
- external ATS connectors or vendor-specific migration promises;
- contact enrichment, email discovery, scraping, or remote document fetching;
- automatic candidate merging or mass overwrite of contact fields;
- migration of application stages, jobs, referrals, offers, outcomes, scores,
  interview feedback, or correspondence;
- a general document-management system or non-CV material types;
- automated outreach, invitation sequences, or a candidate account portal;
- copying pool materials into applications or application evaluation on import;
- a new matching engine, global candidate rank, or independent scoring model;
- full candidate privacy-request management, legal consent collection, or a
  claim of complete erasure across external/customer-held copies;
- new pricing plans, candidate/storage subscriptions, seat billing, or AI
  credit purchases;
- a whole-batch destructive undo, public import links, or operator bypass;
- redesign of existing navigation, onboarding milestones, or branding;
- automatic hiring, rejection, stage changes, or interview scheduling.

## Product completion boundary

The feature is complete when an authorized team can bring a supported CSV and
CV collection into its workspace, inspect accurate outcomes, manage independent
candidate materials, and explicitly rediscover candidates using that evidence
without losing provenance, violating workspace access, or fabricating an
application history.

Every essential decision for that scope is specified above. Expansion into
application activation, broader migration formats, outreach, or commercial
packaging requires its own product scope and must not be inferred as necessary
to finish this feature.
