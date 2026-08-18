<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $application_id
 * @property int|null $application_criterion_score_id
 * @property string $criterion
 * @property string $priority
 * @property string $reason
 * @property string $question
 * @property int $sort_order
 */
#[Fillable(['company_id', 'application_id', 'application_criterion_score_id', 'criterion', 'priority', 'reason', 'question', 'sort_order'])]
class ApplicationInterviewBriefItem extends Model
{
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

    /** @return BelongsTo<ApplicationCriterionScore, $this> */
    public function criterionScore(): BelongsTo
    {
        return $this->belongsTo(ApplicationCriterionScore::class, 'application_criterion_score_id');
    }
}
