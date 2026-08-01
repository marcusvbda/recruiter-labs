<?php

namespace App\Filament\Resources\Jobs\Pages;

use App\Filament\Resources\Jobs\JobResource;
use App\Models\Job;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditJob extends EditRecord
{
    protected static string $resource = JobResource::class;

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
                        Tab::make(__('jobs.edit_tabs.edit'))
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->schema([
                                $this->getFormContentComponent(),
                            ]),
                        Tab::make(__('jobs.edit_tabs.preview'))
                            ->icon(Heroicon::OutlinedEye)
                            ->schema([
                                View::make('filament.resources.jobs.components.application-preview')
                                    ->viewData([
                                        'previewUrl' => $this->getPreviewUrl(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
                $this->getRelationManagersContentComponent(),
            ]);
    }

    private function getPreviewUrl(): string
    {
        $job = $this->getRecord();

        abort_unless($job instanceof Job, 404);

        return route('job.preview', [
            'key' => $job->key,
            'version' => $job->updated_at?->timestamp,
        ]);
    }
}
