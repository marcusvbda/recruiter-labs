<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Tables\JobProgressColumn;
use App\Models\Company;
use App\Models\Job;
use App\Services\RecruitmentProgressService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Active hiring processes and how far each one has actually moved, so a
 * recruiter can spot the stalled ones without opening every job. A paused job
 * is still an active process: its candidates are still being interviewed.
 */
class ActiveJobsProgressWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->jobsQuery())
            ->heading(__('dashboard.active_jobs.heading'))
            ->description(__('dashboard.active_jobs.description'))
            ->emptyStateHeading(__('dashboard.active_jobs.empty'))
            ->emptyStateIcon(Heroicon::OutlinedBriefcase)
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->headerActions([
                Action::make('openJobs')
                    ->label(__('jobs.navigation_label'))
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->color('gray')
                    ->url(fn (): string => JobResource::getUrl()),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(__('jobs.fields.name'))
                    ->weight('medium')
                    ->description(fn (Job $record): ?string => $record->pipeline?->name)
                    ->url(fn (Job $record): string => JobResource::getUrl('view', ['record' => $record])),
                JobProgressColumn::make('progress'),
            ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.active_jobs.heading');
    }

    /** @return Builder<Job> */
    private function jobsQuery(): Builder
    {
        $company = Filament::getTenant();

        if (! $company instanceof Company) {
            return Job::query()->whereRaw('1 = 0');
        }

        return Job::query()
            ->whereBelongsTo($company)
            ->currentlyActive()
            ->with('pipeline')
            ->withCount(RecruitmentProgressService::ProgressCounts)
            ->limit(5);
    }
}
