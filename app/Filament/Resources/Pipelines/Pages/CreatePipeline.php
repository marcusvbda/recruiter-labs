<?php

namespace App\Filament\Resources\Pipelines\Pages;

use App\Actions\ProvisionDefaultPipeline;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Models\Pipeline;
use Filament\Resources\Pages\CreateRecord;

class CreatePipeline extends CreateRecord
{
    protected static string $resource = PipelineResource::class;

    public function getSubheading(): ?string
    {
        return __('pipelines.create_subheading');
    }

    protected function getRedirectUrl(): string
    {
        // Straight into the stage editor: a pipeline without statuses is not
        // usable yet, so the next step is always reviewing them.
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        /** @var Pipeline $pipeline */
        $pipeline = $this->getRecord();

        app(ProvisionDefaultPipeline::class)->seedStarterStatuses($pipeline);
    }
}
