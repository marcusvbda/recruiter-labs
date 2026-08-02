<?php

namespace App\Filament\Resources\Referrals;

use App\Filament\Clusters\Recruitment\RecruitmentCluster;
use App\Filament\Resources\Referrals\Pages\CreateReferral;
use App\Filament\Resources\Referrals\Pages\EditReferral;
use App\Filament\Resources\Referrals\Pages\ListReferrals;
use App\Filament\Resources\Referrals\Schemas\ReferralForm;
use App\Filament\Resources\Referrals\Tables\ReferralsTable;
use App\Models\Referral;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReferralResource extends Resource
{
    protected static ?string $model = Referral::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $cluster = RecruitmentCluster::class;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('referrals.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('referrals.plural_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationLabel(): string
    {
        return __('referrals.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return ReferralForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferralsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferrals::route('/'),
            'create' => CreateReferral::route('/create'),
            'edit' => EditReferral::route('/{record}/edit'),
        ];
    }
}
