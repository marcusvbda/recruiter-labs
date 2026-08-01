<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('email-templates.sections.details'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('email-templates.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subject')
                            ->label(__('email-templates.fields.subject'))
                            ->helperText(__('email-templates.fields.subject_helper_text'))
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make(__('email-templates.sections.content'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Textarea::make('body')
                            ->label(__('email-templates.fields.body'))
                            ->helperText(__('email-templates.fields.body_helper_text'))
                            ->required()
                            ->rows(10),
                    ]),
            ]);
    }
}
