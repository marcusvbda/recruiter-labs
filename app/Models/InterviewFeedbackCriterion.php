<?php

namespace App\Models;

use App\Enums\InterviewFeedbackResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One criterion's human result inside a submitted {@see InterviewFeedback}.
 *
 * `criterion` and `weight` are a snapshot of the {@see JobCriterion} this row
 * was recorded against, resolved by ID at submission time and never from text
 * supplied by the caller. Criteria are editable, so without the snapshot a later
 * job edit would silently rewrite what the interviewer originally evaluated.
 *
 * `result` carries no number and no ranking. Interview evidence informs a human
 * decision; it never recalculates fit or evidence coverage.
 *
 * @property int $id
 * @property int $company_id
 * @property int $interview_feedback_id
 * @property int|null $job_criterion_id
 * @property string $criterion
 * @property int $weight
 * @property InterviewFeedbackResult $result
 * @property string|null $evidence_note
 */
#[Fillable(['company_id', 'interview_feedback_id', 'job_criterion_id', 'criterion', 'weight', 'result', 'evidence_note'])]
class InterviewFeedbackCriterion extends Model
{
    protected $table = 'interview_feedback_criteria';

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'result' => InterviewFeedbackResult::class,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<InterviewFeedback, $this> */
    public function feedback(): BelongsTo
    {
        return $this->belongsTo(InterviewFeedback::class, 'interview_feedback_id');
    }

    /**
     * The criterion this result was recorded against, when it still exists.
     * A null relation means the criterion was deleted after the interview — the
     * observation itself survives in `criterion`, `weight` and `result`.
     *
     * @return BelongsTo<JobCriterion, $this>
     */
    public function jobCriterion(): BelongsTo
    {
        return $this->belongsTo(JobCriterion::class);
    }
}
