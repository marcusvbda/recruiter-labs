<?php

namespace App\Filament\Resources\AutomationEvents\Pages;

use App\Filament\Resources\AutomationEvents\AutomationEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAutomationEvents extends ListRecords
{
    protected static string $resource = AutomationEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
