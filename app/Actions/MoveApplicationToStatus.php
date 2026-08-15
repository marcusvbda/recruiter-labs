<?php

namespace App\Actions;

use App\Events\ApplicationEnteredStatus;
use App\Exceptions\RecruitmentWorkflowException;
use App\Models\Application;
use App\Models\Job;
use App\Models\Status;
use Illuminate\Support\Facades\DB;

/**
 * The single path through which an application changes status. Every caller —
 * Kanban drag and drop, the application detail page, anything added later — goes
 * through here so integrity checks and the resulting communication can never be
 * bypassed by a plain `update(['status_id' => ...])`.
 */
class MoveApplicationToStatus
{
    public function handle(Application $application, Status $status): Application
    {
        return DB::transaction(function () use ($application, $status): Application {
            $lockedApplication = Application::query()
                ->withoutGlobalScopes()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $job = Job::query()->whereKey($lockedApplication->job_id)->firstOrFail();

            if ((int) $status->company_id !== (int) $lockedApplication->company_id) {
                throw RecruitmentWorkflowException::crossTenantStatus();
            }

            if ((int) $status->pipeline_id !== (int) $job->pipeline_id) {
                throw RecruitmentWorkflowException::crossPipelineStatus();
            }

            // Re-entering the same status is a no-op: no write, no event, and
            // therefore no duplicate email.
            if ((int) $lockedApplication->status_id === (int) $status->getKey()) {
                return $lockedApplication;
            }

            $previousStatusId = (int) $lockedApplication->status_id;

            $lockedApplication->status()->associate($status);
            $lockedApplication->save();

            ApplicationEnteredStatus::dispatch(
                (int) $lockedApplication->getKey(),
                (int) $status->getKey(),
                $previousStatusId,
            );

            return $lockedApplication;
        });
    }
}
