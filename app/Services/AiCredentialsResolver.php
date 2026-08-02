<?php

namespace App\Services;

use App\Data\AiProviderConfigurationData;
use App\Enums\AiProvider;
use App\Models\Company;
use App\Models\CompanyAiSetting;

class AiCredentialsResolver
{
    public function resolve(Company $company): AiProviderConfigurationData
    {
        $company->loadMissing(['plan', 'aiSetting']);
        $relatedSetting = $company->getRelation('aiSetting');
        $setting = $relatedSetting instanceof CompanyAiSetting ? $relatedSetting : null;

        if ($setting === null) {
            return $this->platformConfiguration();
        }

        if ($setting->canUseOwnKey($company)) {
            return new AiProviderConfigurationData(
                provider: AiProvider::Own,
                apiKey: $setting->openai_api_key,
                model: $setting->model,
                usesOwnKey: true,
                isConfigured: true,
            );
        }

        return $this->platformConfiguration($setting->model);
    }

    private function platformConfiguration(?string $model = null): AiProviderConfigurationData
    {
        $platformKey = config('services.openai.api_key');

        return new AiProviderConfigurationData(
            provider: AiProvider::Platform,
            apiKey: is_string($platformKey) && filled($platformKey) ? $platformKey : null,
            model: $model ?? (string) config('services.openai.model', 'gpt-4o-mini'),
            usesOwnKey: false,
            isConfigured: is_string($platformKey) && filled($platformKey),
        );
    }
}
