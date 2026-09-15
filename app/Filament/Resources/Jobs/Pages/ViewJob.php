<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Actions\AddCandidateToJob;
use App\Actions\RunSourcingSearch;
use App\Data\RecruitmentAttentionQueue;
use App\Enums\AiExecutionOrigin;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\RecruitmentWorkflowException;
use App\Filament\Clusters\Settings\Pages\PlanSettings;
use App\Filament\Resources\Jobs\Actions\JobStateActions;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Widgets\JobApplicationStatusChart;
use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Filament\Resources\Jobs\Widgets\JobSourcingPanel;
use App\Filament\Resources\Jobs\Widgets\JobTrafficStats;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\SourcingSearch;
use App\Models\User;
use App\Services\CandidateSourcingEligibilityService;
use App\Services\JobDashboardService;
use App\Services\RecruitmentAttentionService;
use App\Services\RecruitmentProgressService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use LogicException;

class ViewJob extends ViewRecord
{
    protected static string $resource = JobResource::class;

    protected JobDashboardService $jobDashboardService;

    protected AddCandidateToJob $addCandidateToJob;

    protected RecruitmentProgressService $recruitmentProgressService;

    protected RecruitmentAttentionService $recruitmentAttentionService;

    public function boot(
        JobDashboardService $jobDashboardService,
        AddCandidateToJob $addCandidateToJob,
        RecruitmentProgressService $recruitmentProgressService,
        RecruitmentAttentionService $recruitmentAttentionService,
    ): void {
        $this->jobDashboardService = $jobDashboardService;
        $this->addCandidateToJob = $addCandidateToJob;
        $this->recruitmentProgressService = $recruitmentProgressService;
        $this->recruitmentAttentionService = $recruitmentAttentionService;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getJob()->name;
    }

    /**
     * The workspace summary can start the sourcing work it identifies. This
     * repeats the same authorization and state gates as the sourcing panel
     * because summary data can be stale by the time a recruiter clicks it.
     */
    public function runSourcingFromAttention(): void
    {
        $company = Filament::getTenant();
        $recruiter = Filament::auth()->user();

        abort_unless($company instanceof Company && $recruiter instanceof User, 403);

        $job = Job::query()
            ->whereBelongsTo($company)
            ->whereKey($this->getJob()->getKey())
            ->with('sourcingSearch')
            ->firstOrFail();

        abort_unless(JobResource::canEdit($job), 403);

        $search = $job->sourcingSearch;

        if ($search instanceof SourcingSearch && $search->status->isInProgress()) {
            return;
        }

        $job->load('jobCriteria');

        if (! $job->hasConfirmedCriteria() || $job->jobCriteria->isEmpty()) {
            return;
        }

        if (app(CandidateSourcingEligibilityService::class)->eligibleCandidateCount($job) === 0) {
            return;
        }

        app(RunSourcingSearch::class)->handle(
            $job,
            (int) $recruiter->getKey(),
            AiExecutionOrigin::UserRequested,
            'sourcing_requested_from_attention',
        );

        Notification::make()
            ->title(__('sourcing.panel.search_started'))
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        $job = $this->getJob();

        return [
            EditAction::make(),
            JobStateActions::publish(),
            JobStateActions::unpublish(),
            Action::make('openPublicPage')
                ->label(__('jobs.workspace.open_public_page'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(route('job.show', ['key' => $job->key]))
                ->openUrlInNewTab(),
            ActionGroup::make([
                Action::make('openPipeline')
                    ->label(__('jobs.view_tabs.pipeline'))
                    ->icon(Heroicon::OutlinedViewColumns)
                    ->url(static::getResource()::getUrl('view', ['record' => $job, 'section' => 'pipeline'])),
                JobStateActions::duplicate(),
            ]),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $job = $this->getJob();
        $dashboard = $this->jobDashboardService->get($job);

        return $schema
            ->components([
                View::make('filament.resources.jobs.components.workspace-summary')
                    ->viewData(['summary' => $this->summaryData($job)])
                    ->columnSpanFull(),
                Tabs::make('job-view-tabs')
                    ->tabs([
                        Tab::make(__('jobs.view_tabs.overview'))
                            ->id('overview')
                            ->key('overview')
                            ->icon(Heroicon::OutlinedPresentationChartBar)
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'xl' => 2,
                                ])->schema([
                                    Livewire::make(JobApplicationStatusChart::class, [
                                        'record' => $job,
                                        'statusDistribution' => $dashboard['status_distribution'],
                                    ])->key("job-status-chart-{$job->getKey()}"),
                                    View::make('filament.resources.jobs.components.overview-details')
                                        ->viewData([
                                            'details' => [
                                                'hired_count' => $dashboard['hired_count'],
                                                'pipeline_name' => $job->pipeline->name,
                                                'pipeline_url' => PipelineResource::getUrl('edit', ['record' => $job->pipeline]),
                                                'stages' => $dashboard['status_distribution'],
                                            ],
                                        ]),
                                ]),
                            ]),
                        Tab::make(__('jobs.view_tabs.sourcing'))
                            ->id('sourcing')
                            ->key('sourcing')
                            ->icon(Heroicon::OutlinedMagnifyingGlass)
                            ->schema(
                                $job->hasConfirmedCriteria()
                                    ? [
                                        Livewire::make(JobSourcingPanel::class, ['record' => $job])
                                            ->key("job-sourcing-{$job->getKey()}"),
                                    ]
                                    : [
                                        View::make('filament.resources.jobs.components.sourcing-not-confirmed')
                                            ->viewData([
                                                'confirmCriteriaUrl' => static::getResource()::getUrl('edit', ['record' => $job]),
                                            ]),
                                    ],
                            ),
                        Tab::make(__('jobs.view_tabs.pipeline'))
                            ->id('pipeline')
                            ->key('pipeline')
                            ->icon(Heroicon::OutlinedViewColumns)
                            ->schema([
                                // Lifted onto the tab row by CSS rather than
                                // sitting in a band of its own beneath it — see
                                // `.rl-workspace-tabs` in the panel theme. The
                                // end alignment is the small-screen fallback,
                                // where it stays stacked under the tabs.
                                Actions::make([
                                    $this->makeAddCandidateAction(),
                                ])
                                    ->key('pipeline-actions')
                                    ->alignment(Alignment::End)
                                    ->extraAttributes(['class' => 'rl-workspace-tabs__actions']),
                                Livewire::make(JobPipelineKanban::class, ['record' => $job])
                                    ->key("job-pipeline-{$job->getKey()}"),
                            ]),
                        Tab::make(__('jobs.view_tabs.analytics'))
                            ->id('analytics')
                            ->key('analytics')
                            ->icon(Heroicon::OutlinedMegaphone)
                            ->schema([
                                Livewire::make(JobTrafficStats::class, [
                                    'record' => $job,
                                    'metrics' => [
                                        'clicks_count' => $dashboard['clicks_count'],
                                        'running_days' => $dashboard['running_days'],
                                        'remaining_days' => $dashboard['remaining_days'],
                                        'has_ended' => $dashboard['has_ended'],
                                    ],
                                ])->key("job-traffic-stats-{$job->getKey()}"),
                                View::make('filament.resources.jobs.components.analytics-rankings')
                                    ->viewData([
                                        'utmRanking' => $dashboard['utm_ranking'],
                                    ]),
                            ]),
                    ])
                    ->persistTabInQueryString('section')
                    ->extraAttributes(['class' => 'rl-workspace-tabs'])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The persistent header of the job workspace: is this process healthy, and
     * if not, what exactly is wrong. Visible before any tab is opened.
     *
     * Progress figures are metrics; the attention list is work. Both are here,
     * but they stay distinguishable, and every attention row links either to the
     * board or to the affected application rather than describing a problem the
     * recruiter then has to go hunting for.
     *
     * @return array<string, mixed>
     */
    private function summaryData(Job $job): array
    {
        $progress = $this->recruitmentProgressService->forJob($job);
        $recruiter = Filament::auth()->user();
        $attention = $recruiter instanceof User
            ? $this->recruitmentAttentionService->forJob($job, $recruiter)
            : RecruitmentAttentionQueue::empty();

        return [
            'state_label' => match (true) {
                ! $job->published => __('jobs.state.draft'),
                $job->applications_paused => __('jobs.state.paused'),
                default => __('jobs.state.published'),
            },
            'state_color' => match (true) {
                ! $job->published => 'gray',
                $job->applications_paused => 'warning',
                default => 'success',
            },
            'key' => $job->key,
            'pipeline_name' => $job->pipeline->name,
            'pipeline_url' => PipelineResource::getUrl('edit', ['record' => $job->pipeline]),
            'pipeline_board_url' => static::getResource()::getUrl('view', ['record' => $job, 'section' => 'pipeline']),
            'metrics' => [
                [
                    'label' => __('jobs.progress.metrics.applications'),
                    'value' => $progress['applications'],
                    'color' => 'text-gray-950 dark:text-white',
                ],
                [
                    'label' => __('jobs.progress.metrics.interviewing'),
                    'value' => $progress['interviewing'],
                    'color' => 'text-info-600 dark:text-info-400',
                ],
                [
                    'label' => __('jobs.progress.metrics.finalists'),
                    'value' => $progress['finalists'],
                    'color' => 'text-warning-600 dark:text-warning-400',
                ],
                [
                    // The only metric shown against a goal: "1 hired" means
                    // something different on a job hiring one and a job hiring four.
                    'label' => __('jobs.progress.metrics.hired'),
                    'value' => $progress['hired'],
                    'target' => $progress['hiring_target'],
                    'color' => 'text-success-600 dark:text-success-400',
                ],
            ],
            'hiring' => [
                'hired' => $progress['hired'],
                'target' => $progress['hiring_target'],
                'remaining' => $progress['remaining'],
                'target_reached' => $progress['target_reached'],
            ],
            'waiting' => $progress['waiting'],
            'attention' => $attention->toArray(),
            'attention_hidden_count' => $attention->hiddenCount(),
        ];
    }

    private function makeAddCandidateAction(): Action
    {
        return Action::make('addCandidate')
            ->label(__('applications.pipeline.add_candidate'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->schema([
                Select::make('candidate_id')
                    ->label(__('applications.pipeline.select_candidate'))
                    ->options(fn (): array => Candidate::query()
                        ->where('company_id', $this->getJob()->company_id)
                        ->whereDoesntHave(
                            'applications',
                            fn (Builder $query): Builder => $query->where('job_id', $this->getJob()->id),
                        )
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $job = $this->getJob();

                $candidate = Candidate::query()
                    ->where('company_id', $job->company_id)
                    ->whereKey($data['candidate_id'])
                    ->first();

                if (! $candidate instanceof Candidate) {
                    Notification::make()
                        ->title(__('applications.pipeline.already_added'))
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $this->addCandidateToJob->handle($job, $candidate);
                } catch (PlanLimitExceededException $exception) {
                    $this->notifyPlanLimitReached($job, $exception);

                    throw new Halt;
                } catch (RecruitmentWorkflowException) {
                    Notification::make()
                        ->title(__('applications.pipeline.already_added'))
                        ->danger()
                        ->send();

                    return;
                } catch (ValidationException) {
                    Notification::make()
                        ->title(__('applications.pipeline.no_statuses'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('applications.pipeline.candidate_added'))
                    ->success()
                    ->send();

                $this->dispatch('pipeline-updated')->to(JobPipelineKanban::class);
            });
    }

    private function notifyPlanLimitReached(Job $job, PlanLimitExceededException $exception): void
    {
        $company = $job->company;

        abort_unless($company instanceof Company, 404);

        Notification::make()
            ->title(__('settings.plan.limit_reached'))
            ->body($exception->getMessage())
            ->warning()
            ->actions([
                Action::make('managePlan')
                    ->label(__('settings.topbar.manage_plan'))
                    ->url(PlanSettings::getUrl(tenant: $company))
                    ->button(),
            ])
            ->send();
    }

    private function getJob(): Job
    {
        $record = $this->getRecord();

        if (! $record instanceof Job) {
            throw new LogicException('The job view page must be bound to a job.');
        }

        return $record;
    }
}
