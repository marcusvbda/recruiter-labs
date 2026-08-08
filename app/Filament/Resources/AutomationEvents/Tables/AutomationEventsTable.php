<?php

namespace App\Filament\Resources\AutomationEvents\Tables;

use App\Enums\AutomationActionType;
use App\Enums\AutomationEventType;
use App\Filament\Resources\AutomationEvents\Schemas\AutomationEventForm;
use App\Models\AutomationEvent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomationEventsTable
{
    public static function configure(Table $table, bool $includeAutomatableColumn = false): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_type')
                    ->label(__('event-hooks.fields.event_type'))
                    ->badge()
                    ->formatStateUsing(fn (AutomationEventType $state): string => $state->label())
                    ->description(fn (AutomationEvent $record): ?string => $record->status?->name),
                TextColumn::make('action_type')
                    ->label(__('event-hooks.fields.action_type'))
                    ->badge()
                    ->formatStateUsing(fn (AutomationActionType $state): string => $state->label()),
                IconColumn::make('is_active')
                    ->label(__('event-hooks.fields.is_active'))
                    ->boolean(),
                ...($includeAutomatableColumn ? [
                    TextColumn::make('automatable')
                        ->label(__('event-hooks.fields.automatable'))
                        ->state(fn (AutomationEvent $record): ?string => AutomationEventForm::automatableRecordLabel($record)),
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
