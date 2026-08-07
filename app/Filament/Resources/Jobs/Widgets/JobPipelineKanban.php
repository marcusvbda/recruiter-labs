<?php

namespace App\Filament\Resources\Jobs\Widgets;

use App\Enums\ApplicationAnalysisStatus;
use App\Enums\ApplicationSource;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CompanyScoringSetting;
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

    /** @var array{analysis: int, referral: int}|null */
    protected ?array $scoringWeights = null;

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
        return match (Str::lower($this->getColumnHexColor($key))) {
            '#3b82f6', '#8b5cf6' => 'primary',
            '#f59e0b' => 'warning',
            '#06b6d4' => 'info',
            '#22c55e' => 'success',
            '#ef4444' => 'danger',
            default => 'gray',
        };
    }

    protected function getColumnHexColor(string $key): string
    {
        $color = $this->findStatus($key)?->color;

        return is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color)
            ? Str::lower($color)
            : '#94a3b8';
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

    public function isReferralApplication(Application $application): bool
    {
        return $application->source === ApplicationSource::Referral;
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

    public function getAnalysisIcon(Application $application): string
    {
        $value = $this->enumValue($application->analysis_status);

        return match ($value) {
            'processing' => 'heroicon-m-arrow-path',
            'completed' => 'heroicon-m-sparkles',
            'failed' => 'heroicon-m-exclamation-triangle',
            'pending_quota' => 'heroicon-m-bolt-slash',
            default => 'heroicon-m-clock',
        };
    }

    public function showsAnalysisBadge(Application $application): bool
    {
        return $this->enumValue($application->analysis_status) !== ApplicationAnalysisStatus::AwaitingCriteria->value;
    }

    public function showsScoreBadge(Application $application): bool
    {
        return $application->analysis_status === ApplicationAnalysisStatus::Completed
            && $application->analysis_score !== null;
    }

    public function showAverageScoreBadge(Application $application): bool
    {
        return $application->analysis_status === ApplicationAnalysisStatus::Completed
            && $application->analysis_score !== null;
    }

    public function getScoreColor(mixed $obj, string $index = 'analysis_score'): string
    {
        $score = (float) data_get($obj, $index, 0);

        return match (true) {
            $score >= 70 => 'success',
            $score >= 40 => 'warning',
            default => 'danger',
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
     * Highest overall score on top within each column — the same blended
     * value {@see Application::getOverallScoreData()} would return (AI fit
     * score weighted with the company's referral bonus, per
     * {@see CompanyScoringSetting::overallScore()}), computed inline in SQL
     * so it can be applied before any per-column `LIMIT`. Unscored
     * applications (`analysis_score IS NULL`) fall back to a score of 0,
     * matching that method's null short-circuit — this also sidesteps the
     * NULL-ordering differences across engines (MySQL/SQLite sort NULL last,
     * PostgreSQL sorts it first) that a plain `ORDER BY analysis_score DESC`
     * would otherwise hit, since the `CASE` expression never evaluates to
     * NULL itself.
     *
     * The weights are bound as query parameters (not interpolated into the
     * SQL string) even though they currently only come from trusted app
     * data.
     *
     * `created_at` asc then `updated_at` desc are kept as tie-breakers, for
     * parity with the parent class's default card ordering.
     *
     * @param  Builder<Application>  $query
     * @return Builder<Application>
     */
    private function applyCardOrdering(Builder $query): Builder
    {
        $weights = $this->getScoringWeights();

        return $query
            ->orderByRaw(
                'CASE
                    WHEN analysis_score IS NULL THEN 0
                    ELSE ROUND((analysis_score * ? + (CASE WHEN source = ? THEN 100 ELSE 0 END) * ?) / 100)
                END DESC',
                [$weights['analysis'], ApplicationSource::Referral->value, $weights['referral']],
            )
            ->orderBy('created_at')
            ->orderByDesc('updated_at');
    }

    /**
     * Resolve and cache this company's AI/referral scoring weights, used to
     * blend `analysis_score` into the overall score for {@see applyCardOrdering()}.
     * {@see getQuery()} already scopes this widget to a single company, so
     * the weights are constant for every row and only need resolving once.
     *
     * @return array{analysis: int, referral: int}
     */
    private function getScoringWeights(): array
    {
        if ($this->scoringWeights !== null) {
            return $this->scoringWeights;
        }

        $scoringSetting = $this->record->company->scoringSetting ?? new CompanyScoringSetting;

        return $this->scoringWeights = [
            'analysis' => $scoringSetting->analysis_weight,
            'referral' => $scoringSetting->referral_weight,
        ];
    }

    /**
     * Overridden instead of ordering in {@see getQuery()} because the parent's
     * {@see StateKanbanBoard::getFilteredQuery()} is shared by both the record-fetching
     * path and {@see StateKanbanBoard::getColumnTotals()}'s `GROUP BY status_id` count
     * query. Ordering by `analysis_score`/`created_at` at the `getQuery()` level leaked
     * into that `GROUP BY` query, which PostgreSQL rejects (strict grouping rules)
     * even though SQLite silently allows it.
     *
     * @param  list<string>  $columns
     * @return array<string, \Illuminate\Support\Collection<int, Model>>
     */
    protected function fetchRecordsGroupedByColumn(array $columns, int $limit): array
    {
        $field = $this->getStateField();
        $totals = $this->getColumnTotals();
        $totalMatchingRecords = array_sum($totals);
        $maxSingleFetch = count($columns) * $limit;

        if ($totalMatchingRecords <= $maxSingleFetch) {
            $grouped = $this->applyCardOrdering($this->getFilteredQuery()->whereIn($field, $columns))
                ->get()
                ->groupBy(fn (Model $record): string => $this->resolveColumnKey($record));

            return collect($columns)
                ->mapWithKeys(fn (string $column): array => [
                    $column => ($grouped->get($column) ?? collect())->take($limit)->values(),
                ])
                ->all();
        }

        return collect($columns)
            ->mapWithKeys(function (string $column) use ($field, $limit): array {
                return [
                    $column => $this->applyCardOrdering($this->getFilteredQuery()->where($field, $column))
                        ->limit($limit)
                        ->get(),
                ];
            })
            ->all();
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
