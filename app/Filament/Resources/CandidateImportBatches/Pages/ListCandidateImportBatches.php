<?php

namespace App\Filament\Resources\CandidateImportBatches\Pages;

use App\Filament\Resources\CandidateImportBatches\CandidateImportBatchResource;
use App\Filament\Resources\CandidateImportBatches\Tables\CandidateImportBatchesTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Every import the workspace has started, shared across the workspace rather
 * than scoped to the viewer: import history is workspace information, and
 * attribution per row already names the real uploader and confirmer.
 */
class ListCandidateImportBatches extends ListRecords
{
    protected static string $resource = CandidateImportBatchResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('candidate_imports.history.title');
    }

    public function table(Table $table): Table
    {
        return CandidateImportBatchesTable::configure($table);
    }
}
