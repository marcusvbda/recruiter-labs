<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Integrations\Pages\CalendarSettings;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Pipelines\PipelineResource;
use App\Filament\Resources\Referrals\ReferralResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getWidgets(): array
    {
        return [];
    }

    /**
     * @return array{
     *     greeting: string,
     *     userName: string,
     *     userFirstName: string,
     *     userEmail: string,
     *     userInitials: string,
     *     companyName: string,
     *     quickAccess: list<array{label: string, description: string, icon: string, url: string}>
     * }
     */
    protected function getViewData(): array
    {
        $authenticatedUser = Filament::auth()->user();
        $selectedCompany = Filament::getTenant();
        $userName = $authenticatedUser instanceof User
            ? $authenticatedUser->name
            : __('dashboard.welcome.fallback_user');
        $userInitials = Str::of($userName)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $namePart): string => Str::upper(Str::substr($namePart, 0, 1)))
            ->implode('');

        return [
            'greeting' => $this->getGreeting(),
            'userName' => $userName,
            'userFirstName' => Str::before($userName, ' '),
            'userEmail' => $authenticatedUser instanceof User
                ? $authenticatedUser->email
                : __('dashboard.welcome.fallback_email'),
            'userInitials' => $userInitials,
            'companyName' => $selectedCompany instanceof Company
                ? $selectedCompany->name
                : __('dashboard.welcome.no_company'),
            'quickAccess' => $this->quickAccessItems(),
        ];
    }

    /**
     * Entry points surfaced on the dashboard, in the order a recruiter is most
     * likely to need them during a working day.
     *
     * @return list<array{label: string, description: string, icon: string, url: string}>
     */
    private function quickAccessItems(): array
    {
        return [
            [
                'label' => __('dashboard.welcome.quick_access_items.jobs.title'),
                'description' => __('dashboard.welcome.quick_access_items.jobs.description'),
                'icon' => 'heroicon-o-briefcase',
                'url' => JobResource::getUrl(),
            ],
            [
                'label' => __('dashboard.welcome.quick_access_items.calendar.title'),
                'description' => __('dashboard.welcome.quick_access_items.calendar.description'),
                'icon' => 'heroicon-o-calendar-days',
                'url' => Calendar::getUrl(),
            ],
            [
                'label' => __('dashboard.welcome.quick_access_items.candidates.title'),
                'description' => __('dashboard.welcome.quick_access_items.candidates.description'),
                'icon' => 'heroicon-o-users',
                'url' => CandidateResource::getUrl(),
            ],
            [
                'label' => __('dashboard.welcome.quick_access_items.pipelines.title'),
                'description' => __('dashboard.welcome.quick_access_items.pipelines.description'),
                'icon' => 'heroicon-o-arrows-right-left',
                'url' => PipelineResource::getUrl(),
            ],
            [
                'label' => __('dashboard.welcome.quick_access_items.referrals.title'),
                'description' => __('dashboard.welcome.quick_access_items.referrals.description'),
                'icon' => 'heroicon-o-user-plus',
                'url' => ReferralResource::getUrl(),
            ],
            [
                'label' => __('dashboard.welcome.quick_access_items.integrations.title'),
                'description' => __('dashboard.welcome.quick_access_items.integrations.description'),
                'icon' => 'heroicon-o-puzzle-piece',
                'url' => CalendarSettings::getUrl(),
            ],
        ];
    }

    private function getGreeting(): string
    {
        return match (true) {
            now()->hour < 12 => __('dashboard.welcome.good_morning'),
            now()->hour < 18 => __('dashboard.welcome.good_afternoon'),
            default => __('dashboard.welcome.good_evening'),
        };
    }
}
