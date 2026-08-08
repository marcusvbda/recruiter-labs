<?php

namespace App\Actions;

use App\Enums\EmailProvider;
use App\Enums\EmailProviderConfigurationEventType;
use App\Models\Company;
use App\Models\CompanyAuditLog;
use App\Models\CompanyEmailProviderSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SetDefaultCompanyEmailProvider
{
    public function run(Company $company, User $changedBy, EmailProvider $provider): CompanyEmailProviderSetting
    {
        Gate::forUser($changedBy)->authorize('update', $company);

        return DB::transaction(function () use ($company, $changedBy, $provider): CompanyEmailProviderSetting {
            $setting = CompanyEmailProviderSetting::query()
                ->where('company_id', $company->getKey())
                ->where('provider', $provider->value)
                ->first();

            if ($setting === null || blank($setting->api_key)) {
                throw new InvalidArgumentException('This provider must be configured with a valid API key before it can be set as default.');
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
