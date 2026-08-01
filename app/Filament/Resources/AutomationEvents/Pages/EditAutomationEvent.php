<?php

namespace App\Filament\Resources\AutomationEvents\Pages;

use App\Filament\Resources\AutomationEvents\AutomationEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAutomationEvent extends EditRecord
{
    protected static string $resource = AutomationEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
