<?php

namespace App\Filament\Resources\Statuses\Tables;

use App\Models\Status;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('statuses.fields.name'))
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')
                    ->label(__('statuses.fields.color')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action): void {
                            $hasApplications = $action->getSelectedRecords()
                                ->contains(fn (Status $record): bool => $record->applications()->exists());

                            if (! $hasApplications) {
                                return;
                            }

                            Notification::make()
                                ->title(__('statuses.notifications.has_applications'))
                                ->danger()
                                ->send();

                            $action->halt();
                        }),
                ]),
            ]);
    }
}
