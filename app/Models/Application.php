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
 * @property int|null $referral_id
 * @property ApplicationSource $source
 * @property ApplicationAnalysisStatus $analysis_status
 * @property int $analysis_generation
 * @property string|null $analysis_score
 * @property CarbonImmutable|null $analyzed_at
 * @property ApplicationCoverLetterType $cover_letter_type
 * @property string|null $cover_letter_text
 * @property string|null $submitted_ip
 */
#[Fillable(['company_id', 'job_id', 'candidate_id', 'status_id', 'referral_id', 'source', 'analysis_status', 'analysis_generation', 'analysis_score', 'analyzed_at', 'cover_letter_type', 'cover_letter_text', 'submitted_ip'])]
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
        // Creating an application *is* entering its first status. Every later
        // movement goes through {@see MoveApplicationToStatus}, which emits the
        // same event itself; this hook only covers the entry point, so no
        // creation path can silently skip the status's communication.
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
            'analysis_score' => 'decimal:2',
            'analyzed_at' => 'immutable_datetime',
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
     * "interviewing" — see {@see scopeInterviewing()}.
     *
     * @return HasMany<Interview, $this>
     */
    public function upcomingInterviews(): HasMany
    {
        return $this->interviews()->upcoming();
    }
}
