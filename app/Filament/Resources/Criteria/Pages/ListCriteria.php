<?php

namespace App\Filament\Resources\Criteria\Pages;

use App\Filament\Resources\Criteria\CriteriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCriteria extends ListRecords
{
    protected static string $resource = CriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
