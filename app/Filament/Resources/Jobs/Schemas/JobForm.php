<?php

namespace App\Filament\Resources\Jobs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobForm
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
                            ->label(__('jobs.fields.name'))
                            ->required()
                            ->maxLength(255),
                    ]),
            ]);
    }
}
