<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Widgets\JobPipelineKanban;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use LogicException;

class JobPipeline extends Page
{
    // `HasActions`/`HasSchemas` and their `InteractsWithActions`/
    // `InteractsWithSchemas` traits are already provided by the base
    // `Filament\Pages\BasePage` class — re-declaring them here would collide
    // with `InteractsWithRecord::afterActionCalled()`.
    use InteractsWithRecord;

    protected static string $resource = JobResource::class;

    protected string $view = 'filament.resources.jobs.pages.job-pipeline';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(JobResource::canEdit($this->getJob()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return __('applications.pipeline.title', ['job' => $this->getJob()->name]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addCandidate')
                ->label(__('applications.pipeline.add_candidate'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->schema([
                    Select::make('candidate_id')
                        ->label(__('applications.pipeline.select_candidate'))
                        ->options(fn (): array => Candidate::query()
                            ->where('company_id', Filament::getTenant()?->getKey())
                            ->whereDoesntHave(
                                'applications',
                                fn (Builder $query): Builder => $query->where('job_id', $this->getJob()->id),
                            )
                            ->pluck('name', 'id')
                            ->all())
                        ->helperText(fn (Select $component): ?string => $component->getOptions() === []
                            ? (string) __('applications.pipeline.no_eligible_candidates')
                            : null)
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $job = $this->getJob();

                    $firstStatus = Status::query()
                        ->where('company_id', Filament::getTenant()?->getKey())
                        ->orderBy('order')
                        ->first();

                    if (! $firstStatus) {
                        Notification::make()
                            ->title(__('applications.pipeline.no_statuses'))
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        Application::query()->create([
                            'company_id' => Filament::getTenant()?->getKey(),
                            'job_id' => $job->id,
                            'candidate_id' => $data['candidate_id'],
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
                }),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            JobPipelineKanban::class,
        ];
    }

    protected function getJob(): Job
    {
        $record = $this->getRecord();

        if (! $record instanceof Job) {
            throw new LogicException('The pipeline page must be bound to a job.');
        }

        return $record;
    }
}
