<?php

namespace App\Filament\Resources\Pipelines\Pages;

use App\Filament\Resources\Pipelines\Actions\DuplicatePipelineAction;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Models\Pipeline;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPipeline extends EditRecord
{
    protected static string $resource = PipelineResource::class;

    public function getSubheading(): ?string
    {
        return __('pipelines.edit_subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            DuplicatePipelineAction::make(),
            DeleteAction::make()
                ->before(function (DeleteAction $action, Pipeline $record): void {
                    $jobCount = $record->jobs()->count();

                    if ($jobCount === 0) {
                        return;
                    }

                    Notification::make()
                        ->title(__('pipelines.notifications.pipeline_in_use_title'))
                        ->body(__('pipelines.errors.pipeline_in_use', ['count' => $jobCount]))
                        ->danger()
                        ->send();

                    $action->halt();
                }),
        ];
    }
}
