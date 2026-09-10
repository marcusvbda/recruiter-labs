<?php

namespace App\Filament\Resources\CandidateImportBatches\Pages;

use App\Data\CandidateImportReviewRow;
use App\Data\CandidateImportSummary;
use App\Enums\CandidateImportIdentityAction;
use App\Enums\CandidateImportIssueCode;
use App\Enums\CandidateImportStatus;
use App\Exceptions\CandidateImportRefused;
use App\Filament\Resources\CandidateImportBatches\CandidateImportBatchResource;
use App\Models\CandidateImportBatch;
use App\Models\User;
use App\Services\CandidateImportConfirmation;
use App\Services\CandidateImportReview;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * The five decisions a reviewer may take on an already-validated import, and
 * the confirmation that turns the surviving rows into a promise.
 *
 * Nothing here edits a row's data: {@see CandidateImportReview} exposes only
 * include/exclude/confirm-reuse/import-contact-only/choose-duplicate, and this
 * page is a thin surface over exactly that vocabulary. The batch itself is
 * always re-read after a decision, never patched in place, so the revision the
 * confirm action later sends is the one the database actually holds.
 */
class ReviewCandidateImportBatch extends ViewRecord
{
    protected static string $resource = CandidateImportBatchResource::class;

    public string $statusFilter = 'all';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $batch = $this->getBatch();

        if ($batch->status !== CandidateImportStatus::Ready) {
            $this->redirect(CandidateImportBatchResource::getUrl('create', ['batch' => $batch->getKey()]));
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('candidate_imports.review.title');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.candidate-import-batches.pages.review')
                ->columnSpanFull(),
        ]);
    }

    /** @return list<CandidateImportReviewRow> */
    public function rows(): array
    {
        return app(CandidateImportReview::class)->rows($this->getBatch());
    }

    /** @return list<CandidateImportReviewRow> */
    public function filteredRows(): array
    {
        $rows = $this->rows();

        return match ($this->statusFilter) {
            'needs_decision' => array_values(array_filter($rows, fn (CandidateImportReviewRow $row): bool => $row->needsDecision())),
            'blocked' => array_values(array_filter($rows, fn (CandidateImportReviewRow $row): bool => $row->isBlocked())),
            'importable' => array_values(array_filter($rows, fn (CandidateImportReviewRow $row): bool => $row->willImport())),
            default => $rows,
        };
    }

    /** @return array<string, int> */
    public function filterCounts(): array
    {
        $rows = $this->rows();

        return [
            'all' => count($rows),
            'needs_decision' => count(array_filter($rows, fn (CandidateImportReviewRow $row): bool => $row->needsDecision())),
            'blocked' => count(array_filter($rows, fn (CandidateImportReviewRow $row): bool => $row->isBlocked())),
            'importable' => count(array_filter($rows, fn (CandidateImportReviewRow $row): bool => $row->willImport())),
        ];
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $filter;
    }

    public function summary(): CandidateImportSummary
    {
        return app(CandidateImportReview::class)->summaryOf($this->getBatch(), $this->rows());
    }

    /** @return list<string> */
    public function batchErrors(): array
    {
        $batch = $this->getBatch();

        if ($batch->errors === null) {
            return [];
        }

        /** @var list<array<string, mixed>> $errors */
        $errors = is_array($batch->errors['issues'] ?? null) ? $batch->errors['issues'] : [];

        return array_values(collect($errors)
            ->map(fn (array $error): string => __('candidate_imports.csv_error_codes.'.$error['code']))
            ->all());
    }

    /** @param array<string, mixed> $issue */
    public function issueLabel(array $issue): string
    {
        return __('candidate_imports.issue_codes.'.$issue['code']);
    }

    public function identityLabel(CandidateImportReviewRow $row): string
    {
        return __('candidate_imports.review.identity.'.$row->identity()->value);
    }

    public function canInclude(CandidateImportReviewRow $row): bool
    {
        return ! $row->resolvedSelected() && $row->isEligible() && ! $row->isExcluded();
    }

    public function canExclude(CandidateImportReviewRow $row): bool
    {
        return ! $row->isExcluded();
    }

    public function canConfirmReuse(CandidateImportReviewRow $row): bool
    {
        return $row->identity() === CandidateImportIdentityAction::Reuse
            && $row->hasIssue(CandidateImportIssueCode::ExistingNameDiffers);
    }

    public function canImportContactOnly(CandidateImportReviewRow $row): bool
    {
        return $row->referencesCv() && ! $row->isContactOnly() && ! $row->isExcluded();
    }

    /**
     * The existing candidate's name/id the reviewer is being asked to confirm.
     *
     * @return array<string, mixed>|null
     */
    public function existingNameContext(CandidateImportReviewRow $row): ?array
    {
        foreach ($row->issuesToArray() as $issue) {
            if ($issue['code'] === CandidateImportIssueCode::ExistingNameDiffers->value) {
                return $issue['context'];
            }
        }

        return null;
    }

    public function includeRowAction(): Action
    {
        return Action::make('includeRow')
            ->label(__('candidate_imports.review.actions.include'))
            ->action(fn (array $arguments) => $this->applyDecision(
                fn (CandidateImportBatch $batch, int $record, User $actor) => app(CandidateImportReview::class)->include($batch, $record, $actor),
                $arguments,
            ));
    }

    public function excludeRowAction(): Action
    {
        return Action::make('excludeRow')
            ->label(__('candidate_imports.review.actions.exclude'))
            ->color('danger')
            ->action(fn (array $arguments) => $this->applyDecision(
                fn (CandidateImportBatch $batch, int $record, User $actor) => app(CandidateImportReview::class)->exclude($batch, $record, $actor),
                $arguments,
            ));
    }

    public function confirmReuseRowAction(): Action
    {
        return Action::make('confirmReuseRow')
            ->label(__('candidate_imports.review.actions.confirm_reuse'))
            ->requiresConfirmation()
            ->action(fn (array $arguments) => $this->applyDecision(
                function (CandidateImportBatch $batch, int $record, User $actor) use ($arguments) {
                    $existingCandidateId = (int) ($arguments['existing_candidate_id'] ?? 0);

                    return app(CandidateImportReview::class)->confirmReuse($batch, $record, $actor, $existingCandidateId);
                },
                $arguments,
            ));
    }

    public function contactOnlyRowAction(): Action
    {
        return Action::make('contactOnlyRow')
            ->label(__('candidate_imports.review.actions.contact_only'))
            ->requiresConfirmation()
            ->action(fn (array $arguments) => $this->applyDecision(
                fn (CandidateImportBatch $batch, int $record, User $actor) => app(CandidateImportReview::class)->importContactOnly($batch, $record, $actor),
                $arguments,
            ));
    }

    public function chooseDuplicateRowAction(): Action
    {
        return Action::make('chooseDuplicateRow')
            ->label(__('candidate_imports.review.actions.choose_duplicate'))
            ->requiresConfirmation()
            ->action(fn (array $arguments) => $this->applyDecision(
                fn (CandidateImportBatch $batch, int $record, User $actor) => app(CandidateImportReview::class)->chooseDuplicate($batch, $record, $actor),
                $arguments,
            ));
    }

    public function confirmImportAction(): Action
    {
        return Action::make('confirmImport')
            ->label(__('candidate_imports.review.actions.confirm'))
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn (): string => $this->summary()->statement())
            ->disabled(fn (): bool => ! $this->summary()->hasSelection() || $this->batchErrors() !== [])
            ->schema([
                Checkbox::make('declaration')
                    ->label(__('candidate_imports.review.declaration'))
                    ->accepted()
                    ->required(),
            ])
            ->action(function (array $data, CandidateImportConfirmation $confirmation): void {
                $batch = $this->getBatch();
                Gate::authorize('update', $batch);

                /** @var CandidateImportBatch $fresh */
                $fresh = CandidateImportBatchResource::getEloquentQuery()->whereKey($batch->getKey())->firstOrFail();

                try {
                    $confirmation->confirm(
                        $fresh,
                        $this->getUser(),
                        (int) $fresh->revision,
                        (bool) ($data['declaration'] ?? false),
                    );
                } catch (CandidateImportRefused $exception) {
                    $this->notifyRefusal($exception);
                    $this->record = CandidateImportBatchResource::getEloquentQuery()->whereKey($batch->getKey())->firstOrFail();

                    return;
                }

                Notification::make()->title(__('candidate_imports.review.notifications.confirmed'))->success()->send();

                $this->redirect($this->postConfirmUrl($fresh));
            });
    }

    /** @param array<string, mixed> $arguments */
    private function applyDecision(callable $decide, array $arguments): void
    {
        $batch = $this->getBatch();
        Gate::authorize('update', $batch);

        $recordNumber = (int) ($arguments['record'] ?? 0);
        $actor = $this->getUser();

        try {
            $decide($batch, $recordNumber, $actor);
        } catch (CandidateImportRefused $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->record = CandidateImportBatchResource::getEloquentQuery()->whereKey($batch->getKey())->firstOrFail();
    }

    private function refusalMessage(CandidateImportRefused $exception): string
    {
        if ($exception->refusalCode()->value === 'stale_revision') {
            return __('candidate_imports.review.errors.stale_revision');
        }

        if ($exception->refusalCode()->value === 'execution_in_progress') {
            return __('candidate_imports.review.errors.execution_in_progress');
        }

        return $exception->getMessage();
    }

    /**
     * A batch already executing elsewhere in this workspace is not just a
     * generic refusal: the spec requires explaining the condition and linking
     * to that batch's progress, so the recruiter can see it rather than being
     * left with a bare error and no way to check.
     */
    private function notifyRefusal(CandidateImportRefused $exception): void
    {
        $notification = Notification::make()->title($this->refusalMessage($exception))->danger();

        if ($exception->refusalCode()->value === 'execution_in_progress') {
            $runningId = $exception->context['batch'] ?? null;
            $runningBatch = is_int($runningId) || is_string($runningId)
                ? CandidateImportBatchResource::getEloquentQuery()->find($runningId)
                : null;

            if ($runningBatch instanceof CandidateImportBatch) {
                try {
                    $notification->actions([
                        Action::make('viewRunningImport')
                            ->label(__('candidate_imports.review.errors.view_running_import'))
                            ->url(CandidateImportBatchResource::getUrl('progress', ['record' => $runningBatch])),
                    ]);
                } catch (\Throwable) {
                    // The progress page may not resolve; the message alone still explains the condition.
                }
            }
        }

        $notification->send();
    }

    /**
     * Delivery 3 owns the progress page; link to it by name once it exists,
     * and fall back to this batch's own review URL until it does.
     */
    private function postConfirmUrl(CandidateImportBatch $batch): string
    {
        try {
            return CandidateImportBatchResource::getUrl('progress', ['record' => $batch]);
        } catch (\Throwable) {
            return CandidateImportBatchResource::getUrl('review', ['record' => $batch]);
        }
    }

    private function getBatch(): CandidateImportBatch
    {
        $record = $this->getRecord();

        abort_unless($record instanceof CandidateImportBatch, 404);

        return $record;
    }

    private function getUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
