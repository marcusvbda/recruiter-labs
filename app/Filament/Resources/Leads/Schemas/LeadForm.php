<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\SocialNetwork;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('leads.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('leads.fields.email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('leads.fields.phone'))
                            ->tel()
                            ->maxLength(255),
                        Repeater::make('socials')
                            ->label(__('leads.fields.socials'))
                            ->schema([
                                Select::make('network')
                                    ->label(__('leads.fields.network'))
                                    ->options(collect(SocialNetwork::cases())
                                        ->mapWithKeys(fn(SocialNetwork $network) => [$network->value => $network->label()]))
                                    ->required(),
                                TextInput::make('account')
                                    ->label(__('leads.fields.account'))
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel(__('leads.fields.socials')),
                    ])
            ]);
    }
}
