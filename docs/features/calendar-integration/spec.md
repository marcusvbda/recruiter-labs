---
status: implemented
type: as-built
---

# Calendar integration

## Problem

Interview scheduling depends on an external calendar account owned by the
recruiter. OAuth credentials can expire or require reauthorization, and an event
can fail to synchronize even when the product has an interview record.

Recruiters need a visible, recoverable connection state instead of hidden
calendar assumptions.

## Objective

Provide a per-user, per-workspace Google Calendar connection that supports the
interview lifecycle, exposes account and synchronization state, and makes
reauthorization or sync failure actionable.

## User behaviour

In calendar settings, an authenticated workspace user can:

- connect a Google Calendar account;
- see the connected account identity and connection state;
- reconnect when authorization is no longer usable;
- disconnect the integration.

In interview workflows, the recruiter can see which account owns the calendar
event and whether the interview is synchronized successfully.

When a connected account needs reauthorization and upcoming interviews depend on
it, the product can surface that as recruitment attention.

## Business rules

The recruitment workflow rules in
`.ai/skills/recruitment-workflow/SKILL.md` apply to calendar-backed recruitment
actions.

- Calendar authorization is scoped to both a user and a company workspace.
- A calendar connection for one workspace/user must not be treated as another
  workspace/user's connection.
- The implemented interview calendar provider is Google Calendar.
- The product distinguishes a usable connected state from a state that requires
  reauthorization.
- OAuth connect, reconnect, callback, and disconnect flows require authenticated
  workspace context.
- Calendar-backed interview actions verify that the current recruiter's
  connection is usable before relying on it.
- Interview synchronization state is separate from the interview's recruitment
  meaning. A calendar failure is an operational problem, not a candidate signal.
- The recruiter can see when calendar synchronization failed rather than the
  product silently claiming success.
- Upcoming interviews remain tied to the recruiter/calendar owner that created
  them.
- Reauthorization need can surface as a deterministic attention item when it
  threatens upcoming interview commitments.
- Calendar connection state must never influence candidate fit, evidence
  coverage, confidence, or hiring decisions.

## User flow

### Connect

1. An authenticated workspace user opens calendar settings.
2. The user starts the Google Calendar connection flow.
3. Google authorization returns to the product.
4. The product associates the usable calendar account with that user in that
   workspace.
5. The connected account is available for interview scheduling.

### Reauthorize

1. A previously connected account becomes unusable or requires renewed consent.
2. The product marks the connection as requiring reauthorization instead of
   silently treating it as healthy.
3. The recruiter reconnects the Google account.
4. Once usable again, calendar-backed interview actions can continue.

### Disconnect

1. The authenticated workspace user chooses to disconnect the calendar account.
2. The integration is no longer available for new calendar-backed interview
   operations from that connection.
3. Existing interview history remains part of the recruitment record.

## Acceptance criteria

- **AC01** — A calendar connection belongs to one authenticated user in one
  company workspace.
- **AC02** — The implemented calendar connection can be established through
  Google OAuth.
- **AC03** — The product exposes the connected account identity when available.
- **AC04** — A connection that requires reauthorization is distinguishable from
  a healthy connected state.
- **AC05** — An authenticated user can reconnect a calendar integration that
  requires renewed authorization.
- **AC06** — An authenticated user can disconnect their workspace calendar
  integration.
- **AC07** — Interview scheduling does not silently rely on a missing or unusable
  calendar connection.
- **AC08** — Interview calendar synchronization success/failure is visible
  independently from candidate evaluation.
- **AC09** — A calendar failure or reauthorization state can become an
  operational attention signal when upcoming interview commitments are affected.
- **AC10** — Calendar connection state does not influence candidate fit or
  recruitment decisions.

## Out of scope

- Microsoft Outlook/Exchange calendar integration.
- A generic multi-provider calendar abstraction exposed to users.
- AI scheduling assistants.
- Calendar-derived candidate scoring.
- Interview feedback or transcript capture.
- Enterprise-wide shared calendar administration.

## Related feature specs

- `../interview-scheduling/spec.md`
- `../recruitment-attention/spec.md`
