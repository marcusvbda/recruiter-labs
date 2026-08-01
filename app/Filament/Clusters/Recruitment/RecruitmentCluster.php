<?php

namespace App\Filament\Clusters\Recruitment;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class RecruitmentCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('clusters.recruitment');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('clusters.recruitment');
    }
}
