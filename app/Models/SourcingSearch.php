<?php

namespace App\Models;

use App\Enums\SourcingSearchStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The state of active candidate sourcing for one job.
 *
 * `generation` identifies the search request (so a queued run can tell whether
 * it is still the one that was asked for) and `criteria_generation` identifies
 * the confirmed criteria revision the stored result was produced against.
 * Both are needed, for the same reason candidate evaluation needs both.
 *
 * @property int $id
 * @property int $company_id
 * @property int $job_id
 * @property int|null $criteria_generation
 * @property int $generation
 * @property SourcingSearchStatus $status
 * @property int|null $candidates_considered
 * @property int|null $matches_found
 * @property int|null $insufficient_count
 * @property int|null $requested_by_id
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['company_id', 'job_id', 'criteria_generation', 'generation', 'status', 'candidates_considered', 'matches_found', 'insufficient_count', 'requested_by_id', 'started_at', 'completed_at'])]
class SourcingSearch extends Model
{
    protected $attributes = [
        'status' => SourcingSearchStatus::NotStarted->value,
        'generation' => 0,
    ];

    protected function casts(): array
    {
        return [
            'criteria_generation' => 'integer',
            'generation' => 'integer',
            'status' => SourcingSearchStatus::class,
            'candidates_considered' => 'integer',
            'matches_found' => 'integer',
            'insufficient_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Whether the stored result still describes the criteria the recruiter
     * confirmed. A search that completed against an earlier revision measured
     * criteria that no longer govern this hiring process, so it is history and
     * must never be presented as the current picture.
     *
     * Results recorded without a criteria revision cannot claim to be current
     * either.
     */
    public function isCurrent(): bool
    {
        if ($this->status !== SourcingSearchStatus::Completed) {
            return false;
        }

        $job = $this->job;

        return $job instanceof Job
            && $job->hasConfirmedCriteria()
            && $this->criteria_generation !== null
            && $this->criteria_generation === $job->criteria_generation;
    }

    /**
     * A finished search whose criteria the job has since moved on from.
     * Distinguished from "never searched" so the workspace can say *why* there
     * is nothing current to show.
     */
    public function isOutdated(): bool
    {
        return $this->status === SourcingSearchStatus::Completed && ! $this->isCurrent();
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

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * The matches this job's sourcing has produced. Related through the job
     * rather than through this row: a match survives a rerun of the search and
     * keeps the recruiter's saved/dismissed decision.
     *
     * @return HasMany<SourcingMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(SourcingMatch::class, 'job_id', 'job_id');
    }
}
