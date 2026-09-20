<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Services\EmailTemplateRenderer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('email_templates.sections.details'))
                    ->description(__('email_templates.sections.details_description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('email_templates.fields.name'))
                            ->helperText(__('email_templates.fields.name_helper'))
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_available')
                            ->label(__('email_templates.fields.is_available'))
                            ->helperText(__('email_templates.fields.is_available_helper'))
                            ->inline(false)
                            ->default(true),
                    ]),
                Section::make(__('email_templates.sections.content'))
                    ->description(__('email_templates.sections.content_description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('subject')
                            ->label(__('email_templates.fields.subject'))
                            ->placeholder(__('email_templates.fields.subject_placeholder'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),
                        RichEditor::make('body')
                            ->label(__('email_templates.fields.body'))
                            ->helperText(__('email_templates.fields.body_helper'))
                            ->fileAttachments(false)
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])
                            ->required()
                            ->live(onBlur: true),
                        // The same copy-a-chip catalog the stage email uses: one
                        // token vocabulary, described in one place.
                        View::make('filament.resources.pipelines.components.template-variables')
                            ->viewData(['groups' => EmailTemplateRenderer::placeholderCatalog()]),
                    ]),
                Section::make(__('email_templates.sections.preview'))
                    ->description(__('email_templates.sections.preview_description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Placeholder::make('preview')
                            ->hiddenLabel()
                            ->content(fn (Get $get): Htmlable => self::preview(
                                subject: self::asString($get('subject')),
                                body: self::asString($get('body')),
                            )),
                    ]),
            ]);
    }

    /**
     * Resolved against sample values, never against a real candidate: the point
     * is to show what the tokens turn into before anything is sent.
     */
    private static function preview(?string $subject, ?string $body): Htmlable
    {
        $renderer = app(EmailTemplateRenderer::class);

        return view('filament.resources.email-templates.components.preview', [
            'subject' => $renderer->preview($subject),
            'body' => $renderer->preview($body, escape: false),
        ]);
    }

    private static function asString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
