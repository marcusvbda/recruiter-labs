<?php

namespace App\Filament\Resources\CandidateImportBatches\Pages;

use App\Enums\CandidateImportCsvMode;
use App\Enums\CandidateImportCsvSeparator;
use App\Enums\CandidateImportStatus;
use App\Exceptions\CandidateImportRefused;
use App\Filament\Resources\CandidateImportBatches\CandidateImportBatchResource;
use App\Models\CandidateImportBatch;
use App\Models\Company;
use App\Models\User;
use App\Services\CandidateImportDrafts;
use App\Services\CandidateImportFileStaging;
use App\Services\CandidateImportLimits;
use App\Services\CandidateImportPreviewRun;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * Start an import and feed it its CSV and CV files, one batch at a time.
 *
 * The whole flow lives on one page: choosing the batch's own settings creates
 * the draft (redirecting into `/create/{batch}`), and from there uploading the
 * CSV or any CV re-validates the batch against
 * {@see CandidateImportPreviewRun}. Nothing here decides which row imports —
 * that is the review page delivery 2 adds at `'review'`.
 */
class UploadCandidateImportBatch extends Page
{
    protected static string $resource = CandidateImportBatchResource::class;

    protected string $view = 'filament.resources.candidate-import-batches.pages.upload';

    public ?CandidateImportBatch $batch = null;

    /** @var array{files: int, bytes: int} */
    public array $held = ['files' => 0, 'bytes' => 0];

    public function mount(?CandidateImportBatch $batch = null): void
    {
        if ($batch !== null) {
            abort_unless($batch->company_id === $this->getCompany()->getKey(), 404);
            Gate::authorize('view', $batch);
            $this->batch = $batch;
        }

        $this->refreshHeld();
    }

    public function getTitle(): string|Htmlable
    {
        return __('candidate_imports.upload.title');
    }

    /** @return array<string, mixed> */
    public function limitsData(): array
    {
        return [
            'rows' => Number::format(CandidateImportLimits::ROWS),
            'csv_size' => Number::fileSize(CandidateImportLimits::CSV_BYTES),
            'cv_size' => Number::fileSize(CandidateImportLimits::CV_BYTES),
            'batch_files' => Number::format(CandidateImportLimits::BATCH_FILES),
            'batch_cv_size' => Number::fileSize(CandidateImportLimits::BATCH_CV_BYTES),
        ];
    }

    public function startImportAction(): Action
    {
        return Action::make('startImport')
            ->label(__('candidate_imports.upload.actions.start'))
            ->schema([
                TextInput::make('source_label')
                    ->label(__('candidate_imports.fields.source_label'))
                    ->required()
                    ->minLength(1)
                    ->maxLength(120),
                Select::make('separator')
                    ->label(__('candidate_imports.fields.separator'))
                    ->native(false)
                    ->options([
                        CandidateImportCsvSeparator::Comma->value => __('candidate_imports.separators.comma'),
                        CandidateImportCsvSeparator::Semicolon->value => __('candidate_imports.separators.semicolon'),
                    ])
                    ->default(CandidateImportCsvSeparator::Comma->value)
                    ->required(),
                Checkbox::make('correction_mode')
                    ->label(__('candidate_imports.fields.correction_mode'))
                    ->helperText(__('candidate_imports.fields.correction_mode_helper')),
            ])
            ->action(function (array $data, CandidateImportDrafts $drafts): void {
                $company = $this->getCompany();

                if (! $drafts->hasCapacity($company->getKey())) {
                    Notification::make()
                        ->title(__('candidate_imports.notifications.capacity_reached', [
                            'limit' => CandidateImportLimits::UNCONFIRMED_BATCHES,
                        ]))
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $batch = $drafts->start(
                        $this->getUser(),
                        $company->getKey(),
                        (string) $data['source_label'],
                        (string) $data['separator'],
                        (bool) ($data['correction_mode'] ?? false),
                    );
                } catch (CandidateImportRefused $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                $this->redirect(static::getUrl(['batch' => $batch->getKey()]));
            });
    }

    public function editSettingsAction(): Action
    {
        return Action::make('editSettings')
            ->label(__('candidate_imports.upload.actions.edit_settings'))
            ->fillForm(fn (): array => [
                'source_label' => $this->batch?->source_label,
                'separator' => $this->batch?->separator,
                'correction_mode' => $this->batch?->correction_mode,
            ])
            ->schema([
                TextInput::make('source_label')
                    ->label(__('candidate_imports.fields.source_label'))
                    ->required()
                    ->minLength(1)
                    ->maxLength(120),
                Select::make('separator')
                    ->label(__('candidate_imports.fields.separator'))
                    ->native(false)
                    ->options([
                        CandidateImportCsvSeparator::Comma->value => __('candidate_imports.separators.comma'),
                        CandidateImportCsvSeparator::Semicolon->value => __('candidate_imports.separators.semicolon'),
                    ])
                    ->required(),
                Checkbox::make('correction_mode')
                    ->label(__('candidate_imports.fields.correction_mode'))
                    ->helperText(__('candidate_imports.fields.correction_mode_helper')),
            ])
            ->action(function (array $data, CandidateImportPreviewRun $previewRun): void {
                $batch = $this->requireBatch();
                Gate::authorize('update', $batch);

                $batch->forceFill([
                    'source_label' => (string) $data['source_label'],
                    'separator' => (string) $data['separator'],
                    'correction_mode' => (bool) ($data['correction_mode'] ?? false),
                ])->save();

                $previewRun->invalidate($batch);
                $this->revalidate($previewRun);

                Notification::make()->title(__('candidate_imports.notifications.settings_updated'))->success()->send();
            });
    }

    public function uploadCsvAction(): Action
    {
        return Action::make('uploadCsv')
            ->label(__('candidate_imports.upload.actions.upload_csv'))
            ->schema([
                FileUpload::make('file')
                    ->label(__('candidate_imports.fields.csv_file'))
                    ->storeFiles(false)
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                    ->required(),
            ])
            ->action(function (array $data, CandidateImportPreviewRun $previewRun): void {
                $batch = $this->requireBatch();
                Gate::authorize('update', $batch);

                $file = $data['file'] ?? null;

                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'file' => __('candidate_imports.upload.errors.csv_required'),
                    ]);
                }

                $directory = 'companies/'.$batch->company_id.'/imports/'.$batch->getKey();
                $path = $directory.'/source.csv';

                Storage::disk('candidate_materials')->putFileAs($directory, $file, 'source.csv', ['visibility' => 'private']);

                $batch->forceFill([
                    'csv_path' => $path,
                    'csv_original_name' => $file->getClientOriginalName(),
                ])->save();

                $previewRun->invalidate($batch);
                app(CandidateImportDrafts::class)->touch($batch);
                $this->revalidate($previewRun);

                Notification::make()->title(__('candidate_imports.notifications.csv_uploaded'))->success()->send();
            });
    }

    public function uploadCvFilesAction(): Action
    {
        return Action::make('uploadCvFiles')
            ->label(__('candidate_imports.upload.actions.upload_cvs'))
            ->schema([
                FileUpload::make('files')
                    ->label(__('candidate_imports.fields.cv_files'))
                    ->multiple()
                    ->storeFiles(false)
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->required(),
            ])
            ->action(function (array $data, CandidateImportFileStaging $staging, CandidateImportPreviewRun $previewRun): void {
                $batch = $this->requireBatch();
                Gate::authorize('update', $batch);

                /** @var list<UploadedFile> $files */
                $files = array_values(array_filter(
                    is_array($data['files'] ?? null) ? $data['files'] : [],
                    fn ($file): bool => $file instanceof UploadedFile,
                ));

                if ($files === []) {
                    throw ValidationException::withMessages([
                        'files' => __('candidate_imports.upload.errors.cv_required'),
                    ]);
                }

                $staging->stage($batch, $files);

                $previewRun->invalidate($batch);
                app(CandidateImportDrafts::class)->touch($batch);
                $this->refreshHeld();
                $this->revalidate($previewRun);

                Notification::make()->title(__('candidate_imports.notifications.cvs_uploaded'))->success()->send();
            });
    }

    public function discardAction(): Action
    {
        return Action::make('discard')
            ->label(__('candidate_imports.upload.actions.discard'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('candidate_imports.upload.discard_confirmation'))
            ->visible(fn (): bool => $this->batch !== null)
            ->action(function (CandidateImportDrafts $drafts): void {
                $batch = $this->requireBatch();

                try {
                    $drafts->discard($batch, $this->getUser());
                } catch (CandidateImportRefused $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title(__('candidate_imports.notifications.discarded'))->success()->send();

                $this->redirect(CandidateImportBatchResource::getUrl('create'));
            });
    }

    /** @return list<array{name: string, issues: list<string>}> */
    public function fileIssues(): array
    {
        if ($this->batch === null) {
            return [];
        }

        $rows = $this->batch->files()
            ->whereNotNull('errors')
            ->get()
            ->map(function ($file): array {
                /** @var list<array<string, mixed>> $issues */
                $issues = is_array($file->errors['issues'] ?? null) ? $file->errors['issues'] : [];

                return [
                    'name' => (string) ($file->association_name ?? $file->original_name ?? __('candidate_imports.upload.unnamed_file')),
                    'issues' => array_values(collect($issues)
                        ->map(fn (array $issue): string => __('candidate_imports.issue_codes.'.$issue['code']))
                        ->all()),
                ];
            })
            ->all();

        return array_values($rows);
    }

    /** @return list<string> */
    public function csvErrors(): array
    {
        if ($this->batch === null || $this->batch->errors === null) {
            return [];
        }

        /** @var list<array<string, mixed>> $errors */
        $errors = is_array($this->batch->errors['issues'] ?? null) ? $this->batch->errors['issues'] : [];

        return array_values(collect($errors)
            ->map(function (array $error): string {
                $code = $error['code'] ?? null;
                $record = $error['record'] ?? null;

                $message = __('candidate_imports.csv_error_codes.'.$code);

                return $record === null ? $message : __('candidate_imports.upload.record_error', [
                    'record' => $record,
                    'message' => $message,
                ]);
            })
            ->all());
    }

    public function isReady(): bool
    {
        return $this->batch !== null
            && $this->batch->status === CandidateImportStatus::Ready
            && $this->batch->validated_revision !== null
            && (int) $this->batch->validated_revision === (int) $this->batch->revision;
    }

    /**
     * The review page belongs to delivery 2; its route may not exist yet.
     * Linked by name regardless, so it starts working the moment that
     * delivery registers it — and simply is not shown until then.
     */
    public function reviewUrl(): ?string
    {
        if ($this->batch === null) {
            return null;
        }

        try {
            return CandidateImportBatchResource::getUrl('review', ['record' => $this->batch]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function revalidate(CandidateImportPreviewRun $previewRun): void
    {
        $batch = $this->requireBatch();

        if ($batch->csv_path === null) {
            $this->refreshBatch();

            return;
        }

        $path = Storage::disk($batch->disk)->path($batch->csv_path);

        $reader = app(\App\Services\CandidateImportCsvReader::class);
        $reading = $reader->read(
            $path,
            CandidateImportCsvSeparator::from($batch->separator),
            $batch->correction_mode ? CandidateImportCsvMode::CorrectionFile : CandidateImportCsvMode::Template,
        );

        $previewRun->run($batch, $reading);

        $this->refreshBatch();
    }

    private function refreshBatch(): void
    {
        $batch = $this->requireBatch();
        $this->batch = CandidateImportBatchResource::getEloquentQuery()->whereKey($batch->getKey())->firstOrFail();
        $this->refreshHeld();
    }

    private function refreshHeld(): void
    {
        if ($this->batch === null) {
            $this->held = ['files' => 0, 'bytes' => 0];

            return;
        }

        $this->held = app(CandidateImportFileStaging::class)->held(
            $this->batch->company_id,
            (int) $this->batch->getKey(),
        );
    }

    private function requireBatch(): CandidateImportBatch
    {
        abort_if($this->batch === null, 404);

        return $this->batch;
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
