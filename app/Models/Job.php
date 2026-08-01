<?php

namespace App\Models;

use App\Enums\ApplicationLocale;
use App\Enums\CoverLetterType;
use App\Models\Concerns\HasUniqueKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['company_id', 'name', 'application_locale', 'description', 'starts_at', 'ends_at', 'campaign_expectation', 'published', 'cover_letter_required', 'cover_letter_type'])]
class Job extends Model
{
    use HasFactory, HasUniqueKey;

    // Not `jobs`: that table name is reserved by Laravel's queue system and is
    // unrelated to this model (also unrelated to any `App\Jobs\*` queue job class).
    protected $table = 'job_postings';

    protected $attributes = [
        'application_locale' => ApplicationLocale::English->value,
        'published' => false,
        'cover_letter_required' => false,
        'cover_letter_type' => CoverLetterType::Text->value,
    ];

    protected function casts(): array
    {
        return [
            'application_locale' => ApplicationLocale::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'published' => 'boolean',
            'cover_letter_required' => 'boolean',
            'cover_letter_type' => CoverLetterType::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jobCriteria(): HasMany
    {
        return $this->hasMany(JobCriterion::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<JobClick, $this> */
    public function clicks(): HasMany
    {
        return $this->hasMany(JobClick::class);
    }

    public function applicationQuestions(): HasMany
    {
        return $this->hasMany(JobApplicationQuestion::class)->orderBy('sort');
    }

    public function acceptedCvTypes(): BelongsToMany
    {
        return $this->belongsToMany(CvFileType::class, 'cv_file_type_job')->orderBy('sort');
    }

    public function coverLetterFileTypes(): BelongsToMany
    {
        return $this->belongsToMany(CvFileType::class, 'cover_letter_file_type_job')->orderBy('sort');
    }

    public function automationEvents(): MorphMany
    {
        return $this->morphMany(AutomationEvent::class, 'automatable');
    }
}
