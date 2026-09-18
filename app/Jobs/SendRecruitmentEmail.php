<?php

namespace App\Jobs;

use App\Data\CandidateCommunicationEmailContext;
use App\Data\InterviewEmailContext;
use App\Data\RecruitmentEmailContext;
use App\Data\StatusEmailContext;
use App\Enums\CandidateCommunicationMessageKind;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Enums\EmailNotificationType;
use App\Enums\RecruitmentEmailDeliveryStatus;
use App\Mail\Recruitment\RecruitmentMail;
use App\Models\Application;
use App\Models\CandidateCommunicationMessage;
use App\Models\CandidateCommunicationThread;
use App\Models\CompanyEmailProviderSetting;
use App\Models\Interview;
use App\Models\RecruitmentEmailDelivery;
use App\Services\NativeRecruitmentMailFactory;
use App\Services\RecruitmentEmailSenderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendRecruitmentEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'recruitment-emails';

    public int $tries = 4;

    public int $timeout = 60;

    public int $uniqueFor = 86400;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $companyId,
        public readonly int $providerSettingId,
        public readonly ?EmailNotificationType $type,
        public readonly RecruitmentEmailContext $context,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        NativeRecruitmentMailFactory $mailFactory,
        RecruitmentEmailSenderRegistry $senders,
    ): void {
        if (Cache::has($this->deliveryKey())) {
            return;
        }

        if (! $this->context instanceof CandidateCommunicationEmailContext && ! $this->type instanceof EmailNotificationType) {
            throw new \LogicException('Automated recruitment email jobs require a notification type.');
        }

        $providerSetting = CompanyEmailProviderSetting::query()
            ->whereKey($this->providerSettingId)
            ->where('company_id', $this->companyId)
            ->when(! $this->context instanceof CandidateCommunicationEmailContext, fn ($query) => $query->where('is_default', true))
            ->first();

        $fromAddress = $providerSetting?->validSenderAddress();

        $sender = $providerSetting instanceof CompanyEmailProviderSetting
            ? $senders->sender($providerSetting->provider)
            : null;

        if ($this->context instanceof CandidateCommunicationEmailContext
            && ! $this->hasCurrentCandidateCommunicationSnapshot($providerSetting, $sender)) {
            $this->failCandidateCommunication('AuthorizedProviderUnavailable');

            return;
        }

        if (
            ! $providerSetting instanceof CompanyEmailProviderSetting
            || $fromAddress === null
            || ! $sender?->isReady($providerSetting)
        ) {
            if ($this->context instanceof CandidateCommunicationEmailContext) {
                $this->failCandidateCommunication('AuthorizedProviderUnavailable');
            }

            Log::warning('Queued recruitment email was skipped because its tenant provider is no longer available.', [
                'company_id' => $this->companyId,
                'provider_setting_id' => $this->providerSettingId,
                'notification_type' => $this->type?->value,
                'context_key' => $this->context->idempotencyKey(),
            ]);

            return;
        }

        $mail = $this->context instanceof CandidateCommunicationEmailContext
            ? $mailFactory->makeCandidateCommunication($this->context)
            : $mailFactory->make($this->type, $this->context);

        try {
            $sender->send(
                $providerSetting,
                $mail,
                $this->context->recipientEmail(),
                $this->context->companyName(),
                $this->providerIdempotencyKey(),
            );
        } finally {
            $this->synchronizeCommunicationHistory($providerSetting, $mail);
        }

        $delivery = $this->delivery();

        if ($delivery?->status === RecruitmentEmailDeliveryStatus::Delivered) {
            Cache::forever($this->deliveryKey(), true);
        }
    }

    public function uniqueId(): string
    {
        return $this->companyId.':'.($this->type?->value ?? 'candidate_communication').':'.$this->context->idempotencyKey();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Recruitment email delivery failed after all retries.', [
            'company_id' => $this->companyId,
            'provider_setting_id' => $this->providerSettingId,
            'notification_type' => $this->type?->value,
            'context_key' => $this->context->idempotencyKey(),
            'exception_class' => $exception !== null ? $exception::class : null,
        ]);

        RecruitmentEmailDelivery::query()
            ->where('company_id', $this->companyId)
            ->where('idempotency_key', $this->providerIdempotencyKey())
            ->whereNotIn('status', [RecruitmentEmailDeliveryStatus::Delivered->value, RecruitmentEmailDeliveryStatus::Ambiguous->value])
            ->update([
                'status' => RecruitmentEmailDeliveryStatus::Failed->value,
                'last_exception_class' => $exception !== null ? $exception::class : null,
            ]);

        $this->synchronizeCommunicationHistory();
    }

    private function providerIdempotencyKey(): string
    {
        return 'recruiter-labs/'.hash('sha256', $this->uniqueId());
    }

    private function deliveryKey(): string
    {
        return 'recruitment-email-delivered:'.$this->uniqueId();
    }

    private function delivery(): ?RecruitmentEmailDelivery
    {
        return RecruitmentEmailDelivery::query()
            ->where('company_id', $this->companyId)
            ->where('idempotency_key', $this->providerIdempotencyKey())
            ->first();
    }

    private function synchronizeCommunicationHistory(
        ?CompanyEmailProviderSetting $providerSetting = null,
        ?RecruitmentMail $mail = null,
    ): void {
        $delivery = $this->delivery();

        if (! $delivery instanceof RecruitmentEmailDelivery) {
            return;
        }

        DB::transaction(function () use ($delivery, $providerSetting, $mail): void {
            $delivery = RecruitmentEmailDelivery::query()->whereKey($delivery->getKey())->lockForUpdate()->sole();

            if ($this->context instanceof CandidateCommunicationEmailContext) {
                $message = CandidateCommunicationMessage::query()
                    ->whereKey($this->context->messageId)
                    ->where('company_id', $this->companyId)
                    ->where('idempotency_key', $this->context->idempotencyKey())
                    ->lockForUpdate()
                    ->first();

                if ($message instanceof CandidateCommunicationMessage) {
                    $message->forceFill([
                        'delivery_id' => $delivery->getKey(),
                        'status' => $this->messageStatus($delivery->status),
                        'sent_at' => $delivery->delivered_at,
                    ])->save();
                }

                return;
            }

            $context = $this->systemCommunicationContext($mail);

            if ($context === null) {
                return;
            }

            $type = $this->type;

            if (! $type instanceof EmailNotificationType) {
                return;
            }

            [$candidateId, $jobId, $applicationId, $subject, $body] = $context;
            $thread = CandidateCommunicationThread::query()->firstOrCreate([
                'company_id' => $this->companyId,
                'candidate_id' => $candidateId,
                'job_id' => $jobId,
            ], ['application_id' => $applicationId]);

            if ($applicationId !== null && $thread->application_id !== $applicationId) {
                $thread->update(['application_id' => $applicationId]);
            }

            $message = CandidateCommunicationMessage::query()->firstOrCreate(
                ['delivery_id' => $delivery->getKey()],
                [
                    'company_id' => $this->companyId,
                    'thread_id' => $thread->getKey(),
                    'kind' => CandidateCommunicationMessageKind::fromNotificationType($type),
                    'status' => $this->messageStatus($delivery->status),
                    'authorized_subject' => $subject,
                    'authorized_body' => $body,
                    'recipient_email' => $this->context->recipientEmail(),
                    'sender_email' => $providerSetting?->validSenderAddress(),
                    'provider' => $delivery->provider,
                    'idempotency_key' => 'system/'.$delivery->idempotency_key,
                    'send_requested_at' => $delivery->last_attempted_at,
                    'sent_at' => $delivery->delivered_at,
                ],
            );

            $message->forceFill([
                'status' => $this->messageStatus($delivery->status),
                'sent_at' => $delivery->delivered_at,
            ])->save();
        });
    }

    /** @return array{int, int, int, string, string|null}|null */
    private function systemCommunicationContext(?RecruitmentMail $mail): ?array
    {
        if (! $mail instanceof RecruitmentMail || ! $this->type instanceof EmailNotificationType) {
            return null;
        }

        $applicationId = null;

        if ($this->context instanceof StatusEmailContext) {
            $applicationId = $this->context->applicationId;
            $subject = $mail->envelope()->subject;
            $body = $this->context->body;
        } elseif ($this->context instanceof InterviewEmailContext) {
            $interview = Interview::query()->find($this->context->interviewId);
            $applicationId = $interview?->application_id;
            $subject = $mail->envelope()->subject;
            // The existing interview mail owns its rendered content. Do not
            // manufacture a shorter historical body from current records.
            $body = null;
        } else {
            return null;
        }

        $application = $applicationId === null ? null : Application::query()
            ->where('company_id', $this->companyId)
            ->find($applicationId);

        if (! $application instanceof Application || $subject === null) {
            return null;
        }

        return [(int) $application->candidate_id, (int) $application->job_id, (int) $application->getKey(), $subject, $body];
    }

    private function messageStatus(RecruitmentEmailDeliveryStatus $status): CandidateCommunicationMessageStatus
    {
        return match ($status) {
            RecruitmentEmailDeliveryStatus::Pending => CandidateCommunicationMessageStatus::Queued,
            RecruitmentEmailDeliveryStatus::Sending => CandidateCommunicationMessageStatus::Sending,
            RecruitmentEmailDeliveryStatus::Delivered => CandidateCommunicationMessageStatus::Sent,
            RecruitmentEmailDeliveryStatus::Failed => CandidateCommunicationMessageStatus::Failed,
            RecruitmentEmailDeliveryStatus::Ambiguous => CandidateCommunicationMessageStatus::Ambiguous,
        };
    }

    private function hasCurrentCandidateCommunicationSnapshot(?CompanyEmailProviderSetting $providerSetting, mixed $sender): bool
    {
        $message = CandidateCommunicationMessage::query()
            ->whereKey($this->context->messageId)
            ->where('company_id', $this->companyId)
            ->where('idempotency_key', $this->context->idempotencyKey())
            ->first();

        return $message instanceof CandidateCommunicationMessage
            && $message->isAuthorized()
            && (int) $message->provider_setting_id === $this->providerSettingId
            && $message->provider !== null
            && $providerSetting instanceof CompanyEmailProviderSetting
            && $providerSetting->provider === $message->provider
            && $providerSetting->validSenderAddress() === $message->sender_email
            && $sender !== null
            && $sender->isReady($providerSetting);
    }

    private function failCandidateCommunication(string $failure): void
    {
        if (! $this->context instanceof CandidateCommunicationEmailContext) {
            return;
        }

        DB::transaction(function () use ($failure): void {
            $message = CandidateCommunicationMessage::query()
                ->whereKey($this->context->messageId)
                ->where('company_id', $this->companyId)
                ->where('idempotency_key', $this->context->idempotencyKey())
                ->lockForUpdate()
                ->first();

            if (! $message instanceof CandidateCommunicationMessage
                || ! $message->isAuthorized()
                || $message->provider === null
                || in_array($message->status, [CandidateCommunicationMessageStatus::Sent, CandidateCommunicationMessageStatus::Ambiguous], true)) {
                return;
            }

            $delivery = RecruitmentEmailDelivery::query()->firstOrCreate(
                ['idempotency_key' => $this->providerIdempotencyKey()],
                [
                    'company_id' => $this->companyId,
                    'provider_setting_id' => $message->provider_setting_id,
                    'provider' => $message->provider,
                    'status' => RecruitmentEmailDeliveryStatus::Failed,
                    'last_exception_class' => $failure,
                ],
            );

            $delivery = RecruitmentEmailDelivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->sole();

            if (in_array($delivery->status, [RecruitmentEmailDeliveryStatus::Delivered, RecruitmentEmailDeliveryStatus::Ambiguous], true)) {
                return;
            }

            $delivery->forceFill([
                'status' => RecruitmentEmailDeliveryStatus::Failed,
                'last_exception_class' => $failure,
            ])->save();
            $message->forceFill([
                'delivery_id' => $delivery->getKey(),
                'status' => CandidateCommunicationMessageStatus::Failed,
            ])->save();
        });
    }
}
