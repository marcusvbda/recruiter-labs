<?php

namespace App\Services;

use App\Data\UsageMetricData;
use App\Enums\Limit;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Company;

class LimitManager
{
    public function __construct(private readonly CompanyUsageService $usageService) {}

    public function usage(Company $company, Limit $limit): UsageMetricData
    {
        return $this->usageService->usageFor($company, $limit);
    }

    public function ensureCanCreateUser(Company $company): void
    {
        $this->ensureWithinLimit($company, Limit::Users);
    }

    /**
     * The plan's job allowance counts every registered job, published or not.
     * Only creating a job consumes a slot — editing or publishing an existing
     * one never does, since the row already occupies its share.
     */
    public function ensureCanCreateJob(Company $company): void
    {
        $this->ensureWithinLimit($company, Limit::Jobs);
    }

    public function ensureCanReceiveApplication(Company $company): void
    {
        $this->ensureWithinLimit($company, Limit::Applications);
    }

    public function ensureCanRunAiAnalysis(Company $company): void
    {
        $this->ensureWithinLimit($company, Limit::AiAnalyses);
    }

    private function ensureWithinLimit(Company $company, Limit $limit): void
    {
        $metric = $this->usage($company, $limit);

        if ($metric->isReached) {
            throw new PlanLimitExceededException($metric);
        }
    }
}
