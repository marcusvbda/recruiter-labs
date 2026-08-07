<?php

namespace App\Console\Commands;

use App\Actions\ScheduleApplicationFitAnalysis;
use App\Enums\ApplicationAnalysisStatus;
use App\Models\Application;
use Illuminate\Console\Command;

class BackfillApplicationFitAnalysis extends Command
{
    protected $signature = 'applications:backfill-fit-analysis';

    protected $description = 'Schedule fit analysis for applications left stuck in the old default status from before this feature existed';

    public function handle(ScheduleApplicationFitAnalysis $scheduleApplicationFitAnalysis): int
    {
        // `analysis_status = Pending` with `analysis_generation = 0` is impossible to
        // produce under the current system: `ScheduleApplicationFitAnalysis::handle()`
        // always increments the generation in the same write that sets status to Pending.
        // Rows matching both conditions are leftover data from before this feature existed
        // and were never actually dispatched to any queue.
        $staleApplications = Application::query()
            ->where('analysis_status', ApplicationAnalysisStatus::Pending)
            ->where('analysis_generation', 0)
            ->get();

        if ($staleApplications->isEmpty()) {
            $this->components->info('No stale applications found.');

            return self::SUCCESS;
        }

        $scheduled = 0;
        $awaitingCriteria = 0;

        foreach ($staleApplications as $application) {
            $scheduleApplicationFitAnalysis->handle($application);

            match ($application->refresh()->analysis_status) {
                ApplicationAnalysisStatus::Pending => $scheduled++,
                ApplicationAnalysisStatus::AwaitingCriteria => $awaitingCriteria++,
                default => null,
            };
        }

        $this->components->info(
            "{$scheduled} scheduled for analysis, {$awaitingCriteria} still awaiting job criteria."
        );

        return self::SUCCESS;
    }
}
