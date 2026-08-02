<?php

namespace App\Data;

use App\Enums\Limit;
use Carbon\CarbonImmutable;

class CompanyUsageSummaryData
{
    /** @param array<string, UsageMetricData> $metrics */
    public function __construct(
        public readonly string $planName,
        public readonly string $planSlug,
        public readonly array $metrics,
        public readonly int $platformAiAnalyses,
        public readonly int $ownAiAnalyses,
        public readonly CarbonImmutable $cycleStart,
        public readonly CarbonImmutable $cycleEnd,
    ) {}

    public function metric(Limit $limit): UsageMetricData
    {
        return $this->metrics[$limit->value];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'plan_name' => $this->planName,
            'plan_slug' => $this->planSlug,
            'metrics' => array_map(
                fn (UsageMetricData $metric): array => $metric->toArray(),
                $this->metrics,
            ),
            'platform_ai_analyses' => $this->platformAiAnalyses,
            'own_ai_analyses' => $this->ownAiAnalyses,
            'cycle_start' => $this->cycleStart->toDateString(),
            'cycle_end' => $this->cycleEnd->toDateString(),
        ];
    }
}
