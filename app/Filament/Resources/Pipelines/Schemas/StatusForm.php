<?php

namespace App\Filament\Resources\Pipelines\Schemas;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateRenderer;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                        Toggle::make('is_terminal')
                            ->label(__('statuses.fields.is_terminal'))
                            ->helperText(__('statuses.fields.is_terminal_helper'))
                            ->inline(false)
                            ->live(),
                        // What "waiting too long" means differs per stage and per
                        // workspace, so the expectation is configured here rather
                        // than assumed by the product. A closing stage is not
                        // waiting on anyone, so it has no expectation to set.
                        TextInput::make('attention_after_days')
                            ->label(__('statuses.fields.attention_after_days'))
                            ->helperText(__('statuses.fields.attention_after_days_helper'))
                            ->placeholder(__('statuses.fields.attention_after_days_placeholder'))
                            ->suffix(__('statuses.fields.attention_after_days_suffix'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->nullable()
                            ->visible(fn (Get $get): bool => ! (bool) $get('is_terminal')),
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
                        // One reusable template, not a second copy of the content:
                        // the message itself is written and maintained in
                        // Settings → Email templates.
                        Select::make('email_template_id')
                            ->label(__('statuses.fields.email_template'))
                            ->helperText(__('statuses.fields.email_template_helper'))
                            ->hintIcon(Heroicon::OutlinedEnvelope)
                            ->hint(__('statuses.fields.email_template_hint'))
                            ->hintAction(
                                Action::make('manageEmailTemplates')
                                    ->label(__('statuses.actions.manage_email_templates'))
                                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                    ->url(fn (): ?string => self::emailTemplatesUrl(), shouldOpenInNewTab: true)
                                    ->visible(fn (): bool => self::emailTemplatesUrl() !== null),
                            )
                            ->options(self::templateOptions(...))
                            ->searchable()
                            ->native(false)
                            ->required(fn (Get $get): bool => (bool) $get('sends_email'))
                            ->visible(fn (Get $get): bool => (bool) $get('sends_email')),
                        View::make('filament.resources.pipelines.components.template-variables')
                            ->viewData(['groups' => EmailTemplateRenderer::placeholderCatalog()])
                            ->visible(fn (Get $get): bool => (bool) $get('sends_email')),
                    ]),
            ]);
    }

    /**
     * The templates this workspace may attach to a stage. Scoped to the current
     * tenant explicitly — nothing scopes EmailTemplate globally — and limited to
     * available ones, except for a template already attached here: a retired
     * template must stay visible (and labelled) rather than silently vanish from
     * a stage that is still configured to send it.
     *
     * @return array<int, string>
     */
    private static function templateOptions(Get $get): array
    {
        $company = Filament::getTenant();

        if (! $company instanceof Company) {
            return [];
        }

        $selected = $get('email_template_id');

        return EmailTemplate::query()
            ->whereBelongsTo($company)
            ->where(fn ($query) => $query
                ->where('is_available', true)
                ->when($selected !== null, fn ($available) => $available->orWhere('id', $selected)))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (EmailTemplate $template): array => [
                (int) $template->getKey() => $template->is_available
                    ? $template->name
                    : $template->name.' — '.__('statuses.fields.email_template_retired'),
            ])
            ->all();
    }

    private static function emailTemplatesUrl(): ?string
    {
        $company = Filament::getTenant();

        return $company instanceof Company
            ? EmailTemplateResource::getUrl('index', tenant: $company)
            : null;
    }
}
