<?php

namespace App\Data;

use App\Enums\Feature;

class PlanComparisonData
{
    /**
     * @param list<PlanLimitChangeData> $limitChanges
     * @param list<Feature> $addedFeatures
     * @param list<Feature> $removedFeatures
     */
    public function __construct(
        public readonly string $direction,
        public readonly array $limitChanges,
        public readonly array $addedFeatures,
        public readonly array $removedFeatures,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction,
            'limit_changes' => array_map(
                fn (PlanLimitChangeData $change): array => $change->toArray(),
                $this->limitChanges,
            ),
            'added_features' => array_map(fn (Feature $feature): string => $feature->value, $this->addedFeatures),
            'removed_features' => array_map(fn (Feature $feature): string => $feature->value, $this->removedFeatures),
        ];
    }
}
