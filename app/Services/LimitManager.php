<?php

namespace App\Services;

use App\Data\UsageMetricData;
use App\Enums\Limit;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Company;
use App\Models\Job;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use LogicException;

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

    public function ensureCanCreateJob(Company $company): void
    {
        $this->ensureWithinLimit($company, Limit::Jobs);
    }

    /** @param array<string, mixed> $attributes */
    public function ensureCanSaveJob(Company $company, array $attributes, ?Job $job = null): void
    {
        if ($job !== null && $job->company_id !== $company->getKey()) {
            throw new LogicException('The job does not belong to the selected company.');
        }

        if (! $this->wouldBeCurrentlyActive($attributes, $job)) {
            return;
        }

        $metric = $this->usageService->activeJobUsageExcluding($company, $job);

        if ($metric->isReached) {
            throw new PlanLimitExceededException($metric);
        }
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

    /** @param array<string, mixed> $attributes */
    private function wouldBeCurrentlyActive(array $attributes, ?Job $job): bool
    {
        $published = Arr::exists($attributes, 'published')
            ? (bool) Arr::get($attributes, 'published')
            : (bool) ($job === null ? false : $job->published);
        $startsAt = Arr::exists($attributes, 'starts_at')
            ? Arr::get($attributes, 'starts_at')
            : ($job === null ? null : $job->starts_at);
        $endsAt = Arr::exists($attributes, 'ends_at')
            ? Arr::get($attributes, 'ends_at')
            : ($job === null ? null : $job->ends_at);
        $today = CarbonImmutable::instance(today())->startOfDay();

        return $published
            && ($startsAt === null || CarbonImmutable::parse($startsAt)->startOfDay()->lessThanOrEqualTo($today))
            && ($endsAt === null || CarbonImmutable::parse($endsAt)->startOfDay()->greaterThanOrEqualTo($today));
    }
}
