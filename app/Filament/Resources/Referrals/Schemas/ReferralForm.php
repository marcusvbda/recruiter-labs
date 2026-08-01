<?php

namespace App\Filament\Resources\Referrals\Schemas;

use App\Models\Referral;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class ReferralForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('referrals.sections.details'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Select::make('job_id')
                            ->label(__('referrals.fields.job'))
                            ->relationship(
                                name: 'job',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('company_id', Filament::getTenant()?->id),
                            )
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('user_id')
                            ->label(__('referrals.fields.user'))
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas(
                                    'companies',
                                    fn (Builder $query): Builder => $query->whereKey(Filament::getTenant()?->id),
                                ),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            // Guards against duplicate referrals for the same job+user
                            // pair, matching the DB-level unique(['job_id', 'user_id'])
                            // constraint — surfaces as a normal validation error
                            // instead of a query exception on submit.
                            ->unique(
                                table: Referral::class,
                                column: 'user_id',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('job_id', $get('job_id')),
                            ),
                    ]),
            ]);
    }
}
