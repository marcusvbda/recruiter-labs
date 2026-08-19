<?php

namespace App\Filament\Resources\Jobs\Widgets;

use App\Models\Job;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class JobApplicationStatusChart extends ChartWidget
{
    public ?Job $record = null;

    /** @var list<array{name: string, color: string, count: int}> */
    public array $statusDistribution = [];

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '20rem';

    protected ?string $emptyStateHeading = null;

    public function getHeading(): string|Htmlable|null
    {
        return __('jobs.overview.status_chart.title');
    }

    public function getEmptyStateHeading(): string|Htmlable
    {
        return __('jobs.overview.status_chart.empty');
    }

    protected function getData(): array
    {
        if (array_sum(array_column($this->statusDistribution, 'count')) === 0) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => __('jobs.overview.status_chart.dataset'),
                    'data' => array_column($this->statusDistribution, 'count'),
                    'backgroundColor' => array_column($this->statusDistribution, 'color'),
                    'borderWidth' => 0,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => array_column($this->statusDistribution, 'name'),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '66%',
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
