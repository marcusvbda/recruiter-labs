<?php

namespace App\Filament\Resources\Jobs\Widgets;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\Status;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Usamamuneerchaudhary\FilamentModelStates\Widgets\StateKanbanBoard;

class JobPipelineKanban extends StateKanbanBoard
{
    protected string $view = 'filament.resources.jobs.widgets.job-pipeline-kanban';

    public Job $record;

    /** @var Collection<int, Status>|null */
    protected ?Collection $pipelineStatuses = null;

    protected function getModel(): string
    {
        return Application::class;
    }

    protected function getStateField(): string
    {
        return 'status_id';
    }

    /**
     * @return list<string>
     */
    protected function getColumns(): array
    {
        $columns = [];

        foreach ($this->getPipelineStatuses() as $status) {
            $columns[] = (string) $status->getKey();
        }

        return $columns;
    }

    protected function getColumnLabel(string $key): string
    {
        $status = $this->findStatus($key);

        return $status === null ? $key : $status->name;
    }

    protected function getColumnColor(string $key): string
    {
        $status = $this->findStatus($key);

        return match (Str::lower($status === null ? '' : $status->color)) {
            '#3b82f6', '#8b5cf6' => 'primary',
            '#f59e0b' => 'warning',
            '#06b6d4' => 'info',
            '#22c55e' => 'success',
            '#ef4444' => 'danger',
            default => 'gray',
        };
    }

    protected function getCardTitle(Model $record): string
    {
        if (! $record instanceof Application) {
            return parent::getCardTitle($record);
        }

        $candidate = $record->candidate;

        return $candidate instanceof Candidate
            ? $candidate->name
            : parent::getCardTitle($record);
    }

    protected function getCardSubtitle(Model $record): ?string
    {
        if (! $record instanceof Application) {
            return null;
        }

        $candidate = $record->candidate;

        if (! $candidate instanceof Candidate) {
            return null;
        }

        $email = $candidate->getAttribute('email');

        return is_string($email) ? $email : null;
    }

    public function getApplicationUrl(Application $application): string
    {
        return ApplicationResource::getUrl('view', [
            'record' => $application,
        ], tenant: Filament::getTenant());
    }

    public function getAnalysisLabel(Application $application): string
    {
        $value = $this->enumValue($application->analysis_status);

        return __("applications.admin.ai.states.{$value}.label");
    }

    public function getAnalysisColor(Application $application): string
    {
        $value = $this->enumValue($application->analysis_status);

        return match ($value) {
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            'pending_quota' => 'warning',
            default => 'gray',
        };
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    /** @return Builder<Application> */
    protected function getQuery(): Builder
    {
        return Application::query()
            ->whereBelongsTo($this->record, 'job')
            ->where('company_id', $this->record->company_id)
            ->with('candidate')
            ->withCount(['answers', 'documents']);
    }

    /**
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->whereHas('candidate', function (Builder $query) use ($search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%");
            });
        });
    }

    protected function authorizeMoveRecord(Model $record): void
    {
        if (
            ! $record instanceof Application
            || $record->job_id !== $this->record->getKey()
            || $record->company_id !== $this->record->company_id
            || ! JobResource::canEdit($this->record)
        ) {
            throw new AuthorizationException;
        }
    }

    protected function resolveStateValue(string $column): int
    {
        return (int) $column;
    }

    #[On('pipeline-updated')]
    public function refreshBoard(): void
    {
        $this->cachedBoardColumns = null;
        $this->pipelineStatuses = null;
    }

    /**
     * @return Collection<int, Status>
     */
    protected function getPipelineStatuses(): Collection
    {
        return $this->pipelineStatuses ??= Status::query()
            ->where('company_id', $this->record->company_id)
            ->orderBy('order')
            ->get();
    }

    protected function findStatus(string $key): ?Status
    {
        return $this->getPipelineStatuses()->firstWhere('id', (int) $key);
    }
}
