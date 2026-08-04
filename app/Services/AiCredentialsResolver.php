<?php

namespace App\Services;

use App\Data\AiProviderConfigurationData;
use App\Enums\AiProvider;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use Laravel\Ai\Ai;

class AiCredentialsResolver
{
    /**
     * Register a temporary, company-scoped AI provider for the given configuration and
     * return its name, so an agent can be prompted with the company's own credentials.
     *
     * Returns null when the platform credentials should be used (the agent's own
     * configured provider / model attributes apply in that case).
     */
    public function registerRuntimeProvider(Company $company, AiProviderConfigurationData $configuration): ?string
    {
        if (! $configuration->usesOwnKey || blank($configuration->apiKey())) {
            return null;
        }

        $name = $this->runtimeProviderName($company);

        config(["ai.providers.{$name}" => [
            'driver' => 'openai',
            'key' => $configuration->apiKey(),
            'url' => config('ai.providers.openai.url', 'https://api.openai.com/v1'),
        ]]);

        // Long-lived workers (queue, Octane) cache provider instances by name, so
        // force a fresh instance in case a stale key was previously registered.
        Ai::purge($name);

        return $name;
    }

    /**
     * Forget a company's temporary runtime provider once the agent call has finished,
     * so its decrypted API key does not linger in the process's config/cache.
     */
    public function forgetRuntimeProvider(Company $company): void
    {
        $name = $this->runtimeProviderName($company);

        Ai::purge($name);
        config(["ai.providers.{$name}" => null]);
    }

    private function runtimeProviderName(Company $company): string
    {
        return 'byok:'.$company->getKey();
    }

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
