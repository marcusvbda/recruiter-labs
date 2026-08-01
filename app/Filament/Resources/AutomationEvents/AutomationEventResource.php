<?php

namespace App\Filament\Resources\AutomationEvents;

use App\Filament\Resources\AutomationEvents\Pages\CreateAutomationEvent;
use App\Filament\Resources\AutomationEvents\Pages\EditAutomationEvent;
use App\Filament\Resources\AutomationEvents\Pages\ListAutomationEvents;
use App\Filament\Resources\AutomationEvents\Schemas\AutomationEventForm;
use App\Filament\Resources\AutomationEvents\Tables\AutomationEventsTable;
use App\Models\AutomationEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AutomationEventResource extends Resource
{
    protected static ?string $model = AutomationEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    public static function getModelLabel(): string
    {
        return __('automation-events.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('automation-events.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('automation-events.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return AutomationEventForm::configure($schema, includeAutomatableSelect: true);
    }

    public static function table(Table $table): Table
    {
        return AutomationEventsTable::configure($table, includeAutomatableColumn: true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomationEvents::route('/'),
            'create' => CreateAutomationEvent::route('/create'),
            'edit' => EditAutomationEvent::route('/{record}/edit'),
        ];
    }
}
