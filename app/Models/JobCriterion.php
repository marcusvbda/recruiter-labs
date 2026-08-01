<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['company_id', 'job_id', 'criterion_id', 'weight'])]
class JobCriterion extends Pivot
{
    protected $table = 'job_criterion';

    // Unlike default pivot tables, `job_criterion` has its own auto-increment
    // `id` column, so this is a real Pivot model rather than an attribute-array pivot.
    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
