<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Exceptions\PlanLimitExceededException;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Widgets\JobApplicationStatusChart;
use App\Filament\Resources\Jobs\Widgets\JobOverviewStats;
use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Services\ApplicationAvailabilityService;
use App\Services\JobDashboardService;
use App\Services\LimitManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use LogicException;

class ViewJob extends ViewRecord
{
    protected static string $resource = JobResource::class;

    protected JobDashboardService $jobDashboardService;

    protected ApplicationAvailabilityService $applicationAvailabilityService;

    protected LimitManager $limitManager;

    public function boot(
        JobDashboardService $jobDashboardService,
        ApplicationAvailabilityService $applicationAvailabilityService,
        LimitManager $limitManager,
    ): void {
        $this->jobDashboardService = $jobDashboardService;
        $this->applicationAvailabilityService = $applicationAvailabilityService;
        $this->limitManager = $limitManager;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getJob()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $job = $this->getJob();
        $dashboard = $this->jobDashboardService->get($job);

        return $schema
            ->components([
                Tabs::make('job-view-tabs')
                    ->tabs([
                        Tab::make(__('jobs.view_tabs.dashboard'))
                            ->icon(Heroicon::OutlinedPresentationChartBar)
                            ->schema([
                                View::make('filament.resources.jobs.components.dashboard-introduction')
                                    ->viewData([
                                        'job' => $job,
                                        'dashboard' => $dashboard,
                                        'publicUrl' => route('job.show', ['key' => $job->key]),
                                    ]),
                                Livewire::make(JobOverviewStats::class, [
                                    'record' => $job,
                                    'metrics' => [
                                        'clicks_count' => $dashboard['clicks_count'],
                                        'applications_count' => $dashboard['applications_count'],
                                        'running_days' => $dashboard['running_days'],
                                        'remaining_days' => $dashboard['remaining_days'],
                                        'has_ended' => $dashboard['has_ended'],
                                    ],
                                ])->key("job-overview-stats-{$job->getKey()}"),
                                Grid::make([
                                    'default' => 1,
                                    'xl' => 2,
                                ])->schema([
                                    Livewire::make(JobApplicationStatusChart::class, [
                                        'record' => $job,
                                        'statusDistribution' => $dashboard['status_distribution'],
                                    ])->key("job-status-chart-{$job->getKey()}"),
                                    View::make('filament.resources.jobs.components.dashboard-details')
                                        ->viewData([
                                            'job' => $job,
                                            'dashboard' => $dashboard,
                                        ]),
                                ]),
                                View::make('filament.resources.jobs.components.dashboard-rankings')
                                    ->viewData([
                                        'utmRanking' => $dashboard['utm_ranking'],
                                        'ipRanking' => $dashboard['ip_ranking'],
                                    ]),
                            ]),
                        Tab::make(__('jobs.view_tabs.pipeline'))
                            ->icon(Heroicon::OutlinedViewColumns)
                            ->schema([
                                Actions::make([
                                    $this->makeAddCandidateAction(),
                                ])
                                    ->key('pipeline-actions')
                                    ->alignment(Alignment::End),
                                Livewire::make(JobPipelineKanban::class, ['record' => $job])
                                    ->key("job-pipeline-{$job->getKey()}"),
                            ]),
                    ])
                    ->persistTabInQueryString('section')
                    ->columnSpanFull(),
            ]);
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
                $this->ensureCanAddApplication($job);

                $candidate = Candidate::query()
                    ->where('company_id', $job->company_id)
                    ->whereKey($data['candidate_id'])
                    ->whereDoesntHave(
                        'applications',
                        fn (Builder $query): Builder => $query->where('job_id', $job->id),
                    )
                    ->first();

                if (! $candidate) {
                    Notification::make()
                        ->title(__('applications.pipeline.already_added'))
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $firstStatus = $this->applicationAvailabilityService->initialStatus($job);
                } catch (ValidationException) {
                    Notification::make()
                        ->title(__('applications.pipeline.no_statuses'))
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    Application::query()->create([
                        'company_id' => $job->company_id,
                        'job_id' => $job->id,
                        'candidate_id' => $candidate->id,
                        'status_id' => $firstStatus->id,
                    ]);
                } catch (QueryException) {
                    Notification::make()
                        ->title(__('applications.pipeline.already_added'))
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

    private function ensureCanAddApplication(Job $job): void
    {
        $company = $job->company;

        abort_unless($company instanceof Company, 404);

        try {
            $this->limitManager->ensureCanReceiveApplication($company);
        } catch (PlanLimitExceededException $exception) {
            Notification::make()
                ->title(__('settings.plan.limit_reached'))
                ->body($exception->getMessage())
                ->warning()
                ->actions([
                    Action::make('managePlan')
                        ->label(__('settings.topbar.manage_plan'))
                        ->url(Settings::getUrl(['section' => 'plan'], tenant: $company))
                        ->button(),
                ])
                ->send();

            throw new Halt;
        }
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
