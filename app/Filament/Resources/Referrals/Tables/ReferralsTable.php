<?php

namespace App\Filament\Resources\Referrals\Tables;

use App\Filament\Actions\CopyTrackedUrlAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReferralsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job.name')
                    ->label(__('referrals.fields.job'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('referrals.fields.user'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('published')
                    ->label(__('referrals.fields.published'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('referrals.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    CopyTrackedUrlAction::make('referral.show'),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
