<?php

namespace App\Filament\Resources\Candidates;

use App\Enums\Feature;
use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Candidates\Schemas\CandidateForm;
use App\Filament\Resources\Candidates\Tables\CandidatesTable;
use App\Models\Candidate;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CandidateResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $cluster = RecruitmentCluster::class;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('candidates.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('candidates.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('candidates.navigation_label');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::getTenant()?->hasFeature(Feature::Candidates);
    }

    public static function form(Schema $schema): Schema
    {
        return CandidateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidates::route('/'),
            'create' => CreateCandidate::route('/create'),
            'edit' => EditCandidate::route('/{record}/edit'),
        ];
    }
}
