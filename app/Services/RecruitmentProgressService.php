<?php

namespace App\Services;

use App\Enums\InterviewStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Single source of truth for "how is this hiring process going?".
 *
 * Every surface that answers that question — the overview, the jobs list and the
 * job workspace — reads its counts from here, so the same words always mean the
 * same thing.
 */
class RecruitmentProgressService
{
    /**
     * Relations to eager-count on a job query to obtain a full progress summary
     * in a single round trip.
     *
     * @var list<string>
     */
    public const ProgressCounts = [
        'applications',
        'interviewingApplications',
        'finalStageApplications',
        'hiredApplications',
    ];

    /**
     * @return array{applications: int, interviewing: int, finalists: int, hired: int}
     */
    public function forJob(Job $job): array
    {
        $missing = array_values(array_filter(
            self::ProgressCounts,
            fn (string $relation): bool => $job->getAttribute(Str::snake($relation).'_count') === null,
        ));

        if ($missing !== []) {
            $job->loadCount($missing);
        }

        return [
            'applications' => (int) $job->getAttribute('applications_count'),
            'interviewing' => (int) $job->getAttribute('interviewing_applications_count'),
            'finalists' => (int) $job->getAttribute('final_stage_applications_count'),
            'hired' => (int) $job->getAttribute('hired_applications_count'),
        ];
    }

    /**
     * Whether a job looks stalled: candidates arrived, but nobody has moved into
     * an interview, a late stage or a hire.
     */
    public function isStalled(Job $job): bool
    {
        $progress = $this->forJob($job);

        return $progress['applications'] > 0
            && $progress['interviewing'] === 0
            && $progress['finalists'] === 0
            && $progress['hired'] === 0;
    }

    /**
     * Workspace-wide operational figures for the overview page.
     *
     * @return array{
     *     open_jobs: int,
     *     draft_jobs: int,
     *     open_applications: int,
     *     interviewing: int,
     *     finalists: int,
     *     hired: int,
     *     upcoming_interviews: int,
     *     needs_attention: int
     * }
     */
    public function workspaceSummary(Company $company): array
    {
        $openJobIds = Job::query()
            ->whereBelongsTo($company)
            ->currentlyActive()
            ->pluck('id');

        return [
            'open_jobs' => $openJobIds->count(),
            'draft_jobs' => Job::query()
                ->whereBelongsTo($company)
                ->where('published', false)
                ->count(),
            'open_applications' => Application::query()
                ->whereBelongsTo($company)
                ->whereIn('job_id', $openJobIds)
                ->whereHas('status', fn (Builder $query): Builder => $query->where('is_hired', false))
                ->count(),
            'interviewing' => Application::query()
                ->whereBelongsTo($company)
                ->whereHas(
                    'interviews',
                    fn (Builder $query): Builder => $query->where('status', '!=', InterviewStatus::Cancelled->value),
                )
                ->whereHas('status', fn (Builder $query): Builder => $query->where('is_hired', false))
                ->count(),
            'finalists' => Application::query()
                ->whereBelongsTo($company)
                ->whereHas('status', fn (Builder $query): Builder => $query
                    ->where('is_final_stage', true)
                    ->where('is_hired', false))
                ->count(),
            'hired' => Application::query()
                ->whereBelongsTo($company)
                ->whereHas('status', fn (Builder $query): Builder => $query->where('is_hired', true))
                ->count(),
            'upcoming_interviews' => $this->upcomingInterviewsQuery($company)->count(),
            'needs_attention' => $this->jobsNeedingAttention($company)->count(),
        ];
    }

    /**
     * Published jobs that are not making progress: either nobody applied, or
     * applicants arrived and none of them moved forward.
     *
     * @return Collection<int, Job>
     */
    public function jobsNeedingAttention(Company $company): Collection
    {
        return Job::query()
            ->whereBelongsTo($company)
            ->currentlyActive()
            ->withCount(self::ProgressCounts)
            ->get()
            ->filter(fn (Job $job): bool => (int) $job->getAttribute('interviewing_applications_count') === 0
                && (int) $job->getAttribute('final_stage_applications_count') === 0
                && (int) $job->getAttribute('hired_applications_count') === 0)
            ->values();
    }

    /**
     * @return Builder<Interview>
     */
    public function upcomingInterviewsQuery(Company $company): Builder
    {
        return Interview::query()
            ->whereBelongsTo($company)
            ->where('status', '!=', InterviewStatus::Cancelled->value)
            ->where('ends_at', '>=', CarbonImmutable::now())
            ->orderBy('scheduled_at');
    }
}
