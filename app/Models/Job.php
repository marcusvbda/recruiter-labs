<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['company_id', 'name', 'description', 'starts_at', 'ends_at', 'campaign_expectation'])]
class Job extends Model
{
    use HasFactory, HasUniqueKey;

    // Not `jobs`: that table name is reserved by Laravel's queue system and is
    // unrelated to this model (also unrelated to any `App\Jobs\*` queue job class).
    protected $table = 'job_postings';

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
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

    /**
     * Read-side convenience only; writes to the pivot go through
     * `JobCriterion` directly since it is a real Pivot model.
     */
    public function criteria(): BelongsToMany
    {
        return $this->belongsToMany(Criterion::class, 'job_criterion')
            ->using(JobCriterion::class)
            ->withPivot('weight')
            ->withTimestamps();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function automationEvents(): MorphMany
    {
        return $this->morphMany(AutomationEvent::class, 'automatable');
    }
}
