<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $job_id
 * @property string $category
 * @property string $severity
 * @property string|null $excerpt
 * @property string $issue
 * @property string $suggestion
 * @property int $sort_order
 */
#[Fillable(['company_id', 'job_id', 'category', 'severity', 'excerpt', 'issue', 'suggestion', 'sort_order'])]
class JobReviewAlert extends Model
{
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
