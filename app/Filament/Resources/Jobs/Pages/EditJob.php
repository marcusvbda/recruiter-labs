<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Pages\Concerns\GuardsJobPlanLimit;
use App\Models\Job;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditJob extends EditRecord
{
    use GuardsJobPlanLimit;

    protected static string $resource = JobResource::class;

    public string $activeJobEditTab = 'edit';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('job-edit-tabs')
                    ->tabs([
                        'edit' => Tab::make(__('jobs.edit_tabs.edit'))
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->schema([
                                $this->getFormContentComponent(),
                            ]),
                        'preview' => Tab::make(__('jobs.edit_tabs.preview'))
                            ->icon(Heroicon::OutlinedEye)
                            ->schema([
                                View::make('filament.resources.jobs.components.application-preview')
                                    ->viewData([
                                        'previewUrl' => $this->getPreviewUrl(),
                                    ]),
                            ]),
                    ])
                    ->livewireProperty('activeJobEditTab')
                    ->columnSpanFull(),
                $this->getRelationManagersContentComponent(),
            ]);
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Job, 404);

        $this->ensureJobCanBeSaved($data, $record);

        return parent::handleRecordUpdate($record, $data);
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
