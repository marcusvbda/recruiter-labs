<?php

namespace App\Models;

use App\Enums\AnalysisConfidence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One criterion's result inside a candidate evaluation.
 *
 * `criterion` and `weight` are a snapshot of the {@see JobCriterion} that
 * produced this row, resolved by ID at persistence time — never by matching the
 * text the model echoed back, and never with a fallback weight.
 *
 * @property int $id
 * @property int $company_id
 * @property int $application_id
 * @property string $criterion
 * @property int $weight
 * @property int|null $score
 * @property string $reason
 * @property list<array{source: string, detail: string}>|null $evidence
 * @property AnalysisConfidence $confidence
 */
#[Fillable(['company_id', 'application_id', 'criterion', 'weight', 'score', 'reason', 'evidence', 'confidence'])]
class ApplicationCriterionScore extends Model
{
    protected $table = 'application_criterion_scores';

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'score' => 'integer',
            'evidence' => 'array',
            'confidence' => AnalysisConfidence::class,
        ];
    }

    /**
     * Whether the supplied application contained enough information to judge
     * fit for this criterion at all. An unassessed criterion is uncertainty:
     * it lowers evidence coverage and stays out of the fit calculation
     * entirely, rather than being scored as if the candidate had failed it.
     */
    public function isAssessed(): bool
    {
        return $this->score !== null;
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
