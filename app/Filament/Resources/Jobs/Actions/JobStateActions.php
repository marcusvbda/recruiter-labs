<?php

namespace App\Filament\Resources\Jobs\Actions;

use App\Actions\DuplicateJob;
use App\Exceptions\PlanLimitExceededException;
use App\Filament\Resources\Jobs\JobResource;
use App\Filament\Resources\Jobs\Pages\Concerns\GuardsJobPlanLimit;
use App\Models\Job;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Draft/published state and duplication, shared by the job list, the edit page
 * and the job dashboard so the same controls appear wherever a job is managed.
 */
class JobStateActions
{
    public static function publish(): Action
    {
        return Action::make('publish')
            ->label(__('jobs.state.publish'))
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('jobs.state.publish_description'))
            ->visible(fn (Job $record): bool => ! $record->published)
            ->action(function (Job $record): void {
                $record->update(['published' => true]);

                Notification::make()
                    ->title(__('jobs.state.published_notification'))
                    ->success()
                    ->send();
            });
    }

    public static function unpublish(): Action
    {
        return Action::make('unpublish')
            ->label(__('jobs.state.unpublish'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('jobs.state.unpublish_description'))
            ->visible(fn (Job $record): bool => $record->published)
            ->action(function (Job $record): void {
                $record->update(['published' => false]);

                Notification::make()
                    ->title(__('jobs.state.unpublished_notification'))
                    ->success()
                    ->send();
            });
    }

    public static function duplicate(): Action
    {
        return Action::make('duplicate')
            ->label(__('jobs.duplicate.action'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->requiresConfirmation()
            ->modalDescription(__('jobs.duplicate.description'))
            ->action(function (Job $record): void {
                try {
                    $copy = app(DuplicateJob::class)->handle($record);
                } catch (PlanLimitExceededException) {
                    GuardsJobPlanLimit::notifyJobLimitReached();

                    return;
                }

                Notification::make()
                    ->title(__('jobs.duplicate.notification', ['name' => $copy->name]))
                    ->success()
                    ->send();

                redirect(JobResource::getUrl('edit', [
                    'record' => $copy,
                ], tenant: Filament::getTenant()));
            });
    }
}
