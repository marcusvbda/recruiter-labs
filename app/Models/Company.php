<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\Limit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'plan_id'])]
class Company extends Model
{
    use HasFactory;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function hasFeature(Feature $feature): bool
    {
        return $this->plan->hasFeature($feature);
    }

    public function hasReachedLimit(Limit $limit, int $currentCount): bool
    {
        $max = $this->plan->getLimit($limit);

        return $max !== null && $currentCount >= $max;
    }
}
