<?php

namespace App\Services;

use App\Data\RecruitmentEmailContext;
use App\Enums\EmailNotificationType;
use App\Jobs\SendRecruitmentEmail;
use App\Models\Company;
use App\Models\CompanyEmailNotificationSetting;
use App\Models\CompanyEmailProviderSetting;
use Illuminate\Support\Facades\Log;

class RecruitmentEmailDispatcher
{
    public function __construct(private RecruitmentEmailSenderRegistry $senders) {}

    public function dispatch(
        Company $company,
        EmailNotificationType $type,
        RecruitmentEmailContext $context,
    ): bool {
        if (! $this->isEnabled($company, $type)) {
            return false;
        }

        if (filter_var($context->recipientEmail(), FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('Recruitment email was not queued because the recipient address is invalid.', [
                'company_id' => $company->getKey(),
                'notification_type' => $type->value,
                'context_key' => $context->idempotencyKey(),
            ]);

            return false;
        }

        $providerSetting = CompanyEmailProviderSetting::query()
            ->whereBelongsTo($company)
            ->where('is_default', true)
            ->first();

        if (! $providerSetting instanceof CompanyEmailProviderSetting
            || ! $this->senders->sender($providerSetting->provider)->isReady($providerSetting)) {
            Log::notice('Recruitment email was not queued because the company has no fully configured default email provider.', [
                'company_id' => $company->getKey(),
                'notification_type' => $type->value,
                'context_key' => $context->idempotencyKey(),
            ]);

            return false;
        }

        SendRecruitmentEmail::dispatch(
            (int) $company->getKey(),
            (int) $providerSetting->getKey(),
            $type,
            $context,
        )->onQueue(SendRecruitmentEmail::QUEUE)->afterCommit();

        return true;
    }

    private function isEnabled(Company $company, EmailNotificationType $type): bool
    {
        $override = CompanyEmailNotificationSetting::query()
            ->whereBelongsTo($company)
            ->where('notification_type', $type->value)
            ->first();

        if (! $override instanceof CompanyEmailNotificationSetting) {
            return true;
        }

        return $override->enabled;
    }
}
