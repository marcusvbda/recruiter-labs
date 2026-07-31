<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Filament\Resources\Jobs\JobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJob extends EditRecord
{
    protected static string $resource = JobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
