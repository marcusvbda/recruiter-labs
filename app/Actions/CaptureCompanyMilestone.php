<?php

namespace App\Actions;

use App\Enums\CompanyMilestone;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reports a milestone from the product action that actually reached it.
 *
 * Activation progress only observes the recruitment workflow: it may never
 * decide whether a job gets created, criteria get confirmed or a candidate gets
 * evaluated. Two rules make that true at every report site, so none of them has
 * to remember them:
 *
 * - the ledger write is deferred until the surrounding transaction commits, so a
 *   milestone can neither roll back the action that reported it nor claim credit
 *   for an action that was rolled back;
 * - a ledger failure is reported and swallowed, because losing a progress row is
 *   a cosmetic problem while losing a hiring action is not.
 *
 * {@see RecordCompanyMilestone} stays the single writer and the only place that
 * decides what a milestone means, including which ones may be recorded at all.
 */
class CaptureCompanyMilestone
{
    public function __construct(private readonly RecordCompanyMilestone $recordCompanyMilestone) {}

    /**
     * @param  Company|int  $company  The workspace, or just its id — report sites
     *                                include model hooks that only hold one.
     */
    public function handle(Company|int $company, CompanyMilestone $milestone): void
    {
        $companyId = $company instanceof Company ? (int) $company->getKey() : $company;

        DB::connection()->afterCommit(function () use ($companyId, $milestone): void {
            try {
                $this->recordCompanyMilestone->handle($companyId, $milestone);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
