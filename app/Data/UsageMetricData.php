<?php

namespace App\Data;

use App\Enums\Limit;
use App\Enums\UsageWarningState;
use Carbon\CarbonImmutable;

class UsageMetricData
{
    public function __construct(
        public readonly Limit $limit,
        public readonly int $used,
        public readonly ?int $limitValue,
        public readonly ?int $remaining,
        public readonly int $percentage,
        public readonly bool $isUnlimited,
        public readonly bool $isReached,
        public readonly bool $isOverLimit,
        public readonly UsageWarningState $warningState,
        public readonly ?CarbonImmutable $cycleStart = null,
        public readonly ?CarbonImmutable $cycleEnd = null,
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'key' => $this->limit->value,
            'used' => $this->used,
            'limit' => $this->limitValue,
            'remaining' => $this->remaining,
            'percentage' => $this->percentage,
            'is_unlimited' => $this->isUnlimited,
            'is_reached' => $this->isReached,
            'is_over_limit' => $this->isOverLimit,
            'warning_state' => $this->warningState->value,
            'cycle_start' => $this->cycleStart?->toDateString(),
            'cycle_end' => $this->cycleEnd?->toDateString(),
        ];
    }
}
