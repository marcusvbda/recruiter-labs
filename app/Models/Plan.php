<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\Limit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property list<string> $features
 * @property array<string, int|null> $limits
 */
#[Fillable(['name', 'slug', 'sort_order', 'features', 'limits'])]
class Plan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function hasFeature(Feature $feature): bool
    {
        return in_array($feature->value, $this->features ?? [], strict: true);
    }

    public function getLimit(Limit $limit): ?int
    {
        return $this->limits[$limit->value] ?? null;
    }

    public static function default(): self
    {
        return static::query()->where('slug', 'starter')->firstOrFail();
    }
}
