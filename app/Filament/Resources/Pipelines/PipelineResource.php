<?php

namespace App\Filament\Resources\Pipelines;

use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Resources\Pipelines\Pages\CreatePipeline;
use App\Filament\Resources\Pipelines\Pages\EditPipeline;
use App\Filament\Resources\Pipelines\Pages\ListPipelines;
use App\Filament\Resources\Pipelines\RelationManagers\StatusesRelationManager;
use App\Filament\Resources\Pipelines\Schemas\PipelineForm;
use App\Filament\Resources\Pipelines\Tables\PipelinesTable;
use App\Models\Pipeline;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PipelineResource extends Resource
{
    protected static ?string $model = Pipeline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $cluster = RecruitmentCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('pipelines.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pipelines.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('pipelines.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return PipelineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PipelinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StatusesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPipelines::route('/'),
            'create' => CreatePipeline::route('/create'),
            'edit' => EditPipeline::route('/{record}/edit'),
        ];
    }
}
