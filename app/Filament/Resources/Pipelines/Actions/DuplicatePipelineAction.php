<?php

namespace App\Filament\Resources\Pipelines\Actions;

use App\Actions\DuplicatePipeline;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Models\Pipeline;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class DuplicatePipelineAction
{
    public static function make(string $name = 'duplicate'): Action
    {
        return Action::make($name)
            ->label(__('pipelines.actions.duplicate'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->requiresConfirmation()
            ->modalDescription(__('pipelines.actions.duplicate_description'))
            ->action(function (Pipeline $record): void {
                $copy = app(DuplicatePipeline::class)->handle($record);

                Notification::make()
                    ->title(__('pipelines.notifications.duplicated', ['name' => $copy->name]))
                    ->success()
                    ->send();

                redirect(PipelineResource::getUrl('edit', [
                    'record' => $copy,
                ], tenant: Filament::getTenant()));
            });
    }
}
