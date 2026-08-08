<?php

namespace App\Filament\Clusters\Integrations\Pages;

use App\Actions\RemoveCompanyEmailProviderCredentials;
use App\Actions\SetDefaultCompanyEmailProvider;
use App\Actions\TestCompanyEmailProviderCredentials;
use App\Actions\UpdateCompanyEmailProviderSettings;
use App\Enums\EmailCredentialStatus;
use App\Enums\EmailProvider;
use App\Filament\Clusters\Integrations\IntegrationsCluster;
use App\Models\Company;
use App\Models\CompanyEmailProviderSetting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

class EmailProviderSettings extends Page
{
    protected static ?string $cluster = IntegrationsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.clusters.integrations.pages.email-provider-settings';

    /** @var array<int, array<string, mixed>> */
    public array $emailProviderSettings = [];

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

    public function mount(): void
    {
        $this->refreshEmailProviderState();
    }

    public function configureProviderAction(): Action
    {
        return Action::make('configureProvider')
            ->modal()
            ->modalHeading(fn (array $arguments): string => __('email_provider.configure.heading', [
                'provider' => $this->providerLabel($arguments),
            ]))
            ->modalDescription(__('email_provider.configure.description'))
            ->modalIcon('heroicon-o-key')
            ->modalSubmitActionLabel(__('email_provider.configure.save'))
            ->schema([
                TextInput::make('api_key')
                    ->label(__('email_provider.fields.api_key'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(512)
                    ->autocomplete('new-password'),
            ])
            ->action(function (
                array $data,
                array $arguments,
                UpdateCompanyEmailProviderSettings $updateSettings,
                TestCompanyEmailProviderCredentials $testCredentials,
            ): void {
                $provider = EmailProvider::from($arguments['provider']);
                $company = $this->getCompany();
                $user = $this->getRecord();

                $updateSettings->run($company, $user, $provider, $data['api_key']);
                $result = $testCredentials->run($company, $user, $provider);

                $this->refreshEmailProviderState();

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
            ->action(function (array $arguments, TestCompanyEmailProviderCredentials $testCredentials): void {
                $provider = EmailProvider::from($arguments['provider']);

                try {
                    $result = $testCredentials->run($this->getCompany(), $this->getRecord(), $provider);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $this->refreshEmailProviderState();

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
            ->action(function (array $arguments, SetDefaultCompanyEmailProvider $setDefaultProvider): void {
                $provider = EmailProvider::from($arguments['provider']);

                try {
                    $setDefaultProvider->run($this->getCompany(), $this->getRecord(), $provider);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $this->refreshEmailProviderState();

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
            ->modalHeading(fn (array $arguments): string => __('email_provider.remove.heading', [
                'provider' => $this->providerLabel($arguments),
            ]))
            ->modalDescription(__('email_provider.remove.description'))
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('email_provider.remove.confirm'))
            ->color('danger')
            ->action(function (array $arguments, RemoveCompanyEmailProviderCredentials $removeCredentials): void {
                $provider = EmailProvider::from($arguments['provider']);

                $removeCredentials->run($this->getCompany(), $this->getRecord(), $provider);

                $this->refreshEmailProviderState();

                Notification::make()
                    ->title(__('email_provider.notifications.key_removed'))
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
        return __('email_provider.providers.'.$arguments['provider']);
    }

    private function refreshEmailProviderState(): void
    {
        $company = $this->getCompany();
        $company->refresh()->load('emailProviderSettings');

        $settingsByProvider = $company->emailProviderSettings->keyBy(
            fn (CompanyEmailProviderSetting $setting): string => $setting->provider->value,
        );

        $this->emailProviderSettings = collect(EmailProvider::cases())
            ->map(function (EmailProvider $provider) use ($settingsByProvider): array {
                $setting = $settingsByProvider->get($provider->value);
                $hasKey = $setting !== null && filled($setting->api_key);

                return [
                    'provider' => $provider->value,
                    'provider_label' => __('email_provider.providers.'.$provider->value),
                    'icon' => $this->providerIcon($provider),
                    'is_default' => $setting?->is_default ?? false,
                    'has_key' => $hasKey,
                    'masked_key' => $setting?->maskedKey(),
                    'credential_status_label' => $setting !== null ? $this->credentialStatusLabel($setting) : null,
                    'last_validated_label' => $setting?->validated_at !== null
                        ? __('email_provider.status.last_validated', [
                            'date' => $setting->validated_at->translatedFormat('d M Y, H:i'),
                        ])
                        : __('email_provider.status.never_validated'),
                ];
            })
            ->all();
    }

    private function providerIcon(EmailProvider $provider): string
    {
        return match ($provider) {
            EmailProvider::Resend => asset('assets/image/resend-icon.png'),
        };
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
