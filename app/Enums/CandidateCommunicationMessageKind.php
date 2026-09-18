<?php

namespace App\Enums;

/**
 * Identifies who or what produced a candidate-facing message. This is
 * deliberately separate from AI assistance: AI may help a recruiter compose
 * a message, but it never becomes the sender or authorizing actor.
 */
enum CandidateCommunicationMessageKind: string
{
    case RecruiterAuthored = 'recruiter_authored';
    case PipelineStatusNotification = 'pipeline_status_notification';
    case InterviewScheduled = 'interview_scheduled';
    case InterviewRescheduled = 'interview_rescheduled';
    case InterviewCancelled = 'interview_cancelled';

    public static function fromNotificationType(EmailNotificationType $type): self
    {
        return match ($type) {
            EmailNotificationType::PipelineStatus => self::PipelineStatusNotification,
            EmailNotificationType::InterviewScheduled => self::InterviewScheduled,
            EmailNotificationType::InterviewRescheduled => self::InterviewRescheduled,
            EmailNotificationType::InterviewCancelled => self::InterviewCancelled,
        };
    }

    public function isRecruiterAuthored(): bool
    {
        return $this === self::RecruiterAuthored;
    }
}
