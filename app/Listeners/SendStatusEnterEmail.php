<?php

namespace App\Listeners;

use App\Data\StatusEmailContext;
use App\Enums\EmailNotificationType;
use App\Events\ApplicationEnteredStatus;
use App\Models\Application;
use App\Models\Status;
use App\Services\EmailTemplateRenderer;
use App\Services\RecruitmentEmailDispatcher;

/**
 * The single consumer of {@see ApplicationEnteredStatus}: it resolves the
 * status's own communication settings and hands the result to the tenant's
 * existing email delivery pipeline. It knows nothing about Gmail or Resend.
 */
class SendStatusEnterEmail
{
    public function __construct(
        private readonly RecruitmentEmailDispatcher $dispatcher,
        private readonly EmailTemplateRenderer $renderer,
    ) {}

    public function handle(ApplicationEnteredStatus $event): void
    {
        $application = Application::query()
            ->withoutGlobalScopes()
            ->with(['candidate', 'job', 'company', 'status'])
            ->find($event->applicationId);

        if (! $application instanceof Application) {
            return;
        }

        $status = Status::query()->find($event->statusId);

        if (
            ! $status instanceof Status
            || (int) $status->company_id !== (int) $application->company_id
            || ! $status->sendsOnEnterEmail()
        ) {
            return;
        }

        $this->dispatcher->dispatch(
            $application->company,
            EmailNotificationType::PipelineStatus,
            new StatusEmailContext(
                applicationId: (int) $application->getKey(),
                statusId: (int) $status->getKey(),
                candidateEmail: (string) $application->candidate?->email,
                employerName: $application->company->name,
                subject: $this->renderer->render($status->email_subject, $application),
                body: $this->renderer->render($status->email_body, $application, escape: true),
                enteredAt: now()->getTimestamp(),
            ),
        );
    }
}
