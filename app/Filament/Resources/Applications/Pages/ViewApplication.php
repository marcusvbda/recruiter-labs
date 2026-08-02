<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Enums\PhoneCountry;
use App\Enums\SocialNetwork;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\ApplicationDocument;
use App\Models\ApplicationUtmParameter;
use App\Models\Candidate;
use App\Models\Status;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use LogicException;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('applications.admin.title', [
            'candidate' => $this->getApplication()->candidate->name,
        ]);
    }

    public function getBreadcrumb(): string
    {
        return $this->getApplication()->candidate->name;
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        $application = $this->getApplication();

        return [
            JobResource::getUrl(tenant: $application->company) => __('jobs.plural_label'),
            JobResource::getUrl('view', ['record' => $application->job], tenant: $application->company) => $application->job->name,
            '' => $this->getBreadcrumb(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $application = $this->getApplication();

        return [
            Action::make('moveStatus')
                ->label(__('applications.admin.actions.move_status'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->schema([
                    Select::make('status_id')
                        ->label(__('applications.admin.actions.choose_status'))
                        ->options(fn (): array => Status::query()
                            ->where('company_id', $application->company_id)
                            ->orderBy('order')
                            ->pluck('name', 'id')
                            ->all())
                        ->default($application->status_id)
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data) use ($application): void {
                    Gate::authorize('update', $application);

                    $this->record = DB::transaction(function () use ($application, $data): Application {
                        $lockedApplication = Application::query()
                            ->where('company_id', $application->company_id)
                            ->where('job_id', $application->job_id)
                            ->lockForUpdate()
                            ->findOrFail((int) $application->getKey());
                        $status = Status::query()
                            ->where('company_id', $application->company_id)
                            ->findOrFail((int) $data['status_id']);

                        $lockedApplication->status()->associate($status);
                        $lockedApplication->save();

                        return ApplicationResource::getEloquentQuery()->findOrFail((int) $lockedApplication->getKey());
                    });

                    Notification::make()
                        ->title(__('applications.admin.actions.status_updated'))
                        ->success()
                        ->send();
                }),
            Action::make('backToPipeline')
                ->label(__('applications.admin.actions.back_to_pipeline'))
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(JobResource::getUrl('view', [
                    'record' => $application->job,
                    'section' => 'pipeline',
                ], tenant: $application->company)),
            Action::make('openJobPage')
                ->label(__('applications.admin.actions.open_job_page'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(route('job.show', ['key' => $application->job->key]))
                ->openUrlInNewTab(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $application = $this->getApplication();

        return $schema->components([
            View::make('filament.resources.applications.components.header')
                ->viewData(['header' => $this->headerData($application)]),
            Tabs::make('application-details-tabs')
                ->tabs([
                    Tab::make(__('applications.admin.tabs.overview'))
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->schema([
                            View::make('filament.resources.applications.components.overview')
                                ->viewData(['overview' => $this->overviewData($application)]),
                        ]),
                    Tab::make(__('applications.admin.tabs.application'))
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->schema([
                            View::make('filament.resources.applications.components.application')
                                ->viewData(['applicationDetails' => $this->applicationData($application)]),
                        ]),
                    Tab::make(__('applications.admin.tabs.documents'))
                        ->icon(Heroicon::OutlinedFolderOpen)
                        ->badge($application->documents->count())
                        ->schema([
                            View::make('filament.resources.applications.components.documents')
                                ->viewData(['documents' => $this->documentsData($application)]),
                        ]),
                    Tab::make(__('applications.admin.tabs.ai_analysis'))
                        ->icon(Heroicon::OutlinedSparkles)
                        ->schema([
                            View::make('filament.resources.applications.components.ai-analysis')
                                ->viewData(['analysis' => $this->analysisData($application)]),
                        ]),
                ])
                ->persistTabInQueryString('section')
                ->columnSpanFull(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function headerData(Application $application): array
    {
        $analysisStatus = $this->enumValue($application->analysis_status);
        $source = $this->enumValue($application->source);

        return [
            'candidate_name' => $application->candidate->name,
            'candidate_initials' => Str::of($application->candidate->name)
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
                ->join(''),
            'email' => $application->candidate->email ?? __('applications.admin.not_provided'),
            'phone' => PhoneCountry::formatInternational($application->candidate->phone)
                ?? __('applications.admin.not_provided'),
            'job' => $application->job->name,
            'status' => $application->status->name,
            'applied_at' => $application->created_at->translatedFormat('M j, Y · H:i'),
            'source' => (string) __("applications.admin.sources.{$source}"),
            'referral' => $application->referral?->user->name ?? (string) __('applications.admin.not_applicable'),
            'analysis_label' => __("applications.admin.ai.states.{$analysisStatus}.label"),
            'analysis_color' => $this->analysisColor($analysisStatus),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewData(Application $application): array
    {
        $source = $this->enumValue($application->source);

        return [
            'candidate' => [
                'name' => $application->candidate->name,
                'email' => $application->candidate->email ?? __('applications.admin.not_provided'),
                'phone' => PhoneCountry::formatInternational($application->candidate->phone)
                    ?? __('applications.admin.not_provided'),
                'socials' => $this->socialProfiles($application->candidate),
            ],
            'recruitment' => [
                'job' => $application->job->name,
                'status' => $application->status->name,
                'applied_at' => $application->created_at->translatedFormat('M j, Y · H:i'),
            ],
            'origin' => [
                'source' => __("applications.admin.sources.{$source}"),
                'referral' => $application->referral?->user->name ?? __('applications.admin.not_applicable'),
                'submitted_ip' => $application->submitted_ip ?? __('applications.admin.not_provided'),
                'utms' => $application->utmParameters
                    ->map(fn (ApplicationUtmParameter $parameter): array => [
                        'name' => $parameter->name,
                        'value' => $parameter->value,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationData(Application $application): array
    {
        $coverLetterType = $this->enumValue($application->cover_letter_type);

        return [
            'candidate_name' => $application->candidate->name,
            'candidate_email' => $application->candidate->email ?? __('applications.admin.not_provided'),
            'candidate_phone' => PhoneCountry::formatInternational($application->candidate->phone)
                ?? __('applications.admin.not_provided'),
            'cover_letter_type' => __("applications.admin.cover_letter.types.{$coverLetterType}"),
            'cover_letter_text' => $application->cover_letter_text,
            'answers' => $application->answers->map(function (ApplicationAnswer $answer): array {
                $responseType = $this->enumValue($answer->response_type);
                $value = $responseType === 'number'
                    ? ($answer->value_number === null ? null : (string) $answer->value_number)
                    : $answer->value_text;

                return [
                    'question' => $answer->question_snapshot,
                    'type' => __("jobs.application.question_types.{$responseType}"),
                    'value' => filled($value) ? $value : __('applications.admin.not_answered'),
                ];
            })->all(),
        ];
    }

    /**
     * @return list<array<string, bool|int|string|null>>
     */
    private function documentsData(Application $application): array
    {
        $documents = $application->documents
            ->sortBy(fn (ApplicationDocument $document): string => $this->enumValue($document->type))
            ->map(function (ApplicationDocument $document) use ($application): array {
                $type = $this->enumValue($document->type);

                return [
                    'type' => (string) __("applications.admin.documents.types.{$type}"),
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'extension' => Str::upper($document->extension),
                    'size' => Number::fileSize($document->size),
                    'uploaded_at' => $this->formatDate($document->getAttribute('uploaded_at'))
                        ?? $document->created_at->translatedFormat('M j, Y · H:i'),
                    'can_preview' => Str::lower($document->extension) === 'pdf',
                    'view_url' => route('application-documents.view', [
                        'company' => $application->company,
                        'application' => $application,
                        'document' => $document,
                    ]),
                    'download_url' => route('application-documents.download', [
                        'company' => $application->company,
                        'application' => $application,
                        'document' => $document,
                    ]),
                ];
            })
            ->values()
            ->all();

        return array_values($documents);
    }

    /**
     * @return array<string, string>
     */
    private function analysisData(Application $application): array
    {
        $status = $this->enumValue($application->analysis_status);

        return [
            'status' => $status,
            'label' => __("applications.admin.ai.states.{$status}.label"),
            'title' => __("applications.admin.ai.states.{$status}.title"),
            'description' => __("applications.admin.ai.states.{$status}.description"),
            'icon' => match ($status) {
                'processing' => 'heroicon-o-arrow-path',
                'completed' => 'heroicon-o-check-badge',
                'failed' => 'heroicon-o-x-circle',
                'pending_quota' => 'heroicon-o-bolt-slash',
                default => 'heroicon-o-clock',
            },
            'received_at' => $application->created_at->translatedFormat('M j, Y · H:i'),
        ];
    }

    private function analysisColor(string $status): string
    {
        return match ($status) {
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            'pending_quota' => 'warning',
            default => 'gray',
        };
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * @return list<array{label: string, account: string, url: string|null}>
     */
    private function socialProfiles(Candidate $candidate): array
    {
        $socials = $candidate->getAttribute('socials');

        if (! is_array($socials)) {
            return [];
        }

        $profiles = [];

        foreach ($socials as $social) {
            if (! is_array($social)) {
                continue;
            }

            $network = is_string($social['network'] ?? null) ? $social['network'] : 'other';
            $account = is_string($social['account'] ?? null) ? $social['account'] : '';

            if ($account === '') {
                continue;
            }

            $profiles[] = [
                'label' => SocialNetwork::tryFrom($network)?->label() ?? Str::headline($network),
                'account' => $account,
                'url' => filter_var($account, FILTER_VALIDATE_URL) ? $account : null,
            ];
        }

        return $profiles;
    }

    private function formatDate(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->translatedFormat('M j, Y · H:i') : null;
    }

    private function getApplication(): Application
    {
        $record = $this->getRecord();

        if (! $record instanceof Application) {
            throw new LogicException('The application view page must be bound to an application.');
        }

        return $record;
    }
}
