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
 * @property int $referral_bonus_percentage
 */
#[Fillable(['company_id', 'referral_bonus_percentage'])]
class CompanyScoringSetting extends Model
{
    /** @use HasFactory<CompanyScoringSettingFactory> */
    use HasFactory;

    protected $attributes = [
        'referral_bonus_percentage' => 40,
    ];

    protected function casts(): array
    {
        return [
            'referral_bonus_percentage' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The AI fit analysis is the score. A referral does not dilute it: it adds a
     * percentage on top of it, capped at 100 — so an 80 with a 40% bonus reads as
     * 100, and a referral can never drag a strong candidate down.
     *
     * Returns null when the application's analysis score isn't computed yet.
     */
    public function overallScore(Application $application): ?float
    {
        if ($application->analysis_score === null) {
            return null;
        }

        $score = (float) $application->analysis_score;

        if ($application->source === ApplicationSource::Referral) {
            $score *= 1 + ($this->referral_bonus_percentage / 100);
        }

        return round(min($score, 100), 2);
    }
}
