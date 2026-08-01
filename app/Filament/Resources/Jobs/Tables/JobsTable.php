<?php

namespace App\Filament\Resources\Jobs\Tables;

use App\Models\Job;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                    ->label('URL')
                    ->copyable()
                    ->copyableState(fn (Job $record): string => route('job.show', ['key' => $record->key]))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('published')
                    ->label(__('jobs.fields.published'))
                    ->boolean()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
