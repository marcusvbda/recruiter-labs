<?php

namespace App\Filament\Resources\Jobs\Tables;

use App\Filament\Actions\CopyTrackedUrlAction;
use App\Filament\Resources\Jobs\Actions\JobStateActions;
use App\Models\Job;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class JobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pipeline')->withCount('applications'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('jobs.fields.name'))
                    ->weight('medium')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('published')
                    ->label(__('jobs.fields.state'))
                    ->badge()
                    ->state(fn (Job $record): string => self::stateLabel($record))
                    ->color(fn (Job $record): string => self::stateColor($record)),
                TextColumn::make('pipeline.name')
                    ->label(__('jobs.fields.pipeline'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('applications_count')
                    ->label(__('jobs.fields.applications_count'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                TernaryFilter::make('published')
                    ->label(__('jobs.fields.state'))
                    ->trueLabel(__('jobs.state.published'))
                    ->falseLabel(__('jobs.state.draft'))
                    ->placeholder(__('jobs.state.all')),
                SelectFilter::make('pipeline')
                    ->label(__('jobs.fields.pipeline'))
                    ->relationship('pipeline', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    CopyTrackedUrlAction::make('job.show'),
                    JobStateActions::publish(),
                    JobStateActions::unpublish(),
                    JobStateActions::duplicate(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function stateLabel(Job $record): string
    {
        return match (true) {
            ! $record->published => __('jobs.state.draft'),
            $record->applications_paused => __('jobs.state.paused'),
            default => __('jobs.state.published'),
        };
    }

    private static function stateColor(Job $record): string
    {
        return match (true) {
            ! $record->published => 'gray',
            $record->applications_paused => 'warning',
            default => 'success',
        };
    }
}
