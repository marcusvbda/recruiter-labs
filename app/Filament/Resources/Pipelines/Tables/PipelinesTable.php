<?php

namespace App\Filament\Resources\Pipelines\Tables;

use App\Filament\Resources\Pipelines\Actions\DuplicatePipelineAction;
use App\Models\Pipeline;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class PipelinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('statuses')->withCount('jobs'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('pipelines.fields.name'))
                    ->description(fn (Pipeline $record): ?string => $record->description)
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_default')
                    ->label(__('pipelines.fields.is_default'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('pipelines.badges.default')
                        : __('pipelines.badges.secondary'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('statuses')
                    ->label(__('pipelines.fields.flow'))
                    ->state(fn (Pipeline $record): Htmlable => self::flow($record))
                    ->html()
                    ->wrap(),
                TextColumn::make('jobs_count')
                    ->label(__('pipelines.fields.jobs_count'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->alignEnd(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DuplicatePipelineAction::make(),
                    self::setDefaultAction(),
                    self::deleteAction(),
                ]),
            ]);
    }

    /**
     * A compact, readable rendering of the workflow: "Applied → Screening → …",
     * each stage in its own color.
     */
    private static function flow(Pipeline $pipeline): Htmlable
    {
        if ($pipeline->statuses->isEmpty()) {
            return new HtmlString(
                '<span class="text-gray-400">'.e(__('pipelines.empty_flow')).'</span>',
            );
        }

        $stages = $pipeline->statuses
            ->map(fn (Status $status): string => sprintf(
                '<span class="whitespace-nowrap"><span class="inline-block size-2 rounded-full align-middle" style="background-color: %s"></span> %s</span>',
                e($status->color),
                e($status->name),
            ))
            ->implode('<span class="text-gray-400 mx-1">&rarr;</span> ');

        return new HtmlString('<span class="flex flex-wrap items-center gap-x-1 gap-y-1">'.$stages.'</span>');
    }

    private static function setDefaultAction(): Action
    {
        return Action::make('setDefault')
            ->label(__('pipelines.actions.set_default'))
            ->icon(Heroicon::OutlinedStar)
            ->visible(fn (Pipeline $record): bool => ! $record->is_default)
            ->action(function (Pipeline $record): void {
                $record->update(['is_default' => true]);

                Notification::make()
                    ->title(__('pipelines.notifications.default_updated'))
                    ->success()
                    ->send();
            });
    }

    private static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (DeleteAction $action, Pipeline $record): void {
                $jobCount = $record->jobs()->count();

                if ($jobCount === 0) {
                    return;
                }

                Notification::make()
                    ->title(__('pipelines.notifications.pipeline_in_use_title'))
                    ->body(__('pipelines.errors.pipeline_in_use', ['count' => $jobCount]))
                    ->danger()
                    ->send();

                $action->halt();
            });
    }
}
