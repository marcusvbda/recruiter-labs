<?php

namespace App\Filament\Resources\Jobs\Tables;

use App\Filament\Actions\CopyTrackedUrlAction;
use App\Filament\Resources\Jobs\Actions\JobStateActions;
use App\Filament\Resources\Jobs\JobResource;
use App\Models\Job;
use App\Services\RecruitmentProgressService;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('pipeline')
                ->withCount(RecruitmentProgressService::ProgressCounts))
            // The primary click means "work on this hiring process", not "open a
            // menu". The rule itself lives on the resource, so the overview
            // enters a job exactly the same way.
            ->recordUrl(fn (Job $record): string => JobResource::getWorkspaceUrl($record))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('jobs.fields.name'))
                    ->weight('medium')
                    ->description(fn (Job $record): ?string => $record->pipeline?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('published')
                    ->label(__('jobs.fields.state'))
                    ->badge()
                    ->state(fn (Job $record): string => self::stateLabel($record))
                    ->color(fn (Job $record): string => self::stateColor($record)),
                JobProgressColumn::make('progress'),
            ])
            ->filters([
                TernaryFilter::make('published')
                    ->label(__('jobs.fields.state'))
                    ->trueLabel(__('jobs.state.published'))
                    ->falseLabel(__('jobs.state.draft'))
                    ->placeholder(__('jobs.state.all')),
                SelectFilter::make('progress')
                    ->label(__('jobs.progress.filter_label'))
                    ->options([
                        'target_reached' => __('jobs.progress.filters.target_reached'),
                        'waiting' => __('jobs.progress.filters.waiting'),
                        'hired' => __('jobs.progress.filters.hired'),
                        'finalists' => __('jobs.progress.filters.finalists'),
                        'interviewing' => __('jobs.progress.filters.interviewing'),
                        'stalled' => __('jobs.progress.filters.stalled'),
                        'no_applications' => __('jobs.progress.filters.no_applications'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::applyProgressFilter($query, $data['value'] ?? null)),
                SelectFilter::make('pipeline')
                    ->label(__('jobs.fields.pipeline'))
                    ->relationship('pipeline', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    CopyTrackedUrlAction::make('job.show'),
                    JobStateActions::publish(),
                    JobStateActions::unpublish(),
                    JobStateActions::duplicate(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    private static function applyProgressFilter(Builder $query, ?string $value): Builder
    {
        return match ($value) {
            // Hires counted against the job's own target, in SQL so the filter
            // and the column cannot disagree.
            'target_reached' => $query->whereRaw(
                '(select count(*) from applications'
                .' inner join statuses on statuses.id = applications.status_id'
                .' where applications.job_id = job_postings.id and statuses.is_hired = ?) >= job_postings.hiring_target',
                [true],
            ),
            'waiting' => $query->has('overdueApplications'),
            'hired' => $query->has('hiredApplications'),
            'finalists' => $query->has('finalStageApplications'),
            'interviewing' => $query->has('interviewingApplications'),
            // Candidates arrived, but nobody moved forward.
            'stalled' => $query
                ->has('applications')
                ->doesntHave('interviewingApplications')
                ->doesntHave('finalStageApplications')
                ->doesntHave('hiredApplications'),
            'no_applications' => $query->doesntHave('applications'),
            default => $query,
        };
    }

    private static function stateLabel(Job $record): string
    {
        return match (true) {
            ! $record->published => __('jobs.state.draft'),
            $record->applications_paused => __('jobs.state.paused'),
            default => __('jobs.state.published'),
        };
    }

    private static function stateColor(Job $record): string
    {
        return match (true) {
            ! $record->published => 'gray',
            $record->applications_paused => 'warning',
            default => 'success',
        };
    }
}
