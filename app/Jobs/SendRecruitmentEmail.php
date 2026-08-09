<?php

namespace App\Jobs;

use App\Data\RecruitmentEmailContext;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailNotificationType;
use App\Models\CompanyEmailProviderSetting;
use App\Services\NativeRecruitmentMailFactory;
use App\Services\ResendRecruitmentEmailSender;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
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
        public readonly EmailNotificationType $type,
        public readonly RecruitmentEmailContext $context,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        NativeRecruitmentMailFactory $mailFactory,
        ResendRecruitmentEmailSender $sender,
    ): void {
        if (Cache::has($this->deliveryKey())) {
            return;
        }

        $providerSetting = CompanyEmailProviderSetting::query()
            ->whereKey($this->providerSettingId)
            ->where('company_id', $this->companyId)
            ->where('is_default', true)
            ->first();

        $fromAddress = $providerSetting?->validSenderAddress();

        if (! $providerSetting instanceof CompanyEmailProviderSetting
            || blank($providerSetting->api_key)
            || $fromAddress === null
            || $providerSetting->credential_status !== EmailCredentialStatus::Active) {
            Log::warning('Queued recruitment email was skipped because its tenant provider is no longer available.', [
                'company_id' => $this->companyId,
                'provider_setting_id' => $this->providerSettingId,
                'notification_type' => $this->type->value,
                'context_key' => $this->context->idempotencyKey(),
            ]);

            return;
        }

        $sender->send(
            $providerSetting,
            $mailFactory->make($this->type, $this->context),
            $this->context->recipientEmail(),
            $this->context->companyName(),
            $this->providerIdempotencyKey(),
        );

        Cache::forever($this->deliveryKey(), true);
    }

    public function uniqueId(): string
    {
        return $this->companyId.':'.$this->type->value.':'.$this->context->idempotencyKey();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Recruitment email delivery failed after all retries.', [
            'company_id' => $this->companyId,
            'provider_setting_id' => $this->providerSettingId,
            'notification_type' => $this->type->value,
            'context_key' => $this->context->idempotencyKey(),
            'exception' => $exception,
        ]);
    }

    private function providerIdempotencyKey(): string
    {
        return 'recruiter-labs/'.hash('sha256', $this->uniqueId());
    }

    private function deliveryKey(): string
    {
        return 'recruitment-email-delivered:'.$this->uniqueId();
    }
}
