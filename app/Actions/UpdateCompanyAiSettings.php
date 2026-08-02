<?php

namespace App\Actions;

use App\Enums\AiConfigurationEventType;
use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Enums\Feature;
use App\Exceptions\PlanFeatureUnavailableException;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\CompanyAuditLog;
use App\Models\User;
use App\Services\CompanyTopbarSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateCompanyAiSettings
{
    public function __construct(private readonly CompanyTopbarSummary $topbarSummary) {}

    public function run(
        Company $company,
        User $changedBy,
        AiProvider $provider,
        string $model,
        ?string $apiKey = null,
    ): CompanyAiSetting {
        Gate::forUser($changedBy)->authorize('update', $company);
        $company->loadMissing('plan');

        if ($provider === AiProvider::Own && ! $company->hasFeature(Feature::OwnAiKey)) {
            throw new PlanFeatureUnavailableException(Feature::OwnAiKey);
        }

        $model = (string) Str::of($model)->trim();

        if (blank($model) || Str::length($model) > 100) {
            throw new InvalidArgumentException('The AI model must contain between 1 and 100 characters.');
        }

        if ($apiKey !== null && (blank($apiKey) || Str::length($apiKey) > 512)) {
            throw new InvalidArgumentException('The OpenAI API key is invalid.');
        }

        $setting = DB::transaction(function () use ($company, $changedBy, $provider, $model, $apiKey): CompanyAiSetting {
            $setting = CompanyAiSetting::query()->firstOrNew(['company_id' => $company->getKey()]);
            $previousProvider = $setting->provider;
            $hadKey = filled($setting->openai_api_key);

            if ($provider === AiProvider::Own && $apiKey === null && ! $hadKey) {
                throw new InvalidArgumentException('An OpenAI API key is required for the selected provider.');
            }

            $setting->provider = $provider;
            $setting->model = $model;

            if ($apiKey !== null) {
                $setting->openai_api_key = $apiKey;
                $setting->credential_status = AiCredentialStatus::PendingValidation;
                $setting->validated_at = null;
            }

            $setting->save();
            $company->setRelation('aiSetting', $setting);

            if ($previousProvider !== $provider) {
                $this->audit($company, $changedBy, AiConfigurationEventType::ProviderChanged, [
                    'from' => $previousProvider->value,
                    'to' => $provider->value,
                ]);
            }

            if ($apiKey !== null) {
                $this->audit(
                    $company,
                    $changedBy,
                    $hadKey ? AiConfigurationEventType::CredentialReplaced : AiConfigurationEventType::CredentialAdded,
                );
            }

            return $setting;
        });

        $this->topbarSummary->forget($company);

        return $setting;
    }

    /** @param array<string, mixed> $metadata */
    private function audit(
        Company $company,
        User $user,
        AiConfigurationEventType $event,
        array $metadata = [],
    ): void {
        CompanyAuditLog::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
