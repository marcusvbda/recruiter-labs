<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property string $path
 * @property int $attempts
 * @property CarbonImmutable|null $failed_at
 */
#[Fillable(['company_id', 'path', 'attempts', 'failed_at'])]
class CandidateFileCleanup extends Model
{
    protected $attributes = ['attempts' => 0];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'failed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
