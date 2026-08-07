<?php

namespace App\Models;

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationCoverLetterType;
use App\Enums\ApplicationSource;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    /** @return HasMany<AiUsageRecord, $this> */
    public function aiUsageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }
}
