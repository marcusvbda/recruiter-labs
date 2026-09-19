<?php

namespace App\Filament\Resources\CandidateImportBatches\Pages;

use App\Data\CandidateImportProgress;
use App\Enums\CandidateImportFailureCode;
use App\Enums\CandidateImportRefusalCode;
use App\Enums\CandidateImportStatus;
use App\Exceptions\CandidateImportRefused;
use App\Filament\Resources\CandidateImportBatches\CandidateImportBatchResource;
use App\Models\CandidateImportBatch;
use App\Models\Company;
use App\Models\User;
use App\Services\CandidateImportExecution;
use App\Services\CandidateImportReport;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Where a confirmed import is watched to completion, and recovered when it
 * stalls. Reads current state from the database on every load and every
 * poll — nothing here is trusted from a prior page visit or session.
 */
class ProgressCandidateImportBatch extends Page
{
    protected static string $resource = CandidateImportBatchResource::class;

    protected string $view = 'filament.resources.candidate-import-batches.pages.progress';

    public CandidateImportBatch $batch;

    public function mount(CandidateImportBatch $record): void
    {
        abort_unless($record->company_id === $this->getCompany()->getKey(), 404);
        Gate::authorize('view', $record);
        $this->batch = $record;
    }

    public function getTitle(): string|Htmlable
    {
        return __('candidate_imports.progress.title');
    }

    public function progress(): CandidateImportProgress
    {
        return app(CandidateImportExecution::class)->progress($this->batch);
    }

    /** A short, plain phrase for the current state — never colour-only. */
    public function stateMessage(): string
    {
        $status = $this->batch->status;

        // `CandidateImportRetention::markExpired()` only ever moves an
        // unfinished Draft/Validating/Ready/Paused batch to Expired; a
        // terminal Completed/CompletedWithIssues/Failed batch keeps its own
        // status forever and only gains `details_expired_at`. So Expired
        // always means "never finished," never "finished, then aged out."
        if ($status === CandidateImportStatus::Expired) {
            return __('candidate_imports.progress.state.expired_unfinished');
        }

        return match ($status) {
            CandidateImportStatus::Processing => __('candidate_imports.progress.state.processing'),
            CandidateImportStatus::Paused => __('candidate_imports.progress.state.paused'),
            CandidateImportStatus::Completed => __('candidate_imports.progress.state.completed'),
            CandidateImportStatus::CompletedWithIssues => __('candidate_imports.progress.state.completed_with_issues'),
            CandidateImportStatus::Failed => __('candidate_imports.progress.state.failed'),
            CandidateImportStatus::Discarded => __('candidate_imports.progress.state.discarded'),
            default => __('candidate_imports.statuses.'.$status->value),
        };
    }

    public function failureMessage(): ?string
    {
        $code = CandidateImportFailureCode::tryFrom((string) $this->batch->failure_code);

        return $code === null ? null : (string) __('candidate_imports.progress.failure_codes.'.$code->value);
    }

    public function canResume(): bool
    {
        return $this->batch->status === CandidateImportStatus::Paused
            && $this->batch->details_expired_at === null;
    }

    public function retryableRows(): int
    {
        return app(CandidateImportExecution::class)->retryableRows($this->batch);
    }

    public function canRetry(): bool
    {
        return in_array($this->batch->status, [
            CandidateImportStatus::Paused,
            CandidateImportStatus::CompletedWithIssues,
            CandidateImportStatus::Failed,
        ], true) && $this->retryableRows() > 0;
    }

    public function hasCorrections(): bool
    {
        return $this->batch->confirmed_at !== null
            && app(CandidateImportReport::class)->hasCorrections($this->batch);
    }

    public function reportUrl(): string
    {
        return route('candidate-import-batches.report', [
            'company' => $this->getCompany(),
            'candidateImportBatch' => $this->batch,
        ]);
    }

    public function correctionUrl(): ?string
    {
        return $this->hasCorrections()
            ? route('candidate-import-batches.correction', [
                'company' => $this->getCompany(),
                'candidateImportBatch' => $this->batch,
            ])
            : null;
    }

    public function resumeAction(): Action
    {
        return Action::make('resume')
            ->label(__('candidate_imports.progress.actions.resume'))
            ->requiresConfirmation()
            ->modalDescription(__('candidate_imports.progress.actions.resume_confirm_description'))
            ->visible(fn (): bool => $this->canResume())
            ->action(function (CandidateImportExecution $execution): void {
                try {
                    $execution->resume($this->batch, $this->getUser());
                } catch (CandidateImportRefused $exception) {
                    $this->notifyRefusal($exception);

                    return;
                }

                $this->refreshBatch();
                Notification::make()->title(__('candidate_imports.progress.notifications.resumed'))->success()->send();
            });
    }

    public function retryAction(): Action
    {
        return Action::make('retry')
            ->label(__('candidate_imports.progress.actions.retry'))
            ->requiresConfirmation()
            ->modalDescription(__('candidate_imports.progress.actions.retry_confirm_description'))
            ->visible(fn (): bool => $this->canRetry())
            ->action(function (CandidateImportExecution $execution): void {
                try {
                    $execution->retry($this->batch, $this->getUser());
                } catch (CandidateImportRefused $exception) {
                    $this->notifyRefusal($exception);

                    return;
                }

                $this->refreshBatch();
                Notification::make()->title(__('candidate_imports.progress.notifications.retried'))->success()->send();
            });
    }

    /**
     * A batch already executing elsewhere in this workspace is not just a
     * generic refusal: the spec requires explaining the condition and linking
     * to that batch's progress, so the recruiter can see it rather than being
     * left with a bare error and no way to check.
     */
    private function notifyRefusal(CandidateImportRefused $exception): void
    {
        $key = match ($exception->refusalCode()) {
            CandidateImportRefusalCode::NotOpen => 'not_open',
            CandidateImportRefusalCode::NothingSelected => 'nothing_selected',
            CandidateImportRefusalCode::ExecutionInProgress => 'execution_in_progress',
            default => 'default',
        };

        $notification = Notification::make()->title(__('candidate_imports.progress.refusals.'.$key))->danger();

        if ($exception->refusalCode() === CandidateImportRefusalCode::ExecutionInProgress) {
            $runningId = $exception->context['batch'] ?? null;
            $runningBatch = is_int($runningId) || is_string($runningId)
                ? CandidateImportBatchResource::getEloquentQuery()->find($runningId)
                : null;

            if ($runningBatch instanceof CandidateImportBatch) {
                try {
                    $notification->actions([
                        Action::make('viewRunningImport')
                            ->label(__('candidate_imports.progress.refusals.view_running_import'))
                            ->url(CandidateImportBatchResource::getUrl('progress', ['record' => $runningBatch])),
                    ]);
                } catch (\Throwable) {
                    // The progress page may not resolve; the message alone still explains the condition.
                }
            }
        }

        $notification->send();
    }

    private function refreshBatch(): void
    {
        $this->batch = CandidateImportBatchResource::getEloquentQuery()->whereKey($this->batch->getKey())->firstOrFail();
    }

    private function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function getUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
