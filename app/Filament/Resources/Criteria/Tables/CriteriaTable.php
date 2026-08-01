<?php

namespace App\Filament\Resources\Criteria\Tables;

use App\Models\Criterion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CriteriaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('criteria.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('prompt')
                    ->label(__('criteria.fields.prompt'))
                    ->limit(50)
                    ->tooltip(fn (Criterion $record): string => $record->prompt),
                TextColumn::make('created_at')
                    ->label(__('criteria.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
