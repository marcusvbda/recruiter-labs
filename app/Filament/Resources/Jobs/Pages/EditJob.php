<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Actions\RequireJobCriteriaReview;
use App\Filament\Resources\Jobs\Actions\JobStateActions;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Schemas\JobForm;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
use App\Models\JobCriterion;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditJob extends EditRecord
{
    protected static string $resource = JobResource::class;

    public string $activeJobEditTab = 'edit';

    private RequireJobCriteriaReview $requireJobCriteriaReview;

    /**
     * What the evaluation criteria depend on, captured before the save so it can
     * be compared with what the save actually produced.
     *
     * @var array<string, mixed>|null
     */
    private ?array $evaluationInputsBeforeSave = null;

    public function boot(RequireJobCriteriaReview $requireJobCriteriaReview): void
    {
        $this->requireJobCriteriaReview = $requireJobCriteriaReview;
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
        $this->evaluationInputsBeforeSave = $this->evaluationInputs($this->job());
    }

    /**
     * Only a change that can actually affect the evaluation criteria costs the
     * recruiter their confirmation. Editing a criterion or a weight obviously
     * does; so does rewriting the description, the title or the application
     * questions the criteria were derived from. Campaign dates, pausing intake,
     * the hiring target and the rest of the operational metadata do not, and
     * invalidating on every save would train recruiters to click through the
     * confirmation without reading it.
     *
     * The comparison happens after the save because the criteria and questions
     * are Filament repeater relationships: they are written after the record
     * itself, so their new state does not exist yet in `beforeSave()`.
     */
    protected function afterSave(): void
    {
        $before = $this->evaluationInputsBeforeSave;
        $this->evaluationInputsBeforeSave = null;

        $job = $this->job();
        $job->unsetRelation('jobCriteria')->unsetRelation('applicationQuestions')->unsetRelation('coverLetterFileTypes');

        if ($before === null || $before === $this->evaluationInputs($job)) {
            return;
        }

        $this->requireJobCriteriaReview->handle($job);
    }

    /**
     * The job's own contribution to what the criteria mean. Deliberately not the
     * whole record: everything listed here is something an extraction reads, or
     * is the criteria themselves.
     *
     * @return array<string, mixed>
     */
    private function evaluationInputs(Job $job): array
    {
        return [
            'name' => $job->name,
            'description' => $job->description,
            'application_locale' => $job->application_locale->value,
            'cover_letter_required' => $job->cover_letter_required,
            'cover_letter_type' => $job->cover_letter_type->value,
            'cover_letter_file_types' => $job->coverLetterFileTypes
                ->pluck('extension')
                ->sort()
                ->values()
                ->all(),
            'questions' => $job->applicationQuestions
                ->map(fn (JobApplicationQuestion $question): array => [
                    'question' => $question->question,
                    'description' => $question->description,
                    'response_type' => $question->response_type->value,
                    'required' => (bool) $question->required,
                ])
                ->values()
                ->all(),
            'criteria' => $job->jobCriteria
                ->map(fn (JobCriterion $criterion): array => [
                    'id' => (int) $criterion->getKey(),
                    'criterion' => $criterion->criterion,
                    'weight' => $criterion->weight,
                ])
                ->sortBy('id')
                ->values()
                ->all(),
        ];
    }

    private function job(): Job
    {
        $job = $this->getRecord();

        abort_unless($job instanceof Job, 404);

        return $job;
    }

    private function getPreviewUrl(): string
    {
        return route('job.preview', [
            'key' => $this->job()->key,
            'version' => now()->getTimestampMs(),
        ]);
    }
}
