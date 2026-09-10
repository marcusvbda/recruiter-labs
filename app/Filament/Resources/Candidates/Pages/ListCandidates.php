<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\CandidateImportBatches\CandidateImportBatchResource;
use App\Filament\Resources\Candidates\CandidateResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCandidates extends ListRecords
{
    protected static string $resource = CandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importCandidates')
                ->label(__('candidate_imports.actions.import_candidates'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->url(CandidateImportBatchResource::getUrl('create')),
            Action::make('importHistory')
                ->label(__('candidate_imports.actions.import_history'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->url(CandidateImportBatchResource::getUrl('history')),
            CreateAction::make(),
        ];
    }
}
