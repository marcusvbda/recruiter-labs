<?php

namespace App\Filament\Resources\Jobs\Tables;

use App\Filament\Resources\Jobs\JobResource;
use App\Models\Job;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('jobs.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->label(__('jobs.fields.key'))
                    ->copyable()
                    ->copyableState(fn (Job $record): string => route('job.show', ['key' => $record->key]))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label(__('jobs.fields.starts_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('jobs.fields.ends_at'))
                    ->date()
                    ->sortable(),
                IconColumn::make('published')
                    ->label(__('jobs.fields.published'))
                    ->boolean()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('pipeline')
                    ->label(__('jobs.pipeline.view'))
                    ->icon(Heroicon::OutlinedViewColumns)
                    ->url(fn ($record): string => JobResource::getUrl('pipeline', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
