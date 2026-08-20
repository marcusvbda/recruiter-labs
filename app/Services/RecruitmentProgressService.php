<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

/**
 * Single source of truth for "how is this hiring process going?".
 *
 * Every surface that answers that question — the overview, the jobs list and the
 * job workspace — reads its counts from here, so the same words always mean the
 * same thing. The meaning of each count lives in the model scopes this service
 * composes ({@see Application::scopeInterviewing()} and friends).
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
        'overdueApplications',
    ];

    /**
     * @return array{applications: int, interviewing: int, finalists: int, hired: int, waiting: int, hiring_target: int, remaining: int, target_reached: bool}
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

        $hired = (int) $job->getAttribute('hired_applications_count');
        // A target below one would make "objective reached" meaningless; the
        // column defaults to 1 and validation enforces it, so this only guards
        // against data written outside the product.
        $target = max(1, $job->hiring_target);

        return [
            'applications' => (int) $job->getAttribute('applications_count'),
            'interviewing' => (int) $job->getAttribute('interviewing_applications_count'),
            'finalists' => (int) $job->getAttribute('final_stage_applications_count'),
            'hired' => $hired,
            'waiting' => (int) $job->getAttribute('overdue_applications_count'),
            'hiring_target' => $target,
            'remaining' => max(0, $target - $hired),
            // Hires are read from the workflow's own hired stages, never inferred
            // from a fit score, a final stage or an interview outcome.
            'target_reached' => $hired >= $target,
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
     * Everything here is scoped to the recruiter's *current* recruitment: only
     * jobs that are active hiring processes today, so a hire from a campaign
     * that ended months ago cannot inflate "what is happening right now". The
     * interview figure is personal — the signed-in recruiter's own commitments.
     *
     * Deliberately no "needs attention" figure: what needs attention is a queue
     * of explainable items, and it is produced by
     * {@see RecruitmentAttentionService}. Two definitions of the same word would
     * drift apart within a release.
     *
     * @return array{
     *     active_jobs: int,
     *     draft_jobs: int,
     *     active_applications: int,
     *     interviewing: int,
     *     finalists: int,
     *     hired: int,
     *     upcoming_interviews: int
     * }
     */
    public function workspaceSummary(Company $company, User $recruiter): array
    {
        $activeJobIds = $this->activeJobIds($company);

        return [
            'active_jobs' => $activeJobIds->count(),
            'draft_jobs' => Job::query()
                ->whereBelongsTo($company)
                ->where('published', false)
                ->count(),
            'active_applications' => $this->activeJobApplications($company, $activeJobIds)->inProcess()->count(),
            'interviewing' => $this->activeJobApplications($company, $activeJobIds)->interviewing()->count(),
            'finalists' => $this->activeJobApplications($company, $activeJobIds)->inFinalStage()->count(),
            'hired' => $this->activeJobApplications($company, $activeJobIds)->hired()->count(),
            'upcoming_interviews' => $this->upcomingInterviewsQuery($company, $recruiter)->count(),
        ];
    }

    /**
     * Interviews still to be kept. Passing a recruiter narrows it to the ones
     * they personally own, which is what the overview promises; the agenda page
     * omits it and applies its own recruiter filter instead.
     *
     * @return Builder<Interview>
     */
    public function upcomingInterviewsQuery(Company $company, ?User $recruiter = null): Builder
    {
        return Interview::query()
            ->whereBelongsTo($company)
            ->upcoming()
            ->when(
                $recruiter !== null,
                fn (Builder $query): Builder => $query->where('calendar_user_id', $recruiter->getKey()),
            )
            ->orderBy('scheduled_at');
    }

    /**
     * @return SupportCollection<int, int>
     */
    private function activeJobIds(Company $company): SupportCollection
    {
        return Job::query()
            ->whereBelongsTo($company)
            ->currentlyActive()
            ->pluck('id');
    }

    /**
     * @param  SupportCollection<int, int>  $activeJobIds
     * @return Builder<Application>
     */
    private function activeJobApplications(Company $company, SupportCollection $activeJobIds): Builder
    {
        return Application::query()
            ->whereBelongsTo($company)
            ->whereIn('job_id', $activeJobIds);
    }
}
