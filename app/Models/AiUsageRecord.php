<?php

namespace App\Models;

use App\Enums\AiProvider;
use App\Enums\AiUsageStatus;
use Database\Factories\AiUsageRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AiProvider $provider
 * @property AiUsageStatus $status
 */
#[Fillable(['company_id', 'user_id', 'application_id', 'job_id', 'execution_id', 'attempt', 'operation', 'provider', 'ai_provider', 'model', 'input_tokens', 'output_tokens', 'total_tokens', 'cached_tokens', 'estimated_cost', 'duration_ms', 'status', 'used_own_key'])]
class AiUsageRecord extends Model
{
    /** @use HasFactory<AiUsageRecordFactory> */
    use HasFactory;

    protected $attributes = [
        'input_tokens' => 0,
        'output_tokens' => 0,
        'total_tokens' => 0,
        'cached_tokens' => 0,
        'attempt' => 1,
        'status' => AiUsageStatus::Pending->value,
        'used_own_key' => false,
    ];

    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'estimated_cost' => 'decimal:6',
            'status' => AiUsageStatus::class,
            'used_own_key' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
