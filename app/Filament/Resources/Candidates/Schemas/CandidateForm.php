<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Enums\PhoneCountry;
use App\Enums\SocialNetwork;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Support\Js;

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
                        Group::make()
                            ->columns(3)
                            ->schema([
                                Select::make('phone_country')
                                    ->label(__('candidates.fields.phone_country'))
                                    ->options(PhoneCountry::options())
                                    ->default(PhoneCountry::Brazil->value)
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->saved(false),
                                TextInput::make('phone')
                                    ->label(__('candidates.fields.phone'))
                                    ->tel()
                                    ->mask(self::phoneMask())
                                    ->prefix(fn (Get $get): string => self::phoneCountry($get)->callingCode())
                                    ->placeholder(fn (Get $get): string => self::phoneCountry($get)->placeholder())
                                    ->stripCharacters(['(', ')', ' ', '-'])
                                    ->afterStateHydrated(function (TextInput $component, ?string $state, Set $set): void {
                                        $phone = PhoneCountry::split($state);

                                        $set('phone_country', $phone['country']->value);
                                        $component->state($phone['national_number']);
                                    })
                                    ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => self::phoneCountry($get)->toInternational($state))
                                    ->maxLength(15)
                                    ->columnSpan(2),
                            ]),
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

    private static function phoneCountry(Get $get): PhoneCountry
    {
        return PhoneCountry::tryFrom((string) $get('phone_country')) ?? PhoneCountry::Brazil;
    }

    private static function phoneMask(): RawJs
    {
        $masks = Js::from(PhoneCountry::masks())->toHtml();
        $defaultMask = Js::from(PhoneCountry::Brazil->mask())->toHtml();

        return RawJs::make("{$masks}[\$wire.get('data.phone_country')] ?? {$defaultMask}");
    }
}
