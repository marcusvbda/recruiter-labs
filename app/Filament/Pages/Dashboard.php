<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveJobsProgressWidget;
use App\Filament\Widgets\RecruitmentOverviewStats;
use App\Filament\Widgets\UpcomingInterviewsWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The operational answer to "what needs my attention?". It is deliberately not
 * a welcome page: no greeting, no workspace identity, no decorative hero.
 */
class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('dashboard.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('dashboard.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('dashboard.subtitle');
    }

    public function getWidgets(): array
    {
        return [
            RecruitmentOverviewStats::class,
            UpcomingInterviewsWidget::class,
            ActiveJobsProgressWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
