<?php

namespace App\Filament\Resources\CandidateImportBatches\Tables;

use App\Enums\CandidateImportStatus;
use App\Filament\Resources\CandidateImportBatches\CandidateImportBatchResource;
use App\Models\CandidateImportBatch;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The workspace's shared import history: every batch, whoever started it.
 *
 * A batch still open (`Draft`/`Validating`/`Ready`) points into the upload
 * page delivery 1 built and, once it exists, delivery 2's review page. A
 * batch past that (`Processing` onward) points at delivery 3's progress page.
 * Neither destination is registered yet outside this delivery's own upload
 * page, so the link is only shown once the corresponding route resolves.
 */
class CandidateImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_label')
                    ->label(__('candidate_imports.fields.source_label'))
                    ->searchable(),
                TextColumn::make('uploadedBy.name')
                    ->label(__('candidate_imports.history.uploaded_by'))
                    ->placeholder(__('candidate_imports.history.unknown_uploader')),
                TextColumn::make('status')
                    ->label(__('candidate_imports.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (CandidateImportStatus $state): string => __('candidate_imports.statuses.'.$state->value))
                    ->color(fn (CandidateImportStatus $state): string => match ($state) {
                        CandidateImportStatus::Completed => 'success',
                        CandidateImportStatus::CompletedWithIssues, CandidateImportStatus::Paused => 'warning',
                        CandidateImportStatus::Failed, CandidateImportStatus::Discarded, CandidateImportStatus::Expired => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label(__('candidate_imports.history.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('confirmed_at')
                    ->label(__('candidate_imports.history.confirmed_at'))
                    ->dateTime()
                    ->placeholder(__('candidate_imports.history.not_confirmed'))
                    ->sortable(),
                TextColumn::make('confirmedBy.name')
                    ->label(__('candidate_imports.history.confirmed_by'))
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label(__('candidate_imports.history.open'))
                    ->url(fn (CandidateImportBatch $record): ?string => self::destinationUrl($record))
                    ->visible(fn (CandidateImportBatch $record): bool => self::destinationUrl($record) !== null),
            ]);
    }

    private static function destinationUrl(CandidateImportBatch $record): ?string
    {
        $isOpen = in_array($record->status, [
            CandidateImportStatus::Draft,
            CandidateImportStatus::Validating,
            CandidateImportStatus::Ready,
        ], true);

        // An open batch is not yet confirmed, so it belongs on delivery 2's
        // review page once that exists; until then the upload page it was
        // started from is still a usable destination, since it is bound to
        // the same batch and shows the same state.
        if ($isOpen) {
            try {
                return CandidateImportBatchResource::getUrl('review', ['record' => $record]);
            } catch (\Throwable) {
                return CandidateImportBatchResource::getUrl('create', ['batch' => $record]);
            }
        }

        try {
            return CandidateImportBatchResource::getUrl('progress', ['record' => $record]);
        } catch (\Throwable) {
            return null;
        }
    }
}
