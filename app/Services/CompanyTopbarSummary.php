<?php

namespace App\Services;

use App\Data\CompanyTopbarSummaryData;
use App\Enums\Limit;
use App\Models\Company;

class CompanyTopbarSummary
{
    /** @var array<int, CompanyTopbarSummaryData> */
    private array $resolved = [];

    public function __construct(
        private readonly CompanyUsageService $usageService,
        private readonly AiCredentialsResolver $credentialsResolver,
    ) {}

    public function for(Company $company): CompanyTopbarSummaryData
    {
        return $this->resolved[$company->getKey()] ??= $this->resolve($company);
    }

    public function forget(Company $company): void
    {
        unset($this->resolved[$company->getKey()]);
    }

    private function resolve(Company $company): CompanyTopbarSummaryData
    {
        $company->loadMissing('plan');
        $aiUsage = $this->usageService->usageFor($company, Limit::AiAnalyses);
        $provider = $this->credentialsResolver->resolve($company);
        $limits = [];

        foreach (Limit::cases() as $limit) {
            $limits[$limit->value] = $company->getLimit($limit);
        }

        return new CompanyTopbarSummaryData(
            planName: $company->plan->name,
            planSlug: $company->plan->slug,
            planLimits: $limits,
            aiUsage: $aiUsage,
            provider: $provider->provider,
            model: $provider->model,
        );
    }
}
