<?php

namespace App\Filament\Resources\Jobs\Widgets;

use App\Models\Job;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class JobOverviewStats extends StatsOverviewWidget
{
    public ?Job $record = null;

    /** @var array<string, int|bool|null> */
    public array $metrics = [];

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $remainingDays = $this->metrics['remaining_days'] ?? null;
        $hasEnded = (bool) ($this->metrics['has_ended'] ?? false);

        return [
            Stat::make(__('jobs.dashboard.metrics.clicks'), (int) ($this->metrics['clicks_count'] ?? 0))
                ->icon(Heroicon::OutlinedCursorArrowRays)
                ->description(__('jobs.dashboard.metrics.clicks_description'))
                ->color('primary'),
            Stat::make(__('jobs.dashboard.metrics.applications'), (int) ($this->metrics['applications_count'] ?? 0))
                ->icon(Heroicon::OutlinedUsers)
                ->description(__('jobs.dashboard.metrics.applications_description'))
                ->color('info'),
            Stat::make(
                __('jobs.dashboard.metrics.running_time'),
                trans_choice(
                    'jobs.dashboard.metrics.days',
                    (int) ($this->metrics['running_days'] ?? 0),
                    ['count' => (int) ($this->metrics['running_days'] ?? 0)],
                ),
            )
                ->icon(Heroicon::OutlinedClock)
                ->description(__('jobs.dashboard.metrics.running_time_description'))
                ->color('warning'),
            Stat::make(
                __('jobs.dashboard.metrics.time_remaining'),
                $this->remainingTimeLabel($remainingDays, $hasEnded),
            )
                ->icon(Heroicon::OutlinedCalendarDateRange)
                ->description(__('jobs.dashboard.metrics.time_remaining_description'))
                ->color($hasEnded ? 'danger' : 'success'),
        ];
    }

    private function remainingTimeLabel(mixed $remainingDays, bool $hasEnded): string
    {
        if ($remainingDays === null) {
            return __('jobs.dashboard.metrics.no_end_date');
        }

        if ($hasEnded) {
            return __('jobs.dashboard.metrics.ended');
        }

        return trans_choice(
            'jobs.dashboard.metrics.days',
            (int) $remainingDays,
            ['count' => (int) $remainingDays],
        );
    }
}
