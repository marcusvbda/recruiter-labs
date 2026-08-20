<?php

namespace App\Enums;

/**
 * The recruitment situations the product knows how to recognise on its own.
 *
 * Every case is derived from persisted state and can be explained in one
 * sentence to the recruiter — no heuristic scoring, and never a model asked
 * "what should be done next?".
 */
enum RecruitmentAttentionType: string
{
    /** The candidate answered the calendar invitation with a decline. */
    case InterviewDeclined = 'interview_declined';

    /** The interview exists in the product but not (reliably) in the calendar. */
    case InterviewCalendarFailed = 'interview_calendar_failed';

    /** Interviews are booked, but the calendar account behind them needs re-authorising. */
    case CalendarReconnectRequired = 'calendar_reconnect_required';

    /** The candidate evaluation errored and produced nothing. */
    case EvaluationFailed = 'evaluation_failed';

    /** Evaluations are queued but the workspace has no AI allowance left. */
    case EvaluationBlockedByQuota = 'evaluation_blocked_by_quota';

    /** The candidate has been in a stage longer than that stage allows. */
    case StageOverdue = 'stage_overdue';

    /** A finalist with no interview ahead of them: the process is waiting on a decision. */
    case DecisionPending = 'decision_pending';

    /** Candidates arrived, and none of them moved forward. */
    case JobStalled = 'job_stalled';

    /** The campaign is about to end with nobody close to being hired. */
    case JobEndingWithoutFinalists = 'job_ending_without_finalists';

    /** As many people have been hired as the job set out to hire. */
    case HiringTargetReached = 'hiring_target_reached';

    /** One opening left before the job meets its hiring target. */
    case HiringTargetNear = 'hiring_target_near';

    public function severity(): RecruitmentAttentionSeverity
    {
        return match ($this) {
            self::InterviewDeclined,
            self::InterviewCalendarFailed,
            self::CalendarReconnectRequired => RecruitmentAttentionSeverity::Critical,
            self::EvaluationFailed,
            self::EvaluationBlockedByQuota,
            self::StageOverdue,
            self::DecisionPending,
            self::JobStalled,
            self::JobEndingWithoutFinalists,
            self::HiringTargetReached => RecruitmentAttentionSeverity::Warning,
            self::HiringTargetNear => RecruitmentAttentionSeverity::Info,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InterviewDeclined => 'heroicon-m-calendar-days',
            self::InterviewCalendarFailed => 'heroicon-m-exclamation-triangle',
            self::CalendarReconnectRequired => 'heroicon-m-link-slash',
            self::EvaluationFailed => 'heroicon-m-x-circle',
            self::EvaluationBlockedByQuota => 'heroicon-m-bolt-slash',
            self::StageOverdue => 'heroicon-m-clock',
            self::DecisionPending => 'heroicon-m-hand-raised',
            self::JobStalled => 'heroicon-m-pause-circle',
            self::JobEndingWithoutFinalists => 'heroicon-m-calendar',
            self::HiringTargetReached => 'heroicon-m-check-badge',
            self::HiringTargetNear => 'heroicon-m-flag',
        };
    }
}
