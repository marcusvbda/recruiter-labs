<?php

namespace App\Models;

use App\Enums\AnalysisConfidence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One criterion's result inside a sourcing match.
 *
 * `criterion` and `weight` are a snapshot of the {@see JobCriterion} that
 * produced this row, resolved by ID at persistence time — never by matching the
 * text the model echoed back, and never with a fallback weight.
 *
 * @property int $id
 * @property int $company_id
 * @property int $sourcing_match_id
 * @property string $criterion
 * @property int $weight
 * @property int|null $score
 * @property string $reason
 * @property list<array{source: string, detail: string, submitted_at: string|null}>|null $evidence
 * @property AnalysisConfidence $confidence
 */
#[Fillable(['company_id', 'sourcing_match_id', 'criterion', 'weight', 'score', 'reason', 'evidence', 'confidence'])]
class SourcingMatchCriterionScore extends Model
{
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
     * Whether the candidate's material supported a judgement for this criterion
     * at all. An unassessed criterion is uncertainty: it lowers evidence
     * coverage and stays out of the match figure entirely, rather than being
     * scored as if the candidate had failed it.
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

    /** @return BelongsTo<SourcingMatch, $this> */
    public function sourcingMatch(): BelongsTo
    {
        return $this->belongsTo(SourcingMatch::class);
    }
}
