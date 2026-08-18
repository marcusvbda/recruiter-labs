<?php

namespace App\Models;

use App\Enums\AnalysisConfidence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $application_id
 * @property string $criterion
 * @property int $weight
 * @property int $score
 * @property string $reason
 * @property AnalysisConfidence $confidence
 */
#[Fillable(['company_id', 'application_id', 'criterion', 'weight', 'score', 'reason', 'confidence'])]
class ApplicationCriterionScore extends Model
{
    protected $table = 'application_criterion_scores';

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'score' => 'integer',
            'confidence' => AnalysisConfidence::class,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return HasMany<ApplicationInterviewBriefItem, $this> */
    public function interviewBriefItems(): HasMany
    {
        return $this->hasMany(ApplicationInterviewBriefItem::class);
    }
}
