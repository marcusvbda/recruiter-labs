<?php

namespace App\Models;

use App\Actions\MoveApplicationToStatus;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationSource;
use App\Events\ApplicationEnteredStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $job_id
 * @property int $candidate_id
 * @property int $status_id
 * @property CarbonImmutable $status_entered_at
 * @property int|null $referral_id
 * @property ApplicationSource $source
 * @property ApplicationAnalysisStatus $analysis_status
 * @property int $analysis_generation
 * @property int|null $analysis_criteria_generation
 * @property string|null $analysis_score
 * @property int|null $analysis_coverage
 * @property CarbonImmutable|null $analyzed_at
 * @property ApplicationCoverLetterType $cover_letter_type
 * @property string|null $cover_letter_text
 * @property string|null $submitted_ip
 */
#[Fillable(['company_id', 'job_id', 'candidate_id', 'status_id', 'status_entered_at', 'referral_id', 'source', 'analysis_status', 'analysis_generation', 'analysis_criteria_generation', 'analysis_score', 'analysis_coverage', 'analyzed_at', 'cover_letter_type', 'cover_letter_text', 'submitted_ip'])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $attributes = [
        'source' => ApplicationSource::Direct->value,
        'analysis_status' => ApplicationAnalysisStatus::Pending->value,
        'cover_letter_type' => ApplicationCoverLetterType::None->value,
    ];

    protected static function booted(): void
    {
        // Creating an application *is* entering its first status, so the stage
        // clock starts here rather than being inferred from `created_at` later.
        static::creating(function (Application $application): void {
            $application->status_entered_at ??= CarbonImmutable::now();
        });

        // Every later movement goes through {@see MoveApplicationToStatus},
        // which emits the same event itself; this hook only covers the entry
        // point, so no creation path can silently skip the status's
        // communication.
        static::created(function (Application $application): void {
            ApplicationEnteredStatus::dispatch(
                (int) $application->getKey(),
                (int) $application->status_id,
                null,
            );
        });
    }

    protected function casts(): array
    {
        return [
            'source' => ApplicationSource::class,
            'analysis_status' => ApplicationAnalysisStatus::class,
            'analysis_generation' => 'integer',
            'analysis_criteria_generation' => 'integer',
            'analysis_score' => 'decimal:2',
            'analysis_coverage' => 'integer',
            'analyzed_at' => 'immutable_datetime',
            'status_entered_at' => 'immutable_datetime',
            'cover_letter_type' => ApplicationCoverLetterType::class,
        ];
    }

    /**
     * Still being recruited: the stage it sits in has not closed the process,
     * whether that ending was a hire or any other final outcome.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeInProcess(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $status): Builder => $status->where('is_terminal', false));
    }

    /**
     * Interviewing means a commitment is still pending — an interview booked for
     * later today or already running. An interview that ended last week does not
     * keep a candidate here, and a finished application never counts.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeInterviewing(Builder $query): Builder
    {
        return $query->inProcess()->has('upcomingInterviews');
    }

    /**
     * Sitting in a stage the workflow explicitly marks as close to a decision.
     * The hired stage is the decision itself, so it is not a finalist stage.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeInFinalStage(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $status): Builder => $status
            ->where('is_final_stage', true)
            ->where('is_hired', false));
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeHired(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $status): Builder => $status->where('is_hired', true));
    }

    /**
     * Candidates who have been sitting in their current stage longer than that
     * stage's configured expectation. A stage without
     * {@see Status::$attention_after_days} never qualifies, and a stage that
     * ended the process never does either — nobody is waiting on a closed
     * application.
     *
     * The comparison is resolved per threshold in PHP rather than as SQL date
     * arithmetic: the number of distinct thresholds in a workspace is tiny, and
     * this keeps the query portable across the supported databases.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    public function scopeOverdueInStage(Builder $query, ?Company $company = null): Builder
    {
        $now = CarbonImmutable::now();
        $thresholds = Status::query()
            ->when($company !== null, fn (Builder $statuses): Builder => $statuses->whereBelongsTo($company))
            ->whereNotNull('attention_after_days')
            ->where('is_terminal', false)
            ->get(['id', 'attention_after_days'])
            ->groupBy('attention_after_days');

        if ($thresholds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($thresholds, $now): void {
            foreach ($thresholds as $days => $statuses) {
                $query->orWhere(fn (Builder $query): Builder => $query
                    ->whereIn('status_id', $statuses->pluck('id')->all())
                    ->where('status_entered_at', '<=', $now->subDays((int) $days)));
            }
        });
    }

    /**
     * Whether the stored evaluation still describes the criteria the recruiter
     * confirmed. A completed evaluation produced against an earlier revision is
     * history, not the current assessment, and must never be shown as if it
     * were: the criteria it measured no longer govern this hiring process.
     *
     * Evaluations produced before the revision link existed have no recorded
     * revision, so they cannot claim to be current either.
     */
    public function hasCurrentEvaluation(): bool
    {
        if ($this->analysis_status !== ApplicationAnalysisStatus::Completed) {
            return false;
        }

        $job = $this->job;

        return $job instanceof Job
            && $job->hasConfirmedCriteria()
            && $this->analysis_criteria_generation !== null
            && $this->analysis_criteria_generation === $job->criteria_generation;
    }

    /**
     * A finished evaluation that measured criteria the job has since moved on
     * from. Distinguished from "no evaluation yet" so the UI can say *why* there
     * is nothing current to show.
     */
    public function hasOutdatedEvaluation(): bool
    {
        return $this->analysis_status === ApplicationAnalysisStatus::Completed
            && ! $this->hasCurrentEvaluation();
    }

    /**
     * When the candidate entered the stage they are in now. Applications created
     * before the column existed fall back to their creation time.
     */
    public function statusEnteredAt(): CarbonImmutable
    {
        return $this->status_entered_at ?? CarbonImmutable::instance($this->created_at);
    }

    /** Full days the candidate has been waiting in the current stage. */
    public function daysInCurrentStage(): int
    {
        return (int) $this->statusEnteredAt()->diffInDays(CarbonImmutable::now(), absolute: true);
    }

    /**
     * Whether the current stage's own expectation has been exceeded. Explaining
     * *why* needs the threshold too, which is why the status is read here rather
     * than the answer being reduced to a boolean elsewhere.
     */
    public function isOverdueInCurrentStage(): bool
    {
        $status = $this->status;
        $threshold = $status?->attention_after_days;

        if ($status === null || $threshold === null || $status->is_terminal) {
            return false;
        }

        return $this->daysInCurrentStage() >= $threshold;
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

    /** @return BelongsTo<Status, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** @return BelongsTo<Referral, $this> */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    /** @return HasMany<ApplicationAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(ApplicationAnswer::class);
    }

    /** @return HasMany<ApplicationDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /** @return HasMany<ApplicationUtmParameter, $this> */
    public function utmParameters(): HasMany
    {
        return $this->hasMany(ApplicationUtmParameter::class);
    }

    /** @return HasMany<ApplicationCriterionScore, $this> */
    public function criterionScores(): HasMany
    {
        return $this->hasMany(ApplicationCriterionScore::class);
    }

    /** @return HasMany<ApplicationInterviewBriefItem, $this> */
    public function interviewBriefItems(): HasMany
    {
        return $this->hasMany(ApplicationInterviewBriefItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<AiUsageRecord, $this> */
    public function aiUsageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    /** @return HasMany<Interview, $this> */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /**
     * Interviews that have not happened yet, which is what makes a candidate
     * "interviewing" — see {@see scopeInterviewing()}. Soonest first, so every
     * caller that wants "the next one" gets the same answer.
     *
     * @return HasMany<Interview, $this>
     */
    public function upcomingInterviews(): HasMany
    {
        return $this->interviews()->upcoming()->orderBy('scheduled_at');
    }
}
