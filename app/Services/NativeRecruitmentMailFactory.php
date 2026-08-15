<?php

namespace App\Services;

use App\Data\InterviewEmailContext;
use App\Data\RecruitmentEmailContext;
use App\Data\StatusEmailContext;
use App\Enums\EmailNotificationType;
use App\Mail\Recruitment\InterviewCancelledMail;
use App\Mail\Recruitment\InterviewRescheduledMail;
use App\Mail\Recruitment\InterviewScheduledMail;
use App\Mail\Recruitment\PipelineStatusMail;
use App\Mail\Recruitment\RecruitmentMail;
use LogicException;

class NativeRecruitmentMailFactory
{
    public function make(EmailNotificationType $type, RecruitmentEmailContext $context): RecruitmentMail
    {
        return match ($type) {
            EmailNotificationType::PipelineStatus => new PipelineStatusMail(
                $this->statusContext($type, $context),
            ),
            EmailNotificationType::InterviewScheduled => new InterviewScheduledMail(
                $this->interviewContext($type, $context),
            ),
            EmailNotificationType::InterviewRescheduled => new InterviewRescheduledMail(
                $this->interviewContext($type, $context),
            ),
            EmailNotificationType::InterviewCancelled => new InterviewCancelledMail(
                $this->interviewContext($type, $context),
            ),
        };
    }

    private function statusContext(
        EmailNotificationType $type,
        RecruitmentEmailContext $context,
    ): StatusEmailContext {
        if (! $context instanceof StatusEmailContext) {
            throw new LogicException("{$type->value} requires a status email context.");
        }

        return $context;
    }

    private function interviewContext(
        EmailNotificationType $type,
        RecruitmentEmailContext $context,
    ): InterviewEmailContext {
        if (! $context instanceof InterviewEmailContext) {
            throw new LogicException("{$type->value} requires an interview email context.");
        }

        return $context;
    }
}
