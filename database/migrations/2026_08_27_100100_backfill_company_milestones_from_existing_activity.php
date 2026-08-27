<?php

use App\Actions\RecordCompanyMilestone;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CompanyMilestone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Workspaces that were already recruiting before the milestone ledger existed
     * must not be presented as unfinished new workspaces. Their activation
     * history is reconstructed from the recruitment activity itself, dated from
     * the earliest real timestamp of each action rather than from the moment this
     * migration runs, so the funnel reads as what actually happened.
     *
     * Insert-only, through the same unique constraint the runtime writer relies
     * on: an existing ledger row always wins, nothing is updated or deleted, and
     * a second run inserts nothing. Only the five primary milestones are derived
     * from activity; the two composites are derived from those the same way
     * {@see RecordCompanyMilestone} derives them, so a workspace that
     * already did everything comes out fully activated.
     */
    public function up(): void
    {
        $jobActivity = DB::table('job_postings')
            ->selectRaw('company_id, MIN(created_at) as first_job_at, MIN(criteria_confirmed_at) as first_confirmed_at')
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        $applicationActivity = DB::table('applications')
            ->selectRaw('company_id, MIN(created_at) as first_application_at')
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        // `analyzed_at` is the only column that states when an evaluation was
        // completed. Where a completed analysis predates that column being
        // stamped, `updated_at` is the closest honest fallback — the row's last
        // change, which for a finished evaluation is at or after it — and
        // `created_at` is the final floor, never a date invented from today.
        $evaluationActivity = DB::table('applications')
            ->where('analysis_status', ApplicationAnalysisStatus::Completed->value)
            ->selectRaw('company_id, MIN(COALESCE(analyzed_at, updated_at, created_at)) as first_evaluated_at')
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        $rows = [];
        $recordedAt = CarbonImmutable::now();

        DB::table('companies')->orderBy('id')->each(function (object $company) use (
            $jobActivity,
            $applicationActivity,
            $evaluationActivity,
            $recordedAt,
            &$rows,
        ): void {
            $companyId = (int) $company->id;
            $jobs = $jobActivity->get($companyId);
            $applications = $applicationActivity->get($companyId);
            $evaluations = $evaluationActivity->get($companyId);

            $reached = array_filter([
                // A workspace exists from the moment it was created, so this one
                // is true for every company without exception.
                CompanyMilestone::WorkspaceCreated->value => $this->at($company->created_at ?? null),
                CompanyMilestone::FirstJobCreated->value => $this->at($jobs->first_job_at ?? null),
                // Only a recorded confirmation counts. The confirmation columns
                // were introduced by pushing every previously completed criteria
                // set back to review precisely because no human had confirmed it,
                // so the existence of criteria rows proves nothing here.
                CompanyMilestone::FirstCriteriaConfirmed->value => $this->at($jobs->first_confirmed_at ?? null),
                CompanyMilestone::FirstApplicationCreated->value => $this->at($applications->first_application_at ?? null),
                CompanyMilestone::FirstApplicationEvaluated->value => $this->at($evaluations->first_evaluated_at ?? null),
            ]);

            $setupCompletedAt = $this->latestOf(
                $reached,
                CompanyMilestone::FirstJobCreated,
                CompanyMilestone::FirstCriteriaConfirmed,
            );

            if ($setupCompletedAt !== null) {
                $reached[CompanyMilestone::WorkspaceSetupCompleted->value] = $setupCompletedAt;
            }

            $activatedAt = $this->latestOf(
                $reached,
                CompanyMilestone::WorkspaceSetupCompleted,
                CompanyMilestone::FirstApplicationCreated,
                CompanyMilestone::FirstApplicationEvaluated,
            );

            if ($activatedAt !== null) {
                $reached[CompanyMilestone::WorkspaceActivated->value] = $activatedAt;
            }

            foreach ($reached as $milestone => $achievedAt) {
                $rows[] = [
                    'company_id' => $companyId,
                    'milestone' => $milestone,
                    'achieved_at' => $achievedAt,
                    'created_at' => $recordedAt,
                ];
            }
        });

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('company_milestones')->insertOrIgnore($chunk);
        }
    }

    /**
     * Not reversible. Nothing distinguishes a row this backfill inserted from one
     * the product recorded from a real action — including rows recorded after it
     * ran — so deleting anything here would destroy genuine workspace history to
     * undo a derivation. Dropping the table is the migration that owns it.
     */
    public function down(): void {}

    private function at(mixed $timestamp): ?CarbonImmutable
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        return CarbonImmutable::parse($timestamp);
    }

    /**
     * The latest of the given milestones, or null when one of them is missing: a
     * composite is not partially true.
     *
     * @param  array<string, CarbonImmutable>  $reached
     */
    private function latestOf(array $reached, CompanyMilestone ...$milestones): ?CarbonImmutable
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
};
