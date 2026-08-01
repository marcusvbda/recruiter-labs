<?php

namespace App\Filament\Resources\AutomationEvents\Pages;

use App\Filament\Resources\AutomationEvents\AutomationEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomationEvent extends CreateRecord
{
    protected static string $resource = AutomationEventResource::class;
}
