<?php

namespace App\Filament\Resources\Jobs\Tables;

use App\Filament\Resources\Jobs\JobResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
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
                TextColumn::make('starts_at')
                    ->label(__('jobs.fields.starts_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('jobs.fields.ends_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('jobs.fields.created_at'))
                    ->dateTime()
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
