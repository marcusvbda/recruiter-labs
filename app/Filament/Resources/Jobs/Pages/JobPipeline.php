<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\Candidate;
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
use Illuminate\Support\Collection;

class JobPipeline extends Page
{
    // `HasActions`/`HasSchemas` and their `InteractsWithActions`/
    // `InteractsWithSchemas` traits are already provided by the base
    // `Filament\Pages\BasePage` class — re-declaring them here would collide
    // with `InteractsWithRecord::afterActionCalled()`.
    use InteractsWithRecord;

    protected static string $resource = JobResource::class;

    protected string $view = 'filament.resources.jobs.pages.job-pipeline';

    /**
     * Toggles between the Kanban and list rendering modes. Deliberately not
     * named `$view`: `Filament\Pages\Page` already declares a (non-static)
     * `$view` property holding the Blade view path used to render the page —
     * reusing that name here would silently overwrite it.
     */
    public string $activeView = 'kanban';

    /** @var Collection<int, Status> */
    public Collection $statuses;

    /** @var Collection<int, Collection<int, Application>> */
    public Collection $applicationsByStatus;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(JobResource::canEdit($this->getRecord()), 403);

        $this->refreshPipelineData();
    }

    public function getTitle(): string|Htmlable
    {
        return __('applications.pipeline.title', ['job' => $this->getRecord()->name]);
    }

    protected function refreshPipelineData(): void
    {
        $job = $this->getRecord();

        $this->statuses = $job->company->statuses()->orderBy('order')->get();

        // `collect()` (rather than assigning the `groupBy()` result directly)
        // forces a plain `Illuminate\Support\Collection` as the outer
        // container. Left as-is, `groupBy()` called on an
        // `Illuminate\Database\Eloquent\Collection` returns another Eloquent
        // Collection via late static binding — even though its items are
        // inner `Collection`s, not `Application` models — which makes
        // Livewire's Eloquent-collection synthesizer try to call `getKey()`
        // on each group and fatal.
        $this->applicationsByStatus = collect(
            $job->applications()
                ->with(['candidate', 'status'])
                ->get()
                ->groupBy('status_id')
                ->all(),
        );
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewKanban')
                ->label(__('applications.pipeline.view_kanban'))
                ->icon(Heroicon::OutlinedViewColumns)
                ->color(fn (): string => $this->activeView === 'kanban' ? 'primary' : 'gray')
                ->action(fn () => $this->activeView = 'kanban'),
            Action::make('viewList')
                ->label(__('applications.pipeline.view_list'))
                ->icon(Heroicon::OutlinedQueueList)
                ->color(fn (): string => $this->activeView === 'list' ? 'primary' : 'gray')
                ->action(fn () => $this->activeView = 'list'),
            Action::make('addCandidate')
                ->label(__('applications.pipeline.add_candidate'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->schema([
                    Select::make('candidate_id')
                        ->label(__('applications.pipeline.select_candidate'))
                        ->options(fn (): array => Candidate::query()
                            ->where('company_id', Filament::getTenant()?->id)
                            ->whereDoesntHave(
                                'applications',
                                fn (Builder $query): Builder => $query->where('job_id', $this->getRecord()->id),
                            )
                            ->pluck('name', 'id')
                            ->all())
                        ->helperText(fn (Select $component): ?string => $component->getOptions() === []
                            ? __('applications.pipeline.no_eligible_candidates')
                            : null)
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $job = $this->getRecord();

                    $firstStatus = Status::query()
                        ->where('company_id', Filament::getTenant()?->id)
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
                            'company_id' => Filament::getTenant()?->id,
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

                    $this->refreshPipelineData();
                }),
        ];
    }

    /**
     * Livewire-callable from the Kanban board's Alpine/SortableJS `x-on:end`
     * handler when a card is dropped into a (possibly different) column.
     *
     * Security: this is a public method reachable with attacker-supplied IDs
     * from the client, so both the application and the destination status
     * must be re-validated server-side against this job's own tenant/company
     * before persisting — the drag-and-drop markup alone cannot be trusted.
     */
    public function moveApplication(int $applicationId, int $statusId): void
    {
        $job = $this->getRecord();

        $applicationBelongsToJob = Application::query()
            ->whereKey($applicationId)
            ->where('job_id', $job->id)
            ->exists();

        $statusBelongsToCompany = Status::query()
            ->whereKey($statusId)
            ->where('company_id', $job->company_id)
            ->exists();

        if (! $applicationBelongsToJob || ! $statusBelongsToCompany) {
            return;
        }

        Application::query()->whereKey($applicationId)->update(['status_id' => $statusId]);

        $this->refreshPipelineData();
    }
}
