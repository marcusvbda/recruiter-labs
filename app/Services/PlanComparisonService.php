<?php

namespace App\Services;

use App\Data\PlanComparisonData;
use App\Data\PlanLimitChangeData;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Models\Company;
use App\Models\Plan;

class PlanComparisonService
{
    public function compare(Company $company, Plan $newPlan): PlanComparisonData
    {
        $company->loadMissing('plan');
        $direction = match ($newPlan->sort_order <=> $company->plan->sort_order) {
            1 => 'upgrade',
            -1 => 'downgrade',
            default => 'current',
        };

        $limitChanges = array_map(
            fn (Limit $limit): PlanLimitChangeData => new PlanLimitChangeData(
                limit: $limit,
                from: $company->plan->getLimit($limit),
                to: $newPlan->getLimit($limit),
            ),
            Limit::cases(),
        );
        $currentFeatures = $company->plan->features ?? [];
        $newFeatures = $newPlan->features ?? [];

        return new PlanComparisonData(
            direction: $direction,
            limitChanges: $limitChanges,
            addedFeatures: $this->featuresFromValues(array_values(array_diff($newFeatures, $currentFeatures))),
            removedFeatures: $this->featuresFromValues(array_values(array_diff($currentFeatures, $newFeatures))),
        );
    }

    /**
     * @param  list<string>  $values
     * @return list<Feature>
     */
    private function featuresFromValues(array $values): array
    {
        return array_map(fn (string $value): Feature => Feature::from($value), $values);
    }
}
