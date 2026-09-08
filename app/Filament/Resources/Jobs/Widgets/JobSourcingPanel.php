<?php

namespace App\Filament\Resources\Jobs\Widgets;

use App\Actions\AddCandidateToJob;
use App\Actions\DismissSourcingMatch;
use App\Actions\RestoreSourcingMatch;
use App\Actions\RunSourcingSearch;
use App\Actions\SaveSourcingMatch;
use App\Data\CandidateRecruitmentHistoryEntry;
use App\Enums\CriterionEvidenceSource;
use App\Enums\SourcingMatchState;
use App\Enums\SourcingSearchStatus;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\RecruitmentWorkflowException;
use App\Exceptions\SourcingMatchException;
use App\Filament\Clusters\Settings\Pages\PlanSettings;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\SourcingMatch;
use App\Models\SourcingMatchCriterionScore;
use App\Models\SourcingSearch;
use App\Models\User;
use App\Services\CandidateSourcingEligibilityService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

/**
 * The job workspace's Sourcing panel: how the workspace's own existing
 * candidates stack up against this job's current confirmed criteria.
 *
 * Every write here is delegated to an existing domain action
 * ({@see RunSourcingSearch}, {@see SaveSourcingMatch}, {@see DismissSourcingMatch},
 * {@see RestoreSourcingMatch}, {@see AddCandidateToJob}) — this class only reads,
 * shapes a view model, and reports the outcome. It never touches
 * `sourcing_matches`/`sourcing_searches`/`applications` directly.
 */
class JobSourcingPanel extends Widget
{
    protected string $view = 'filament.resources.jobs.widgets.job-sourcing-panel';

    public Job $record;

    private RunSourcingSearch $runSourcingSearch;

    private SaveSourcingMatch $saveSourcingMatch;

    private DismissSourcingMatch $dismissSourcingMatch;

    private RestoreSourcingMatch $restoreSourcingMatch;

    private AddCandidateToJob $addCandidateToJob;

    private CandidateSourcingEligibilityService $eligibilityService;

    public function boot(
        RunSourcingSearch $runSourcingSearch,
        SaveSourcingMatch $saveSourcingMatch,
        DismissSourcingMatch $dismissSourcingMatch,
        RestoreSourcingMatch $restoreSourcingMatch,
        AddCandidateToJob $addCandidateToJob,
        CandidateSourcingEligibilityService $eligibilityService,
    ): void {
        $this->runSourcingSearch = $runSourcingSearch;
        $this->saveSourcingMatch = $saveSourcingMatch;
        $this->dismissSourcingMatch = $dismissSourcingMatch;
        $this->restoreSourcingMatch = $restoreSourcingMatch;
        $this->addCandidateToJob = $addCandidateToJob;
        $this->eligibilityService = $eligibilityService;
    }

    /**
     * Queues a fresh sourcing sweep. A no-op while one is already in flight —
     * the button is disabled for that case too, this is the second line of
     * defence against a stray double click.
     */
    public function findMatches(): void
    {
        abort_unless(JobResource::canEdit($this->record), 403);

        $search = $this->currentSearch();

        if ($search instanceof SourcingSearch && $search->status->isInProgress()) {
            return;
        }

        $this->runSourcingSearch->handle($this->record, $this->currentUserId());

        Notification::make()
            ->title(__('sourcing.panel.search_started'))
            ->success()
            ->send();

        $this->record->unsetRelation('sourcingSearch');
    }

    public function save(int $matchId): void
    {
        $this->decide($matchId, fn (SourcingMatch $match, User $user): SourcingMatch => $this->saveSourcingMatch->handle($match, $user), __('sourcing.panel.saved'));
    }

    public function dismiss(int $matchId): void
    {
        $this->decide($matchId, fn (SourcingMatch $match, User $user): SourcingMatch => $this->dismissSourcingMatch->handle($match, $user), __('sourcing.panel.dismissed'));
    }

    public function restore(int $matchId): void
    {
        $this->decide($matchId, fn (SourcingMatch $match, User $user): SourcingMatch => $this->restoreSourcingMatch->handle($match, $user), __('sourcing.panel.restored'));
    }

    /**
     * Reuses the exact same domain action the pipeline's manual "add candidate"
     * button calls, so a sourced candidate enters the job through one path.
     */
    public function addToJob(int $matchId): void
    {
        abort_unless(JobResource::canEdit($this->record), 403);

        $match = $this->matchFor($matchId);

        if (! $match instanceof SourcingMatch) {
            return;
        }

        $candidate = $match->candidate;

        if (! $candidate instanceof Candidate) {
            Notification::make()
                ->title(__('applications.pipeline.already_added'))
                ->danger()
                ->send();

            return;
        }

        try {
            $this->addCandidateToJob->handle($this->record, $candidate);
        } catch (PlanLimitExceededException $exception) {
            $this->notifyPlanLimitReached($exception);

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
    }

    /**
     * @param  callable(SourcingMatch, User): SourcingMatch  $handler
     */
    private function decide(int $matchId, callable $handler, string $successMessage): void
    {
        abort_unless(JobResource::canEdit($this->record), 403);

        $match = $this->matchFor($matchId);

        if (! $match instanceof SourcingMatch) {
            return;
        }

        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return;
        }

        try {
            $handler($match, $user);
        } catch (SourcingMatchException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($successMessage)
            ->success()
            ->send();
    }

    private function matchFor(int $matchId): ?SourcingMatch
    {
        return SourcingMatch::query()
            ->where('company_id', $this->record->company_id)
            ->where('job_id', $this->record->getKey())
            ->whereKey($matchId)
            ->first();
    }

    private function notifyPlanLimitReached(PlanLimitExceededException $exception): void
    {
        $company = $this->record->company;

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

    private function currentSearch(): ?SourcingSearch
    {
        return $this->record->sourcingSearch;
    }

    /** The signed-in recruiter, for the action that records who asked for a search. */
    private function currentUserId(): ?int
    {
        $id = Filament::auth()->id();

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $job = $this->record;
        $job->unsetRelation('sourcingSearch');

        $search = $this->currentSearch();

        return [
            'operationalStatus' => $this->operationalStatus($search),
            'canFindMatches' => ! ($search instanceof SourcingSearch && $search->status->isInProgress()),
            'summary' => $this->summaryFor($search),
            'eligibleCount' => $this->eligibilityService->eligibleCandidateCount($job),
            'active' => $this->activeMatches($job),
            'dismissed' => $this->dismissedMatches($job),
        ];
    }

    /**
     * @return array{key: string, label: string, color: string, icon: string}
     */
    private function operationalStatus(?SourcingSearch $search): array
    {
        if (! $search instanceof SourcingSearch || $search->status === SourcingSearchStatus::NotStarted) {
            return ['key' => 'not_started', 'label' => __('sourcing.status.not_started'), 'color' => 'gray', 'icon' => 'heroicon-o-magnifying-glass'];
        }

        if ($search->status->isInProgress()) {
            return ['key' => 'searching', 'label' => __('sourcing.status.searching'), 'color' => 'info', 'icon' => 'heroicon-o-arrow-path'];
        }

        if ($search->status === SourcingSearchStatus::Failed) {
            return ['key' => 'failed', 'label' => __('sourcing.status.failed'), 'color' => 'danger', 'icon' => 'heroicon-o-exclamation-triangle'];
        }

        if ($search->status === SourcingSearchStatus::PendingQuota) {
            return ['key' => 'blocked', 'label' => __('sourcing.status.blocked'), 'color' => 'warning', 'icon' => 'heroicon-o-bolt-slash'];
        }

        if ($search->isOutdated()) {
            return ['key' => 'outdated', 'label' => __('sourcing.status.outdated'), 'color' => 'warning', 'icon' => 'heroicon-o-arrow-path'];
        }

        return ['key' => 'completed', 'label' => __('sourcing.status.completed'), 'color' => 'success', 'icon' => 'heroicon-o-check-circle'];
    }

    /**
     * @return array{considered: int, matched: int, insufficient: int, completed_at: string|null}|null
     */
    private function summaryFor(?SourcingSearch $search): ?array
    {
        if (! $search instanceof SourcingSearch || $search->candidates_considered === null) {
            return null;
        }

        return [
            'considered' => $search->candidates_considered,
            'matched' => $search->matches_found ?? 0,
            'insufficient' => $search->insufficient_count ?? 0,
            'completed_at' => $search->completed_at?->translatedFormat('M j, Y · H:i'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeMatches(Job $job): array
    {
        return array_values($this->matchesQuery($job)
            ->whereIn('state', [SourcingMatchState::Suggested->value, SourcingMatchState::Saved->value])
            ->orderByRaw('potential_match is null')
            ->orderByDesc('potential_match')
            ->orderByDesc('evidence_coverage')
            ->orderBy('id')
            ->get()
            ->map(fn (SourcingMatch $match): array => $this->matchViewModel($match, $job))
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dismissedMatches(Job $job): array
    {
        return array_values($this->matchesQuery($job)
            ->where('state', SourcingMatchState::Dismissed->value)
            ->orderByDesc('state_changed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SourcingMatch $match): array => $this->matchViewModel($match, $job))
            ->all());
    }

    /**
     * @return Builder<SourcingMatch>
     */
    private function matchesQuery(Job $job): Builder
    {
        return SourcingMatch::query()
            ->where('company_id', $job->company_id)
            ->where('job_id', $job->getKey())
            ->with(['candidate', 'criterionScores']);
    }

    /**
     * @return array<string, mixed>
     */
    private function matchViewModel(SourcingMatch $match, Job $job): array
    {
        $candidate = $match->candidate;
        $alreadyInJob = $match->candidateAlreadyInJob();

        $criteria = $match->criterionScores->map(fn (SourcingMatchCriterionScore $score): array => [
            'criterion' => $score->criterion,
            'weight' => $score->weight,
            'is_assessed' => $score->isAssessed(),
            'score' => $score->score,
            'confidence' => $score->confidence->value,
            'reason' => $score->reason,
            'evidence' => collect($score->evidence ?? [])
                ->map(fn (array $item): array => [
                    'source' => CriterionEvidenceSource::tryFrom($item['source'])?->label() ?? $item['source'],
                    'detail' => $item['detail'],
                    'submitted_at' => isset($item['submitted_at'])
                        ? CarbonImmutable::parse($item['submitted_at'])->translatedFormat('M j, Y')
                        : null,
                ])
                ->all(),
        ]);

        // Three groups, mirroring the vocabulary the evaluation tab already
        // uses for the same underlying shape (assessed/high-confidence,
        // assessed/lower-confidence, unassessed). An unassessed criterion stays
        // "insufficient information", never a fabricated low score.
        $strongSupport = $criteria->filter(fn (array $c): bool => $c['is_assessed'] && $c['confidence'] === 'high')->sortByDesc('weight')->values()->all();
        $needsValidation = $criteria->filter(fn (array $c): bool => $c['is_assessed'] && $c['confidence'] !== 'high')->sortByDesc('weight')->values()->all();
        $insufficient = $criteria->filter(fn (array $c): bool => ! $c['is_assessed'])->sortByDesc('weight')->values()->all();

        $history = $candidate instanceof Candidate
            ? Collection::make($this->eligibilityService->recruitmentHistoryFor($candidate, $job))
                ->reject(fn (CandidateRecruitmentHistoryEntry $entry): bool => $entry->isCurrentJob)
                ->values()
            : Collection::make();

        return [
            'id' => $match->getKey(),
            'candidate_name' => $candidate instanceof Candidate ? $candidate->name : __('sourcing.panel.candidate_removed'),
            'candidate_url' => $candidate instanceof Candidate
                ? CandidateResource::getUrl('view', ['record' => $candidate], tenant: $job->company)
                : null,
            'state' => $match->state->value,
            'already_in_job' => $alreadyInJob,
            'sufficient_information' => $match->sufficient_information,
            'is_outdated' => $match->isOutdated(),
            'potential_match' => $match->potential_match,
            'evidence_coverage' => $match->evidence_coverage,
            'confidence' => $match->confidence?->value,
            'criteria' => [
                'strong_support' => $strongSupport,
                'needs_validation' => $needsValidation,
                'insufficient' => $insufficient,
            ],
            'history' => $history->take(2)
                ->map(fn (CandidateRecruitmentHistoryEntry $entry): array => [
                    'job_name' => $entry->jobName,
                    'applied_at' => $entry->appliedAt->translatedFormat('M Y'),
                    'status_name' => $entry->statusName,
                    'reached_final_stage' => $entry->reachedFinalStage,
                    'was_hired' => $entry->wasHired,
                    'is_closed' => $entry->isClosed,
                ])
                ->all(),
            'history_hidden_count' => max($history->count() - 2, 0),
        ];
    }

    #[On('pipeline-updated')]
    public function refreshFromPipeline(): void
    {
        // A candidate may have just entered the job through the pipeline's own
        // manual "add candidate" action; the panel's already-in-job note has to
        // catch up without waiting for the next full page load.
    }
}
