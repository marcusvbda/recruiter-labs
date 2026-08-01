<?php

namespace App\Filament\Resources\AutomationEvents\Schemas;

use App\Enums\AutomationActionType;
use App\Enums\AutomationEventType;
use App\Models\EmailTemplate;
use App\Models\Job;
use Filament\Facades\Filament;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AutomationEventForm
{
    /**
     * Shared by the global `AutomationEventResource` form and the contextual
     * `AutomationEventsRelationManager` on `JobResource`. The `MorphToSelect`
     * is only relevant on the global resource: the relation manager's create
     * form doesn't need it, since the parent `Job` record already supplies
     * `automatable_type`/`automatable_id` automatically for the `MorphMany`
     * relationship.
     */
    public static function configure(Schema $schema, bool $includeAutomatableSelect = false): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        ...($includeAutomatableSelect ? [static::morphToSelect()] : []),
                        Select::make('event_type')
                            ->label(__('automation-events.fields.event_type'))
                            ->options(collect(AutomationEventType::cases())->mapWithKeys(fn (AutomationEventType $case) => [$case->value => $case->label()]))
                            ->required(),
                        Select::make('action_type')
                            ->label(__('automation-events.fields.action_type'))
                            ->options(collect(AutomationActionType::cases())->mapWithKeys(fn (AutomationActionType $case) => [$case->value => $case->label()]))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('action_config', [])),
                        // Writes into the nested `action_config` JSON column via
                        // Filament's dot-notation state binding: a component named
                        // `action_config.email_template_id` reads/writes
                        // `$data['action_config']['email_template_id']` when the
                        // schema's state is dehydrated, without needing a `Group`
                        // container or manual `mutateFormDataBeforeCreate`/`Save`
                        // hooks. This mirrors Filament's documented pattern for
                        // saving flat fields into JSON-cast columns.
                        Select::make('action_config.email_template_id')
                            ->label(__('automation-events.fields.email_template'))
                            ->options(fn (): array => EmailTemplate::query()
                                ->where('company_id', Filament::getTenant()?->id)
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->visible(fn (Get $get): bool => $get('action_type') === AutomationActionType::SendEmail->value)
                            ->dehydrated(fn (Get $get): bool => $get('action_type') === AutomationActionType::SendEmail->value),
                        Toggle::make('is_active')
                            ->label(__('automation-events.fields.is_active'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function morphToSelect(): MorphToSelect
    {
        return MorphToSelect::make('automatable')
            ->types([
                Type::make(Job::class)
                    ->titleAttribute('name')
                    ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $query->where('company_id', Filament::getTenant()?->id)),
            ])
            ->required()
            ->columnSpanFull();
    }
}
