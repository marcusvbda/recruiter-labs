<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Actions\InvalidateJobCriteriaExtraction;
use App\Enums\JobCriteriaProcessingStatus;
use App\Filament\Resources\Jobs\Actions\JobStateActions;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Schemas\JobForm;
use App\Models\Job;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditJob extends EditRecord
{
    protected static string $resource = JobResource::class;

    public string $activeJobEditTab = 'edit';

    private InvalidateJobCriteriaExtraction $invalidateJobCriteriaExtraction;

    public function boot(InvalidateJobCriteriaExtraction $invalidateJobCriteriaExtraction): void
    {
        $this->invalidateJobCriteriaExtraction = $invalidateJobCriteriaExtraction;
    }

    protected function getHeaderActions(): array
    {
        return [
            JobStateActions::publish(),
            JobStateActions::unpublish(),
            JobStateActions::duplicate(),
            DeleteAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return JobForm::configureForEdit($schema, $this->getPreviewUrl());
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Job, 404);

        return parent::handleRecordUpdate($record, $data);
    }

    protected function beforeSave(): void
    {
        $job = $this->getRecord();

        abort_unless($job instanceof Job, 404);

        // Only invalidate the current generation when criteria are actually editable
        // (i.e. a completed analysis is being manually reviewed). Otherwise there is
        // nothing to invalidate, and doing so regardless of status would incorrectly
        // force the job into a "completed" state with no criteria.
        if ($job->criteria_processing_status === JobCriteriaProcessingStatus::Completed) {
            $this->invalidateJobCriteriaExtraction->handle($job);
        }
    }

    private function getPreviewUrl(): string
    {
        $job = $this->getRecord();

        abort_unless($job instanceof Job, 404);

        return route('job.preview', [
            'key' => $job->key,
            'version' => now()->getTimestampMs(),
        ]);
    }
}
