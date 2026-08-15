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
        int $referralBonusPercentage,
    ): CompanyScoringSetting {
        Gate::forUser($changedBy)->authorize('update', $company);

        if ($referralBonusPercentage < 0 || $referralBonusPercentage > 100) {
            throw new InvalidArgumentException('The referral bonus must be between 0 and 100.');
        }

        return DB::transaction(function () use ($company, $referralBonusPercentage): CompanyScoringSetting {
            $setting = CompanyScoringSetting::query()->firstOrNew(['company_id' => $company->getKey()]);
            $setting->referral_bonus_percentage = $referralBonusPercentage;
            $setting->save();

            $company->setRelation('scoringSetting', $setting);

            return $setting;
        });
    }
}
