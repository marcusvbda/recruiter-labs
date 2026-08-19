<?php

namespace App\Models;

use App\Enums\ApplicationLocale;
use App\Enums\CoverLetterType;
use App\Enums\InterviewStatus;
use App\Enums\JobCriteriaProcessingStatus;
use App\Exceptions\RecruitmentWorkflowException;
use App\Models\Concerns\HasUniqueKey;
use Carbon\CarbonImmutable;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $pipeline_id
 * @property string $name
 * @property ApplicationLocale $application_locale
 * @property string|null $description
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $published
 * @property bool $applications_paused
 * @property int|null $application_limit
 * @property bool $cover_letter_required
 * @property CoverLetterType $cover_letter_type
 * @property JobCriteriaProcessingStatus $criteria_processing_status
 * @property int $criteria_generation
 */
#[Fillable(['company_id', 'pipeline_id', 'name', 'application_locale', 'description', 'starts_at', 'ends_at', 'published', 'applications_paused', 'application_limit', 'cover_letter_required', 'cover_letter_type', 'criteria_processing_status', 'criteria_generation'])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory, HasUniqueKey;

    // Not `jobs`: that table name is reserved by Laravel's queue system and is
    // unrelated to this model (also unrelated to any `App\Jobs\*` queue job class).
    protected $table = 'job_postings';

    protected $attributes = [
        'application_locale' => ApplicationLocale::English->value,
        'published' => false,
        'applications_paused' => false,
        'cover_letter_required' => false,
        'cover_letter_type' => CoverLetterType::Text->value,
        'criteria_processing_status' => JobCriteriaProcessingStatus::NotStarted->value,
        'criteria_generation' => 0,
    ];

    protected static function booted(): void
    {
        // Changing the pipeline of a job that already has applications would leave
        // every one of them pointing at a status from a different workflow. Pipeline
        // migration/mapping is deliberately not supported, so the change is refused.
        static::updating(function (Job $job): void {
            if (! $job->isDirty('pipeline_id')) {
                return;
            }

            if ($job->applications()->exists()) {
                throw RecruitmentWorkflowException::pipelineLocked();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'application_locale' => ApplicationLocale::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'published' => 'boolean',
            'applications_paused' => 'boolean',
            'application_limit' => 'integer',
            'cover_letter_required' => 'boolean',
            'cover_letter_type' => CoverLetterType::class,
            'criteria_processing_status' => JobCriteriaProcessingStatus::class,
            'criteria_generation' => 'integer',
        ];
    }

    /**
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $today = today();

        return $query
            ->where('published', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('starts_at')
                ->orWhereDate('starts_at', '<=', $today))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('ends_at')
                ->orWhereDate('ends_at', '>=', $today));
    }

    public function acceptsApplications(): bool
    {
        $today = CarbonImmutable::instance(today());
        $startsAt = $this->getRawOriginal('starts_at');
        $endsAt = $this->getRawOriginal('ends_at');

        return $this->published
            && ! $this->applications_paused
            && ($startsAt === null || CarbonImmutable::parse($startsAt)->lessThanOrEqualTo($today))
            && ($endsAt === null || CarbonImmutable::parse($endsAt)->greaterThanOrEqualTo($today));
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Whether the recruitment pipeline can still be swapped: only while no
     * candidate has entered it.
     */
    public function canChangePipeline(): bool
    {
        return ! $this->applications()->exists();
    }

    /** @return HasMany<JobCriterion, $this> */
    public function jobCriteria(): HasMany
    {
        return $this->hasMany(JobCriterion::class);
    }

    /** @return HasMany<JobReviewAlert, $this> */
    public function reviewAlerts(): HasMany
    {
        return $this->hasMany(JobReviewAlert::class);
    }

    /** @return HasMany<AiUsageRecord, $this> */
    public function aiUsageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Applications with at least one interview that has not been cancelled.
     * This is what "interviewing" means operationally: a commitment exists.
     *
     * @return HasMany<Application, $this>
     */
    public function interviewingApplications(): HasMany
    {
        return $this->applications()->whereHas(
            'interviews',
            fn (Builder $query): Builder => $query->where('status', '!=', InterviewStatus::Cancelled->value),
        );
    }

    /**
     * Applications sitting in a stage explicitly marked as late/final by the
     * workflow. The hired stage is the outcome, so it is excluded here.
     *
     * @return HasMany<Application, $this>
     */
    public function finalStageApplications(): HasMany
    {
        return $this->applications()->whereHas(
            'status',
            fn (Builder $query): Builder => $query
                ->where('is_final_stage', true)
                ->where('is_hired', false),
        );
    }

    /** @return HasMany<Application, $this> */
    public function hiredApplications(): HasMany
    {
        return $this->applications()->whereHas(
            'status',
            fn (Builder $query): Builder => $query->where('is_hired', true),
        );
    }

    /** @return HasMany<JobClick, $this> */
    public function clicks(): HasMany
    {
        return $this->hasMany(JobClick::class);
    }

    /** @return HasMany<JobApplicationQuestion, $this> */
    public function applicationQuestions(): HasMany
    {
        return $this->hasMany(JobApplicationQuestion::class)->orderBy('sort');
    }

    /** @return BelongsToMany<CvFileType, $this> */
    public function acceptedCvTypes(): BelongsToMany
    {
        return $this->belongsToMany(CvFileType::class, 'cv_file_type_job')->orderBy('sort');
    }

    /** @return BelongsToMany<CvFileType, $this> */
    public function coverLetterFileTypes(): BelongsToMany
    {
        return $this->belongsToMany(CvFileType::class, 'cover_letter_file_type_job')->orderBy('sort');
    }
}
