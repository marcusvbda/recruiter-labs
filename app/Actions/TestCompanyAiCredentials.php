<?php

namespace App\Actions;

use App\Data\AiCredentialTestResultData;
use App\Enums\AiConfigurationEventType;
use App\Enums\AiCredentialStatus;
use App\Enums\Feature;
use App\Exceptions\PlanFeatureUnavailableException;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\CompanyAuditLog;
use App\Models\User;
use App\Services\OpenAiCredentialTester;
use App\Services\CompanyTopbarSummary;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class TestCompanyAiCredentials
{
    public function __construct(
        private readonly OpenAiCredentialTester $tester,
        private readonly CompanyTopbarSummary $topbarSummary,
    ) {}

    public function run(Company $company, User $changedBy): AiCredentialTestResultData
    {
        Gate::forUser($changedBy)->authorize('update', $company);
        $company->loadMissing('plan');

        if (! $company->hasFeature(Feature::OwnAiKey)) {
            throw new PlanFeatureUnavailableException(Feature::OwnAiKey);
        }

        $setting = CompanyAiSetting::query()->whereBelongsTo($company)->first();

        if ($setting === null || blank($setting->openai_api_key)) {
            throw new InvalidArgumentException('No OpenAI API key is configured for this company.');
        }

        $result = $this->tester->test($setting->openai_api_key, $setting->model);
        $setting->update([
            'credential_status' => $result->status,
            'validated_at' => now(),
        ]);
        $company->setRelation('aiSetting', $setting);

        CompanyAuditLog::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $changedBy->getKey(),
            'event' => $result->success
                ? AiConfigurationEventType::CredentialTestSucceeded
                : AiConfigurationEventType::CredentialTestFailed,
            'metadata' => ['model' => $setting->model],
        ]);

        $this->topbarSummary->forget($company);

        return $result;
    }
}
