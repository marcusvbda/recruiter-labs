<?php

namespace App\Actions;

use App\Enums\PlanChangeSource;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanChange;
use App\Models\User;
use App\Services\CompanyTopbarSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ChangeCompanyPlan
{
    public function __construct(private readonly CompanyTopbarSummary $topbarSummary) {}

    /** @param array<string, mixed> $metadata */
    public function run(
        Company $company,
        Plan $newPlan,
        ?User $changedBy,
        PlanChangeSource $source = PlanChangeSource::ManualSettings,
        array $metadata = [],
    ): PlanChange {
        if ($changedBy !== null) {
            Gate::forUser($changedBy)->authorize('update', $company);
        } elseif (in_array($source, [PlanChangeSource::ManualSettings, PlanChangeSource::Admin], strict: true)) {
            throw new InvalidArgumentException('This plan change source requires a responsible user.');
        }

        $change = DB::transaction(function () use ($company, $newPlan, $changedBy, $source, $metadata): PlanChange {
            $lockedCompany = Company::query()
                ->with('plan')
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCompany->plan_id === $newPlan->getKey()) {
                throw new InvalidArgumentException('The company already uses this plan.');
            }

            $change = PlanChange::query()->create([
                'company_id' => $lockedCompany->getKey(),
                'previous_plan_id' => $lockedCompany->plan_id,
                'new_plan_id' => $newPlan->getKey(),
                'changed_by_id' => $changedBy?->getKey(),
                'source' => $source,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            $lockedCompany->update(['plan_id' => $newPlan->getKey()]);
            $company->setAttribute('plan_id', $newPlan->getKey())->setRelation('plan', $newPlan);

            return $change;
        });

        $this->topbarSummary->forget($company);

        return $change;
    }
}
