<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveJobsProgressWidget;
use App\Filament\Widgets\RecruitmentAttentionWidget;
use App\Filament\Widgets\RecruitmentOverviewStats;
use App\Filament\Widgets\UpcomingInterviewsWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The operational answer to "what needs my attention?". It is deliberately not
 * a welcome page: no greeting, no workspace identity, no decorative hero.
 *
 * The order of the widgets *is* the hierarchy the product promises, and it runs
 * from action to information: what needs attention, what I have committed to,
 * how my live processes are doing, and only then the totals. Metrics never
 * outrank the queue.
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
            RecruitmentAttentionWidget::class,
            UpcomingInterviewsWidget::class,
            ActiveJobsProgressWidget::class,
            RecruitmentOverviewStats::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
