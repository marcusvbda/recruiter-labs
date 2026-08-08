<?php

namespace App\Actions;

use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Enums\EmailProviderConfigurationEventType;
use App\Models\Company;
use App\Models\CompanyAuditLog;
use App\Models\CompanyEmailProviderSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveCompanyEmailProviderCredentials
{
    public function run(Company $company, User $changedBy, EmailProvider $provider): CompanyEmailProviderSetting
    {
        Gate::forUser($changedBy)->authorize('update', $company);

        return DB::transaction(function () use ($company, $changedBy, $provider): CompanyEmailProviderSetting {
            $setting = CompanyEmailProviderSetting::query()->firstOrCreate([
                'company_id' => $company->getKey(),
                'provider' => $provider->value,
            ]);
            $hadKey = filled($setting->api_key);

            $setting->update([
                'api_key' => null,
                'credential_status' => EmailCredentialStatus::NotConfigured,
                'validated_at' => null,
            ]);

            if ($hadKey) {
                CompanyAuditLog::query()->create([
                    'company_id' => $company->getKey(),
                    'user_id' => $changedBy->getKey(),
                    'event' => EmailProviderConfigurationEventType::CredentialRemoved->value,
                    'metadata' => ['provider' => $provider->value],
                ]);
            }

            return $setting;
        });
    }
}
