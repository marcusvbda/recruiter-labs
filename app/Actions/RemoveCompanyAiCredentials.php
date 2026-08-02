<?php

namespace App\Actions;

use App\Enums\AiConfigurationEventType;
use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\CompanyAuditLog;
use App\Models\User;
use App\Services\CompanyTopbarSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveCompanyAiCredentials
{
    public function __construct(private readonly CompanyTopbarSummary $topbarSummary) {}

    public function run(Company $company, User $changedBy): CompanyAiSetting
    {
        Gate::forUser($changedBy)->authorize('update', $company);

        $setting = DB::transaction(function () use ($company, $changedBy): CompanyAiSetting {
            $setting = CompanyAiSetting::query()->firstOrCreate(['company_id' => $company->getKey()]);
            $hadKey = filled($setting->openai_api_key);

            $setting->update([
                'provider' => AiProvider::Platform,
                'openai_api_key' => null,
                'credential_status' => AiCredentialStatus::NotConfigured,
                'validated_at' => null,
            ]);
            $company->setRelation('aiSetting', $setting);

            if ($hadKey) {
                CompanyAuditLog::query()->create([
                    'company_id' => $company->getKey(),
                    'user_id' => $changedBy->getKey(),
                    'event' => AiConfigurationEventType::CredentialRemoved,
                ]);
            }

            return $setting;
        });

        $this->topbarSummary->forget($company);

        return $setting;
    }
}
