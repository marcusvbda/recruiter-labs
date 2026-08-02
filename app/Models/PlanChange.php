<?php

namespace App\Models;

use App\Enums\PlanChangeSource;
use Database\Factories\PlanChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'previous_plan_id', 'new_plan_id', 'changed_by_id', 'source', 'metadata'])]
class PlanChange extends Model
{
    /** @use HasFactory<PlanChangeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'source' => PlanChangeSource::class,
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'previous_plan_id');
    }

    /** @return BelongsTo<Plan, $this> */
    public function newPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'new_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
