<?php

namespace App\Models;

use App\Enums\ApplicationQuestionType;
use Database\Factories\ApplicationAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'application_id', 'job_application_question_id', 'question_snapshot', 'response_type', 'value_text', 'value_number'])]
class ApplicationAnswer extends Model
{
    /** @use HasFactory<ApplicationAnswerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'response_type' => ApplicationQuestionType::class,
            'value_number' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<JobApplicationQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(JobApplicationQuestion::class, 'job_application_question_id');
    }
}
