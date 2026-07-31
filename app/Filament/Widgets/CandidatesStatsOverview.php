<?php

namespace App\Filament\Widgets;

use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CandidatesStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $company = Filament::getTenant();

        if (! $company) {
            return [
                Stat::make(__('dashboard.stats.total_candidates'), '—')
                    ->description(__('dashboard.stats.select_company')),
            ];
        }

        $days = collect(range(13, 0))->map(fn (int $daysAgo) => now()->subDays($daysAgo)->startOfDay());

        $counts = $company->candidates()
            ->selectRaw('date(created_at) as date, count(*) as aggregate')
            ->whereDate('created_at', '>=', $days->first())
            ->groupBy('date')
            ->pluck('aggregate', 'date');

        $chart = $days->map(fn (CarbonInterface $day) => (int) ($counts[$day->toDateString()] ?? 0))->all();

        return [
            Stat::make(__('dashboard.stats.total_candidates'), (string) $company->candidates()->count())
                ->description(__('dashboard.chart.heading'))
                ->chart($chart)
                ->color('warning'),
        ];
    }
}
