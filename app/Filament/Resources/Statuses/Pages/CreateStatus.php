<?php

namespace App\Filament\Resources\Statuses\Pages;

use App\Filament\Resources\Statuses\StatusesResource;
use App\Models\Status;
use Filament\Resources\Pages\CreateRecord;

class CreateStatus extends CreateRecord
{
    protected static string $resource = StatusesResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The `order` column drives the Kanban board's column order and is
        // managed exclusively via drag-and-drop reordering on the table, so
        // new records are simply appended to the end of the list.
        $data['order'] = (Status::query()->max('order') ?? 0) + 1;

        return $data;
    }
}
