<?php

namespace App\Services;

use App\Data\CandidateCommunicationEmailContext;
use App\Data\RecruitmentEmailContext;
use App\Enums\CandidateCommunicationMessageStatus;
use App\Enums\EmailNotificationType;
use App\Enums\RecruitmentEmailDeliveryStatus;
use App\Jobs\SendRecruitmentEmail;
use App\Models\CandidateCommunicationMessage;
use App\Models\Company;
use App\Models\CompanyEmailNotificationSetting;
use App\Models\CompanyEmailProviderSetting;
use App\Models\RecruitmentEmailDelivery;
use Illuminate\Support\Facades\DB;
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

    /**
     * Queue an already-authorized communication using its trusted delivery
     * snapshot. Unlike automated notifications, this must not be redirected
     * to whatever provider happens to be the current workspace default.
     */
    public function dispatchCandidateCommunication(CandidateCommunicationMessage $message): bool
    {
        if (! $message->isAuthorized()
            || $message->provider_setting_id === null
            || $message->provider === null
            || ! is_string($message->recipient_email)
            || ! is_string($message->sender_email)
            || ! is_string($message->authorized_subject)
            || ! is_string($message->authorized_body)
            || ! is_string($message->idempotency_key)
            || filter_var($message->recipient_email, FILTER_VALIDATE_EMAIL) === false) {
            $this->failCandidateCommunication($message, 'InvalidAuthorizedCommunicationSnapshot');

            return false;
        }

        $providerSetting = CompanyEmailProviderSetting::query()
            ->whereKey($message->provider_setting_id)
            ->where('company_id', $message->company_id)
            ->where('provider', $message->provider->value)
            ->first();

        // The snapshot is a safety boundary: never silently send under a
        // different identity after a provider setting has been edited.
        if (! $providerSetting instanceof CompanyEmailProviderSetting
            || $providerSetting->validSenderAddress() !== $message->sender_email
            || ! $this->senders->sender($message->provider)->isReady($providerSetting)) {
            $this->failCandidateCommunication($message, 'AuthorizedProviderUnavailable');

            return false;
        }

        $company = $message->company;

        if (! $company instanceof Company) {
            $this->failCandidateCommunication($message, 'AuthorizedCompanyUnavailable');

            return false;
        }

        SendRecruitmentEmail::dispatch(
            (int) $message->company_id,
            (int) $providerSetting->getKey(),
            null,
            new CandidateCommunicationEmailContext(
                messageId: (int) $message->getKey(),
                recipient: $message->recipient_email,
                company: $company->name,
                subject: $message->authorized_subject,
                body: $message->authorized_body,
                key: $message->idempotency_key,
            ),
        )->onQueue(SendRecruitmentEmail::QUEUE)->afterCommit();

        return true;
    }

    private function failCandidateCommunication(CandidateCommunicationMessage $message, string $failure): void
    {
        if (! $message->isAuthorized() || ! is_string($message->idempotency_key) || $message->provider === null) {
            return;
        }

        DB::transaction(function () use ($message, $failure): void {
            $message = CandidateCommunicationMessage::query()
                ->whereKey($message->getKey())
                ->where('company_id', $message->company_id)
                ->lockForUpdate()
                ->first();

            if (! $message instanceof CandidateCommunicationMessage
                || ! $message->isAuthorized()
                || ! is_string($message->idempotency_key)
                || $message->provider === null
                || in_array($message->status, [CandidateCommunicationMessageStatus::Sent, CandidateCommunicationMessageStatus::Ambiguous], true)) {
                return;
            }

            $delivery = RecruitmentEmailDelivery::query()->firstOrCreate(
                ['idempotency_key' => 'recruiter-labs/'.hash('sha256', $message->company_id.':candidate_communication:'.$message->idempotency_key)],
                [
                    'company_id' => $message->company_id,
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
