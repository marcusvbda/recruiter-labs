<?php

namespace App\Events;

use App\Actions\RecordCompanyMilestone;
use App\Enums\CompanyMilestone;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace reached a milestone for the first time.
 *
 * Dispatched by {@see RecordCompanyMilestone} only when the ledger actually
 * gained a row, so each workspace emits a milestone exactly once. It exists to
 * make the activation funnel observable from inside the product, without
 * committing to an analytics vendor.
 */
class WorkspaceMilestoneReached implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $companyId,
        public readonly CompanyMilestone $milestone,
        public readonly CarbonInterface $achievedAt,
    ) {}
}
