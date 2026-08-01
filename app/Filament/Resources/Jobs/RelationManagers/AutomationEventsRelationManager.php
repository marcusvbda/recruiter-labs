<?php

namespace App\Filament\Resources\Jobs\RelationManagers;

use App\Filament\Resources\AutomationEvents\Schemas\AutomationEventForm;
use App\Filament\Resources\AutomationEvents\Tables\AutomationEventsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AutomationEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'automationEvents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('automation-events.relation_manager.title');
    }

    public function form(Schema $schema): Schema
    {
        // Unlike the global `AutomationEventResource` form, no `MorphToSelect`
        // is needed here: the parent `Job` record supplies
        // `automatable_type`/`automatable_id` automatically since
        // `Job::automationEvents()` is a `MorphMany` relationship.
        return AutomationEventForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return AutomationEventsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
