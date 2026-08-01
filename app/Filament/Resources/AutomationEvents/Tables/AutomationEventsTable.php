<?php

namespace App\Filament\Resources\AutomationEvents\Tables;

use App\Enums\AutomationActionType;
use App\Enums\AutomationEventType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomationEventsTable
{
    /**
     * Shared by the global `AutomationEventResource` table and the contextual
     * `AutomationEventsRelationManager` on `JobResource`. The `automatable`
     * column is only relevant on the global resource, where the target isn't
     * already implied by the current page's context.
     */
    public static function configure(Table $table, bool $includeAutomatableColumn = false): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_type')
                    ->label(__('automation-events.fields.event_type'))
                    ->badge()
                    ->formatStateUsing(fn (AutomationEventType $state): string => $state->label()),
                TextColumn::make('action_type')
                    ->label(__('automation-events.fields.action_type'))
                    ->badge()
                    ->formatStateUsing(fn (AutomationActionType $state): string => $state->label()),
                IconColumn::make('is_active')
                    ->label(__('automation-events.fields.is_active'))
                    ->boolean(),
                ...($includeAutomatableColumn ? [
                    TextColumn::make('automatable.name')
                        ->label(__('automation-events.fields.automatable')),
                ] : []),
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
