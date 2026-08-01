<?php

namespace App\Filament\Pages;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Feature;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.navigation_label');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $user = $this->getRecord();

        $this->form->fill($user->only(['name', 'email', 'locale']));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('settings.actions.save'))
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        $user = $this->getRecord();
        $company = Filament::getTenant();

        return $schema
            ->components([
                Tabs::make('settings')
                    ->tabs([
                        Tab::make(__('settings.tabs.general'))
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('settings.fields.name'))
                                    ->required()
                                    ->rules($this->nameRules()),
                                TextInput::make('email')
                                    ->label(__('settings.fields.email'))
                                    ->required()
                                    ->email()
                                    ->maxLength(255)
                                    // Note: intentionally not reusing the shared
                                    // `emailRules()` helper's `Rule::unique()` here.
                                    // That rule omits an explicit column, so Laravel
                                    // guesses it from the validated attribute name —
                                    // which resolves to the full dotted state path
                                    // (`data.email`) inside this schema's
                                    // `statePath('data')`, not `email`. Filament's own
                                    // `unique()` fluent method (already used by
                                    // `RegisterCompany`'s slug field) correctly scopes
                                    // the column to the component's own name.
                                    ->unique(table: User::class, column: 'email', ignorable: $user),
                                Select::make('locale')
                                    ->label(__('settings.fields.language'))
                                    ->native(false)
                                    ->options([
                                        'en' => 'English',
                                        'pt_BR' => 'Português (Brasil)',
                                        'es' => 'Español',
                                    ]),
                            ]),
                        Tab::make(__('settings.tabs.auth'))
                            ->schema([
                                TextInput::make('current_password')
                                    ->label(__('settings.fields.current_password'))
                                    ->password()
                                    ->revealable()
                                    ->rules(fn (Get $get): array => filled($get('password')) ? ['current_password'] : []),
                                TextInput::make('password')
                                    ->label(__('settings.fields.password'))
                                    ->password()
                                    ->revealable()
                                    ->rules([Password::default()])
                                    ->confirmed(),
                                TextInput::make('password_confirmation')
                                    ->label(__('settings.fields.password_confirmation'))
                                    ->password()
                                    ->revealable(),
                            ]),
                        Tab::make(__('settings.tabs.plan'))
                            ->schema([
                                Placeholder::make('plan_name')
                                    ->label(__('settings.plan.current_plan'))
                                    ->content($company?->plan->name ?? '—'),
                                Placeholder::make('plan_features')
                                    ->label(__('settings.plan.included_features'))
                                    ->content(
                                        collect($company?->plan->features ?? [])
                                            ->map(fn (string $value) => Feature::from($value)->label())
                                            ->implode(', ') ?: '—'
                                    ),
                                Placeholder::make('plan_note')
                                    ->label('')
                                    ->content(__('settings.plan.note')),
                            ]),
                        Tab::make(__('settings.tabs.integrations'))
                            ->schema([
                                Placeholder::make('integrations')
                                    ->label('')
                                    ->content(__('settings.integrations.placeholder')),
                            ]),
                    ]),
            ])
            ->record($user)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = $this->getRecord();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->locale = $data['locale'];

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        app()->setLocale($user->locale ?: config('app.locale'));

        $this->form->fill($user->only(['name', 'email', 'locale']));

        Notification::make()
            ->title(__('settings.notifications.saved'))
            ->success()
            ->send();
    }

    public function getRecord(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
