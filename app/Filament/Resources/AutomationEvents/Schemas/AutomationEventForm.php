<?php

namespace App\Filament\Resources\AutomationEvents\Schemas;

use App\Enums\AutomationActionType;
use App\Enums\AutomationEventType;
use App\Models\AutomationEvent;
use App\Models\EmailTemplate;
use App\Models\Job;
use App\Models\Status;
use Filament\Facades\Filament;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AutomationEventForm
{
    public static function configure(Schema $schema, bool $includeAutomatableSelect = false): Schema
    {
        return $schema
            ->components([
                Section::make(__('event-hooks.sections.trigger'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        ...($includeAutomatableSelect ? [static::morphToSelect()] : []),
                        Select::make('event_type')
                            ->label(__('event-hooks.fields.event_type'))
                            ->options(collect(AutomationEventType::cases())->mapWithKeys(fn (AutomationEventType $case) => [$case->value => $case->label()]))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== AutomationEventType::StatusChanged->value) {
                                    $set('status_id', null);
                                }
                            }),
                        Select::make('status_id')
                            ->label(__('event-hooks.fields.status'))
                            ->options(fn (): array => Status::query()
                                ->where('company_id', Filament::getTenant()?->getKey())
                                ->orderBy('order')
                                ->pluck('name', 'id')
                                ->all())
                            ->required(fn (Get $get): bool => $get('event_type') === AutomationEventType::StatusChanged->value)
                            ->visible(fn (Get $get): bool => $get('event_type') === AutomationEventType::StatusChanged->value)
                            ->dehydrated(fn (Get $get): bool => $get('event_type') === AutomationEventType::StatusChanged->value),
                    ]),
                Section::make(__('event-hooks.sections.action'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('action_type')
                            ->label(__('event-hooks.fields.action_type'))
                            ->options(collect(AutomationActionType::cases())->mapWithKeys(fn (AutomationActionType $case) => [$case->value => $case->label()]))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== AutomationActionType::SendEmail->value) {
                                    $set('action_config.email_template_id', null);
                                }
                            }),
                        // Writes into the nested `action_config` JSON column via
                        // Filament's dot-notation state binding: a component named
                        // `action_config.email_template_id` reads/writes
                        // `$data['action_config']['email_template_id']` when the
                        // schema's state is dehydrated, without needing a `Group`
                        // container or manual `mutateFormDataBeforeCreate`/`Save`
                        // hooks. This mirrors Filament's documented pattern for
                        // saving flat fields into JSON-cast columns.
                        Select::make('action_config.email_template_id')
                            ->label(__('event-hooks.fields.email_template'))
                            ->options(fn (): array => EmailTemplate::query()
                                ->where('company_id', Filament::getTenant()?->getKey())
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('action_type') === AutomationActionType::SendEmail->value)
                            ->visible(fn (Get $get): bool => $get('action_type') === AutomationActionType::SendEmail->value)
                            ->dehydrated(fn (Get $get): bool => $get('action_type') === AutomationActionType::SendEmail->value),
                    ]),
            ]);
    }

    public static function morphToSelect(): MorphToSelect
    {
        $types = static::automatableTypes();

        return MorphToSelect::make('automatable')
            ->types(array_values($types))
            ->modifyKeySelectUsing(fn (Select $select): Select => static::withAllOption($select, $types))
            ->required()
            ->columnSpanFull();
    }

    /**
     * Every automatable type registered here automatically gets the "All X"
     * option added by `withAllOption()` below — a future second type (e.g.
     * `Application`) needs no extra wiring for that.
     *
     * @return array<string, Type>
     */
    public static function automatableTypes(): array
    {
        $types = [
            Type::make(Job::class)
                ->titleAttribute('name')
                ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $query->where('company_id', Filament::getTenant()?->getKey())),
        ];

        return collect($types)->mapWithKeys(fn (Type $type) => [$type->getAlias() => $type])->all();
    }

    /**
     * Used by `AutomationEventsTable` to render either the "All X" fallback
     * label (when `automatable_id` is null) or the linked record's own title
     * for a given `AutomationEvent`, generically across every registered type.
     */
    public static function automatableRecordLabel(AutomationEvent $record): ?string
    {
        $type = static::automatableTypes()[$record->automatable_type] ?? null;

        if (! $type) {
            return null;
        }

        if ($record->automatable_id === null) {
            return static::automatableAllOptionLabel($record->automatable_type);
        }

        return data_get($record->automatable, $type->getTitleAttribute());
    }

    /**
     * Each type's plural noun is looked up explicitly rather than derived via
     * `Str::plural()`, which only pluralizes English text: the label stored on
     * `Type` (and its model's own translations) aren't necessarily in English,
     * so an automatic pluralizer would silently produce untranslated text.
     */
    protected static function automatableAllOptionLabel(?string $alias): ?string
    {
        $pluralLabel = match ($alias) {
            'job' => __('jobs.plural_label'),
            default => null,
        };

        return $pluralLabel ? __('event-hooks.fields.all_option', ['type' => $pluralLabel]) : null;
    }

    /**
     * Adds a synthetic `all` option to the morph "key" select, generically for
     * every registered type: picking it stores a `null` foreign key, which
     * means "every record of this type" rather than one specific record.
     *
     * @param  array<string, Type>  $types
     */
    protected static function withAllOption(Select $select, array $types): Select
    {
        return $select
            ->options(function (Select $component, Get $get) use ($types): array {
                $alias = $get('automatable_type');
                $type = $types[$alias] ?? null;

                if (! $type) {
                    return [];
                }

                $options = $component->evaluate($type->getOptionsUsing) ?? [];
                $allOptionLabel = static::automatableAllOptionLabel($alias);

                return $allOptionLabel ? ['all' => $allOptionLabel] + $options : $options;
            })
            ->getOptionLabelUsing(function (Select $component, Get $get, $value) use ($types): ?string {
                if ($value === 'all') {
                    return static::automatableAllOptionLabel($get('automatable_type'));
                }

                $type = $types[$get('automatable_type')] ?? null;

                return $type ? $component->evaluate($type->getOptionLabelUsing, ['value' => $value]) : null;
            })
            ->afterStateHydrated(function (Select $component, ?Model $record): void {
                if ($record && $record->automatable_id === null && filled($record->automatable_type)) {
                    $component->state('all');
                }
            })
            ->dehydrateStateUsing(fn ($state) => $state === 'all' ? null : $state);
    }
}
