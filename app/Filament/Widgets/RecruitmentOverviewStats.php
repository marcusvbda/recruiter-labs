<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Calendar;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Company;
use App\Models\User;
use App\Services\RecruitmentProgressService;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The volume currently in play, and where it is concentrated. Every stat links
 * to the page where the work happens.
 *
 * These are metrics, not attention: "12 finalists" describes the workspace,
 * while "3 finalists have been waiting past their stage's expectation" is work,
 * and that lives in {@see RecruitmentAttentionWidget} above. Which is also why
 * these sit at the bottom of the overview.
 *
 * Everything counted here belongs to an active hiring process; interviews are
 * the signed-in recruiter's own.
 */
class RecruitmentOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $company = Filament::getTenant();
        $recruiter = Filament::auth()->user();

        if (! $company instanceof Company || ! $recruiter instanceof User) {
            return [];
        }

        $summary = app(RecruitmentProgressService::class)->workspaceSummary($company, $recruiter);

        return [
            Stat::make(__('dashboard.stats.active_jobs'), $summary['active_jobs'])
                ->icon(Heroicon::OutlinedBriefcase)
                ->description(trans_choice('dashboard.stats.draft_jobs', $summary['draft_jobs'], ['count' => $summary['draft_jobs']]))
                ->color($summary['active_jobs'] > 0 ? 'primary' : 'gray')
                ->url(JobResource::getUrl()),
            Stat::make(__('dashboard.stats.active_applications'), $summary['active_applications'])
                ->icon(Heroicon::OutlinedUsers)
                ->description(trans_choice('dashboard.stats.interviewing', $summary['interviewing'], ['count' => $summary['interviewing']]))
                ->color('info')
                ->url(JobResource::getUrl()),
            Stat::make(__('dashboard.stats.finalists'), $summary['finalists'])
                ->icon(Heroicon::OutlinedCheckBadge)
                ->description(trans_choice('dashboard.stats.hired', $summary['hired'], ['count' => $summary['hired']]))
                ->color($summary['finalists'] > 0 ? 'warning' : 'gray')
                ->url(JobResource::getUrl(parameters: ['tableFilters[progress][value]' => 'finalists'])),
            Stat::make(__('dashboard.stats.upcoming_interviews'), $summary['upcoming_interviews'])
                ->icon(Heroicon::OutlinedCalendarDays)
                ->description(__('dashboard.stats.upcoming_interviews_description'))
                ->color($summary['upcoming_interviews'] > 0 ? 'success' : 'gray')
                ->url(Calendar::getUrl()),
        ];
    }
}
