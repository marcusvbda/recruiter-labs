<?php

namespace App\Listeners;

use App\Data\EmailTemplateContext;
use App\Data\StatusEmailContext;
use App\Enums\CandidateCommunicationMessageKind;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Enums\EmailNotificationType;
use App\Events\ApplicationEnteredStatus;
use App\Models\Application;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateCommunicationThread;
use App\Models\EmailTemplate;
use App\Models\Status;
use App\Services\EmailTemplateRenderer;
use App\Services\RecruitmentEmailDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single consumer of {@see ApplicationEnteredStatus}: it resolves the
 * reusable template the stage is configured to send and hands the result to the
 * tenant's existing email delivery pipeline. It knows nothing about Gmail or
 * Resend.
 *
 * The stage transition has already been validated and committed by the time
 * this runs. Nothing here may undo it: a template that is gone, retired, or
 * whose variables cannot be resolved for this candidate is a communication
 * configuration problem, recorded as a failed message in the candidate's
 * communication history so a human can repair it — never a reason to move the
 * candidate back, and never candidate-quality evidence.
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

        $template = $status->emailTemplate;
        $context = EmailTemplateContext::forApplication($application);

        // A retired template is one the workspace decided to stop sending, so
        // sending it anyway would contradict that decision. Either way the
        // stage's automation is now incomplete and must say so instead of
        // quietly emailing something else.
        if (
            ! $template instanceof EmailTemplate
            || (int) $template->company_id !== (int) $application->company_id
            || ! $template->is_available
        ) {
            $this->recordBlockedSend($application, $status, 'template_unavailable');

            return;
        }

        $unresolved = array_values(array_unique(array_merge(
            $this->renderer->unresolvedTokens($template->subject, $context),
            $this->renderer->unresolvedTokens($template->body, $context),
        )));

        if ($unresolved !== []) {
            $this->recordBlockedSend($application, $status, 'unresolved_variables', $unresolved);

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
                subject: $this->renderer->renderSubject($template, $context),
                body: $this->renderer->renderBody($template, $context),
                enteredAt: now()->getTimestamp(),
            ),
        );
    }

    /**
     * Writes the blocked automation into the same communication history a real
     * delivery failure lands in, using the existing failed-message
     * representation rather than a second failure store. No provider was ever
     * reached, so there is no delivery row to attach.
     *
     * @param  list<string>  $unresolved
     */
    private function recordBlockedSend(
        Application $application,
        Status $status,
        string $reason,
        array $unresolved = [],
    ): void {
        try {
            $thread = CandidateCommunicationThread::query()->firstOrCreate([
                'company_id' => $application->company_id,
                'candidate_id' => $application->candidate_id,
                'job_id' => $application->job_id,
            ], ['application_id' => $application->getKey()]);

            $key = 'stage-email-blocked/'.hash(
                'sha256',
                $application->company_id.':'.$application->getKey().':'.$status->getKey().':'.$reason.':'.implode(',', $unresolved),
            );

            if (CandidateCommunicationMessage::query()->where('idempotency_key', $key)->exists()) {
                return;
            }

            $message = new CandidateCommunicationMessage;

            $message->forceFill([
                'company_id' => $application->company_id,
                'thread_id' => $thread->getKey(),
                'kind' => CandidateCommunicationMessageKind::PipelineStatusNotification,
                'status' => CandidateCommunicationMessageStatus::Failed,
                'authorized_subject' => __('statuses.stage_email_blocked.subject', [
                    'status' => $status->name,
                ]),
                'authorized_body' => $reason === 'unresolved_variables'
                    ? __('statuses.stage_email_blocked.unresolved_variables', [
                        'variables' => implode(', ', $unresolved),
                    ])
                    : __('statuses.stage_email_blocked.template_unavailable'),
                'recipient_email' => $application->candidate?->email,
                'idempotency_key' => $key,
                'send_requested_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            // The transition is already committed and must stay that way, so a
            // problem writing the failure record is logged, never rethrown.
            Log::warning('A pipeline stage email was not sent and its failure record could not be written.', [
                'application_id' => $application->getKey(),
                'status_id' => $status->getKey(),
                'reason' => $reason,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        Log::notice('A pipeline stage email was not sent because its template could not produce sendable content.', [
            'application_id' => $application->getKey(),
            'status_id' => $status->getKey(),
            'reason' => $reason,
            'unresolved_variables' => $unresolved,
        ]);
    }
}
