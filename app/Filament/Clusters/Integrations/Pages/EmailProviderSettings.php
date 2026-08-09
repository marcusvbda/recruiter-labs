<?php

namespace App\Filament\Clusters\Integrations\Pages;

use App\Actions\DisconnectConnectedIntegration;
use App\Actions\RemoveCompanyEmailProviderCredentials;
use App\Actions\SetDefaultCompanyEmailProvider;
use App\Actions\TestCompanyEmailProviderCredentials;
use App\Actions\UpdateCompanyEmailProviderSettings;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Filament\Clusters\Integrations\IntegrationsCluster;
use App\Models\Company;
use App\Models\CompanyEmailProviderSetting;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use LogicException;

class EmailProviderSettings extends Page
{
    private const string GmailPluginKey = 'gmail';

    protected static ?string $cluster = IntegrationsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.clusters.integrations.pages.email-provider-settings';

    /** @var array<int, array<string, mixed>> */
    public array $emailProviderSettings = [];

    /** @var array<string, bool|string|null> */
    public array $gmailConnection = [];

    public static function getNavigationLabel(): string
    {
        return __('email_provider.navigation_label');
    }

    public function getTitle(): string
    {
        return __('email_provider.title');
    }

    public function getSubheading(): string
    {
        return __('email_provider.subtitle');
    }

    public function mount(ConnectedIntegrationRegistry $integrationRegistry): void
    {
        Gate::forUser($this->getRecord())->authorize('update', $this->getCompany());

        $this->refreshEmailProviderState($integrationRegistry);
        $this->sendOAuthResultNotification();
    }

    public function configureProviderAction(): Action
    {
        return Action::make('configureProvider')
            ->modal()
            ->modalHeading(fn(array $arguments): string => __('email_provider.configure.heading', [
                'provider' => $this->providerLabel($arguments),
            ]))
            ->modalDescription(__('email_provider.configure.description'))
            ->modalIcon('heroicon-o-key')
            ->modalSubmitActionLabel(__('email_provider.configure.save'))
            ->fillForm(function (array $arguments): array {
                $setting = $this->getProviderSetting($arguments);

                return [
                    'has_existing_api_key' => filled($setting?->api_key),
                    'from_address' => $setting?->from_address,
                ];
            })
            ->schema([
                Hidden::make('has_existing_api_key')
                    ->dehydrated(false),
                TextInput::make('api_key')
                    ->label(__('email_provider.fields.api_key'))
                    ->helperText('Leave blank to keep the existing API key.')
                    ->password()
                    ->revealable()
                    ->required(fn(Get $get): bool => ! (bool) $get('has_existing_api_key'))
                    ->maxLength(512)
                    ->autocomplete('new-password'),
                TextInput::make('from_address')
                    ->label('Sender email address')
                    ->helperText('Recruitment emails will be sent from this address.')
                    ->email()
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (
                array $data,
                array $arguments,
                UpdateCompanyEmailProviderSettings $updateSettings,
                TestCompanyEmailProviderCredentials $testCredentials,
                ConnectedIntegrationRegistry $integrationRegistry,
            ): void {
                $provider = $this->resendProviderFromArguments($arguments);
                $company = $this->getCompany();
                $user = $this->getRecord();

                $updateSettings->run(
                    $company,
                    $user,
                    $provider,
                    filled($data['api_key'] ?? null) ? $data['api_key'] : null,
                    $data['from_address'],
                );
                $result = $testCredentials->run($company, $user, $provider);

                $this->refreshEmailProviderState($integrationRegistry);

                Notification::make()
                    ->title(__($result->messageKey))
                    ->color($result->success ? 'success' : 'danger')
                    ->icon($result->success ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->send();
            });
    }

    public function testProviderAction(): Action
    {
        return Action::make('testProvider')
            ->action(function (
                array $arguments,
                TestCompanyEmailProviderCredentials $testCredentials,
                ConnectedIntegrationRegistry $integrationRegistry,
            ): void {
                $provider = $this->resendProviderFromArguments($arguments);

                try {
                    $result = $testCredentials->run($this->getCompany(), $this->getRecord(), $provider);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $this->refreshEmailProviderState($integrationRegistry);

                Notification::make()
                    ->title(__($result->messageKey))
                    ->color($result->success ? 'success' : 'danger')
                    ->icon($result->success ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->send();
            });
    }

    public function setDefaultProviderAction(): Action
    {
        return Action::make('setDefaultProvider')
            ->action(function (
                array $arguments,
                SetDefaultCompanyEmailProvider $setDefaultProvider,
                ConnectedIntegrationRegistry $integrationRegistry,
            ): void {
                $provider = $this->providerFromArguments($arguments);

                try {
                    $setDefaultProvider->run($this->getCompany(), $this->getRecord(), $provider);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $this->refreshEmailProviderState($integrationRegistry);

                Notification::make()
                    ->title(__('email_provider.notifications.default_updated'))
                    ->success()
                    ->send();
            });
    }

    public function removeProviderAction(): Action
    {
        return Action::make('removeProvider')
            ->requiresConfirmation()
            ->modalHeading(fn(array $arguments): string => __('email_provider.remove.heading', [
                'provider' => $this->providerLabel($arguments),
            ]))
            ->modalDescription(__('email_provider.remove.description'))
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('email_provider.remove.confirm'))
            ->color('danger')
            ->action(function (
                array $arguments,
                RemoveCompanyEmailProviderCredentials $removeCredentials,
                ConnectedIntegrationRegistry $integrationRegistry,
            ): void {
                $provider = $this->resendProviderFromArguments($arguments);

                $removeCredentials->run($this->getCompany(), $this->getRecord(), $provider);

                $this->refreshEmailProviderState($integrationRegistry);

                Notification::make()
                    ->title(__('email_provider.notifications.key_removed'))
                    ->success()
                    ->send();
            });
    }

    public function disconnectGmailAction(): Action
    {
        return Action::make('disconnectGmail')
            ->requiresConfirmation()
            ->modalHeading(fn(): string => __('email_provider.gmail.disconnect.heading', [
                'plugin' => $this->gmailConnection['plugin_label'],
            ]))
            ->modalDescription(__('email_provider.gmail.disconnect.description'))
            ->modalIcon('heroicon-o-link-slash')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('email_provider.gmail.disconnect.confirm'))
            ->color('danger')
            ->action(function (
                DisconnectConnectedIntegration $disconnectIntegration,
                ConnectedIntegrationRegistry $integrationRegistry,
            ): void {
                $company = $this->getCompany();
                $user = $this->getRecord();

                Gate::forUser($user)->authorize('update', $company);

                $disconnectIntegration->run($company, $user, self::GmailPluginKey);
                $this->refreshEmailProviderState($integrationRegistry);

                Notification::make()
                    ->title(__('email_provider.gmail.notifications.disconnected', [
                        'plugin' => $this->gmailConnection['plugin_label'],
                    ]))
                    ->success()
                    ->send();
            });
    }

    public function getRecord(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    /** @param array<string, mixed> $arguments */
    private function providerLabel(array $arguments): string
    {
        return __('email_provider.providers.' . $this->resendProviderFromArguments($arguments)->value);
    }

    private function refreshEmailProviderState(ConnectedIntegrationRegistry $integrationRegistry): void
    {
        $company = $this->getCompany();
        $company->refresh()->load('emailProviderSettings');

        $settingsByProvider = $company->emailProviderSettings
            ->mapWithKeys(fn(CompanyEmailProviderSetting $setting): array => [
                $setting->provider->value => $setting,
            ])
            ->all();

        $this->emailProviderSettings = collect([EmailProvider::Resend])
            ->map(function (EmailProvider $provider) use ($settingsByProvider): array {
                $setting = $settingsByProvider[$provider->value] ?? null;
                $hasKey = $setting !== null && filled($setting->api_key);
                $hasSenderAddress = $setting !== null && filled($setting->from_address);

                return [
                    'provider' => $provider->value,
                    'provider_label' => __('email_provider.providers.' . $provider->value),
                    'icon' => asset('assets/image/icons/resend.png'),
                    'is_default' => $setting !== null && $setting->is_default,
                    'has_key' => $hasKey,
                    'has_sender_address' => $hasSenderAddress,
                    'is_configured' => $hasKey && $hasSenderAddress,
                    'masked_key' => $setting?->maskedKey(),
                    'from_address' => $setting?->from_address,
                    'credential_status_label' => $setting !== null ? $this->credentialStatusLabel($setting) : null,
                    'last_validated_label' => $setting?->validated_at !== null
                        ? __('email_provider.status.last_validated', [
                            'date' => $setting->validated_at->translatedFormat('d M Y, H:i'),
                        ])
                        : __('email_provider.status.never_validated'),
                ];
            })
            ->all();

        $this->refreshGmailConnectionState($integrationRegistry, $settingsByProvider);
    }

    /** @param array<string, CompanyEmailProviderSetting> $settingsByProvider */
    private function refreshGmailConnectionState(
        ConnectedIntegrationRegistry $integrationRegistry,
        array $settingsByProvider,
    ): void {
        $company = $this->getCompany();
        $user = $this->getRecord();
        $pluginMetadata = $this->getPluginMetadata($integrationRegistry, self::GmailPluginKey);
        $connection = ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($user)
            ->where('plugin_key', self::GmailPluginKey)
            ->first();
        $setting = $settingsByProvider[EmailProvider::Gmail->value] ?? null;
        $isConnected = $connection?->status === ConnectedIntegrationStatus::Connected;
        $needsReauthorization = $connection?->status === ConnectedIntegrationStatus::ReauthorizationRequired;
        $isDefault = $setting !== null && $setting->is_default;
        $settingUsesCurrentConnection = $setting !== null
            && $connection !== null
            && $setting->connected_integration_id === $connection->getKey();
        $defaultUsesCurrentConnection = $isDefault && $settingUsesCurrentConnection;

        $this->gmailConnection = [
            'plugin_label' => $pluginMetadata['label'],
            'plugin_description' => $pluginMetadata['description'],
            'plugin_category' => $pluginMetadata['category'],
            'plugin_icon' => $pluginMetadata['icon'],
            'has_credentials' => $isConnected || $needsReauthorization,
            'is_connected' => $isConnected,
            'needs_reauthorization' => $needsReauthorization,
            'is_default' => $isDefault,
            'default_uses_current_connection' => $defaultUsesCurrentConnection,
            'default_uses_another_connection' => $isDefault && ! $settingUsesCurrentConnection,
            'can_set_default' => $isConnected && ! $defaultUsesCurrentConnection,
            'status_label' => match (true) {
                $needsReauthorization => __('email_provider.gmail.status.reauthorization_required'),
                $defaultUsesCurrentConnection => __('email_provider.default_badge'),
                $isConnected => __('email_provider.gmail.status.connected'),
                default => __('email_provider.gmail.status.disconnected'),
            },
            'account_name' => $isConnected ? $connection->account_name : null,
            'account_email' => $isConnected ? $connection->account_email : null,
            'connected_at' => $isConnected && $connection->connected_at !== null
                ? $connection->connected_at->translatedFormat('d M Y, H:i')
                : null,
            'connect_url' => route('integrations.oauth.connect', [
                'company' => $company,
                'plugin' => self::GmailPluginKey,
            ]),
            'reconnect_url' => route('integrations.oauth.reconnect', [
                'company' => $company,
                'plugin' => self::GmailPluginKey,
            ]),
        ];
    }

    /** @return array{key: string, label: string, description: string, category: string, icon: string, capabilities: list<string>} */
    private function getPluginMetadata(ConnectedIntegrationRegistry $integrationRegistry, string $pluginKey): array
    {
        $metadata = collect($integrationRegistry->metadata())
            ->first(fn(array $plugin): bool => $plugin['key'] === $pluginKey);


        if ($metadata === null) {
            throw new LogicException("The [{$pluginKey}] integration plugin is not registered.");
        }

        return $metadata;
    }

    private function sendOAuthResultNotification(): void
    {
        $status = session()->pull('connected_integration_status');
        $message = session()->pull('connected_integration_message');

        if (! is_string($status) || ! is_string($message) || blank($message)) {
            return;
        }

        Notification::make()
            ->title($message)
            ->color(match ($status) {
                'connected', 'disconnected' => 'success',
                default => 'danger',
            })
            ->icon(match ($status) {
                'connected', 'disconnected' => 'heroicon-o-check-circle',
                default => 'heroicon-o-exclamation-triangle',
            })
            ->send();
    }

    /** @param array<string, mixed> $arguments */
    private function getProviderSetting(array $arguments): ?CompanyEmailProviderSetting
    {
        return CompanyEmailProviderSetting::query()
            ->whereBelongsTo($this->getCompany())
            ->where('provider', $this->resendProviderFromArguments($arguments))
            ->first();
    }

    /** @param array<string, mixed> $arguments */
    private function resendProviderFromArguments(array $arguments): EmailProvider
    {
        $provider = $this->providerFromArguments($arguments);

        abort_unless($provider === EmailProvider::Resend, 404);

        return $provider;
    }

    /** @param array<string, mixed> $arguments */
    private function providerFromArguments(array $arguments): EmailProvider
    {
        $provider = EmailProvider::tryFrom((string) ($arguments['provider'] ?? ''));

        abort_unless($provider instanceof EmailProvider, 404);

        return $provider;
    }

    private function credentialStatusLabel(CompanyEmailProviderSetting $setting): string
    {
        return match ($setting->credential_status) {
            EmailCredentialStatus::Active => __('email_provider.status.valid'),
            EmailCredentialStatus::Invalid => __('email_provider.status.invalid'),
            default => __('email_provider.status.untested'),
        };
    }
}
