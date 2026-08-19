<?php

namespace App\Filament\Clusters\Settings;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * Everything that configures the workspace rather than running recruitment:
 * the account, the company, reusable hiring workflows, integrations, AI and the
 * plan. Nothing operational belongs here.
 */
class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('settings.navigation_label');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('settings.navigation_label');
    }
}
