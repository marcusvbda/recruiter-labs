<?php

namespace App\Filament\Resources\Pipelines\Schemas;

use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PipelineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('pipelines.sections.details'))
                    ->description(__('pipelines.sections.details_description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('pipelines.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('description')
                            ->label(__('pipelines.fields.description'))
                            ->helperText(__('pipelines.fields.description_helper'))
                            ->maxLength(255),
                        Toggle::make('is_default')
                            ->label(__('pipelines.fields.is_default'))
                            ->helperText(__('pipelines.fields.is_default_helper'))
                            ->inline(false)
                            // Only pre-enabled when there is nothing to take the
                            // default away from.
                            ->default(function (): bool {
                                $company = Filament::getTenant();

                                return ! $company instanceof Company
                                    || ! $company->pipelines()->exists();
                            }),
                    ]),
            ]);
    }
}
