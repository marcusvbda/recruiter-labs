<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueKey;
use Database\Factories\ReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'job_id', 'user_id', 'published', 'expires_at', 'max_applications'])]
class Referral extends Model
{
    /** @use HasFactory<ReferralFactory> */
    use HasFactory, HasUniqueKey;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'expires_at' => 'datetime',
            'max_applications' => 'integer',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<JobClick, $this> */
    public function clicks(): HasMany
    {
        return $this->hasMany(JobClick::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
