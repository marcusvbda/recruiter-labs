<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Actions\RemoveCompanyAiCredentials;
use App\Actions\TestCompanyAiCredentials;
use App\Actions\UpdateCompanyAiSettings;
use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Filament\Clusters\Settings\Concerns\PresentsPlanUsage;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\AiUsageRecord;
use App\Models\CompanyAiSetting;
use App\Models\Plan;
use App\Services\AiCredentialsResolver;
use App\Services\CompanyUsageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class AiSettings extends Page
{
    use PresentsPlanUsage;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.clusters.settings.pages.ai-page';

    /** @var array<string, mixed> */
    public array $aiSettings = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.tabs.ai');
    }

    public function getTitle(): string
    {
        return __('settings.ai.title');
    }

    public function getSubheading(): string
    {
        return __('settings.ai.subtitle');
    }

    public function mount(CompanyUsageService $usageService, AiCredentialsResolver $credentialsResolver): void
    {
        $this->refreshAiState($usageService, $credentialsResolver);
    }

    public function configureOwnAiAction(): Action
    {
        return Action::make('configureOwnAi')
            ->modal()
            ->modalHeading(__('settings.ai.configure.heading'))
            ->modalDescription(__('settings.ai.configure.description'))
            ->modalIcon('heroicon-o-key')
            ->modalSubmitActionLabel(__('settings.ai.configure.save'))
            ->fillForm(fn (): array => ['model' => $this->aiSettings['model']])
            ->schema([
                TextInput::make('api_key')
                    ->label(__('settings.fields.api_key'))
                    ->helperText(__('settings.ai.configure.key_helper'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(512)
                    ->autocomplete('new-password'),
                Select::make('model')
                    ->label(__('settings.fields.model'))
                    ->native(false)
                    ->required()
                    ->options(['gpt-4o-mini' => 'GPT-4o mini']),
            ])
            ->action(function (
                array $data,
                UpdateCompanyAiSettings $updateSettings,
                TestCompanyAiCredentials $testCredentials,
                CompanyUsageService $usageService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $company = $this->getCompany();
                $user = $this->getRecord();

                $updateSettings->run($company, $user, AiProvider::Own, $data['model'], $data['api_key']);
                $result = $testCredentials->run($company, $user);
                $this->refreshAiState($usageService, $credentialsResolver);
                $this->dispatch('refresh-topbar');

                Notification::make()
                    ->title(__($result->messageKey))
                    ->color($result->success ? 'success' : 'danger')
                    ->icon($result->success ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->send();
            });
    }

    public function testOwnAiAction(): Action
    {
        return Action::make('testOwnAi')
            ->action(function (
                TestCompanyAiCredentials $testCredentials,
                CompanyUsageService $usageService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $result = $testCredentials->run($this->getCompany(), $this->getRecord());
                $this->refreshAiState($usageService, $credentialsResolver);
                $this->dispatch('refresh-topbar');

                Notification::make()
                    ->title(__($result->messageKey))
                    ->color($result->success ? 'success' : 'danger')
                    ->icon($result->success ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->send();
            });
    }

    public function removeOwnAiAction(): Action
    {
        return Action::make('removeOwnAi')
            ->requiresConfirmation()
            ->modalHeading(__('settings.ai.remove.heading'))
            ->modalDescription(__('settings.ai.remove.description'))
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('settings.ai.remove.confirm'))
            ->color('danger')
            ->action(function (
                RemoveCompanyAiCredentials $removeCredentials,
                CompanyUsageService $usageService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $removeCredentials->run($this->getCompany(), $this->getRecord());
                $this->refreshAiState($usageService, $credentialsResolver);
                $this->dispatch('refresh-topbar');

                Notification::make()
                    ->title(__('settings.notifications.ai_key_removed'))
                    ->success()
                    ->send();
            });
    }

    public function usePlatformAiAction(): Action
    {
        return Action::make('usePlatformAi')
            ->action(function (
                UpdateCompanyAiSettings $updateSettings,
                CompanyUsageService $usageService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $updateSettings->run(
                    $this->getCompany(),
                    $this->getRecord(),
                    AiProvider::Platform,
                    $this->aiSettings['model'],
                );
                $this->refreshAiState($usageService, $credentialsResolver);
                $this->dispatch('refresh-topbar');

                Notification::make()
                    ->title(__('settings.notifications.ai_platform_enabled'))
                    ->success()
                    ->send();
            });
    }

    private function refreshAiState(
        CompanyUsageService $usageService,
        AiCredentialsResolver $credentialsResolver,
    ): void {
        $company = $this->getCompany();
        $company->refresh()->load(['plan', 'aiSetting']);
        $usage = $usageService->summary($company);

        $setting = $company->aiSetting ?? new CompanyAiSetting([
            'provider' => AiProvider::Platform,
            'model' => 'gpt-4o-mini',
            'credential_status' => AiCredentialStatus::NotConfigured,
        ]);
        $effectiveConfiguration = $credentialsResolver->resolve($company);
        $aiMetric = $usage->metric(Limit::AiAnalyses);
        $ownKeyAllowed = $company->hasFeature(Feature::OwnAiKey);
        $requiredPlan = Plan::query()
            ->orderBy('sort_order')
            ->get()
            ->firstOrFail(fn (Plan $plan): bool => $plan->hasFeature(Feature::OwnAiKey));

        $this->aiSettings = [
            'provider' => $effectiveConfiguration->provider->value,
            'configured_provider' => $setting->provider->value,
            'provider_label' => $this->providerLabel($effectiveConfiguration->provider),
            'model' => $effectiveConfiguration->model,
            'own_key_allowed' => $ownKeyAllowed,
            'own_key_required_plan' => $requiredPlan->name,
            'has_own_key' => filled($setting->openai_api_key),
            'masked_key' => $setting->maskedKey(),
            'credential_status' => $setting->credential_status->value,
            'credential_status_label' => $this->credentialStatusLabel($setting, $ownKeyAllowed),
            'last_validated' => $setting->validated_at?->translatedFormat('d M Y, H:i'),
            'last_validated_label' => $setting->validated_at
                ? __('settings.ai.status.last_validated', ['date' => $setting->validated_at->translatedFormat('d M Y, H:i')])
                : __('settings.ai.status.never_validated'),
            'plan_url' => PlanSettings::getUrl(),
            'usage' => [
                ...$this->usageMetricViewData($aiMetric),
                'platform_used' => Number::format($usage->platformAiAnalyses),
                'own_used' => Number::format($usage->ownAiAnalyses),
                'remaining_label' => $aiMetric->isUnlimited
                    ? __('settings.plan.unlimited')
                    : __('settings.ai.usage.remaining', ['count' => Number::format($aiMetric->remaining ?? 0)]),
            ],
            'history' => $usageService->recentAiUsage($company, 8)
                ->map(fn (AiUsageRecord $record): array => [
                    'date' => $record->created_at?->translatedFormat('d M Y, H:i') ?? '—',
                    'operation' => $record->operation,
                    'model' => $record->model,
                    'tokens' => Number::format($record->input_tokens + $record->output_tokens + $record->cached_tokens),
                    'cost' => $record->estimated_cost === null ? '—' : Number::currency((float) $record->estimated_cost, 'USD'),
                    'provider' => $this->providerLabel($record->provider),
                    'status' => __("settings.ai.history.statuses.{$record->status->value}"),
                    'status_color' => match ($record->status->value) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    },
                ])
                ->all(),
        ];
    }

    private function credentialStatusLabel(CompanyAiSetting $setting, bool $ownKeyAllowed): string
    {
        if (filled($setting->openai_api_key) && ! $ownKeyAllowed) {
            return __('settings.ai.status.unavailable');
        }

        return match ($setting->credential_status) {
            AiCredentialStatus::Active => __('settings.ai.status.valid'),
            AiCredentialStatus::Invalid => __('settings.ai.status.invalid'),
            default => __('settings.ai.status.untested'),
        };
    }
}
