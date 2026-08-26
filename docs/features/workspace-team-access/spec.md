---
status: implemented
type: feature
---

# Workspace team access

## Problem

Recruiter Labs already organizes recruitment work inside workspaces and already
allows a user to belong to more than one workspace.

However, workspace membership is not yet a complete product experience.

A workspace owner currently has no clear self-service flow to:

- invite another recruiter;
- allow an invited person to join safely;
- see who currently has access;
- manage pending invitations;
- remove someone who should no longer have access.

This prevents Recruiter Labs from working naturally for its target customer:
small internal recruiting teams where multiple people collaborate on the same
jobs, candidates, interviews and hiring evidence.

Recruitment information is sensitive, so team access must solve collaboration
without weakening tenant isolation or historical accountability.

The product does not need enterprise authorization complexity to solve this
problem.

## Objective

Allow a small recruiting team to share one Recruiter Labs workspace safely.

The feature introduces a minimal workspace membership model:

- **Owner**
- **Member**
- **workspace access**: enabled or disabled, for Members

The Owner manages workspace membership and workspace access.

Members collaborate through the existing recruitment workflow.

The feature must make it possible to:

1. invite someone by email;
2. let an existing or new Recruiter Labs user accept the invitation;
3. see active workspace members;
4. manage pending invitations;
5. remove a Member;
6. disable and re-enable a Member's workspace access without ending their
   membership;
7. immediately revoke workspace authorization when membership ends or access is
   disabled;
8. preserve historical authorship and recruitment records after removal or after
   access is disabled.

This feature is intentionally not a general-purpose RBAC system. Workspace
access is the **only** additional permission it introduces.

---

# Product principles

## Workspace membership is the tenant boundary

A user may access recruitment information for a workspace only while they are
an active member of that workspace.

Knowing or constructing another workspace URL must never provide access to that
workspace or reveal its recruitment data.

Workspace authorization must be enforced by the product and not merely by
hiding interface controls.

## Collaboration stays simple

A Member with workspace access enabled receives the same normal recruitment
workspace access that an existing workspace user has today.

This feature does not introduce:

- job-level permissions;
- candidate-level permissions;
- pipeline-level permissions;
- per-feature permissions.

The workspace itself remains the collaboration boundary.

## Team membership and workspace access are different questions

Being listed on the Team answers *who belongs to this workspace*.

Workspace access answers *who may enter it right now*.

A person may remain a Member of the Team while their workspace access is
disabled. Their membership, their role and their historical authorship all
survive; only their current authorization stops.

This is the single additional permission the feature introduces. It is not the
first step of a permission system, and no other permission dimension may be
added alongside it.

## Owner is an administrative distinction

Owner does not mean that the person's recruitment judgment carries more weight.

Owner status exists only to administer workspace membership.

It must not affect:

- candidate evaluation;
- fit score;
- interview evidence;
- hiring decisions;
- AI behavior.

## Human activity remains attributable

Removing someone's current access must not rewrite history.

Recruitment actions and human evidence previously authored by that user remain
attributed to them.

Current authorization and historical attribution are separate concerns.

---

# Roles

## Owner

Every workspace has exactly one Owner.

The Owner:

- is an active workspace member;
- always has workspace access;
- can view active workspace members;
- can view the workspace-access state of every Member;
- can view pending invitations;
- can invite Members;
- can resend invitations;
- can revoke invitations;
- can remove Members;
- can disable a Member's workspace access;
- can re-enable a Member's workspace access.

The Owner cannot remove themselves in this version.

The Owner's own workspace access cannot be disabled, by themselves or by anyone
else.

Only the Owner may manage workspace access.

Ownership transfer is not part of this feature.

## Member

A Member:

- can access the workspace while membership is active **and** workspace access
  is enabled;
- can use the existing recruitment workflow;
- can see the active workspace team;
- cannot invite Members;
- cannot resend or revoke invitations;
- cannot remove Members;
- cannot remove or replace the Owner;
- cannot change anyone's workspace access, including their own.

There are no additional workspace roles in this feature.

## Member with workspace access disabled

A Member whose workspace access is disabled:

- remains a Member and remains listed in Settings → Team;
- keeps all historical authorship and attribution;
- does not need to be invited again for access to be restored;
- cannot enter the workspace;
- must not see that workspace as an available workspace to switch into;
- cannot reach the workspace through a remembered URL or a direct request;
- cannot read workspace recruitment data;
- cannot perform workspace actions;
- cannot use workspace-scoped credentials or integrations for new actions in
  that workspace.

Their Recruiter Labs account remains active, and their membership and access in
every other workspace are unaffected.

---

# Workspace ownership

## New workspace

The user who creates a workspace becomes its Owner.

A newly created workspace must therefore have:

- exactly one Owner;
- that Owner as an active member.

## Existing workspace

Introducing this feature must preserve all existing valid workspace
memberships.

No user may unexpectedly lose access as a consequence of introducing ownership.

For an existing workspace without explicit ownership, one existing active
member must be deterministically established as Owner.

Prefer the longest-standing workspace membership when the existing data allows
that determination.

All other existing members become Members.

The migration to the ownership model must never create multiple Owners for the
same workspace.

## Ownership lifecycle

A workspace must always retain exactly one Owner.

Therefore, in this version:

- Owner cannot remove themselves;
- Member cannot remove Owner;
- Owner cannot be demoted;
- ownership cannot be transferred.

Ownership transfer may be introduced later if customer demand demonstrates that
it is necessary.

---

# Team settings

Workspace Settings contains a Team area.

## Active members

Any workspace member with access can see:

- member name;
- member email;
- role: Owner or Member;
- whether that person's workspace access is currently enabled or disabled.

The Owner additionally receives management controls for removable Members and
controls for enabling or disabling a Member's workspace access.

The Owner's own access is presented as not editable.

Access state is stated in plain product language. The Team area must not become
a permissions matrix, and it must not expose internal authorization
terminology.

Members whose access is disabled still appear here — they are still part of the
team. Disabling access and removing someone are visibly different actions, and
the copy must never blur them.

No member belonging exclusively to another workspace may appear.

## Pending invitations

The Owner can also see invitations that have not yet become membership.

Each pending invitation shows at minimum:

- invited email;
- invitation state;
- invitation date;
- expiration date.

Pending invitations are visibly separate from active Members.

An invitation does not mean that the invited person currently has workspace
access.

Members may not manage pending invitations.

---

# Inviting a Member

The Owner can invite one person by email address.

Creating the invitation must not immediately create workspace membership.

Access begins only after successful invitation acceptance.

The invitation email identifies:

- Recruiter Labs;
- the workspace;
- the inviter;
- that the recipient is being invited to join that workspace;
- the invitation expiration.

The invitation email must not contain:

- candidate information;
- application information;
- interview evidence;
- hiring notes;
- other workspace recruitment data.

---

# Invitation identity

An invitation belongs to:

- exactly one workspace;
- exactly one normalized email address;
- the human inviter;
- one invitation lifecycle.

Email matching is case-insensitive.

For membership purposes:

`Recruiter@Example.com`

and:

`recruiter@example.com`

represent the same email identity.

An invitation may only produce membership for an account whose verified email
matches the invited email.

An authenticated user using another email address must not be able to accept
the invitation.

---

# Invitation expiration

An invitation is valid for **7 days**.

After expiration:

- it cannot create membership;
- it cannot grant workspace access.

The Owner may resend an expired invitation.

Resending creates a new valid 7-day invitation opportunity without producing
multiple simultaneously usable invitations for the same workspace and email.

---

# Duplicate invitation rules

## Already active Member

If the invited email already belongs to an active Member or Owner of that
workspace:

- do not create another invitation;
- explain that the person already has access.

## Invitation already pending

If the same normalized email already has a valid pending invitation for the
workspace:

- do not create another independent active invitation;
- allow the Owner to resend the existing invitation.

There must never be multiple independently usable pending invitations for the
same normalized email and workspace.

## Member of another workspace

Being a Member of another Recruiter Labs workspace is valid.

A single Recruiter Labs account may belong to multiple workspaces.

---

# Resending an invitation

The Owner can resend a pending or expired invitation.

Resending:

- keeps the invitation associated with the same workspace and email identity;
- provides a fresh 7-day validity window;
- sends a new invitation email;
- invalidates any previously usable invitation token when necessary to ensure
  only one usable invitation path remains.

Resending must not create duplicate workspace membership.

---

# Revoking an invitation

The Owner can revoke a pending invitation.

Once revoked:

- it cannot be accepted;
- it cannot grant workspace membership;
- it cannot expose workspace data.

Revocation affects only that invitation.

It must not affect:

- the recipient's Recruiter Labs account;
- memberships in other workspaces.

The Owner may invite that person again later.

---

# Accepting an invitation

## Existing Recruiter Labs user

When the invited email already has a Recruiter Labs account:

1. recipient opens the invitation;
2. recipient authenticates if necessary;
3. Recruiter Labs verifies that the authenticated verified email matches the
   invited email;
4. recipient sees which workspace invited them;
5. recipient explicitly accepts;
6. recipient becomes a Member;
7. recipient can enter the workspace.

Existing memberships in other workspaces remain untouched.

## New Recruiter Labs user

When the invited email does not yet have an account:

1. recipient opens the invitation;
2. Recruiter Labs guides them into registration;
3. registration uses the invited email identity;
4. recipient completes the product's required email-verification flow;
5. recipient accepts the invitation;
6. recipient becomes a Member of the existing workspace;
7. recipient can enter that workspace.

The invited user must not be forced to create a new workspace.

The invitation is specifically a path into the workspace that already exists.

---

# Email verification

Workspace membership must not be activated merely because someone can open an
invitation URL.

The invited email identity must satisfy Recruiter Labs' email-verification
requirement before membership becomes active.

An unverified account must therefore not become a workspace Member until the
matching email identity has been verified.

---

# Successful acceptance

After successful acceptance:

- membership exists exactly once;
- recipient becomes a Member;
- invitation is no longer pending;
- invitation cannot create another membership;
- recipient appears in the active member list;
- recipient receives normal workspace access.

Repeated acceptance attempts must be safe and must not create duplicate
membership.

---

# Invalid invitation states

An invitation must not grant access when it is:

- expired;
- revoked;
- already accepted;
- malformed;
- invalid;
- associated with a different authenticated email.

The product should explain the failure without exposing recruitment data.

Opening an invalid invitation must never expose:

- workspace candidates;
- applications;
- interviews;
- job information;
- team membership details beyond what is required to explain the invitation.

---

# Workspace access

Every Member's workspace access is either enabled or disabled.

A successfully accepted invitation creates a Member with workspace access
**enabled**.

The Owner's access is always enabled and cannot be disabled.

## Disabling a Member's workspace access

Only the Owner can disable a Member's workspace access. This must be enforced by
the application, not merely by hiding the control.

Once disabled, the person keeps their membership and their Member role but loses
current workspace authorization, exactly as described under *Member with
workspace access disabled*.

Disabling access must not:

- end membership;
- remove the person from the Team area;
- delete or reassign any recruitment record;
- change historical authorship;
- affect any other workspace;
- delete or disable their Recruiter Labs account;
- delete historical integration records or previously created external events.

## Restoring a Member's workspace access

Only the Owner can re-enable a Member's workspace access.

Restoring access:

- does not create another membership;
- does not require another invitation;
- immediately restores normal Member workspace authorization;
- preserves historical identity and attribution.

## Access changes take effect immediately

Disabling access must take effect from the product's perspective immediately.

If the Member still has an authenticated browser session:

- their Recruiter Labs authentication may remain valid;
- their authorization to that workspace does not.

On subsequent requests they must no longer be able to enter the workspace, view
its recruitment data, perform workspace actions, use remembered workspace
navigation, or use a direct URL to bypass the restriction. The workspace must
also stop appearing among the workspaces they can switch into.

---

# Removing a Member

Removing a Member and disabling a Member's workspace access are separate
actions, and must remain separate in both behaviour and interface copy.

Disabling access:

- keeps the person on the Team;
- keeps the membership;
- can be reversed directly by the Owner.

Removing:

- ends workspace membership;
- removes the person from the active Team;
- requires a new invitation if they later need to rejoin.

The Owner can remove an active Member.

Removal requires deliberate confirmation.

The interface must make clear that the person will lose access to that
workspace.

After confirmation:

- membership ends;
- workspace authorization ends;
- the person's Recruiter Labs account remains active;
- memberships in other workspaces remain unchanged.

Removing a Member must not delete recruitment history.

---

# Immediate authorization revocation

Removal must take effect from the product's perspective immediately.

If the removed Member still has an authenticated browser session:

- their Recruiter Labs authentication may remain valid;
- their authorization to the removed workspace does not.

On subsequent requests they must no longer be able to:

- enter the workspace;
- view workspace recruitment data;
- perform workspace actions;
- use remembered workspace navigation;
- use a direct URL to bypass removal.

If they belong to another workspace, they may continue using that workspace.

---

# Historical attribution

Removing a Member must not rewrite historical authorship.

For example, interview feedback previously submitted by that person remains:

- present;
- associated with the original interview;
- associated with the original application;
- attributed to its original human author.

Historical records must not become:

- anonymous;
- attributed to Owner;
- attributed to another Member;
- deleted because the author's membership ended.

---

# Existing recruitment records after removal

Removing a Member must not automatically:

- delete jobs;
- delete candidates;
- delete applications;
- delete interviews;
- delete interview feedback;
- cancel interviews;
- move candidates;
- change application statuses;
- recalculate candidate evaluation;
- change AI evidence;
- change candidate scores.

Workspace membership and workspace access control authorization, not historical
recruitment state.

The same guarantees apply when a Member's workspace access is disabled rather
than their membership ended: nothing in the list above may happen as a
consequence of disabling access.

---

# Workspace-scoped external integrations

A Member may have external credentials connected specifically for their activity
inside a workspace.

Once that person no longer has workspace authorization — whether because their
membership ended or because their workspace access was disabled — Recruiter Labs
must not use those workspace-scoped credentials for new actions in that
workspace on their behalf.

Losing authorization does not automatically undo valid historical actions.

For example:

- previously created interviews remain recorded;
- previously created external calendar events are not automatically deleted;
- historical integration records remain understandable.

The rule concerns future authorization, not retroactive deletion. It is the same
rule in both cases; disabling access is not a weaker form of it.

---

# Re-inviting a removed Member

A previously removed person may later be invited again.

They must receive and accept a new valid invitation.

Successful re-invitation restores current Member access.

Historical records remain attributed to the same user identity and must not be
duplicated or reassigned.

---

# Authorization rules

Membership-management and workspace-access permissions must be enforced by the
application.

A Member must not gain Owner capabilities by:

- manually constructing a URL;
- sending an action directly;
- manipulating a request;
- changing workspace identifiers;
- accessing an invitation belonging to another workspace;
- attempting to change their own or anyone else's workspace access.

Workspace authorization must be decided from **both** of:

1. active Team membership;
2. the workspace-access state of that membership.

A Member whose workspace access is disabled must fail authorization even though
their membership row still exists. Every tenant entry point and authorization
path must agree on this — the list of workspaces a person may enter, the
resolution of a workspace from a URL, and every workspace action. Hiding
navigation is not an authorization mechanism.

Interface visibility is not authorization.

Controls should be hidden when unavailable, but server-side authorization
remains authoritative.

---

# Tenant isolation

All membership and invitation behavior belongs to exactly one workspace.

A user in Workspace A must not be able to use Team functionality to:

- list Workspace B members;
- list Workspace B invitations;
- invite into Workspace B without authorization there;
- revoke Workspace B invitations;
- remove Workspace B members;
- change workspace access in Workspace B;
- accept another person's invitation;
- retrieve Workspace B recruitment information.

Invitation tokens themselves must never act as unrestricted workspace access
tokens.

Membership must exist, **and its workspace access must be enabled**, before
normal workspace data becomes accessible.

---

# Member recruitment access

This feature introduces no granular recruiter permissions.

Workspace access is a single on/off gate on entering a workspace at all. It is
not a permission dimension inside the workspace: it never grants or withholds
anything more specific than the workspace itself.

Once a user becomes a Member with workspace access enabled, they receive the
same normal recruitment workspace access currently provided to workspace users.

Existing feature-specific business rules continue to apply.

Owner and Member do not have different authority over:

- candidate fit;
- AI evaluation;
- human evidence;
- interview feedback;
- candidate movement;
- hiring decisions.

The Owner distinction exists only for workspace membership and workspace-access
administration.

---

# Seat limits and billing

This feature does not introduce seat-based billing.

Workspace membership is not limited by a subscription seat count here.

If a future plan introduces:

- maximum members;
- paid seats;
- additional-seat charges;

those rules belong to the billing feature that explicitly introduces them.

Do not anticipate those rules here.

---

# Concurrency and idempotency

Membership behavior must remain correct when actions happen concurrently or are
repeated.

The observable product outcome must guarantee:

- duplicate invite attempts do not produce multiple active invitations;
- repeated acceptance does not produce duplicate membership;
- simultaneous acceptance produces at most one membership;
- revoked invitation cannot subsequently produce valid membership;
- removing an already-removed Member does not corrupt membership;
- an invitation cannot bypass existing active membership;
- membership actions in one workspace cannot affect another workspace.

The product should fail safely rather than create ambiguous authorization.

---

# User flows

## Owner invites an existing user

1. Owner opens Settings → Team.
2. Owner chooses Invite member.
3. Owner enters an email.
4. Recruiter Labs validates the email against current membership and pending
   invitations.
5. Pending invitation is created.
6. Invitation email is sent.
7. Recipient opens invitation.
8. Recipient authenticates with the invited verified email.
9. Recipient sees the workspace invitation.
10. Recipient accepts.
11. Recipient becomes a Member.
12. Recipient appears in the active team list.

## Owner invites a new user

1. Owner opens Settings → Team.
2. Owner enters the person's email.
3. Invitation is sent.
4. Recipient opens the invitation.
5. Recipient follows registration using the invited email.
6. Recipient completes required email verification.
7. Recipient accepts.
8. Recipient becomes a Member of the existing workspace.
9. Recipient enters the workspace.

## Owner resends an invitation

1. Owner opens Settings → Team.
2. Owner finds the pending or expired invitation.
3. Owner chooses Resend.
4. Invitation receives a fresh validity window.
5. Recipient receives a fresh usable invitation.
6. No duplicate active invitation is produced.

## Owner revokes an invitation

1. Owner opens Settings → Team.
2. Owner selects a pending invitation.
3. Owner chooses Revoke.
4. Invitation becomes unusable.
5. No workspace membership is created.

## Owner removes a Member

1. Owner opens Settings → Team.
2. Owner chooses Remove for a Member.
3. Product explains that workspace access will end.
4. Owner confirms.
5. Membership ends.
6. Removed user loses workspace authorization.
7. Historical recruitment records remain intact.

## Owner disables a Member's workspace access

1. Owner opens Settings → Team.
2. Owner sees that the Member's access is currently enabled.
3. Owner chooses to disable that Member's access.
4. Product explains that the person stays on the team but cannot enter the
   workspace.
5. Owner confirms.
6. Membership and role are unchanged; the Member remains listed.
7. The Member's access shows as disabled.
8. On their next request the Member can no longer enter the workspace, and the
   workspace no longer appears among the workspaces they can switch into.
9. Historical recruitment records and authorship remain intact.

## Owner restores a Member's workspace access

1. Owner opens Settings → Team.
2. Owner sees that the Member's access is currently disabled.
3. Owner chooses to enable that Member's access.
4. The Member immediately regains normal workspace authorization.
5. No new invitation is sent and no second membership is created.
6. Historical authorship remains attributed to the same person.

---

# Acceptance criteria

## AC01 — Workspace creator becomes Owner

Creating a workspace creates exactly one Owner, and that user is also an active
workspace member.

## AC02 — Existing memberships are preserved

Introducing workspace ownership does not remove existing valid workspace
memberships.

Each existing workspace ends with exactly one Owner and its other existing
members remain Members.

## AC03 — Active members can view the Team area

A workspace member with access can view the workspace's Team area and see active
members with:

- name;
- email;
- Owner or Member role;
- whether their workspace access is enabled or disabled.

Users belonging only to other workspaces are never included.

## AC04 — Only Owner manages membership and access

Only Owner can:

- invite;
- resend invitations;
- revoke invitations;
- remove Members;
- disable a Member's workspace access;
- re-enable a Member's workspace access.

A Member cannot perform those actions through either the UI or direct requests.

## AC05 — Owner can invite by email

Owner can invite an email that is not already an active member of the
workspace.

Creating the invitation does not itself grant access.

## AC06 — Invitations expire after 7 days

A new invitation expires after 7 days.

An expired invitation cannot create membership without being renewed through
the supported invitation flow.

## AC07 — Invitation email exposes no recruitment data

Invitation email identifies the workspace and invitation purpose without
including candidate, application, interview or other recruitment information.

## AC08 — Existing member cannot be invited again

An email belonging to an existing active Owner or Member cannot receive another
active invitation for the same workspace.

## AC09 — Duplicate pending invitation is prevented

At most one usable pending invitation exists for the same normalized email and
workspace.

## AC10 — Email identity is case-insensitive

Case differences in an email do not bypass duplicate-membership,
duplicate-invitation or acceptance rules.

## AC11 — Existing user can accept

An existing Recruiter Labs user authenticated with the invited verified email
can accept a valid invitation and become a Member.

## AC12 — New user can accept

A new recipient can follow the invitation into registration, satisfy required
email verification and become a Member of the existing workspace without
creating another workspace.

## AC13 — Wrong account cannot accept

An authenticated account whose email does not match the invited email cannot
accept the invitation or gain workspace access from it.

## AC14 — Unverified email cannot activate membership

Invitation acceptance must not activate workspace membership until the matching
email identity satisfies Recruiter Labs' verification requirement.

## AC15 — Acceptance is idempotent

Accepting an invitation successfully creates at most one membership.

Repeated or concurrent acceptance attempts cannot duplicate membership.

## AC16 — Expired invitation cannot grant access

An expired invitation cannot create membership or expose workspace recruitment
data.

## AC17 — Owner can resend

Owner can resend a pending or expired invitation and provide a fresh 7-day
validity window without creating multiple usable invitations.

## AC18 — Owner can revoke

Owner can revoke a pending invitation, after which that invitation cannot
create membership.

## AC19 — Multiple workspace membership remains supported

Accepting a workspace invitation does not remove or alter the user's membership
in other workspaces.

## AC20 — Owner can remove Member

Owner can remove an active Member after deliberate confirmation.

## AC21 — Owner cannot be removed

Owner cannot remove themselves, and a Member cannot remove Owner.

The feature cannot leave a workspace without an Owner.

## AC22 — Removed Member loses workspace authorization

After removal, the former Member cannot access the workspace through:

- normal navigation;
- tenant switching;
- remembered workspace state;
- direct URLs;
- direct actions.

Authentication to Recruiter Labs may remain valid.

Workspace authorization does not.

## AC23 — Removal does not delete the user

Removing workspace membership does not delete or disable the Recruiter Labs
account and does not affect memberships in other workspaces.

## AC24 — Historical authorship survives removal

Recruitment records previously authored by a removed Member remain intact and
attributed to that user.

## AC25 — Recruitment history survives removal

Removing a Member does not automatically delete, cancel, move, recalculate or
reassign existing recruitment records.

## AC26 — Removed user's workspace credentials cannot perform new actions

External credentials associated with a removed user's access to that workspace
cannot be used for new workspace actions after removal.

Historical external actions remain intact.

## AC27 — Removed Member can later rejoin

A removed user can later receive and accept a new valid invitation.

Rejoining restores Member access without rewriting historical authorship.

## AC28 — Team administration is tenant-isolated

Membership and invitation functionality cannot be used from one workspace to
read or modify another workspace's team state without proper authorization.

## AC29 — No granular RBAC is introduced

A Member with workspace access enabled receives the existing normal recruiter
workspace access.

This feature does not introduce job-, candidate-, pipeline- or feature-level
permissions.

Workspace access — enabled or disabled, for Members — is the only additional
permission introduced, and it gates entering the workspace as a whole rather
than anything inside it.

## AC30 — No billing seat rule is introduced

No membership count, seat price or subscription-based team limit is introduced
by this feature.

## AC31 — Concurrent invitation operations remain safe

Concurrent invite, resend, revoke and accept operations cannot create duplicate
active invitations, duplicate membership or ambiguous workspace authorization.

## AC32 — Invitation never creates partial workspace access

Pending, expired, revoked, malformed or otherwise invalid invitations never
provide partial access to workspace recruitment data.

## AC33 — Accepted invitation grants access by default

A successfully accepted invitation produces a Member whose workspace access is
enabled, without granting Owner.

## AC34 — Only Owner changes workspace access

Only the Owner can disable or re-enable a Member's workspace access, enforced
server-side and not merely by hiding the control.

A Member cannot change anyone's workspace access — their own included — through
the UI or a direct request, and cannot do so across workspaces.

## AC35 — Owner access cannot be disabled

The Owner's workspace access can never be disabled, by themselves or by anyone
else. No path may leave a workspace whose Owner cannot enter it.

## AC36 — Disabled access blocks workspace authorization

A Member whose workspace access is disabled cannot enter the workspace, read its
recruitment data, or perform workspace actions — through normal navigation,
workspace switching, remembered workspace state, a direct URL, or a direct
action.

The workspace must not appear among the workspaces they can switch into.

Their Recruiter Labs authentication may remain valid; their workspace
authorization does not.

## AC37 — Disabling access keeps membership and history

A Member whose access is disabled remains a Member, remains listed in the Team
area, keeps their role, and keeps all historical authorship and attribution.

Disabling access does not delete, cancel, move, recalculate or reassign any
recruitment record, does not affect other workspaces, and does not delete or
disable their Recruiter Labs account.

## AC38 — Access can be restored without a new invitation

The Owner can re-enable a disabled Member's workspace access, which immediately
restores normal Member authorization without creating another membership,
without sending another invitation, and without rewriting historical
attribution.

## AC39 — Disabled access blocks workspace-scoped credentials

External credentials associated with a Member's access to a workspace cannot be
used for new workspace actions while that Member's workspace access is disabled.

Historical external actions and integration records remain intact.

## AC40 — Disabling access and removing a Member stay distinct

Disabling access keeps the person on the Team with their membership intact and is
directly reversible by the Owner.

Removing ends membership, takes the person off the active Team, and requires a
new invitation to rejoin.

Neither action may be implemented or described as the other.

---

# Product edge cases

## Email already belongs to workspace

Do not invite again.

Explain that the person already has access.

## Email belongs to a Member whose access is disabled

Do not invite again, and do not create an invitation.

That person is already on the team. Explain that their workspace access is
currently disabled and that the Owner can restore it directly, rather than
implying they need a new invitation.

## Email already has pending invitation

Do not create another independent invitation.

Owner can resend the existing invitation.

## Invitation expired

Do not accept.

Owner can resend.

## Invitation revoked

Do not accept.

Owner may invite the person again later.

## Invitation already accepted

Do not duplicate membership.

The active Member uses the workspace normally.

## User logged into wrong account

Do not accept.

Explain that the invitation belongs to another email identity.

## Recipient has no account

Guide them into registration using the invited email.

Do not require creation of another workspace.

## Recipient already has other workspaces

Add the new membership without modifying existing memberships.

## Member removed while logged in

Keep general authentication if appropriate but deny further access to the
removed workspace.

## Removed Member authored interview feedback

Preserve the feedback and original author attribution.

## Removed Member has scheduled interviews

Do not automatically cancel or delete the interview.

Their removed workspace authorization cannot be used for new actions.

## Previously removed Member is invited again

Require a new valid invitation and acceptance.

Historical authorship remains unchanged.

## Owner attempts self-removal

Reject the action.

Ownership transfer is out of scope.

## Owner attempts to disable their own access

Reject the action. The Owner always has workspace access.

## Member whose access is disabled is still logged in

Keep general authentication if appropriate but deny further access to that
workspace, and stop offering it as a workspace they can switch into.

## Member whose access is disabled authored interview feedback

Preserve the feedback and original author attribution.

## Member whose access is disabled has scheduled interviews

Do not automatically cancel or delete the interview.

Their disabled workspace authorization cannot be used for new actions.

## Access is disabled and then restored

Restore normal Member authorization directly. Do not create a second
membership, and do not require a new invitation.

## Member whose access is disabled opens their old invitation link

Do not tell them they already have access, and do not offer them a way into the
workspace — they would be refused. Explain that they are still part of the
workspace but that their access is currently turned off, and that the Owner can
turn it back on.

## Member calls an Owner action directly

Reject the action even when normal UI controls are bypassed.

## Two acceptance requests happen concurrently

Create at most one active membership.

## Revocation and acceptance race

If the invitation is no longer valid when membership is finalized, fail safely
without granting ambiguous access.

---

# Out of scope

This feature deliberately does not include:

- configurable RBAC;
- custom roles;
- Admin role;
- multiple Owners;
- ownership transfer;
- recruiter vs hiring-manager permissions;
- job-level permissions;
- candidate-level permissions;
- pipeline-level permissions;
- feature-level permissions;
- read-only Members;
- guest accounts;
- external client access;
- agency client portals;
- temporary candidate access;
- bulk invitations;
- CSV team import;
- shareable public invitation links;
- automatic joining by email domain;
- workspace departments;
- nested teams;
- groups;
- SCIM;
- SSO;
- seat-based billing;
- paid seats;
- subscription member limits;
- user account deletion;
- workspace deletion;
- Member self-service departure;
- enterprise authorization management;
- general audit-log redesign;
- automatic interview reassignment after member removal or access disablement;
- automatic cancellation of scheduled interviews after member removal or access
  disablement;
- changes to AI evaluation;
- changes to candidate fit;
- changes to candidate scoring;
- changes to interview-feedback semantics;
- automatic hiring decisions;
- any permission dimension other than workspace access;
- scheduled, time-boxed or automatically expiring workspace access;
- a Member disabling or restoring their own access.

---

# Product boundary

This feature answers:

> Who belongs to this Recruiter Labs workspace, and who is currently allowed to
> enter it?

It deliberately does not answer:

> What individual recruitment objects or operations is each workspace member
> allowed to access?

For the current Recruiter Labs product, workspace membership plus workspace
access is the collaboration boundary. Access is a single gate on the workspace
as a whole, not the beginning of a permission model.

Granular authorization should only be introduced later when concrete customer
requirements justify the additional complexity.
