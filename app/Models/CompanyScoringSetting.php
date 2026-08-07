<?php

namespace App\Models;

use App\Enums\ApplicationSource;
use Database\Factories\CompanyScoringSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $company_id
 * @property int $analysis_weight
 * @property int $referral_weight
 */
#[Fillable(['company_id', 'analysis_weight', 'referral_weight'])]
class CompanyScoringSetting extends Model
{
    /** @use HasFactory<CompanyScoringSettingFactory> */
    use HasFactory;

    protected $attributes = [
        'analysis_weight' => 60,
        'referral_weight' => 40,
    ];

    protected function casts(): array
    {
        return [
            'analysis_weight' => 'integer',
            'referral_weight' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Blend the AI fit analysis score with the referral bonus using this company's weights.
     *
     * Returns null when the application's analysis score isn't computed yet.
     */
    public function overallScore(Application $application): ?float
    {
        if ($application->analysis_score === null) {
            return null;
        }

        $aiComponent = (float) $application->analysis_score;
        $referralComponent = $application->source === ApplicationSource::Referral ? 100 : 0;

        return round(
            ($aiComponent * $this->analysis_weight + $referralComponent * $this->referral_weight) / 100,
            2,
        );
    }
}
