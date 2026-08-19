<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
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

        $this->form->fill($company->only(['name', 'slug']));
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
            ])
            ->record($company)
            ->statePath('data');
    }

    public function save(): void
    {
        $company = $this->getCompany();

        Gate::authorize('update', $company);

        $data = $this->form->getState();
        $company->fill(['name' => $data['name'], 'slug' => $data['slug']])->save();

        Notification::make()
            ->title(__('settings.notifications.saved'))
            ->success()
            ->send();

        // The slug is the tenant's URL segment: after changing it, every later
        // request against the old segment would 404.
        $this->redirect(static::getUrl(tenant: $company->refresh()));
    }

    public function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }
}
