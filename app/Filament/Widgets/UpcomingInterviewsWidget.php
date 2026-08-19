<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Calendar;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Company;
use App\Models\Interview;
use App\Models\User;
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
 * The commitments the signed-in recruiter has to keep, soonest first. Another
 * recruiter's agenda is never shown here as "yours" — the calendar page is
 * where company-wide visibility lives.
 */
class UpcomingInterviewsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->interviewsQuery())
            ->heading(__('dashboard.upcoming_interviews.heading'))
            ->description(__('dashboard.upcoming_interviews.description'))
            ->emptyStateHeading(__('dashboard.upcoming_interviews.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->paginated(false)
            ->headerActions([
                Action::make('openCalendar')
                    ->label(__('agenda.navigation_label'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('gray')
                    ->url(fn (): string => Calendar::getUrl()),
            ])
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label(__('dashboard.upcoming_interviews.when'))
                    ->formatStateUsing(fn (Interview $record): string => $record->scheduled_at
                        ->setTimezone($record->timezone)
                        ->translatedFormat('D, M j · H:i'))
                    ->description(fn (Interview $record): string => $record->timezone)
                    ->weight('medium'),
                TextColumn::make('application.candidate.name')
                    ->label(__('candidates.label'))
                    ->description(fn (Interview $record): ?string => $record->application?->job?->name),
                TextColumn::make('application.status.name')
                    ->label(__('applications.admin.fields.pipeline_status'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('rsvp_status')
                    ->label(__('dashboard.upcoming_interviews.rsvp'))
                    ->badge()
                    ->formatStateUsing(fn (Interview $record): string => __("applications.admin.interviews.rsvp.{$record->rsvp_status->value}"))
                    ->color(fn (Interview $record): string => match ($record->rsvp_status->value) {
                        'accepted' => 'success',
                        'declined' => 'danger',
                        'tentative' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('openApplication')
                    ->label(__('applications.admin.actions.view_application'))
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->iconButton()
                    ->url(fn (Interview $record): ?string => $record->application === null
                        ? null
                        : ApplicationResource::getUrl('view', ['record' => $record->application])),
            ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.upcoming_interviews.heading');
    }

    /** @return Builder<Interview> */
    private function interviewsQuery(): Builder
    {
        $company = Filament::getTenant();
        $recruiter = Filament::auth()->user();

        if (! $company instanceof Company || ! $recruiter instanceof User) {
            return Interview::query()->whereRaw('1 = 0');
        }

        return app(RecruitmentProgressService::class)
            ->upcomingInterviewsQuery($company, $recruiter)
            ->with(['application.candidate', 'application.job', 'application.status'])
            ->limit(5);
    }
}
