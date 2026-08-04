<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Actions\InvalidateJobCriteriaExtraction;
use App\Enums\JobCriteriaProcessingStatus;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Pages\Concerns\GuardsJobPlanLimit;
use App\Filament\Resources\Jobs\Schemas\JobForm;
use App\Models\Job;
use App\Services\LimitManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditJob extends EditRecord
{
    use GuardsJobPlanLimit {
        boot as bootGuardsJobPlanLimit;
    }

    protected static string $resource = JobResource::class;

    public string $activeJobEditTab = 'edit';

    private InvalidateJobCriteriaExtraction $invalidateJobCriteriaExtraction;

    public function boot(
        LimitManager $limitManager,
        InvalidateJobCriteriaExtraction $invalidateJobCriteriaExtraction,
    ): void {
        $this->bootGuardsJobPlanLimit($limitManager);
        $this->invalidateJobCriteriaExtraction = $invalidateJobCriteriaExtraction;
    }

    protected function getHeaderActions(): array
    {
        return [
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

        // The plan-limit guard only concerns published/scheduling fields, which are not
        // editable from the AI Criteria tab. Skip it there, but always persist the
        // submitted data — the form always carries every tab's fields, so returning
        // early without saving would silently discard edits made on the other tabs.
        if ($this->activeJobEditTab !== 'ai-criteria') {
            $this->ensureJobCanBeSaved($data, $record);
        }

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
