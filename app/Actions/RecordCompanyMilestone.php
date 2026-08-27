<?php

namespace App\Actions;

use App\Enums\CompanyMilestone;
use App\Events\WorkspaceMilestoneReached;
use App\Models\Company;
use App\Models\CompanyMilestone as CompanyMilestoneRecord;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The only writer of the workspace milestone ledger.
 *
 * Milestones are reported by places that cannot coordinate with each other —
 * model hooks, queued jobs, backfills — and the same real action may be
 * reported more than once. Recording therefore relies on the unique index on
 * `(company_id, milestone)`: the first report wins and later ones are silently
 * ignored, so a workspace emits {@see WorkspaceMilestoneReached} exactly once
 * per milestone.
 *
 * A reached milestone is history: nothing here updates or deletes an existing
 * row, because the date a workspace first did something cannot stop being true.
 *
 * Composite milestones are derived from the ledger rather than written by
 * callers, so "setup complete" and "workspace activated" can never be claimed
 * without the underlying activity being present. Passing one in is refused, not
 * merely discouraged.
 */
class RecordCompanyMilestone
{
    /**
     * @param  Company|int  $company  The workspace, or just its id — callers
     *                                include model hooks and queued jobs that
     *                                only hold an identifier.
     * @return bool Whether this call is the one that recorded the milestone.
     *
     * @throws InvalidArgumentException When asked to record a composite
     *                                  milestone, which only this action may
     *                                  write, and only from the ledger.
     */
    public function handle(Company|int $company, CompanyMilestone $milestone, ?CarbonInterface $achievedAt = null): bool
    {
        // Callers report real actions, and no real action is "the workspace is
        // activated". Accepting one here would let a mistake in a hook or a
        // backfill declare a workspace activated with no application and no
        // evaluation behind it, so the invariant is enforced rather than
        // documented.
        if ($milestone->isComposite()) {
            throw new InvalidArgumentException(
                "The [{$milestone->value}] milestone is derived from the ledger and cannot be recorded directly."
            );
        }

        $companyId = $company instanceof Company ? (int) $company->getKey() : $company;
        $achievedAt ??= CarbonImmutable::now();

        $recorded = $this->record($companyId, $milestone, $achievedAt);

        // Derivation runs on every report, not only on the one that inserted.
        // Two primaries recorded concurrently can each miss the other's row and
        // leave a composite unwritten, and a workspace whose primaries were
        // recorded before a composite existed has the same gap; re-deriving on
        // any later report closes both without a lock.
        $this->deriveComposites($companyId);

        return $recorded;
    }

    private function record(int $companyId, CompanyMilestone $milestone, CarbonInterface $achievedAt): bool
    {
        $inserted = CompanyMilestoneRecord::query()->insertOrIgnore([
            'company_id' => $companyId,
            'milestone' => $milestone->value,
            'achieved_at' => $achievedAt,
        ]);

        if ($inserted === 0) {
            return false;
        }

        WorkspaceMilestoneReached::dispatch($companyId, $milestone, $achievedAt);

        return true;
    }

    /**
     * A composite is dated from the ledger, at the latest of the milestones that
     * complete it: that is the moment the workspace actually became set up or
     * activated. Deriving the date rather than passing one in means a late or
     * repeated report cannot backdate or postdate product history.
     *
     * Setup is derived before activation and re-read from the ledger, so the row
     * written just above is available to the activation check.
     */
    private function deriveComposites(int $companyId): void
    {
        $reached = $this->reachedAt($companyId);

        $setupCompletedAt = $this->latestOf($reached, CompanyMilestone::FirstJobCreated, CompanyMilestone::FirstCriteriaConfirmed);

        if ($setupCompletedAt !== null) {
            $this->record($companyId, CompanyMilestone::WorkspaceSetupCompleted, $setupCompletedAt);
            $reached = $this->reachedAt($companyId);
        }

        $activatedAt = $this->latestOf(
            $reached,
            CompanyMilestone::WorkspaceSetupCompleted,
            CompanyMilestone::FirstApplicationCreated,
            CompanyMilestone::FirstApplicationEvaluated,
        );

        if ($activatedAt !== null) {
            $this->record($companyId, CompanyMilestone::WorkspaceActivated, $activatedAt);
        }
    }

    /**
     * When this workspace reached each milestone it has reached, keyed by
     * milestone value. Scoped to one workspace, like every read of this ledger.
     *
     * @return array<string, CarbonInterface>
     */
    private function reachedAt(int $companyId): array
    {
        return CompanyMilestoneRecord::query()
            ->where('company_id', $companyId)
            ->pluck('achieved_at', 'milestone')
            ->all();
    }

    /**
     * The latest date among the given milestones, or null when the workspace has
     * not reached all of them — a composite is not partially true.
     *
     * @param  array<string, CarbonInterface>  $reached
     */
    private function latestOf(array $reached, CompanyMilestone ...$milestones): ?CarbonInterface
    {
        $latest = null;

        foreach ($milestones as $milestone) {
            $achievedAt = $reached[$milestone->value] ?? null;

            if ($achievedAt === null) {
                return null;
            }

            if ($latest === null || $achievedAt->greaterThan($latest)) {
                $latest = $achievedAt;
            }
        }

        return $latest;
    }
}
