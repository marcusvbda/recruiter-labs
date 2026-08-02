<?php

namespace App\Providers\Filament;

use App\Data\CompanyTopbarSummaryData;
use App\Enums\AiProvider;
use App\Enums\Limit;
use App\Enums\UsageWarningState;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings;
use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Http\Middleware\ApplyTenantScopes;
use App\Http\Middleware\SetLocale;
use App\Models\Company;
use App\Services\CompanyTopbarSummary;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->favicon(asset('assets/image/favicon.png').'?v=2')
            ->brandLogo(asset('assets/image/logo.png'))
            ->brandLogoHeight('3rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->maxContentWidth(Width::Full)
            ->login()
            ->registration()
            ->tenant(Company::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterCompany::class)
            ->tenantProfile(EditCompanyProfile::class)
            ->tenantMiddleware([
                ApplyTenantScopes::class,
            ], isPersistent: true)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->userMenuItems([
                [
                    Action::make('settings')
                        ->label(fn (): string => __('settings.navigation_label'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->visible(fn (): bool => Filament::getTenant() !== null)
                        ->url(fn (): string => Filament::getTenant() ? Settings::getUrl() : '#'),
                ],
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => $this->renderCompanyTopbarSummary(),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.language-switcher', [
                    'locales' => [
                        'en' => ['label' => 'English', 'flag' => '🇺🇸'],
                        'pt_BR' => ['label' => 'Português (Brasil)', 'flag' => '🇧🇷'],
                        'es' => ['label' => 'Español', 'flag' => '🇪🇸'],
                    ],
                    'current' => auth()->user()?->locale ?? config('app.locale'),
                ])->render(),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function renderCompanyTopbarSummary(): string
    {
        $company = Filament::getTenant();

        if (! $company instanceof Company || ! Filament::auth()->check()) {
            return '';
        }

        $summary = app(CompanyTopbarSummary::class)->for($company);

        return view('filament.topbar-company-usage', [
            'summary' => $this->topbarViewData($summary),
        ])->render();
    }

    /** @return array<string, int|string> */
    private function topbarViewData(CompanyTopbarSummaryData $summary): array
    {
        $usage = $summary->aiUsage;
        $limit = $usage->isUnlimited
            ? __('settings.topbar.unlimited')
            : Number::format($usage->limitValue ?? 0);
        $remaining = $usage->isUnlimited
            ? __('settings.topbar.unlimited')
            : Number::format($usage->remaining ?? 0);
        $percentage = $usage->isUnlimited
            ? __('settings.topbar.unlimited')
            : Number::percentage($usage->percentage);
        $provider = $summary->provider === AiProvider::Own
            ? __('settings.ai.own_key.name')
            : __('settings.ai.platform.name');
        $warningState = $usage->warningState->value;
        $statusLabel = match ($usage->warningState) {
            UsageWarningState::Attention => __('settings.topbar.warning'),
            UsageWarningState::Critical => __('settings.topbar.critical'),
            UsageWarningState::Reached => __('settings.topbar.reached'),
            default => __('settings.topbar.normal'),
        };

        $planLines = [
            __('settings.topbar.current_plan', ['plan' => $summary->planName]),
            '',
            ...collect(Limit::cases())
                ->map(fn (Limit $planLimit): string => __("settings.limits.{$planLimit->value}").': '.(
                    $summary->planLimits[$planLimit->value] === null
                        ? __('settings.topbar.unlimited')
                        : Number::format($summary->planLimits[$planLimit->value])
                ))
                ->all(),
            '',
            __('settings.topbar.manage_plan'),
        ];
        $aiLines = [
            __('settings.topbar.ai_analyses'),
            '',
            __('settings.topbar.used', ['count' => Number::format($usage->used)]),
            __('settings.topbar.remaining', ['count' => $remaining]),
            __('settings.topbar.consumed', ['percentage' => $percentage]),
            '',
            __('settings.topbar.cycle', [
                'start' => $usage->cycleStart?->translatedFormat('d M Y') ?? '—',
                'end' => $usage->cycleEnd?->translatedFormat('d M Y') ?? '—',
            ]),
            __('settings.topbar.provider', ['provider' => $provider]),
            $statusLabel,
            '',
            __('settings.topbar.manage_ai'),
        ];

        return [
            'plan_name' => $summary->planName,
            'plan_initial' => Str::substr($summary->planName, 0, 1),
            'plan_url' => Settings::getUrl(['section' => 'plan']),
            'plan_tooltip' => implode("\n", $planLines),
            'ai_url' => Settings::getUrl(['section' => 'ai']),
            'ai_tooltip' => implode("\n", $aiLines),
            'used' => Number::format($usage->used),
            'limit' => $limit,
            'percentage_label' => $percentage,
            'bar_percentage' => min(100, max(0, $usage->percentage)),
            'warning_state' => $warningState,
            'status_label' => $statusLabel,
            'status_icon' => match ($usage->warningState) {
                UsageWarningState::Reached => 'heroicon-m-no-symbol',
                default => 'heroicon-m-exclamation-triangle',
            },
        ];
    }
}
