<?php

namespace App\Providers\Filament;

use App\Data\CompanyTopbarSummaryData;
use App\Enums\UsageWarningState;
use App\Filament\Auth\Register;
use App\Filament\Clusters\Settings\Pages\AccountSettings;
use App\Filament\Clusters\Settings\Pages\AiSettings;
use App\Filament\Pages\Dashboard;
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
            ->darkMode(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->maxContentWidth(Width::Full)
            ->globalSearchResourceOptIn()
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            ->tenant(Company::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterCompany::class)
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
                        ->label(fn (): string => __('settings.account.navigation_label'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->visible(fn (): bool => Filament::getTenant() !== null)
                        ->url(fn (): string => Filament::getTenant() ? AccountSettings::getUrl() : '#'),
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

    /**
     * Plan and AI allowance are workspace administration, and they live in
     * Settings. The global topbar is reserved for the exception: an allowance
     * close enough to running out that it is about to stop candidate evaluations
     * from running, which *is* a recruitment problem.
     *
     * In the normal state this renders nothing at all.
     */
    private function renderCompanyTopbarSummary(): string
    {
        $company = Filament::getTenant();

        if (! $company instanceof Company || ! Filament::auth()->check()) {
            return '';
        }

        $summary = app(CompanyTopbarSummary::class)->for($company);

        if ($summary->aiUsage->warningState === UsageWarningState::Normal) {
            return '';
        }

        return view('filament.topbar-company-usage', [
            'summary' => $this->topbarViewData($summary),
        ])->render();
    }

    /**
     * Only what the warning chip shows. Plan limits and cycle details are not
     * here any more: reading them is a Settings visit, not something to carry on
     * every page.
     *
     * @return array<string, int|string>
     */
    private function topbarViewData(CompanyTopbarSummaryData $summary): array
    {
        $usage = $summary->aiUsage;
        $used = (string) Number::format($usage->used);
        $limit = $usage->isUnlimited
            ? (string) __('settings.topbar.unlimited')
            : (string) Number::format($usage->limitValue ?? 0);
        $remaining = $usage->isUnlimited
            ? (string) __('settings.topbar.unlimited')
            : (string) Number::format($usage->remaining ?? 0);
        $percentage = $usage->isUnlimited
            ? (string) __('settings.topbar.unlimited')
            : (string) Number::percentage($usage->percentage);
        $statusLabel = (string) match ($usage->warningState) {
            UsageWarningState::Attention => __('settings.topbar.warning'),
            UsageWarningState::Critical => __('settings.topbar.critical'),
            UsageWarningState::Reached => __('settings.topbar.reached'),
            default => __('settings.topbar.normal'),
        };

        return [
            'ai_url' => AiSettings::getUrl(),
            'ai_tooltip' => implode("\n", [
                (string) __('settings.topbar.ai_analyses'),
                '',
                (string) __('settings.topbar.used', ['count' => $used]),
                (string) __('settings.topbar.remaining', ['count' => $remaining]),
                (string) __('settings.topbar.consumed', ['percentage' => $percentage]),
                '',
                $statusLabel,
                '',
                (string) __('settings.topbar.manage_ai'),
            ]),
            'used' => $used,
            'limit' => $limit,
            'percentage_label' => $percentage,
            'bar_percentage' => (int) min(100, max(0, $usage->percentage)),
            'warning_state' => $usage->warningState->value,
            'status_label' => $statusLabel,
            'status_icon' => match ($usage->warningState) {
                UsageWarningState::Reached => 'heroicon-m-no-symbol',
                default => 'heroicon-m-exclamation-triangle',
            },
        ];
    }
}
