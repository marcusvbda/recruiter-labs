<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LeadsStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            LeadsStatsOverview::class,
        ];
    }
}
