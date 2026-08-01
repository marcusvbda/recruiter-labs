<?php

namespace App\Filament\Resources\Criteria\Pages;

use App\Filament\Resources\Criteria\CriteriaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCriterion extends EditRecord
{
    protected static string $resource = CriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
