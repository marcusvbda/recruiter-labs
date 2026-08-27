<?php

namespace App\Enums;

use App\Actions\RecordCompanyMilestone;

/**
 * The activation funnel of a workspace, in product terms.
 *
 * Primary milestones correspond to a single real action inside the workspace and
 * are the only ones callers record. Composite milestones summarise a
 * combination of primaries and are derived from the ledger by
 * {@see RecordCompanyMilestone}, so no caller can claim a workspace is set up
 * or activated without the underlying activity having happened.
 */
enum CompanyMilestone: string
{
    case WorkspaceCreated = 'workspace_created';
    case FirstJobCreated = 'first_job_created';
    case FirstCriteriaConfirmed = 'first_criteria_confirmed';
    case FirstApplicationCreated = 'first_application_created';
    case FirstApplicationEvaluated = 'first_application_evaluated';
    case WorkspaceSetupCompleted = 'workspace_setup_completed';
    case WorkspaceActivated = 'workspace_activated';

    public function isComposite(): bool
    {
        return match ($this) {
            self::WorkspaceSetupCompleted, self::WorkspaceActivated => true,
            default => false,
        };
    }

    public function isPrimary(): bool
    {
        return ! $this->isComposite();
    }
}
