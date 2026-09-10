<?php

namespace App\Filament\Resources\CandidateImportBatches;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\CandidateImportBatches\Pages\ListCandidateImportBatches;
use App\Filament\Resources\CandidateImportBatches\Pages\ProgressCandidateImportBatch;
use App\Filament\Resources\CandidateImportBatches\Pages\ReviewCandidateImportBatch;
use App\Filament\Resources\CandidateImportBatches\Pages\UploadCandidateImportBatch;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\CandidateImportBatch;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

/**
 * Import screens reached from inside Candidates, not from their own nav entry.
 *
 * Kept hidden the same way {@see ApplicationResource}
 * is: no sidebar item of its own. This resource is skipped when the panel
 * builds its active-route map (navigation is off), so keeping Candidates
 * active while the recruiter is here is {@see CandidateResource}'s job, which
 * merges in this resource's own pattern — not the other way around, or the
 * two overrides would call each other forever.
 */
class CandidateImportBatchResource extends Resource
{
    protected static ?string $model = CandidateImportBatch::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('candidate_imports.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('candidate_imports.plural_label');
    }

    /** @return Builder<CandidateImportBatch> */
    public static function getEloquentQuery(): Builder
    {
        $query = CandidateImportBatch::query()->with(['uploadedBy', 'confirmedBy']);

        $tenant = Filament::getTenant();

        return $tenant instanceof Company
            ? $query->whereBelongsTo($tenant, 'company')
            : $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            // Filament's base resource `Page` derives breadcrumbs from the
            // resource's `index` URL by default; a hidden resource with no
            // page registered under that key throws the moment any of its
            // pages tries to render one. The import history list IS this
            // resource's index in every sense that matters, so it fills the
            // role Filament expects rather than needing a dedicated stub.
            'index' => ListCandidateImportBatches::route('/history'),
            'create' => UploadCandidateImportBatch::route('/create/{batch?}'),
            'review' => ReviewCandidateImportBatch::route('/review/{record}'),
            'progress' => ProgressCandidateImportBatch::route('/progress/{record}'),
        ];
    }
}
