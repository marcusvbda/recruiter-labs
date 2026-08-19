<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Enums\PhoneCountry;
use App\Models\Application;
use App\Models\Candidate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                ->withCount('applications'))
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
}
