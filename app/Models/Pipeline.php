<?php

namespace App\Models;

use App\Exceptions\RecruitmentWorkflowException;
use Database\Factories\PipelineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 */
#[Fillable(['company_id', 'name', 'description', 'is_default'])]
class Pipeline extends Model
{
    /** @use HasFactory<PipelineFactory> */
    use HasFactory;

    protected $attributes = [
        'is_default' => false,
    ];

    protected static function booted(): void
    {
        // A pipeline still referenced by a job carries that job's whole recruitment
        // history through its statuses, so deletion is a domain error rather than a
        // cascade. The `restrict` foreign key on `job_postings` is the last line of
        // defence; this hook exists so the rule can be reported, not just enforced.
        static::deleting(function (Pipeline $pipeline): void {
            $jobCount = $pipeline->jobs()->count();

            if ($jobCount > 0) {
                throw RecruitmentWorkflowException::pipelineInUse($jobCount);
            }
        });

        static::saved(function (Pipeline $pipeline): void {
            if (! $pipeline->is_default) {
                return;
            }

            // Query-builder update on purpose: it must not re-trigger this hook.
            static::query()
                ->where('company_id', $pipeline->company_id)
                ->whereKeyNot($pipeline->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });

        static::deleted(function (Pipeline $pipeline): void {
            if (! $pipeline->is_default) {
                return;
            }

            $replacement = static::query()
                ->where('company_id', $pipeline->company_id)
                ->orderBy('id')
                ->first();

            $replacement?->update(['is_default' => true]);
        });
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Status, $this> */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class)->orderBy('order')->orderBy('id');
    }

    /** @return HasMany<Job, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    /**
     * The status new applications enter, i.e. the first one in the configured order.
     */
    public function firstStatus(): ?Status
    {
        return $this->relationLoaded('statuses')
            ? $this->statuses->first()
            : $this->statuses()->first();
    }
}
