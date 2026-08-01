<?php

namespace App\Filament\Clusters\Automation;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class AutomationCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('clusters.automation');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('clusters.automation');
    }
}
