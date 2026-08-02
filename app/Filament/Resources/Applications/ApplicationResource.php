<?php

namespace App\Filament\Resources\Applications;

use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $cluster = RecruitmentCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('applications.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('applications.plural_label');
    }

    /** @return Builder<Application> */
    public static function getEloquentQuery(): Builder
    {
        $query = Application::query()->with([
            'answers',
            'candidate',
            'company',
            'documents',
            'job',
            'referral.user',
            'status',
            'utmParameters',
        ]);

        $tenant = Filament::getTenant();

        return $tenant instanceof Company
            ? $query->whereBelongsTo($tenant, 'company')
            : $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewApplication::route('/{record}'),
        ];
    }
}
