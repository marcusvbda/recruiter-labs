<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read Schema $form
 */
class WorkspaceSettings extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.workspace.navigation_label');
    }

    public function getTitle(): string
    {
        return __('settings.workspace.title');
    }

    public function getSubheading(): string
    {
        return __('settings.workspace.subtitle');
    }

    public function mount(): void
    {
        $company = $this->getCompany();

        Gate::authorize('update', $company);

        $this->form->fill($company->only([
            'name',
            'slug',
            'careers_enabled',
            'careers_description',
            'careers_logo_path',
            'manual_review_minutes_per_application',
        ]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hiringWorkflows')
                ->label(__('pipelines.navigation_label'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->url(fn (): string => PipelineResource::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('workspace-settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('settings.actions.save'))
                                ->submit('save'),
                            DeleteAction::make()
                                ->record($this->getCompany())
                                ->requiresConfirmation()
                                ->after(fn () => $this->redirect(route('filament.admin.tenant'))),
                        ]),
                    ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        $company = $this->getCompany();

        return $schema
            ->components([
                Section::make(__('settings.workspace.identity_heading'))
                    ->description(__('settings.workspace.identity_description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('company.fields.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('company.fields.slug'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', trim((string) $state)))
                            ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                            ->helperText(__('company.fields.slug_helper'))
                            ->unique(Company::class, 'slug', ignoreRecord: true),
                    ]),
                // The one number behind the Overview's "Estimated review time
                // saved". It is optional on purpose: with no number from this
                // workspace, the product shows measured counts and says
                // nothing about time rather than inventing an industry average.
                Section::make(__('settings.workspace.productivity.heading'))
                    ->description(__('settings.workspace.productivity.description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        TextInput::make('manual_review_minutes_per_application')
                            ->label(__('settings.workspace.productivity.manual_review_minutes_label'))
                            ->helperText(__('settings.workspace.productivity.manual_review_minutes_helper'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(600)
                            ->suffix(__('settings.workspace.productivity.minutes_suffix')),
                    ]),
                Section::make(__('settings.workspace.careers.heading'))
                    ->description(__('settings.workspace.careers.description'))
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Toggle::make('careers_enabled')
                            ->label(__('settings.workspace.careers.enabled_label'))
                            ->helperText(__('settings.workspace.careers.enabled_helper'))
                            ->inline(false),
                        TextEntry::make('careers_url')
                            ->label(__('settings.workspace.careers.url_label'))
                            ->state(fn (): string => $this->careersUrl($company))
                            ->url(fn (): string => $this->careersUrl($company), shouldOpenInNewTab: true)
                            ->copyable()
                            ->copyMessage(__('settings.workspace.careers.url_copied'))
                            ->helperText(__('settings.workspace.careers.url_helper')),
                        Textarea::make('careers_description')
                            ->label(__('settings.workspace.careers.description_label'))
                            ->helperText(__('settings.workspace.careers.description_helper'))
                            ->rows(4)
                            ->maxLength(2000),
                        FileUpload::make('careers_logo_path')
                            ->label(__('settings.workspace.careers.logo_label'))
                            ->helperText(__('settings.workspace.careers.logo_helper'))
                            ->disk('public')
                            ->directory("careers/{$company->getKey()}")
                            ->visibility('public')
                            ->preventFilePathTampering()
                            ->image()
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(2048)
                            ->rules([
                                'image',
                                'dimensions:max_width=2000,max_height=2000',
                            ])
                            ->imagePreviewHeight('120'),
                    ]),
            ])
            ->record($company)
            ->statePath('data');
    }

    public function save(): void
    {
        $company = $this->getCompany();

        Gate::authorize('update', $company);

        $previousLogoPath = $company->getRawOriginal('careers_logo_path');
        $data = $this->form->getState();

        $company->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'careers_enabled' => $data['careers_enabled'],
            'careers_description' => $data['careers_description'],
            'careers_logo_path' => $data['careers_logo_path'],
            'manual_review_minutes_per_application' => $data['manual_review_minutes_per_application'] === null
                || $data['manual_review_minutes_per_application'] === ''
                    ? null
                    : (int) $data['manual_review_minutes_per_application'],
        ])->save();

        if (is_string($previousLogoPath) && $previousLogoPath !== '' && $previousLogoPath !== $company->careers_logo_path) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        Notification::make()
            ->title(__('settings.notifications.saved'))
            ->success()
            ->send();

        // The slug is the tenant's URL segment: after changing it, every later
        // request against the old segment would 404.
        $this->redirect(static::getUrl(tenant: $company->refresh()));
    }

    private function careersUrl(Company $company): string
    {
        return route('careers.show', ['company' => $company->slug]);
    }

    public function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }
}
