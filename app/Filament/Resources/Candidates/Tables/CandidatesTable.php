<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Enums\SocialNetwork;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('candidates.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('candidates.fields.email'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('candidates.fields.phone')),
                TextColumn::make('created_at')
                    ->label(__('candidates.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label(__('candidates.filters.created_between'))
                    ->schema([
                        DatePicker::make('from')->label(__('candidates.filters.from')),
                        DatePicker::make('until')->label(__('candidates.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
                    }),
                SelectFilter::make('social_network')
                    ->label(__('candidates.filters.social_network'))
                    ->options(collect(SocialNetwork::cases())
                        ->mapWithKeys(fn (SocialNetwork $network) => [$network->value => $network->label()]))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $network) => $query->whereJsonContains('socials', [['network' => $network]]),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
