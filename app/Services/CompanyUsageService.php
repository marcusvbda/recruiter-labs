<?php

namespace App\Services;

use App\Data\CompanyUsageSummaryData;
use App\Data\UsageMetricData;
use App\Enums\Limit;
use App\Enums\UsageWarningState;
use App\Models\AiUsageRecord;
use App\Models\Company;
use App\Models\Job;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class CompanyUsageService
{
    public function summary(Company $company): CompanyUsageSummaryData
    {
        $company->loadMissing('plan');
        $cycleStart = CarbonImmutable::instance(now())->startOfMonth();
        $cycleEnd = $cycleStart->endOfMonth();

        $aiUsageQuery = $company->aiUsageRecords()
            ->whereBetween('created_at', [$cycleStart, $cycleEnd]);
        $aiAnalyses = (clone $aiUsageQuery)->count();
        $ownAiAnalyses = (clone $aiUsageQuery)->where('used_own_key', true)->count();

        $metrics = [
            Limit::Users->value => $this->usageFor($company, Limit::Users),
            Limit::Jobs->value => $this->usageFor($company, Limit::Jobs),
            Limit::Applications->value => $this->usageFor($company, Limit::Applications),
            Limit::AiAnalyses->value => $this->makeMetric($company, Limit::AiAnalyses, $aiAnalyses, $cycleStart, $cycleEnd),
        ];

        return new CompanyUsageSummaryData(
            planName: $company->plan->name,
            planSlug: $company->plan->slug,
            metrics: $metrics,
            platformAiAnalyses: $aiAnalyses - $ownAiAnalyses,
            ownAiAnalyses: $ownAiAnalyses,
            cycleStart: $cycleStart,
            cycleEnd: $cycleEnd,
        );
    }

    public function usageFor(Company $company, Limit $limit): UsageMetricData
    {
        $company->loadMissing('plan');
        $cycleStart = CarbonImmutable::instance(now())->startOfMonth();
        $cycleEnd = $cycleStart->endOfMonth();

        $used = match ($limit) {
            Limit::Users => $company->users()->count(),
            Limit::Jobs => $company->jobs()->currentlyActive()->count(),
            Limit::Applications => $company->applications()
                ->whereBetween('created_at', [$cycleStart, $cycleEnd])
                ->count(),
            Limit::AiAnalyses => $company->aiUsageRecords()
                ->whereBetween('created_at', [$cycleStart, $cycleEnd])
                ->count(),
        };

        $isMonthly = in_array($limit, [Limit::Applications, Limit::AiAnalyses], strict: true);

        return $this->makeMetric(
            $company,
            $limit,
            $used,
            $isMonthly ? $cycleStart : null,
            $isMonthly ? $cycleEnd : null,
        );
    }

    /** @return Collection<int, AiUsageRecord> */
    public function recentAiUsage(Company $company, int $limit = 10): Collection
    {
        return $company->aiUsageRecords()
            ->with('user:id,name')
            ->latest()
            ->limit(max(1, min($limit, 50)))
            ->get();
    }

    public function activeJobUsageExcluding(Company $company, ?Job $job = null): UsageMetricData
    {
        $company->loadMissing('plan');
        $query = $company->jobs()->currentlyActive();

        if ($job?->exists) {
            $query->whereKeyNot($job->getKey());
        }

        return $this->makeMetric($company, Limit::Jobs, $query->count());
    }

    private function makeMetric(
        Company $company,
        Limit $limit,
        int $used,
        ?CarbonImmutable $cycleStart = null,
        ?CarbonImmutable $cycleEnd = null,
    ): UsageMetricData {
        $limitValue = $company->getLimit($limit);
        $isUnlimited = $limitValue === null;
        $percentage = $isUnlimited || $limitValue === 0
            ? 0
            : (int) round(($used / $limitValue) * 100);
        $isReached = ! $isUnlimited && $used >= $limitValue;

        return new UsageMetricData(
            limit: $limit,
            used: $used,
            limitValue: $limitValue,
            remaining: $isUnlimited ? null : max(0, $limitValue - $used),
            percentage: $percentage,
            isUnlimited: $isUnlimited,
            isReached: $isReached,
            isOverLimit: ! $isUnlimited && $used > $limitValue,
            warningState: $this->warningState($percentage, $isReached),
            cycleStart: $cycleStart,
            cycleEnd: $cycleEnd,
        );
    }

    private function warningState(int $percentage, bool $isReached): UsageWarningState
    {
        if ($isReached) {
            return UsageWarningState::Reached;
        }

        if ($percentage >= 90) {
            return UsageWarningState::Critical;
        }

        if ($percentage >= 80) {
            return UsageWarningState::Attention;
        }

        return UsageWarningState::Normal;
    }
}
