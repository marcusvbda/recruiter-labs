<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Enums\SocialNetwork;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('candidates.sections.contact'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('candidates.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label(__('candidates.fields.email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('candidates.fields.phone'))
                            ->tel()
                            ->maxLength(255),
                    ]),
                Section::make(__('candidates.sections.social_profiles'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('socials')
                            ->label(__('candidates.fields.socials'))
                            ->schema([
                                Select::make('network')
                                    ->label(__('candidates.fields.network'))
                                    ->options(collect(SocialNetwork::cases())
                                        ->mapWithKeys(fn (SocialNetwork $network) => [$network->value => $network->label()]))
                                    ->required(),
                                TextInput::make('account')
                                    ->label(__('candidates.fields.account'))
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel(__('candidates.fields.socials')),
                    ]),
            ]);
    }
}
