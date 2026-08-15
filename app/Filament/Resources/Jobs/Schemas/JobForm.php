<?php

namespace App\Filament\Resources\Jobs\Schemas;

use App\Actions\ScheduleJobCriteriaExtraction;
use App\Enums\ApplicationLocale;
use App\Enums\ApplicationQuestionType;
use App\Enums\CoverLetterType;
use App\Enums\JobCriteriaProcessingStatus;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\Pipeline;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class JobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(self::jobDetailsComponents());
    }

    public static function configureForEdit(Schema $schema, string $previewUrl): Schema
    {
        return $schema
            ->components([
                Tabs::make('job-edit-tabs')
                    ->tabs([
                        'edit' => Tab::make(__('jobs.edit_tabs.edit'))
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->schema(self::jobDetailsComponents()),
                        'ai-criteria' => Tab::make(__('jobs.edit_tabs.ai_criteria'))
                            ->icon(Heroicon::OutlinedSparkles)
                            ->schema(self::aiCriteriaComponents()),
                        'preview' => Tab::make(__('jobs.edit_tabs.preview'))
                            ->icon(Heroicon::OutlinedEye)
                            ->schema([
                                View::make('filament.resources.jobs.components.application-preview')
                                    ->viewData([
                                        'previewUrl' => $previewUrl,
                                    ]),
                            ]),
                    ])
                    ->livewireProperty('activeJobEditTab')
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<Component> */
    private static function jobDetailsComponents(): array
    {
        return [
            Section::make(__('jobs.sections.details'))
                ->description(__('jobs.sections.details_description'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('jobs.fields.name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    RichEditor::make('description')
                        ->label(__('jobs.fields.description'))
                        ->fileAttachments(false)
                        ->columnSpanFull(),
                    self::pipelineSelect(),
                    Select::make('application_locale')
                        ->label(__('jobs.fields.application_locale'))
                        ->helperText(__('jobs.fields.application_locale_helper'))
                        ->options(ApplicationLocale::options())
                        ->default(ApplicationLocale::English->value)
                        ->native(false)
                        ->required(),
                ]),
            Section::make(__('jobs.application.questions'))
                ->description(__('jobs.application.questions_description'))
                ->columnSpanFull()
                ->columns(1)
                ->schema([
                    self::applicationQuestionsRepeater(),
                ]),
            Section::make(__('jobs.sections.advanced'))
                ->description(__('jobs.sections.advanced_description'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull()
                ->columns(1)
                ->schema([
                    Section::make(__('jobs.application.campaign_section'))
                        ->description(__('jobs.application.campaign_section_description'))
                        ->columns(2)
                        ->schema([
                            DatePicker::make('starts_at')
                                ->label(__('jobs.fields.starts_at')),
                            DatePicker::make('ends_at')
                                ->label(__('jobs.fields.ends_at'))
                                ->afterOrEqual('starts_at'),
                        ]),
                    Section::make(__('jobs.application.intake_section'))
                        ->description(__('jobs.application.intake_section_description'))
                        ->columns(2)
                        ->schema([
                            Toggle::make('applications_paused')
                                ->label(__('jobs.application.applications_paused'))
                                ->helperText(__('jobs.application.applications_paused_helper'))
                                ->inline(false)
                                ->default(false),
                            TextInput::make('application_limit')
                                ->label(__('jobs.application.application_limit'))
                                ->helperText(__('jobs.application.application_limit_helper'))
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->nullable(),
                        ]),
                    Section::make(__('jobs.application.cv_section'))
                        ->description(__('jobs.application.cv_section_description'))
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
                        ]),
                    Section::make(__('jobs.application.cover_letter_section'))
                        ->description(__('jobs.application.cover_letter_section_description'))
                        ->columns(2)
                        ->schema([
                            Select::make('cover_letter_type')
                                ->label(__('jobs.application.cover_letter_type'))
                                ->options(collect(CoverLetterType::cases())
                                    ->mapWithKeys(fn (CoverLetterType $coverLetterType) => [$coverLetterType->value => $coverLetterType->label()]))
                                ->default(CoverLetterType::Text->value)
                                ->native(false)
                                ->live()
                                ->required(),
                            Toggle::make('cover_letter_required')
                                ->label(__('jobs.application.cover_letter_required'))
                                ->inline(false)
                                ->default(false),
                            CheckboxList::make('coverLetterFileTypes')
                                ->label(__('jobs.application.accepted_cover_letter_types'))
                                ->helperText(__('jobs.application.accepted_cover_letter_types_helper'))
                                ->relationship(
                                    name: 'coverLetterFileTypes',
                                    titleAttribute: 'extension',
                                    modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('sort'),
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (CvFileType $record): string => __('jobs.application.cv_types.'.$record->extension),
                                )
                                ->default(fn (): array => CvFileType::query()->orderBy('sort')->pluck('id')->all())
                                ->bulkToggleable()
                                ->columns(3)
                                ->required(fn (Get $get): bool => $get('cover_letter_type') === CoverLetterType::File->value)
                                ->minItems(fn (Get $get): ?int => $get('cover_letter_type') === CoverLetterType::File->value ? 1 : null)
                                ->visible(fn (Get $get): bool => $get('cover_letter_type') === CoverLetterType::File->value)
                                ->columnSpanFull(),
                        ]),
                ]),
        ];
    }

    /**
     * The pipeline a job runs on is fixed once candidates are in it: every
     * application points at a status of that pipeline, and remapping them is
     * deliberately unsupported. The field explains itself when locked.
     */
    private static function pipelineSelect(): Select
    {
        return Select::make('pipeline_id')
            ->label(__('jobs.fields.pipeline'))
            ->relationship(
                name: 'pipeline',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query
                    ->where('company_id', self::tenantCompanyId())
                    ->orderBy('name'),
            )
            ->default(fn (): ?int => Pipeline::query()
                ->where('company_id', self::tenantCompanyId())
                ->where('is_default', true)
                ->value('id'))
            ->native(false)
            ->preload()
            ->required()
            ->disabled(fn (?Job $record): bool => $record !== null && ! $record->canChangePipeline())
            ->dehydrated(fn (?Job $record): bool => $record === null || $record->canChangePipeline())
            ->helperText(fn (?Job $record): string => $record !== null && ! $record->canChangePipeline()
                ? __('jobs.fields.pipeline_locked_helper')
                : __('jobs.fields.pipeline_helper'));
    }

    private static function applicationQuestionsRepeater(): Repeater
    {
        return Repeater::make('applicationQuestions')
            ->hiddenLabel()
            ->relationship()
            ->orderColumn('sort')
            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                $data['company_id'] = self::tenantCompanyId();

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
                    ->extraAttributes(['style' => 'margin-block: 0.4rem;'])
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
            ->orderable(false)
            ->collapsible()
            ->collapsed()
            ->columns(2);
    }

    /** @return array<Component> */
    private static function aiCriteriaComponents(): array
    {
        return [
            View::make('filament.resources.jobs.components.ai-criteria-not-started')
                ->visible(fn (Job $record): bool => $record->criteria_processing_status === JobCriteriaProcessingStatus::NotStarted),
            View::make('filament.resources.jobs.components.ai-criteria-processing')
                ->viewData(fn (Job $record): array => [
                    'status' => $record->criteria_processing_status,
                ])
                ->visible(fn (Job $record): bool => in_array($record->criteria_processing_status, [
                    JobCriteriaProcessingStatus::Pending,
                    JobCriteriaProcessingStatus::Processing,
                ], strict: true)),
            View::make('filament.resources.jobs.components.ai-criteria-failed')
                ->visible(fn (Job $record): bool => $record->criteria_processing_status === JobCriteriaProcessingStatus::Failed),
            // Actions are always rendered, each individually visible for its own status, so
            // that a hidden action while processing is still "hidden" rather than "missing".
            Actions::make([
                self::runAiCriteriaAnalysisAction(
                    'startAiCriteriaAnalysis',
                    __('jobs.criteria.start_action'),
                    fn (Job $record): bool => $record->criteria_processing_status === JobCriteriaProcessingStatus::NotStarted,
                ),
                self::runAiCriteriaAnalysisAction(
                    'retryAiCriteriaAnalysis',
                    __('jobs.criteria.retry_action'),
                    fn (Job $record): bool => $record->criteria_processing_status === JobCriteriaProcessingStatus::Failed,
                    requiresConfirmation: true,
                ),
                self::runAiCriteriaAnalysisAction(
                    'rerunAiCriteriaAnalysis',
                    __('jobs.criteria.rerun_action'),
                    fn (Job $record): bool => $record->criteria_processing_status === JobCriteriaProcessingStatus::Completed,
                    requiresConfirmation: true,
                ),
            ])
                ->alignment(Alignment::Center),
            Section::make(__('jobs.criteria.section_title'))
                ->description(__('jobs.criteria.section_description'))
                ->columnSpanFull()
                ->schema([
                    Repeater::make('jobCriteria')
                        ->hiddenLabel()
                        ->relationship()
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            $data['company_id'] = self::tenantCompanyId();

                            return $data;
                        })
                        ->schema([
                            TextInput::make('criterion')
                                ->label(__('jobs.criteria.criterion'))
                                ->required()
                                ->maxLength(150)
                                ->columnSpan(7),
                            TextInput::make('weight')
                                ->label(__('jobs.criteria.weight'))
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(10)
                                ->default(5)
                                ->required()
                                ->columnSpan(3),
                            TextInput::make('reason')
                                ->label(__('jobs.criteria.reason'))
                                ->required()
                                ->maxLength(150)
                                ->columnSpanFull(),
                        ])
                        ->columns(10)
                        ->itemLabel(fn (array $state): ?string => filled($state['criterion'] ?? null)
                            ? "{$state['criterion']} → {$state['weight']}"
                            : null)
                        ->collapsible()
                        ->collapsed()
                        ->addActionLabel(__('jobs.criteria.add'))
                        ->reorderable(false),
                ])
                ->visible(fn (Job $record): bool => $record->criteria_processing_status === JobCriteriaProcessingStatus::Completed),
        ];
    }

    private static function runAiCriteriaAnalysisAction(
        string $name,
        string $label,
        Closure $visible,
        bool $requiresConfirmation = false,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedSparkles)
            ->button()
            ->visible($visible)
            ->requiresConfirmation($requiresConfirmation)
            ->modalDescription($requiresConfirmation ? __('jobs.criteria.overwrite_confirmation') : null)
            ->action(function (Job $record): void {
                app(ScheduleJobCriteriaExtraction::class)->handle($record, Auth::id());
                $record->refresh();
            });
    }

    private static function tenantCompanyId(): int
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return (int) $company->getKey();
    }
}
