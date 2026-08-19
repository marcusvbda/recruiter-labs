<?php

namespace App\Filament\Resources\Pipelines\RelationManagers;

use App\Filament\Resources\Pipelines\Schemas\StatusForm;
use App\Models\Pipeline;
use App\Models\Status;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StatusesRelationManager extends RelationManager
{
    protected static string $relationship = 'statuses';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('statuses.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return StatusForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('statuses.plural_label'))
            ->description(__('statuses.relation_description'))
            ->reorderable('order')
            ->defaultSort('order')
            ->paginated(false)
            ->columns([
                TextColumn::make('order')
                    ->label('#')
                    ->rowIndex(),
                ColorColumn::make('color')
                    ->label(__('statuses.fields.color')),
                TextColumn::make('name')
                    ->label(__('statuses.fields.name'))
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('sends_email')
                    ->label(__('statuses.fields.sends_email'))
                    ->badge()
                    ->state(fn (Status $record): string => $record->sendsOnEnterEmail()
                        ? __('statuses.badges.email_on')
                        : __('statuses.badges.email_off'))
                    ->color(fn (Status $record): string => $record->sendsOnEnterEmail() ? 'success' : 'gray')
                    ->description(fn (Status $record): ?string => $record->sendsOnEnterEmail()
                        ? $record->email_subject
                        : null),
                TextColumn::make('stage_role')
                    ->label(__('statuses.fields.stage_role'))
                    ->badge()
                    ->state(fn (Status $record): string => match (true) {
                        $record->is_hired => __('statuses.badges.hired'),
                        $record->is_final_stage => __('statuses.badges.final_stage'),
                        default => __('statuses.badges.intermediate'),
                    })
                    ->color(fn (Status $record): string => match (true) {
                        $record->is_hired => 'success',
                        $record->is_final_stage => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('applications_count')
                    ->label(__('statuses.fields.applications_count'))
                    ->counts('applications')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->alignEnd(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('statuses.actions.create'))
                    // The email editor and its variable chips need room to breathe.
                    ->modalWidth(Width::SevenExtraLarge)
                    ->mutateDataUsing(function (array $data): array {
                        /** @var Pipeline $pipeline */
                        $pipeline = $this->getOwnerRecord();

                        $data['company_id'] = $pipeline->company_id;
                        // New stages are appended; position is managed by drag
                        // and drop on this table.
                        $data['order'] = ((int) $pipeline->statuses()->max('order')) + 1;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::SevenExtraLarge),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Status $record): void {
                        $applicationCount = $record->applications()->count();

                        if ($applicationCount === 0) {
                            return;
                        }

                        Notification::make()
                            ->title(__('statuses.notifications.has_applications_title'))
                            ->body(__('pipelines.errors.status_in_use', ['count' => $applicationCount]))
                            ->danger()
                            ->send();

                        $action->halt();
                    }),
            ]);
    }
}
