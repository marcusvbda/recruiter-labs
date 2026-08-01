<?php

namespace App\Models;

use App\Enums\ApplicationQuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'job_id', 'question', 'response_type', 'description', 'required', 'sort'])]
class JobApplicationQuestion extends Model
{
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
