<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

/**
 * @property-read Schema $form
 */
class AccountSettings extends Page
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.account.navigation_label');
    }

    public function getTitle(): string
    {
        return __('settings.account.title');
    }

    public function getSubheading(): string
    {
        return __('settings.account.subtitle');
    }

    public function mount(): void
    {
        $this->form->fill($this->getRecord()->only(['name', 'email', 'locale']));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('account-settings-form')
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

        return $schema
            ->components([
                Section::make(__('settings.account.profile_heading'))
                    ->description(__('settings.account.profile_description'))
                    ->columnSpanFull()
                    ->columns(1)
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
                Section::make(__('settings.account.security_heading'))
                    ->description(__('settings.account.security_description'))
                    ->columnSpanFull()
                    ->columns(1)
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
