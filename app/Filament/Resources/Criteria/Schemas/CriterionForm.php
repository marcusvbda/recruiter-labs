<?php

namespace App\Filament\Resources\Criteria\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CriterionForm
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
                            ->label(__('criteria.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('prompt')
                            ->label(__('criteria.fields.prompt'))
                            ->helperText(__('criteria.fields.prompt_helper_text'))
                            ->required()
                            ->rows(3),
                    ]),
            ]);
    }
}
