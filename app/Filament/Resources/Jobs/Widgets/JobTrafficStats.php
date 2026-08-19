<?php

namespace App\Filament\Resources\Jobs\Widgets;

use App\Models\Job;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Reach and timing for the public job page. Deliberately separate from
 * recruitment progress: traffic is a campaign question, not a hiring one.
 */
class JobTrafficStats extends StatsOverviewWidget
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
            Stat::make(__('jobs.analytics.metrics.clicks'), (int) ($this->metrics['clicks_count'] ?? 0))
                ->icon(Heroicon::OutlinedCursorArrowRays)
                ->description(__('jobs.analytics.metrics.clicks_description'))
                ->color('primary'),
            Stat::make(
                __('jobs.analytics.metrics.running_time'),
                trans_choice(
                    'jobs.analytics.metrics.days',
                    (int) ($this->metrics['running_days'] ?? 0),
                    ['count' => (int) ($this->metrics['running_days'] ?? 0)],
                ),
            )
                ->icon(Heroicon::OutlinedClock)
                ->description(__('jobs.analytics.metrics.running_time_description'))
                ->color('gray'),
            Stat::make(
                __('jobs.analytics.metrics.time_remaining'),
                $this->remainingTimeLabel($remainingDays, $hasEnded),
            )
                ->icon(Heroicon::OutlinedCalendarDateRange)
                ->description(__('jobs.analytics.metrics.time_remaining_description'))
                ->color($hasEnded ? 'danger' : 'success'),
        ];
    }

    private function remainingTimeLabel(mixed $remainingDays, bool $hasEnded): string
    {
        if ($remainingDays === null) {
            return __('jobs.analytics.metrics.no_end_date');
        }

        if ($hasEnded) {
            return __('jobs.analytics.metrics.ended');
        }

        return trans_choice(
            'jobs.analytics.metrics.days',
            (int) $remainingDays,
            ['count' => (int) $remainingDays],
        );
    }
}
