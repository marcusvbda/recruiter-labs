<?php

namespace App\Enums;

enum EmailNotificationType: string
{
    /**
     * Candidate communication configured on a pipeline status and sent when an
     * application enters it. Replaces the old fixed application_received /
     * application_rejected / application_hired notifications.
     */
    case PipelineStatus = 'pipeline_status';

    /**
     * Interview emails are independent domain events: reaching an "Interview"
     * status is not the same thing as having an interview scheduled.
     */
    case InterviewScheduled = 'interview_scheduled';
    case InterviewRescheduled = 'interview_rescheduled';
    case InterviewCancelled = 'interview_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PipelineStatus => 'Pipeline status update',
            self::InterviewScheduled => 'Interview scheduled',
            self::InterviewRescheduled => 'Interview rescheduled',
            self::InterviewCancelled => 'Interview cancelled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PipelineStatus => 'Email configured on a pipeline status, sent when a candidate enters that stage.',
            self::InterviewScheduled => 'Send confirmation when an interview is scheduled.',
            self::InterviewRescheduled => 'Send updated interview information after a reschedule.',
            self::InterviewCancelled => 'Notify the candidate if an interview is cancelled.',
        };
    }
}
