<?php

namespace App\Filament\Resources\EmailTemplates\Tables;

use App\Filament\Resources\EmailTemplates\EmailTemplateDeletionGuard;
use App\Models\Company;
use App\Models\EmailTemplate;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('email_templates.fields.name'))
                    ->description(fn ($record): ?string => $record->subject)
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_available')
                    ->label(__('email_templates.fields.is_available'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('email_templates.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->before(fn (DeleteAction $action, EmailTemplate $record) => EmailTemplateDeletionGuard::halt($action, $record)),
                ]),
            ])
            ->emptyStateHeading(__('email_templates.empty_state.heading'))
            ->emptyStateDescription(__('email_templates.empty_state.description'))
            // Realtime refresh instead of polling — see App\Models\EmailTemplate::booted().
            ->socket(
                channel: 'email_templates_'.self::tenantSlug(),
                event: 'EmailTemplateUpdated',
            );
    }

    private static function tenantSlug(): string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Company ? $tenant->slug : '';
    }
}
