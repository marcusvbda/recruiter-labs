<?php

namespace App\Filament\Resources\Statuses\Pages;

use App\Filament\Resources\Statuses\StatusesResource;
use App\Models\Status;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStatus extends EditRecord
{
    protected static string $resource = StatusesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action, Status $record): void {
                    if (! $record->applications()->exists()) {
                        return;
                    }

                    Notification::make()
                        ->title(__('statuses.notifications.has_applications'))
                        ->danger()
                        ->send();

                    $action->halt();
                }),
        ];
    }
}
