<?php

namespace App\Models;

use App\Enums\AnalysisConfidence;
use App\Enums\SourcingMatchState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One workspace candidate assessed against one job's confirmed criteria.
 *
 * This is a suggestion, not a pipeline record: it lives entirely outside
 * `applications` and never influences a candidate's position, stage or ordering
 * in the hiring process.
 *
 * `potential_match` and `evidence_coverage` are separate figures and both are
 * nullable — null means the candidate's material did not support a judgement,
 * which is not the same as a low match. `confidence` describes how strongly the
 * candidate's own submitted material supports the assessment; it verifies
 * nothing against the outside world.
 *
 * @property int $id
 * @property int $company_id
 * @property int $job_id
 * @property int $candidate_id
 * @property int $criteria_generation
 * @property int|null $materials_revision
 * @property int|null $potential_match
 * @property int|null $evidence_coverage
 * @property AnalysisConfidence|null $confidence
 * @property bool $sufficient_information
 * @property SourcingMatchState $state
 * @property CarbonImmutable|null $state_changed_at
 * @property int|null $state_changed_by_id
 * @property CarbonImmutable|null $analyzed_at
 */
#[Fillable(['company_id', 'job_id', 'candidate_id', 'criteria_generation', 'materials_revision', 'potential_match', 'evidence_coverage', 'confidence', 'sufficient_information', 'state', 'state_changed_at', 'state_changed_by_id', 'analyzed_at'])]
class SourcingMatch extends Model
{
    protected $attributes = [
        'sufficient_information' => true,
        'state' => SourcingMatchState::Suggested->value,
    ];

    protected function casts(): array
    {
        return [
            'criteria_generation' => 'integer',
            'materials_revision' => 'integer',
            'potential_match' => 'integer',
            'evidence_coverage' => 'integer',
            'confidence' => AnalysisConfidence::class,
            'sufficient_information' => 'boolean',
            'state' => SourcingMatchState::class,
            'state_changed_at' => 'immutable_datetime',
            'analyzed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Whether this match still describes both the criteria the recruiter
     * confirmed and the material it was built from.
     *
     * A match produced against an earlier criteria revision measured criteria
     * that no longer govern this job. A match produced before this candidate's
     * material changed — a CV became readable, was archived, restored, deleted or
     * had its declared received date corrected — measured evidence the workspace
     * no longer holds in that form.
     *
     * The material check is per candidate on purpose: it compares this
     * candidate's own `materials_revision`, so one person's new CV cannot
     * invalidate everybody else's analyses.
     */
    public function isCurrent(): bool
    {
        $job = $this->job;

        return $job instanceof Job
            && $job->hasConfirmedCriteria()
            && $this->criteria_generation === $job->criteria_generation
            && $this->matchesCurrentMaterial();
    }

    /**
     * Whether the candidate's material is still the material this assessment
     * read.
     *
     * A row recorded before the snapshot existed compares as the initial counter
     * value, which is the truth for a candidate whose material has never changed
     * and stops being the truth the moment it does.
     */
    public function matchesCurrentMaterial(): bool
    {
        $candidate = $this->candidate;

        return ! $candidate instanceof Candidate
            || (int) $this->materials_revision === (int) $candidate->materials_revision;
    }

    /**
     * A stored match the job's criteria have moved on from. It is never shown as
     * the current assessment; it is not deleted either, because the recruiter's
     * saved/dismissed decision about that candidate still stands.
     */
    public function isOutdated(): bool
    {
        return ! $this->isCurrent();
    }

    /**
     * Whether this candidate is already part of this job's hiring process.
     *
     * Computed on read, never stored. The candidate may have entered through the
     * manual add, through sourcing, or by applying directly while a search was
     * running — a persisted flag would only reflect the path that wrote it, and
     * would let the workspace offer "add to job" for someone already in it.
     */
    public function candidateAlreadyInJob(): bool
    {
        return Application::query()
            ->where('job_id', $this->job_id)
            ->where('candidate_id', $this->candidate_id)
            ->exists();
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function stateChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'state_changed_by_id');
    }

    /** @return HasMany<SourcingMatchCriterionScore, $this> */
    public function criterionScores(): HasMany
    {
        return $this->hasMany(SourcingMatchCriterionScore::class);
    }
}
