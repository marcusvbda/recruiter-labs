<?php

namespace App\Filament\Resources\Criteria;

use App\Filament\Resources\Criteria\Pages\CreateCriterion;
use App\Filament\Resources\Criteria\Pages\EditCriterion;
use App\Filament\Resources\Criteria\Pages\ListCriteria;
use App\Filament\Resources\Criteria\Schemas\CriterionForm;
use App\Filament\Resources\Criteria\Tables\CriteriaTable;
use App\Models\Criterion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CriteriaResource extends Resource
{
    protected static ?string $model = Criterion::class;

    // "Criterion" is an irregular singular/plural pair ("Criteria"), which
    // trips up Filament's automatic slug pluralization (it would otherwise
    // derive "criteria/criterias"). Pin the slug explicitly to keep the URL
    // clean and consistent with the other top-level resources.
    protected static ?string $slug = 'criteria';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getModelLabel(): string
    {
        return __('criteria.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('criteria.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('criteria.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return CriterionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CriteriaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCriteria::route('/'),
            'create' => CreateCriterion::route('/create'),
            'edit' => EditCriterion::route('/{record}/edit'),
        ];
    }
}
