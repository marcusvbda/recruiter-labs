<?php

namespace App\Models;

use App\Enums\SourcingSearchStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

/**
 * The state of active candidate sourcing for one job.
 *
 * `generation` identifies the search request (so a queued run can tell whether
 * it is still the one that was asked for) and `criteria_generation` identifies
 * the confirmed criteria revision the stored result was produced against.
 * Both are needed, for the same reason candidate evaluation needs both.
 *
 * `candidate_pool_revision` adds the third thing a result depends on: the pool
 * it actually swept. A search describes the candidates and material that existed
 * while it ran, so a new contact or a newly readable CV makes its coverage older
 * than the workspace's pool even though its criteria are untouched.
 *
 * @property int $id
 * @property int $company_id
 * @property int $job_id
 * @property int|null $criteria_generation
 * @property int|null $candidate_pool_revision
 * @property int $generation
 * @property SourcingSearchStatus $status
 * @property int|null $candidates_considered
 * @property int|null $matches_found
 * @property int|null $insufficient_count
 * @property int|null $requested_by_id
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['company_id', 'job_id', 'criteria_generation', 'candidate_pool_revision', 'generation', 'status', 'candidates_considered', 'matches_found', 'insufficient_count', 'requested_by_id', 'started_at', 'completed_at'])]
class SourcingSearch extends Model
{
    protected $attributes = [
        'status' => SourcingSearchStatus::NotStarted->value,
        'generation' => 0,
    ];

    protected static function booted(): void
    {
        // Drives the job sourcing panel's realtime refresh
        // (job-sourcing-panel.blade.php) instead of polling. Mid-run progress
        // is written via a query-builder update in SourceCandidatesForJob and
        // broadcasts explicitly there instead, since it bypasses this hook.
        static::saved(function (SourcingSearch $search): void {
            RealtimeEvent::dispatch('job_sourcing_'.$search->job_id, 'SourcingSearchUpdated');
        });
    }

    protected function casts(): array
    {
        return [
            'criteria_generation' => 'integer',
            'candidate_pool_revision' => 'integer',
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
     * Whether the stored result still describes both the criteria the recruiter
     * confirmed and the pool it swept.
     *
     * A search that completed against an earlier criteria revision measured
     * criteria that no longer govern this hiring process; a search that completed
     * before the pool changed did not see everything the workspace now holds.
     * Either way it is history and must never be presented as the current
     * picture.
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
            && $this->criteria_generation === $job->criteria_generation
            && $this->coversCurrentPool();
    }

    /**
     * Whether the pool the search swept is still the workspace's pool.
     *
     * `companies.candidate_pool_revision` advances when a candidate is added and
     * when any candidate's material becomes readable, is archived, restored,
     * deleted or has its declared date corrected. A completed search whose
     * snapshot has fallen behind it saw less than the workspace now holds, and
     * the recruiter has to ask for a refresh before that changes — nothing here
     * starts one.
     *
     * A search recorded before this snapshot existed compares as the initial
     * counter value: on a workspace where nothing has changed since, that is the
     * truth, and the first pool change moves it out of currency like any other.
     */
    public function coversCurrentPool(): bool
    {
        $company = $this->company;

        return ! $company instanceof Company
            || (int) $this->candidate_pool_revision === (int) $company->candidate_pool_revision;
    }

    /**
     * A finished search the workspace has moved on from — because the criteria
     * changed, because the pool did, or both. Distinguished from "never searched"
     * so the workspace can say *why* there is nothing current to show.
     */
    public function isOutdated(): bool
    {
        return $this->status === SourcingSearchStatus::Completed && ! $this->isCurrent();
    }

    /**
     * A finished search whose criteria still hold but whose coverage predates the
     * current pool.
     *
     * Kept separate from {@see isOutdated()} because the two need different
     * sentences: criteria moving on invalidates the assessment itself, while new
     * pool evidence means the assessments that exist are still about the right
     * criteria — there are simply people or materials the sweep never saw.
     */
    public function predatesCurrentPool(): bool
    {
        return $this->status === SourcingSearchStatus::Completed
            && ! $this->coversCurrentPool()
            && $this->job instanceof Job
            && $this->job->hasConfirmedCriteria()
            && $this->criteria_generation !== null
            && $this->criteria_generation === $this->job->criteria_generation;
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
