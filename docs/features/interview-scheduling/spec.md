---
status: implemented
type: as-built
---

# Interview scheduling

## Problem

Once a recruiter decides to interview a candidate, the product must turn that
decision into a reliable calendar commitment without duplicate events, ambiguous
timezones, or hidden synchronization failures.

The interview needs to remain connected to the candidate application while
rescheduling, cancellation, RSVP changes, and calendar errors are reflected back
to the recruiter.

## Objective

Let an authorized recruiter schedule and manage candidate interviews through
their connected Google Calendar account, with explicit timezone handling,
calendar synchronization state, candidate RSVP visibility, idempotent scheduling,
and preserved interview history.

## User behaviour

From an active candidate application, a recruiter can:

- schedule an interview at a future date/time;
- choose duration and timezone;
- see which connected calendar account will own the invitation;
- receive the calendar/meeting state back in the application;
- join the generated meeting when a meeting URL is available;
- reschedule an existing interview;
- cancel an interview with an optional reason;
- refresh the candidate's calendar response;
- see upcoming, past, and cancelled interviews separately;
- see calendar sync failures and RSVP state.

The existing Interview Brief can be visible beside the interview workflow as
preparation context.

## Business rules

This feature is governed by
`.ai/skills/recruitment-workflow/SKILL.md`.

- Scheduling is a human action on an active application.
- Terminal applications cannot schedule a new interview until a human reopens the
  application into an active stage.
- Interview scheduling uses the recruiter's connected Google Calendar account in
  the current workspace.
- Scheduled time is interpreted in the explicitly selected timezone.
- An interview must have a future start, a valid duration, and a corresponding
  end time.
- One opened scheduling request has one request identity so duplicate submission
  or retry does not create duplicate interview commitments.
- Calendar synchronization state is explicit; a product-side interview is not
  silently represented as synchronized when calendar creation/update failed.
- Recoverable and terminal calendar failures remain visible to the recruiter.
- Rescheduling updates the existing interview commitment rather than creating a
  second independent interview for the same action.
- Cancellation preserves the interview as history and removes it from upcoming
  commitments.
- Candidate RSVP state can be refreshed from the connected calendar.
- Upcoming means a non-cancelled interview whose commitment has not ended yet.
  Past interviews remain historical records.
- Interview scheduling does not automatically move the candidate's pipeline
  stage.
- The Interview Brief remains decision support. It does not become an automated
  interview score.

## User flow

### Schedule

1. The recruiter opens an active candidate application.
2. The recruiter chooses a future start time, duration, and timezone.
3. The product verifies that the recruiter can update the application and has a
   usable calendar connection.
4. The scheduling request creates or reuses one interview commitment for that
   request.
5. The calendar integration creates the external event and meeting details.
6. The application shows the interview and its synchronization/RSVP state.
7. Retrying the same scheduling request does not create a second interview.

### Reschedule

1. The recruiter selects an existing non-cancelled interview.
2. A new date/time, duration, and timezone are chosen.
3. The existing calendar-backed interview is updated.
4. The application continues to show one interview record with the new
   commitment.

### Cancel

1. The recruiter chooses to cancel an interview and may provide a reason.
2. The connected calendar event is cancelled/updated through the integration.
3. The interview remains in history as cancelled and is no longer an upcoming
   commitment.

### Refresh RSVP

1. The recruiter requests a refresh for an interview owned by the usable
   calendar connection.
2. The product synchronizes the candidate's latest calendar response.
3. RSVP state and response time are reflected in the application.

## Acceptance criteria

- **AC01** — An authorized recruiter can schedule an interview for an active
  application using a future time, duration, and explicit timezone.
- **AC02** — A terminal application cannot schedule a new interview until a human
  reopens the recruitment process.
- **AC03** — Scheduling identifies the recruiter's connected Google Calendar
  account that owns the event.
- **AC04** — Repeating the same scheduling request does not intentionally create
  duplicate interview records or duplicate calendar commitments.
- **AC05** — Calendar synchronization state is visible and a failed sync is not
  presented as a successfully synchronized interview.
- **AC06** — A scheduled interview can expose a meeting URL when the calendar
  provider supplies one.
- **AC07** — An existing interview can be rescheduled without being represented
  as an unrelated second interview.
- **AC08** — An interview can be cancelled while preserving historical context.
- **AC09** — Cancelled interviews are excluded from upcoming commitments.
- **AC10** — An interview whose end time has passed is treated as history rather
  than an upcoming commitment.
- **AC11** — The recruiter can refresh and see the candidate's latest RSVP state.
- **AC12** — Scheduling, rescheduling, cancellation, or RSVP refresh does not
  automatically decide a candidate's pipeline outcome.
- **AC13** — Interview Brief guidance remains accessible as preparation context
  without being converted into automated post-interview feedback.

## Out of scope

- Structured interviewer feedback or scorecards.
- Interview recording or transcription.
- AI notetaking, sentiment analysis, or voice analysis.
- AI-driven interviewer recommendation.
- Automatic candidate stage movement after an interview.
- Multiple calendar providers beyond the implemented Google Calendar workflow.
- Calendar availability optimization or an AI scheduling assistant.

## Related feature specs

- `../calendar-integration/spec.md`
- `../candidate-evaluation/spec.md`
- `../recruitment-pipeline/spec.md`
- `../recruitment-attention/spec.md`
