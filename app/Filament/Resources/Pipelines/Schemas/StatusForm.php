<?php

namespace App\Filament\Resources\Pipelines\Schemas;

use App\Services\EmailTemplateRenderer;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class StatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('statuses.sections.details'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('statuses.fields.name'))
                            ->required()
                            ->maxLength(255),
                        ColorPicker::make('color')
                            ->label(__('statuses.fields.color'))
                            ->default('#3b82f6')
                            ->required(),
                        Toggle::make('is_final_stage')
                            ->label(__('statuses.fields.is_final_stage'))
                            ->helperText(__('statuses.fields.is_final_stage_helper'))
                            ->inline(false),
                        Toggle::make('is_hired')
                            ->label(__('statuses.fields.is_hired'))
                            ->helperText(__('statuses.fields.is_hired_helper'))
                            ->inline(false),
                    ]),
                Section::make(__('statuses.sections.communication'))
                    ->description(__('statuses.sections.communication_description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Toggle::make('sends_email')
                            ->label(__('statuses.fields.sends_email'))
                            ->helperText(__('statuses.fields.sends_email_helper'))
                            ->inline(false)
                            ->live(),
                        TextInput::make('email_subject')
                            ->label(__('statuses.fields.email_subject'))
                            ->placeholder(__('statuses.fields.email_subject_placeholder'))
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => (bool) $get('sends_email'))
                            ->visible(fn (Get $get): bool => (bool) $get('sends_email')),
                        RichEditor::make('email_body')
                            ->label(__('statuses.fields.email_body'))
                            ->helperText(__('statuses.fields.email_body_helper'))
                            ->fileAttachments(false)
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])
                            ->required(fn (Get $get): bool => (bool) $get('sends_email'))
                            ->visible(fn (Get $get): bool => (bool) $get('sends_email')),
                        View::make('filament.resources.pipelines.components.template-variables')
                            ->viewData(['groups' => EmailTemplateRenderer::placeholderCatalog()])
                            ->visible(fn (Get $get): bool => (bool) $get('sends_email')),
                    ]),
            ]);
    }
}
