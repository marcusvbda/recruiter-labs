<?php

namespace App\Filament\Pages;

use App\Actions\ChangeCompanyPlan;
use App\Actions\RemoveCompanyAiCredentials;
use App\Actions\TestCompanyAiCredentials;
use App\Actions\UpdateCompanyAiSettings;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Data\UsageMetricData;
use App\Enums\AiCredentialStatus;
use App\Enums\AiProvider;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\UsageWarningState;
use App\Models\AiUsageRecord;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Plan;
use App\Models\User;
use App\Services\AiCredentialsResolver;
use App\Services\CompanyUsageService;
use App\Services\PlanComparisonService;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Url;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed> */
    public array $planSettings = [];

    /** @var array<string, mixed> */
    public array $aiSettings = [];

    #[Url(as: 'section', except: 'general')]
    public string $activeSettingsTab = 'general';

    public function getTitle(): string
    {
        return __('settings.title');
    }

    public function getSubheading(): string
    {
        return __('settings.subtitle');
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.navigation_label');
    }

    public function mount(
        CompanyUsageService $usageService,
        PlanComparisonService $comparisonService,
        AiCredentialsResolver $credentialsResolver,
    ): void {
        if (! in_array($this->activeSettingsTab, ['general', 'authentication', 'plan', 'ai'], strict: true)) {
            $this->activeSettingsTab = 'general';
        }

        $user = $this->getRecord();

        $this->form->fill($user->only(['name', 'email', 'locale']));
        $this->refreshSettingsState($usageService, $comparisonService, $credentialsResolver);
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
                                ->submit('save')
                                ->visible(fn (): bool => in_array($this->activeSettingsTab, ['general', 'authentication'], strict: true)),
                        ]),
                    ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        $user = $this->getRecord();

        return $schema
            ->components([
                Tabs::make('settings')
                    ->id('settings-tabs')
                    ->livewireProperty('activeSettingsTab')
                    ->contained(false)
                    ->tabs([
                        'general' => Tab::make(__('settings.tabs.general'))
                            ->id('general')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Section::make()->columnSpanFull()
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
                            ]),
                        'authentication' => Tab::make(__('settings.tabs.auth'))
                            ->id('authentication')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make()->columnSpanFull()
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
                            ]),
                        'plan' => Tab::make(__('settings.tabs.plan'))
                            ->id('plan')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                ViewComponent::make('filament.pages.settings.plan'),
                            ]),
                        'ai' => Tab::make(__('settings.tabs.ai'))
                            ->id('ai')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                ViewComponent::make('filament.pages.settings.ai'),
                            ]),
                    ]),
            ])
            ->record($user)
            ->statePath('data');
    }

    public function changePlanAction(): Action
    {
        return Action::make('changePlan')
            ->action(function (
                array $arguments,
                ChangeCompanyPlan $changeCompanyPlan,
                CompanyUsageService $usageService,
                PlanComparisonService $comparisonService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $plan = $this->resolvePlan($arguments);

                $changeCompanyPlan->run($this->getCompany(), $plan, $this->getRecord());
                $this->refreshSettingsState($usageService, $comparisonService, $credentialsResolver);
                $this->dispatch('refresh-topbar');

                Notification::make()
                    ->title(__('settings.notifications.plan_changed', ['plan' => $plan->name]))
                    ->success()
                    ->send();
            });
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
                PlanComparisonService $comparisonService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $company = $this->getCompany();
                $user = $this->getRecord();

                $updateSettings->run($company, $user, AiProvider::Own, $data['model'], $data['api_key']);
                $result = $testCredentials->run($company, $user);
                $this->refreshSettingsState($usageService, $comparisonService, $credentialsResolver);
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
                PlanComparisonService $comparisonService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $result = $testCredentials->run($this->getCompany(), $this->getRecord());
                $this->refreshSettingsState($usageService, $comparisonService, $credentialsResolver);
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
                PlanComparisonService $comparisonService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $removeCredentials->run($this->getCompany(), $this->getRecord());
                $this->refreshSettingsState($usageService, $comparisonService, $credentialsResolver);
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
                PlanComparisonService $comparisonService,
                AiCredentialsResolver $credentialsResolver,
            ): void {
                $updateSettings->run(
                    $this->getCompany(),
                    $this->getRecord(),
                    AiProvider::Platform,
                    $this->aiSettings['model'],
                );
                $this->refreshSettingsState($usageService, $comparisonService, $credentialsResolver);
                $this->dispatch('refresh-topbar');

                Notification::make()
                    ->title(__('settings.notifications.ai_platform_enabled'))
                    ->success()
                    ->send();
            });
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

    public function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function refreshSettingsState(
        CompanyUsageService $usageService,
        PlanComparisonService $comparisonService,
        AiCredentialsResolver $credentialsResolver,
    ): void {
        $company = $this->getCompany();
        $company->refresh()->load(['plan', 'aiSetting']);
        $usage = $usageService->summary($company);

        $this->planSettings = [
            'current_plan' => [
                'id' => $company->plan->getKey(),
                'name' => $company->plan->name,
                'slug' => $company->plan->slug,
            ],
            'plans' => Plan::query()
                ->orderBy('sort_order')
                ->get()
                ->map(function (Plan $plan) use ($company, $comparisonService): array {
                    $comparison = $comparisonService->compare($company, $plan);

                    return [
                        'id' => $plan->getKey(),
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'description' => __("settings.plans.{$plan->slug}"),
                        'icon' => $this->planIcon($plan),
                        'is_current' => $comparison->direction === 'current',
                        'direction' => $comparison->direction,
                        'limits' => array_map(
                            fn (Limit $limit): array => [
                                'key' => $limit->value,
                                'label' => __("settings.limits.{$limit->value}"),
                                'icon' => $this->limitIcon($limit),
                                'value' => $this->formatLimit($plan->getLimit($limit)),
                            ],
                            Limit::cases(),
                        ),
                        'features' => collect($plan->features ?? [])
                            ->map(fn (string $feature): string => Feature::from($feature)->label())
                            ->values()
                            ->all(),
                    ];
                })
                ->all(),
            'usage' => collect($usage->metrics)
                ->map(fn (UsageMetricData $metric): array => $this->usageMetricViewData($metric))
                ->values()
                ->all(),
        ];

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
            'plan_url' => static::getUrl(['section' => 'plan']),
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

    /** @param array<string, mixed> $arguments */
    private function resolvePlan(array $arguments): Plan
    {
        $planId = $arguments['plan'] ?? null;

        abort_unless(is_numeric($planId), 404);

        return Plan::query()->findOrFail((int) $planId);
    }

    /** @return array<string, mixed> */
    private function usageMetricViewData(UsageMetricData $metric): array
    {
        $statusLabel = match (true) {
            $metric->isOverLimit => __('settings.plan.over_limit'),
            $metric->isReached => __('settings.plan.limit_reached'),
            $metric->warningState !== UsageWarningState::Normal => __('settings.plan.approaching_limit'),
            default => __('settings.plan.within_limit'),
        };

        return [
            'key' => $metric->limit->value,
            'label' => __("settings.limits.{$metric->limit->value}"),
            'icon' => $this->limitIcon($metric->limit),
            'used' => Number::format($metric->used),
            'limit' => $this->formatLimit($metric->limitValue),
            'remaining' => $metric->remaining,
            'percentage' => $metric->percentage,
            'percentage_label' => $metric->isUnlimited
                ? __('settings.plan.unlimited')
                : Number::percentage($metric->percentage),
            'bar_percentage' => min(100, max(0, $metric->percentage)),
            'warning_state' => $metric->isOverLimit ? 'exceeded' : $metric->warningState->value,
            'status_label' => $statusLabel,
            'badge_color' => match (true) {
                $metric->isReached, $metric->isOverLimit => 'danger',
                $metric->warningState !== UsageWarningState::Normal => 'warning',
                default => 'success',
            },
            'cycle_label' => $metric->cycleStart && $metric->cycleEnd
                ? __('settings.plan.cycle', [
                    'start' => $metric->cycleStart->translatedFormat('d M'),
                    'end' => $metric->cycleEnd->translatedFormat('d M Y'),
                ])
                : __('settings.plan.no_cycle'),
        ];
    }

    private function formatLimit(?int $limit): string
    {
        if ($limit === null) {
            return __('settings.plan.unlimited');
        }

        $formattedLimit = Number::format($limit);

        return is_string($formattedLimit) ? $formattedLimit : (string) $limit;
    }

    private function providerLabel(AiProvider $provider): string
    {
        return $provider === AiProvider::Own
            ? __('settings.ai.own_key.name')
            : __('settings.ai.platform.name');
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

    private function limitIcon(Limit $limit): string
    {
        return match ($limit) {
            Limit::Users => 'heroicon-o-user-group',
            Limit::Jobs => 'heroicon-o-briefcase',
            Limit::Applications => 'heroicon-o-document-text',
            Limit::AiAnalyses => 'heroicon-o-sparkles',
        };
    }

    private function planIcon(Plan $plan): string
    {
        return match ($plan->slug) {
            'business' => 'heroicon-o-building-office-2',
            'pro' => 'heroicon-o-rocket-launch',
            default => 'heroicon-o-paper-airplane',
        };
    }
}
