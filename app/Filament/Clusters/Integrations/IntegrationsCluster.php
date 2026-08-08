<?php

namespace App\Filament\Clusters\Integrations;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class IntegrationsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('clusters.integrations');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('clusters.integrations');
    }
}
