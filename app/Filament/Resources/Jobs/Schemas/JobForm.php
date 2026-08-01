<?php

namespace App\Filament\Resources\Jobs\Schemas;

use App\Enums\ApplicationQuestionType;
use App\Models\CvFileType;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class JobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('jobs.sections.details'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('jobs.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Toggle::make('published')
                            ->label(__('jobs.fields.published'))
                            ->default(false),
                        MarkdownEditor::make('description')
                            ->label(__('jobs.fields.description'))
                            ->fileAttachments(false)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('jobs.sections.application'))
                    ->description(__('jobs.application.section_description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        CheckboxList::make('acceptedCvTypes')
                            ->label(__('jobs.application.accepted_cv_types'))
                            ->helperText(__('jobs.application.accepted_cv_types_helper'))
                            ->relationship(
                                name: 'acceptedCvTypes',
                                titleAttribute: 'extension',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('sort'),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (CvFileType $record): string => __('jobs.application.cv_types.'.$record->extension),
                            )
                            ->default(fn (): array => CvFileType::query()->orderBy('sort')->pluck('id')->all())
                            ->bulkToggleable()
                            ->columns(3)
                            ->required()
                            ->minItems(1),
                        Repeater::make('applicationQuestions')
                            ->label(__('jobs.application.questions'))
                            ->relationship()
                            ->orderColumn('sort')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['company_id'] = Filament::getTenant()?->id;

                                return $data;
                            })
                            ->schema([
                                TextInput::make('question')
                                    ->label(__('jobs.application.question'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->columnSpanFull(),
                                Select::make('response_type')
                                    ->label(__('jobs.application.response_type'))
                                    ->options(collect(ApplicationQuestionType::cases())
                                        ->mapWithKeys(fn (ApplicationQuestionType $questionType) => [$questionType->value => $questionType->label()]))
                                    ->default(ApplicationQuestionType::Text->value)
                                    ->native(false)
                                    ->required(),
                                Toggle::make('required')
                                    ->label(__('jobs.application.required'))
                                    ->inline(false)
                                    ->default(true),
                                Textarea::make('description')
                                    ->label(__('jobs.application.field_description'))
                                    ->helperText(__('jobs.application.field_description_helper'))
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel(__('jobs.application.add_question'))
                            ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                            ->itemNumbers()
                            ->collapsible()
                            ->columns(2),
                    ]),
                Section::make(__('jobs.sections.campaign'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        DatePicker::make('starts_at')
                            ->label(__('jobs.fields.starts_at')),
                        DatePicker::make('ends_at')
                            ->label(__('jobs.fields.ends_at'))
                            ->afterOrEqual('starts_at'),
                        Textarea::make('campaign_expectation')
                            ->label(__('jobs.fields.campaign_expectation'))
                            ->rows(3)
                            ->helperText(__('jobs.fields.campaign_expectation_helper'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('jobs.sections.criteria'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Repeater::make('jobCriteria')
                            ->label('')
                            ->relationship()
                            // `JobCriterion` has its own required `company_id` column.
                            // Filament auto-fills `job_id` via
                            // `$relationship->save($record)` but has no notion of the
                            // tenant's `company_id` — it must be injected explicitly here.
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['company_id'] = Filament::getTenant()?->id;

                                return $data;
                            })
                            ->schema([
                                TextInput::make('prompt')
                                    ->label(__('jobs.criteria.prompt'))
                                    ->helperText(__('jobs.criteria.prompt_helper'))
                                    ->required()
                                    ->maxLength(150),
                                Slider::make('weight')
                                    ->label(__('jobs.criteria.weight'))
                                    ->range(minValue: 0, maxValue: 10)
                                    ->step(1)
                                    ->default(5)
                                    ->fillTrack()
                                    ->extraAttributes(['style' => 'margin-block: 0.625rem;'])
                                    ->required(),
                            ])
                            ->addActionLabel(__('jobs.criteria.add'))
                            ->reorderable(false)
                            ->columns(2),
                    ]),
            ]);
    }
}
