<?php

namespace App\Filament\Resources\Statuses;

use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Resources\Statuses\Pages\CreateStatus;
use App\Filament\Resources\Statuses\Pages\EditStatus;
use App\Filament\Resources\Statuses\Pages\ListStatuses;
use App\Filament\Resources\Statuses\Schemas\StatusForm;
use App\Filament\Resources\Statuses\Tables\StatusesTable;
use App\Models\Status;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StatusesResource extends Resource
{
    protected static ?string $model = Status::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $cluster = RecruitmentCluster::class;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('statuses.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('statuses.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('statuses.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return StatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StatusesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatuses::route('/'),
            'create' => CreateStatus::route('/create'),
            'edit' => EditStatus::route('/{record}/edit'),
        ];
    }
}
