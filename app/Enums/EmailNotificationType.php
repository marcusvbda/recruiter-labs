<?php

namespace App\Enums;

enum EmailNotificationType: string
{
    case ApplicationReceived = 'application_received';
    case InterviewScheduled = 'interview_scheduled';
    case InterviewRescheduled = 'interview_rescheduled';
    case InterviewCancelled = 'interview_cancelled';
    case ApplicationRejected = 'application_rejected';
    case ApplicationHired = 'application_hired';

    public function label(): string
    {
        return match ($this) {
            self::ApplicationReceived => 'Application received',
            self::InterviewScheduled => 'Interview scheduled',
            self::InterviewRescheduled => 'Interview rescheduled',
            self::InterviewCancelled => 'Interview cancelled',
            self::ApplicationRejected => 'Application rejected',
            self::ApplicationHired => 'Application hired',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ApplicationReceived => 'Automatically email the candidate when an application is submitted.',
            self::InterviewScheduled => 'Send confirmation when an interview is scheduled.',
            self::InterviewRescheduled => 'Send updated interview information after a reschedule.',
            self::InterviewCancelled => 'Notify the candidate if an interview is cancelled.',
            self::ApplicationRejected => 'Notify the candidate when their application is rejected.',
            self::ApplicationHired => 'Notify the candidate when they are marked as hired.',
        };
    }
}
