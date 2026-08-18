<?php

namespace App\Filament\Clusters\Integrations\Pages;

use App\Actions\DisconnectConnectedIntegration;
use App\Enums\ConnectedIntegrationStatus;
use App\Filament\Clusters\Integrations\IntegrationsCluster;
use App\Models\Company;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use LogicException;

class CalendarSettings extends Page
{
    private const string GoogleCalendarPluginKey = 'google-calendar';

    protected static ?string $cluster = IntegrationsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.clusters.integrations.pages.calendar-settings';

    /** @var array<string, bool|string|null> */
    public array $calendarConnection = [];

    public static function getNavigationLabel(): string
    {
        return __('calendar.integration_navigation_label');
    }

    public function getTitle(): string
    {
        return __('calendar.title');
    }

    public function getSubheading(): string
    {
        return __('calendar.subtitle');
    }

    public function mount(ConnectedIntegrationRegistry $integrationRegistry): void
    {
        Gate::forUser($this->getUser())->authorize('update', $this->getCompany());

        $this->refreshCalendarConnectionState($integrationRegistry);
        $this->sendOAuthResultNotification();
    }

    public function disconnectGoogleCalendarAction(): Action
    {
        return Action::make('disconnectGoogleCalendar')
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('calendar.disconnect.heading', [
                'plugin' => $this->calendarConnection['plugin_label'],
            ]))
            ->modalDescription(__('calendar.disconnect.description'))
            ->modalIcon('heroicon-o-link-slash')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('calendar.disconnect.confirm'))
            ->color('danger')
            ->action(function (
                DisconnectConnectedIntegration $disconnectIntegration,
                ConnectedIntegrationRegistry $integrationRegistry,
            ): void {
                $company = $this->getCompany();
                $user = $this->getUser();

                Gate::forUser($user)->authorize('update', $company);

                $disconnectIntegration->run($company, $user, self::GoogleCalendarPluginKey);
                $this->refreshCalendarConnectionState($integrationRegistry);

                Notification::make()
                    ->title(__('calendar.notifications.disconnected', [
                        'plugin' => $this->calendarConnection['plugin_label'],
                    ]))
                    ->success()
                    ->send();
            });
    }

    private function refreshCalendarConnectionState(ConnectedIntegrationRegistry $integrationRegistry): void
    {
        $company = $this->getCompany();
        $user = $this->getUser();
        $pluginMetadata = $this->getPluginMetadata($integrationRegistry);
        $connection = ConnectedIntegration::query()
            ->whereBelongsTo($company)
            ->whereBelongsTo($user)
            ->where('plugin_key', self::GoogleCalendarPluginKey)
            ->first();
        $isConnected = $connection?->status === ConnectedIntegrationStatus::Connected;
        $needsReauthorization = $connection?->status === ConnectedIntegrationStatus::ReauthorizationRequired;

        $this->calendarConnection = [
            'plugin_label' => $pluginMetadata['label'],
            'plugin_description' => $pluginMetadata['description'],
            'plugin_category' => $pluginMetadata['category'],
            'plugin_icon' => $pluginMetadata['icon'],
            'has_credentials' => $isConnected || $needsReauthorization,
            'is_connected' => $isConnected,
            'needs_reauthorization' => $needsReauthorization,
            'status_label' => match (true) {
                $isConnected => __('calendar.status.connected'),
                $needsReauthorization => __('calendar.status.reauthorization_required'),
                default => __('calendar.status.disconnected'),
            },
            'account_name' => $isConnected ? $connection->account_name : null,
            'account_email' => $isConnected ? $connection->account_email : null,
            'connected_at' => $isConnected && $connection->connected_at !== null
                ? $connection->connected_at->translatedFormat('d M Y, H:i')
                : null,
            'connect_url' => route('integrations.oauth.connect', [
                'company' => $company,
                'plugin' => self::GoogleCalendarPluginKey,
            ]),
            'reconnect_url' => route('integrations.oauth.reconnect', [
                'company' => $company,
                'plugin' => self::GoogleCalendarPluginKey,
            ]),
        ];
    }

    /** @return array{key: string, label: string, description: string, category: string, icon: string, capabilities: list<string>} */
    private function getPluginMetadata(ConnectedIntegrationRegistry $integrationRegistry): array
    {
        $metadata = collect($integrationRegistry->metadata())
            ->first(fn (array $plugin): bool => $plugin['key'] === self::GoogleCalendarPluginKey);

        if ($metadata === null) {
            throw new LogicException('The Google Calendar integration plugin is not registered.');
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

    private function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function getUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
