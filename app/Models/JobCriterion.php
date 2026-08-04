<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $job_id
 * @property string $criterion
 * @property int $weight
 * @property string|null $reason
 */
#[Fillable(['company_id', 'job_id', 'criterion', 'weight', 'reason'])]
class JobCriterion extends Model
{
    protected $table = 'job_criterion';

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
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
}
