<?php

namespace App\Services;

use App\Data\ApplicationEmailContext;
use App\Data\InterviewEmailContext;
use App\Data\RecruitmentEmailContext;
use App\Enums\EmailNotificationType;
use App\Mail\Recruitment\ApplicationHiredMail;
use App\Mail\Recruitment\ApplicationReceivedMail;
use App\Mail\Recruitment\ApplicationRejectedMail;
use App\Mail\Recruitment\InterviewCancelledMail;
use App\Mail\Recruitment\InterviewRescheduledMail;
use App\Mail\Recruitment\InterviewScheduledMail;
use App\Mail\Recruitment\RecruitmentMail;
use LogicException;

class NativeRecruitmentMailFactory
{
    public function make(EmailNotificationType $type, RecruitmentEmailContext $context): RecruitmentMail
    {
        return match ($type) {
            EmailNotificationType::ApplicationReceived => new ApplicationReceivedMail(
                $this->applicationContext($type, $context),
            ),
            EmailNotificationType::ApplicationRejected => new ApplicationRejectedMail(
                $this->applicationContext($type, $context),
            ),
            EmailNotificationType::ApplicationHired => new ApplicationHiredMail(
                $this->applicationContext($type, $context),
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

    private function applicationContext(
        EmailNotificationType $type,
        RecruitmentEmailContext $context,
    ): ApplicationEmailContext {
        if (! $context instanceof ApplicationEmailContext) {
            throw new LogicException("{$type->value} requires an application email context.");
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
