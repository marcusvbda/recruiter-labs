<?php

namespace App\Models;

use App\Actions\RecordCompanyMilestone;
use App\Enums\CompanyMilestone as CompanyMilestoneEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A milestone a workspace has reached, once, at a point in time.
 *
 * Rows are append-only history: {@see RecordCompanyMilestone} is the single
 * writer and never updates or deletes one, so the activation funnel can be read
 * later without wondering whether a date was rewritten.
 */
#[Fillable(['company_id', 'milestone', 'achieved_at'])]
class CompanyMilestone extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'milestone' => CompanyMilestoneEnum::class,
            'achieved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
