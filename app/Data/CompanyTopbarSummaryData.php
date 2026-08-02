<?php

namespace App\Data;

use App\Enums\AiProvider;

class CompanyTopbarSummaryData
{
    /** @param array<string, int|null> $planLimits */
    public function __construct(
        public readonly string $planName,
        public readonly string $planSlug,
        public readonly array $planLimits,
        public readonly UsageMetricData $aiUsage,
        public readonly AiProvider $provider,
        public readonly string $model,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'plan_name' => $this->planName,
            'plan_slug' => $this->planSlug,
            'plan_limits' => $this->planLimits,
            'ai_usage' => $this->aiUsage->toArray(),
            'provider' => $this->provider->value,
            'model' => $this->model,
        ];
    }
}
