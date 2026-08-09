<?php

namespace App\Actions;

use App\Enums\ConnectedIntegrationStatus;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailProviderConfigurationEventType;
use App\Models\Company;
use App\Models\CompanyAuditLog;
use App\Models\CompanyEmailProviderSetting;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Services\RecruitmentEmailSenderRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SetDefaultCompanyEmailProvider
{
    public function __construct(private RecruitmentEmailSenderRegistry $senders) {}

    public function run(Company $company, User $changedBy, EmailProvider $provider): CompanyEmailProviderSetting
    {
        Gate::forUser($changedBy)->authorize('update', $company);

        return DB::transaction(function () use ($company, $changedBy, $provider): CompanyEmailProviderSetting {
            $setting = CompanyEmailProviderSetting::query()
                ->where('company_id', $company->getKey())
                ->where('provider', $provider->value)
                ->first();

            if ($provider === EmailProvider::Gmail) {
                $integration = ConnectedIntegration::query()
                    ->whereBelongsTo($company)
                    ->whereBelongsTo($changedBy)
                    ->where('plugin_key', 'gmail')
                    ->where('status', ConnectedIntegrationStatus::Connected->value)
                    ->first();

                if ($integration === null) {
                    throw new InvalidArgumentException('Connect your Gmail account before selecting it as the default provider.');
                }

                $setting ??= new CompanyEmailProviderSetting([
                    'company_id' => $company->getKey(),
                    'provider' => EmailProvider::Gmail,
                ]);
                $setting->fill([
                    'connected_integration_id' => $integration->getKey(),
                    'from_address' => $integration->account_email,
                    'credential_status' => EmailCredentialStatus::Active,
                    'validated_at' => now(),
                ])->save();
            }

            if ($setting === null || ! $this->senders->sender($provider)->isReady($setting)) {
                throw new InvalidArgumentException('This email provider must be connected and active before it can be set as default.');
            }

            CompanyEmailProviderSetting::query()
                ->where('company_id', $company->getKey())
                ->where('id', '!=', $setting->getKey())
                ->update(['is_default' => false]);

            $setting->update(['is_default' => true]);

            CompanyAuditLog::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $changedBy->getKey(),
                'event' => EmailProviderConfigurationEventType::DefaultProviderChanged->value,
                'metadata' => ['provider' => $provider->value],
            ]);

            return $setting;
        });
    }
}
