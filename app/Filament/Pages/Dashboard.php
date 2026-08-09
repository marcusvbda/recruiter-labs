<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Recruitment\RecruitmentCluster;
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
     *     recruitmentUrl: string
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
            'recruitmentUrl' => RecruitmentCluster::getUrl(),
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
