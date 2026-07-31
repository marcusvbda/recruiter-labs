<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CandidatesStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            CandidatesStatsOverview::class,
        ];
    }
}
