<?php

namespace App\Filament\Resources\Jobs;

use App\Filament\Resources\Jobs\Pages\CreateJob;
use App\Filament\Resources\Jobs\Pages\EditJob;
use App\Filament\Resources\Jobs\Pages\ListJobs;
use App\Filament\Resources\Jobs\Pages\ViewJob;
use App\Filament\Resources\Jobs\Schemas\JobForm;
use App\Filament\Resources\Jobs\Tables\JobsTable;
use App\Models\Job;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobResource extends Resource
{
    protected static ?string $model = Job::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 2;

    public static function getModelLabel(): string
    {
        return __('jobs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('jobs.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('jobs.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return JobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobsTable::configure($table);
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    /**
     * Where "work on this hiring process" leads: a job with candidates opens
     * straight onto its board, and a job without any opens the workspace, where
     * the useful next step is publishing or configuring it.
     *
     * Every surface that offers a job as work — the jobs list, the overview —
     * uses this, so the primary click always means the same thing.
     */
    public static function getWorkspaceUrl(Job $record): string
    {
        if ($record->getAttribute('applications_count') === null) {
            $record->loadCount('applications');
        }

        return static::getUrl('view', array_filter([
            'record' => $record,
            'section' => (int) $record->getAttribute('applications_count') > 0 ? 'pipeline' : null,
        ]));
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->withCount('applications');
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Job) {
            return [];
        }

        return [
            __('jobs.fields.state') => match (true) {
                ! $record->published => __('jobs.state.draft'),
                $record->applications_paused => __('jobs.state.paused'),
                default => __('jobs.state.published'),
            },
            __('jobs.fields.applications_count') => (string) $record->getAttribute('applications_count'),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobs::route('/'),
            'create' => CreateJob::route('/create'),
            'view' => ViewJob::route('/{record}'),
            'edit' => EditJob::route('/{record}/edit'),
        ];
    }
}
