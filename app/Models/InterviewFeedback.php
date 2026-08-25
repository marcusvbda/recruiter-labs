<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Structured human evidence recorded by one interviewer after one interview.
 *
 * This is a sibling of the AI evaluation, never a revision of it: it lives in
 * its own tables and knows nothing about `applications.analysis_*` or
 * `application_criterion_scores`. Author, interview, application and submission
 * time are all required, so the evidence is always attributable.
 *
 * `criteria_generation` is the job's criteria revision at submission time. It
 * exists so a later criteria edit is visibly a later criteria edit rather than a
 * silent rewrite of what the interviewer evaluated.
 *
 * @property int $id
 * @property int $company_id
 * @property int $interview_id
 * @property int $application_id
 * @property int $job_id
 * @property int $submitted_by_id
 * @property CarbonImmutable $submitted_at
 * @property int $criteria_generation
 * @property string|null $general_note
 */
#[Fillable(['company_id', 'interview_id', 'application_id', 'job_id', 'submitted_by_id', 'submitted_at', 'criteria_generation', 'general_note'])]
class InterviewFeedback extends Model
{
    protected $table = 'interview_feedback';

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'criteria_generation' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Interview, $this> */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /** @return HasMany<InterviewFeedbackCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(InterviewFeedbackCriterion::class);
    }
}
