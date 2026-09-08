<?php

namespace App\Enums;

use App\Models\Application;

/**
 * Where a job's sourcing search stands operationally.
 *
 * There is deliberately no persisted `Outdated` or `Blocked` case. Whether the
 * stored result still describes the confirmed criteria is derived by comparing
 * the recorded criteria revision against the job's current one — the same way
 * {@see Application::hasCurrentEvaluation()} derives it — so a
 * criteria change cannot leave a stale flag claiming otherwise.
 *
 * A run that stopped early on quota or failure must end in {@see self::Failed}
 * or {@see self::PendingQuota}, never {@see self::Completed}: partial results
 * may stay visible, but the search must not imply it saw the whole workspace.
 */
enum SourcingSearchStatus: string
{
    /** No search has ever run for this job. */
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Processing = 'processing';

    /** Every eligible candidate was considered and the result is complete. */
    case Completed = 'completed';
    case Failed = 'failed';

    /** Stopped early because the workspace's AI allowance ran out. */
    case PendingQuota = 'pending_quota';

    /** Whether a search is queued or currently running. */
    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
