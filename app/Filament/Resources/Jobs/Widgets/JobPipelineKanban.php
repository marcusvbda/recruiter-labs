<?php

namespace App\Filament\Resources\Jobs\Widgets;

use App\Actions\MoveApplicationToStatus;
use App\Enums\AnalysisConfidence;
use App\Enums\ApplicationSource;
use App\Enums\InterviewCalendarSyncStatus;
use App\Enums\InterviewRsvpStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
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

    /**
     * Weight above which a criterion counts as important, mirroring the
     * evaluation tab's own threshold.
     */
    private const HighImportanceWeight = 7;

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
            'completed' => 'gray',
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
            'completed' => 'heroicon-m-document-check',
            'failed' => 'heroicon-m-exclamation-triangle',
            'pending_quota' => 'heroicon-m-bolt-slash',
            'awaiting_criteria' => 'heroicon-m-clipboard-document-check',
            default => 'heroicon-m-clock',
        };
    }

    /**
     * A fit only appears once it is the *current* one. An evaluation produced
     * against criteria the job has since changed is history, and a badge is not
     * the place to explain that.
     */
    public function showsScoreBadge(Application $application): bool
    {
        return $application->analysis_score !== null
            && $application->hasCurrentEvaluation();
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * The board loads exactly what a card has to say, and nothing a recruiter
     * would have to open the application to care about: answer and document
     * counts were dropped because they never change what to do next.
     *
     * @return Builder<Application>
     */
    protected function getQuery(): Builder
    {
        return Application::query()
            ->whereBelongsTo($this->record, 'job')
            ->where('company_id', $this->record->company_id)
            // `job` is loaded because whether an evaluation is still current
            // depends on the job's confirmed criteria revision, not on the
            // application alone.
            ->with(['candidate', 'status', 'job', 'upcomingInterviews'])
            // Only evidence that is both weakly established and important enough
            // to matter is worth a badge — the same rule the evaluation tab uses.
            ->withCount(['criterionScores as needs_validation_count' => fn (Builder $scores): Builder => $scores
                ->where('confidence', '!=', AnalysisConfidence::High->value)
                ->where('weight', '>=', self::HighImportanceWeight)]);
    }

    /**
     * What a recruiter needs to know about this person while looking at the
     * board, ordered by how much it should change their next move: the outcome
     * of the stage, whether the candidate is being kept waiting, whether the
     * booked commitment is in trouble, and only then the evaluation.
     *
     * @return list<array{label: string, color: string, icon: string}>
     */
    public function getCardSignals(Application $application): array
    {
        $signals = [];
        $status = $application->status;

        if ($status instanceof Status) {
            $role = match (true) {
                $status->is_hired => ['statuses.badges.hired', 'success', 'heroicon-m-check-badge'],
                $status->is_terminal => ['statuses.badges.closed', 'gray', 'heroicon-m-x-circle'],
                $status->is_final_stage => ['applications.pipeline.kanban.decision_needed', 'warning', 'heroicon-m-hand-raised'],
                default => null,
            };

            if ($role !== null) {
                $signals[] = ['label' => (string) __($role[0]), 'color' => $role[1], 'icon' => $role[2]];
            }
        }

        if ($application->isOverdueInCurrentStage()) {
            $signals[] = [
                'label' => (string) __('applications.pipeline.kanban.waiting_too_long'),
                'color' => 'warning',
                'icon' => 'heroicon-m-clock',
            ];
        }

        $signals = [...$signals, ...$this->interviewSignals($application)];

        $analysisStatus = $this->enumValue($application->analysis_status);

        if ($analysisStatus === 'failed' || $analysisStatus === 'pending_quota' || $analysisStatus === 'awaiting_criteria') {
            $signals[] = [
                'label' => $this->getAnalysisLabel($application),
                'color' => $this->getAnalysisColor($application),
                'icon' => $this->getAnalysisIcon($application),
            ];
        }

        if ($application->hasOutdatedEvaluation()) {
            $signals[] = [
                'label' => (string) __('applications.admin.ai.states.outdated.label'),
                'color' => 'gray',
                'icon' => 'heroicon-m-arrow-path',
            ];
        }

        if ($this->showsScoreBadge($application)) {
            $signals[] = [
                'label' => (string) __('applications.admin.ai.criteria.fit_label', [
                    'score' => (int) round((float) $application->analysis_score),
                ]),
                'color' => 'gray',
                'icon' => 'heroicon-m-chart-bar',
            ];
        }

        $needsValidation = (int) $application->getAttribute('needs_validation_count');

        if ($needsValidation > 0 && $application->hasCurrentEvaluation()) {
            $signals[] = [
                'label' => trans_choice('applications.admin.summary.needs_validation', $needsValidation, ['count' => $needsValidation]),
                'color' => 'warning',
                'icon' => 'heroicon-m-question-mark-circle',
            ];
        }

        return $signals;
    }

    /**
     * A booked interview is either reassuring or a problem, and the card has to
     * say which: a decline or a calendar failure outranks the date itself.
     *
     * @return list<array{label: string, color: string, icon: string}>
     */
    private function interviewSignals(Application $application): array
    {
        $interview = $application->upcomingInterviews->first();

        if (! $interview instanceof Interview) {
            return [];
        }

        if ($interview->rsvp_status === InterviewRsvpStatus::Declined) {
            return [[
                'label' => (string) __('applications.pipeline.kanban.interview_declined'),
                'color' => 'danger',
                'icon' => 'heroicon-m-calendar-days',
            ]];
        }

        if ($interview->calendar_sync_terminal || $interview->calendar_sync_status === InterviewCalendarSyncStatus::Failed) {
            return [[
                'label' => (string) __('applications.pipeline.kanban.interview_not_synced'),
                'color' => 'danger',
                'icon' => 'heroicon-m-exclamation-triangle',
            ]];
        }

        return [[
            'label' => (string) __('applications.pipeline.kanban.interview_on', [
                'date' => $interview->scheduled_at->setTimezone($interview->timezone)->translatedFormat('M j · H:i'),
            ]),
            'color' => 'info',
            'icon' => 'heroicon-m-calendar-days',
        ]];
    }

    /**
     * How long this person has been waiting where they are — the single most
     * useful thing a stage column cannot show by itself.
     */
    public function getStageAgeLabel(Application $application): string
    {
        $days = $application->daysInCurrentStage();

        return trans_choice('applications.pipeline.kanban.in_stage', $days, ['count' => $days]);
    }

    /**
     * Longest waiting in the stage first — operational work state, never AI fit.
     *
     * A column *is* a status, so every card in it shares that stage's own
     * `attention_after_days` threshold. Ordering by `status_entered_at` ascending
     * therefore puts the genuinely overdue candidates at the top of the column by
     * construction, without a second query or a derived priority column.
     *
     * Fit deliberately takes no part in this. Sorting the board by
     * `analysis_score` would make "highest AI score first" the default order a
     * recruiter reads candidates in, which is an automated hiring recommendation
     * wearing a layout's clothes. Fit stays on the card as context.
     *
     * `created_at` then `id` are deterministic tie-breakers, so two candidates who
     * entered a stage in the same second do not swap places between renders.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyCardOrdering(Builder $query): Builder
    {
        return $query
            ->orderBy('status_entered_at')
            ->orderBy('created_at')
            ->orderBy('id');
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

    /**
     * Drag and drop is just another caller of the domain operation: the widget
     * never writes `status_id` itself, so tenant/pipeline integrity checks and the
     * status's on-enter communication apply here exactly as everywhere else.
     */
    protected function persistStateChange(Model $record, mixed $currentState, string $targetColumn): void
    {
        if (! $record instanceof Application) {
            throw new AuthorizationException;
        }

        $status = $this->findStatus($targetColumn);

        if (! $status instanceof Status) {
            throw new AuthorizationException;
        }

        app(MoveApplicationToStatus::class)->handle($record, $status);
    }

    #[On('pipeline-updated')]
    public function refreshBoard(): void
    {
        $this->cachedBoardColumns = null;
        $this->pipelineStatuses = null;
    }

    /**
     * The board's columns are the statuses of the job's own pipeline — never every
     * status the company happens to have.
     *
     * @return Collection<int, Status>
     */
    protected function getPipelineStatuses(): Collection
    {
        return $this->pipelineStatuses ??= Status::query()
            ->where('company_id', $this->record->company_id)
            ->where('pipeline_id', $this->record->pipeline_id)
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    protected function findStatus(string $key): ?Status
    {
        return $this->getPipelineStatuses()->firstWhere('id', (int) $key);
    }
}
