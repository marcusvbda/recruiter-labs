<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\CompanyScoringSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class UpdateCompanyScoringSettings
{
    public function run(
        Company $company,
        User $changedBy,
        int $analysisWeight,
        int $referralWeight,
    ): CompanyScoringSetting {
        Gate::forUser($changedBy)->authorize('update', $company);

        if ($analysisWeight < 0 || $analysisWeight > 100 || $referralWeight < 0 || $referralWeight > 100) {
            throw new InvalidArgumentException('Both weights must be between 0 and 100.');
        }

        if ($analysisWeight + $referralWeight !== 100) {
            throw new InvalidArgumentException('The fit evaluation and referral weights must sum to exactly 100.');
        }

        return DB::transaction(function () use ($company, $analysisWeight, $referralWeight): CompanyScoringSetting {
            $setting = CompanyScoringSetting::query()->firstOrNew(['company_id' => $company->getKey()]);
            $setting->analysis_weight = $analysisWeight;
            $setting->referral_weight = $referralWeight;
            $setting->save();

            $company->setRelation('scoringSetting', $setting);

            return $setting;
        });
    }
}
