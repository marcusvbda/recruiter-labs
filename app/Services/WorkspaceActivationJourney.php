<?php

namespace App\Services;

use App\Actions\RecordCompanyMilestone;
use App\Data\WorkspaceActivationProgress;
use App\Enums\ApplicationAnalysisStatus;
use App\Enums\CompanyMilestone;
use App\Enums\ConnectedIntegrationStatus;
use App\Filament\Clusters\Settings\Pages\CalendarSettings;
use App\Filament\Clusters\Settings\Pages\EmailProviderSettings;
use App\Filament\Clusters\Settings\Pages\TeamSettings;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyMilestone as CompanyMilestoneRecord;
use App\Models\ConnectedIntegration;
use App\Models\Job;
use App\Models\User;
use App\Policies\CompanyPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Single source of truth for "how far is this workspace from its first
 * evidence-backed evaluation?".
 *
 * Every onboarding surface — the overview checklist, the welcome experience and
 * the floating launcher — reads its progress from here, so they cannot disagree
 * about what is done or about what to do next.
 *
 * Completion is read from the milestone ledger and never recomputed from live
 * recruitment data. Onboarding records that a workspace *has* reached a
 * milestone, so archiving the first job, closing the first application or
 * re-running an evaluation must not take that history back; deriving completion
 * from the current state of those records would do exactly that.
 *
 * The service is read-only and writes nothing: the ledger has one writer
 * ({@see RecordCompanyMilestone}), fed by the real product actions.
 */
class WorkspaceActivationJourney
{
    /**
     * The primary journey, in the order the product presents it, mapped to the
     * milestone that completes each step.
     */
    private const array PrimarySteps = [
        'workspace_created' => CompanyMilestone::WorkspaceCreated,
        'create_first_job' => CompanyMilestone::FirstJobCreated,
        'confirm_hiring_criteria' => CompanyMilestone::FirstCriteriaConfirmed,
        'add_first_application' => CompanyMilestone::FirstApplicationCreated,
        'evaluate_first_application' => CompanyMilestone::FirstApplicationEvaluated,
    ];

    /**
     * How many of the workspace's jobs are inspected when choosing which job a
     * CTA should open. A workspace with more jobs than this has long since
     * passed the steps that need the choice, and it still degrades to its
     * oldest job rather than to nothing.
     */
    private const int JobLookupLimit = 25;

    /** Integration plugin keys, matching the settings pages that own them. */
    private const string GoogleCalendarPluginKey = 'google-calendar';

    private const string GmailPluginKey = 'gmail';

    /**
     * Jobs already read, per workspace. Keyed by workspace so one instance
     * serving two workspaces in the same request cannot answer for the wrong
     * one.
     *
     * @var array<int, Collection<int, Job>>
     */
    private array $jobsByCompany = [];

    public function for(Company $company, User $user): WorkspaceActivationProgress
    {
        // One pass over the ledger answers every question below, and it is
        // scoped to the workspace Filament resolved as tenant: activation
        // progress belongs to one workspace and is never read across two.
        $reached = $this->reachedMilestones($company);

        return new WorkspaceActivationProgress(
            primarySteps: $this->primarySteps($company, $reached),
            optionalSteps: $this->optionalSteps($company, $user),
            setupComplete: in_array(CompanyMilestone::WorkspaceSetupCompleted->value, $reached, true),
            activated: in_array(CompanyMilestone::WorkspaceActivated->value, $reached, true),
        );
    }

    /**
     * The milestone values this workspace has reached.
     *
     * @return list<string>
     */
    private function reachedMilestones(Company $company): array
    {
        $milestones = CompanyMilestoneRecord::query()
            ->where('company_id', $company->getKey())
            ->pluck('milestone')
            ->all();

        $reached = [];

        foreach ($milestones as $milestone) {
            $reached[] = $milestone->value;
        }

        return $reached;
    }

    /**
     * @param  list<string>  $reached
     * @return list<array{key: string, is_complete: bool, url: string|null}>
     */
    private function primarySteps(Company $company, array $reached): array
    {
        $steps = [];

        foreach (self::PrimarySteps as $key => $milestone) {
            $isComplete = in_array($milestone->value, $reached, true);

            $steps[] = [
                'key' => $key,
                'is_complete' => $isComplete,
                // A step's URL is where the workspace goes to complete it, so a
                // step that is already complete carries none — and neither does
                // the workspace itself, which asks nothing of the user. This
                // also keeps an activated workspace from paying for lookups
                // whose answer no surface would show.
                'url' => $isComplete ? null : $this->stepUrl($company, $key),
            ];
        }

        return $steps;
    }

    /**
     * Where each remaining step leads. Always an existing product flow: the
     * onboarding layer points at the real screens instead of offering its own
     * version of job creation, criteria confirmation or application intake.
     */
    private function stepUrl(Company $company, string $key): ?string
    {
        return match ($key) {
            'create_first_job' => JobResource::getUrl('create', tenant: $company),
            'confirm_hiring_criteria' => $this->criteriaConfirmationUrl($company),
            'add_first_application' => $this->applicationIntakeUrl($company),
            'evaluate_first_application' => $this->evaluationUrl($company),
            default => null,
        };
    }

    /**
     * The job edit screen, where the existing criteria review and confirmation
     * live. Preference goes to a job whose criteria are already waiting to be
     * reviewed — that one can be confirmed immediately — then to the oldest job
     * still without confirmed criteria, and finally to the jobs list when the
     * workspace has no job to confirm anything for.
     */
    private function criteriaConfirmationUrl(Company $company): string
    {
        $jobs = $this->jobs($company);

        $job = $jobs->first(fn (Job $job): bool => $job->criteriaAwaitReview())
            ?? $jobs->first(fn (Job $job): bool => ! $job->hasConfirmedCriteria())
            ?? $jobs->first();

        return $job instanceof Job
            ? JobResource::getUrl('edit', ['record' => $job], tenant: $company)
            : JobResource::getUrl(tenant: $company);
    }

    /**
     * The job's pipeline board: the one existing place where a first
     * application can actually arrive, either by adding a candidate already in
     * the workspace or by sharing the public job page from the workspace
     * header. Preference goes to a job whose criteria are confirmed, because an
     * application there can be evaluated straight away, which is the step after
     * this one.
     */
    private function applicationIntakeUrl(Company $company): string
    {
        $jobs = $this->jobs($company);

        $job = $jobs->first(fn (Job $job): bool => $job->hasConfirmedCriteria()) ?? $jobs->first();

        return $job instanceof Job
            ? JobResource::getUrl('view', ['record' => $job, 'section' => 'pipeline'], tenant: $company)
            : JobResource::getUrl(tenant: $company);
    }

    /**
     * The application workspace, where an evaluation is read and can be
     * requested again if it has not produced a result yet. The oldest
     * application without a completed evaluation is the one waiting longest;
     * without one, the workspace still has no application, so this falls back
     * to where a first application comes from.
     */
    private function evaluationUrl(Company $company): string
    {
        $application = Application::query()
            ->where('company_id', $company->getKey())
            ->where('analysis_status', '!=', ApplicationAnalysisStatus::Completed->value)
            ->oldest()
            ->first();

        return $application instanceof Application
            ? ApplicationResource::getUrl('view', ['record' => $application], tenant: $company)
            : $this->applicationIntakeUrl($company);
    }

    /**
     * The workspace's jobs, oldest first, read once per call. Which job a CTA
     * should open is decided with the job model's own criteria semantics
     * ({@see Job::hasConfirmedCriteria()}, {@see Job::criteriaAwaitReview()})
     * rather than a second definition of "confirmed" written in SQL here.
     *
     * @return Collection<int, Job>
     */
    private function jobs(Company $company): Collection
    {
        return $this->jobsByCompany[(int) $company->getKey()] ??= Job::query()
            ->where('company_id', $company->getKey())
            ->oldest()
            ->limit(self::JobLookupLimit)
            ->get();
    }

    /**
     * Optional setup, kept apart from the journey. Each entry says whether the
     * workspace has done it and whether *this* user may do it, and the answer
     * to the second question comes from the abilities that already guard the
     * underlying pages ({@see CompanyPolicy}). Onboarding must not become a
     * second authorization path: a member who cannot invite is shown the step
     * without an actionable CTA rather than being sent somewhere that will
     * refuse them.
     *
     * @return list<array{key: string, is_done: bool, is_actionable: bool, url: string}>
     */
    private function optionalSteps(Company $company, User $user): array
    {
        $connectedPlugins = $this->connectedIntegrationPlugins($company, $user);
        $canConfigureWorkspace = Gate::forUser($user)->allows('update', $company);

        return [
            [
                'key' => 'invite_teammate',
                'is_done' => $company->users()->count() > 1,
                'is_actionable' => Gate::forUser($user)->allows('manageTeam', $company),
                'url' => TeamSettings::getUrl(tenant: $company),
            ],
            [
                'key' => 'connect_calendar',
                'is_done' => in_array(self::GoogleCalendarPluginKey, $connectedPlugins, true),
                'is_actionable' => $canConfigureWorkspace,
                'url' => CalendarSettings::getUrl(tenant: $company),
            ],
            [
                'key' => 'connect_email',
                'is_done' => in_array(self::GmailPluginKey, $connectedPlugins, true),
                'is_actionable' => $canConfigureWorkspace,
                'url' => EmailProviderSettings::getUrl(tenant: $company),
            ],
        ];
    }

    /**
     * Which integrations count as connected here, read exactly as the settings
     * pages read them: a credential belongs to one user inside one workspace,
     * so a colleague's calendar cannot make this user's step look done while
     * their own page still says disconnected.
     *
     * @return list<string>
     */
    private function connectedIntegrationPlugins(Company $company, User $user): array
    {
        $pluginKeys = ConnectedIntegration::query()
            ->where('company_id', $company->getKey())
            ->where('user_id', $user->getKey())
            ->whereIn('plugin_key', [self::GoogleCalendarPluginKey, self::GmailPluginKey])
            ->where('status', ConnectedIntegrationStatus::Connected->value)
            ->pluck('plugin_key')
            ->all();

        $connected = [];

        foreach ($pluginKeys as $pluginKey) {
            $connected[] = (string) $pluginKey;
        }

        return $connected;
    }
}
