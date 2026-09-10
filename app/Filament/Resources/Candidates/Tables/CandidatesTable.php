<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Enums\PhoneCountry;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportRow;
use App\Models\Company;
use App\Services\CandidateImportLimits;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['applications.job', 'applications.status'])
                ->withCount('applications')
                // Counted once here, in the same query as every other row, so
                // the availability signal and its two filters never issue a
                // query per candidate.
                ->withCount(['materials as available_materials_count' => fn (Builder $query): Builder => $query
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at')])
                ->withCount(['materials as materials_needing_attention_count' => fn (Builder $query): Builder => $query
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at')
                    ->where(fn (Builder $query): Builder => $query
                        ->whereIn('preparation_status', ['failed', 'no_readable_text', 'file_unavailable'])
                        ->orWhere(fn (Builder $query): Builder => $query
                            ->where('preparation_status', 'preparing')
                            ->where('preparation_started_at', '<=', now()->subMinutes(CandidateImportLimits::STALLED_MINUTES))))]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('candidates.fields.name'))
                    ->weight('medium')
                    ->description(fn (Candidate $record): ?string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('applications_count')
                    ->label(__('candidates.fields.processes'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('applications')
                    ->label(__('candidates.fields.current_processes'))
                    ->state(fn (Candidate $record): Htmlable => self::processes($record))
                    ->html()
                    ->wrap(),
                TextColumn::make('available_materials_count')
                    ->label(__('candidates.fields.materials'))
                    ->badge()
                    ->state(fn (Candidate $record): string => self::materialsState($record))
                    ->color(fn (Candidate $record): string => match (true) {
                        (int) $record->getAttribute('materials_needing_attention_count') > 0 => 'danger',
                        (int) $record->getAttribute('available_materials_count') > 0 => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label(__('candidates.fields.phone'))
                    ->formatStateUsing(fn (?string $state): ?string => PhoneCountry::formatInternational($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('candidates.fields.created_at'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('job')
                    ->label(__('jobs.label'))
                    ->relationship('applications.job', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->label(__('candidates.filters.created_between'))
                    ->schema([
                        DatePicker::make('from')->label(__('candidates.filters.from')),
                        DatePicker::make('until')->label(__('candidates.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
                    }),
                Filter::make('has_materials')
                    ->label(__('candidates.filters.has_materials'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('materials', fn (Builder $query): Builder => $query
                        ->whereNull('deleted_at')
                        ->whereNull('archived_at'))),
                Filter::make('materials_need_attention')
                    ->label(__('candidates.filters.materials_need_attention'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('materials', fn (Builder $query): Builder => $query
                        ->whereNull('deleted_at')
                        ->whereNull('archived_at')
                        ->where(fn (Builder $query): Builder => $query
                            ->whereIn('preparation_status', ['failed', 'no_readable_text', 'file_unavailable'])
                            ->orWhere(fn (Builder $query): Builder => $query
                                ->where('preparation_status', 'preparing')
                                ->where('preparation_started_at', '<=', now()->subMinutes(CandidateImportLimits::STALLED_MINUTES)))))),
                SelectFilter::make('import_batch')
                    ->label(__('candidates.filters.imported_from'))
                    ->options(fn (): array => self::importBatchOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $batchId): Builder => $query->whereIn(
                            'id',
                            CandidateImportRow::query()
                                ->where('batch_id', $batchId)
                                ->whereNotNull('candidate_id')
                                ->select('candidate_id'),
                        ),
                    )),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Every import batch the workspace has run, labeled by source and date so
     * multiple batches from the same source stay distinguishable. Listed by
     * the batch record itself, which always survives, regardless of whether
     * its row-level detail has since been cleared by retention.
     *
     * @return array<int, string>
     */
    private static function importBatchOptions(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Company) {
            return [];
        }

        return CandidateImportBatch::query()
            ->whereBelongsTo($tenant, 'company')
            ->whereNotNull('confirmed_at')
            ->orderByDesc('created_at')
            ->get(['id', 'source_label', 'created_at'])
            ->mapWithKeys(fn (CandidateImportBatch $batch): array => [
                $batch->getKey() => $batch->source_label.' — '.$batch->created_at->toDateString(),
            ])
            ->all();
    }

    /**
     * Which processes this person is in, and where. A candidate is a person;
     * their stage always belongs to a specific job.
     */
    private static function processes(Candidate $candidate): Htmlable
    {
        if ($candidate->applications->isEmpty()) {
            return new HtmlString(
                '<span class="text-gray-400">'.e(__('candidates.view.no_applications')).'</span>',
            );
        }

        $entries = $candidate->applications
            ->sortByDesc('created_at')
            ->take(3)
            ->map(fn (Application $application): string => sprintf(
                '<span class="whitespace-nowrap"><span class="inline-block size-2 rounded-full align-middle" style="background-color: %s"></span> %s <span class="text-gray-400">·</span> %s</span>',
                e($application->status->color),
                e($application->job->name),
                e($application->status->name),
            ))
            ->implode('');

        $remaining = $candidate->applications->count() - 3;

        if ($remaining > 0) {
            $entries .= sprintf(
                '<span class="text-gray-400">%s</span>',
                e(__('candidates.view.and_more', ['count' => $remaining])),
            );
        }

        return new HtmlString('<span class="flex flex-col gap-1 text-sm">'.$entries.'</span>');
    }

    /**
     * A concise, words-only signal of independent-material availability for
     * this row. It never becomes a candidate score or quality badge — only
     * whether material is on file and whether any of it needs attention.
     */
    private static function materialsState(Candidate $candidate): string
    {
        $available = (int) $candidate->getAttribute('available_materials_count');
        $needingAttention = (int) $candidate->getAttribute('materials_needing_attention_count');

        if ($needingAttention > 0) {
            return __('candidates.materials.list.needs_attention', ['count' => $needingAttention]);
        }

        if ($available > 0) {
            return __('candidates.materials.list.available', ['count' => $available]);
        }

        return __('candidates.materials.list.none');
    }
}
