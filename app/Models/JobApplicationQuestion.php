<?php

namespace App\Models;

use App\Enums\ApplicationQuestionType;
use Database\Factories\JobApplicationQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $job_id
 * @property string $question
 * @property ApplicationQuestionType $response_type
 * @property string|null $description
 * @property bool $required
 * @property int $sort
 */
#[Fillable(['company_id', 'job_id', 'question', 'response_type', 'description', 'required', 'sort'])]
class JobApplicationQuestion extends Model
{
    /** @use HasFactory<JobApplicationQuestionFactory> */
    use HasFactory;

    protected $attributes = [
        'required' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'response_type' => ApplicationQuestionType::class,
            'required' => 'boolean',
            'sort' => 'integer',
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

    /** @return HasMany<ApplicationAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(ApplicationAnswer::class);
    }
}
