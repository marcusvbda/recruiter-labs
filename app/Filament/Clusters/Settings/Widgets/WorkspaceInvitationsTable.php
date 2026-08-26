<?php

namespace App\Filament\Clusters\Settings\Widgets;

use App\Actions\ResendWorkspaceInvitation;
use App\Actions\RevokeWorkspaceInvitation;
use App\Enums\CompanyInvitationStatus;
use App\Exceptions\WorkspaceInvitationNotResendable;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * Invitations still worth the Owner's attention: pending ones (a working link
 * exists) and expired ones (the Owner may resend to issue a fresh link and
 * expiry date, per the domain's `ResendWorkspaceInvitation` rule). Revoked and
 * accepted invitations are excluded — a revoked one was deliberately
 * withdrawn, and an accepted one is no longer an invitation but membership,
 * which already appears on the members table. Listing either here would blur
 * the line the page has to keep clear: an invitation is not access.
 */
class WorkspaceInvitationsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    #[On('workspace-team-changed')]
    public function refreshTable(): void {}

    public function isVisible(): bool
    {
        return $this->canManageTeam();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('team.invitations.heading'))
            ->description(__('team.invitations.description'))
            ->query(fn (): Builder => CompanyInvitation::query()
                ->whereBelongsTo($this->getCompany())
                ->with('company')
                ->where(fn (Builder $query): Builder => $query->pending()->orWhere->expired()))
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('email')
                    ->label(__('team.fields.invited_email'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('team.fields.status'))
                    ->badge()
                    ->state(fn (CompanyInvitation $record): string => $record->status()->label())
                    ->color(fn (CompanyInvitation $record): string => $record->status() === CompanyInvitationStatus::Pending ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label(__('team.fields.invited_at'))
                    ->dateTime(),
                TextColumn::make('expires_at')
                    ->label(__('team.fields.expires_at'))
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label(__('team.actions.resend'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->action(function (CompanyInvitation $record, ResendWorkspaceInvitation $resendWorkspaceInvitation): void {
                        try {
                            $resendWorkspaceInvitation->handle($record, $this->getUser());
                        } catch (WorkspaceInvitationNotResendable $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->warning()
                                ->send();

                            return;
                        } catch (\Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title(__('team.notifications.invitation_email_failed', ['email' => $record->email]))
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('team.notifications.invitation_resent', ['email' => $record->email]))
                            ->success()
                            ->send();
                    }),
                Action::make('revoke')
                    ->label(__('team.actions.revoke'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (CompanyInvitation $record): string => __('team.revoke.heading', ['email' => $record->email]))
                    ->modalDescription(__('team.revoke.description'))
                    ->modalSubmitActionLabel(__('team.revoke.confirm'))
                    ->action(function (CompanyInvitation $record, RevokeWorkspaceInvitation $revokeWorkspaceInvitation): void {
                        $revokeWorkspaceInvitation->handle($record, $this->getUser());

                        Notification::make()
                            ->title(__('team.notifications.invitation_revoked', ['email' => $record->email]))
                            ->success()
                            ->send();

                        $this->dispatch('workspace-team-changed');
                    }),
            ]);
    }

    private function canManageTeam(): bool
    {
        return Gate::forUser($this->getUser())->allows('manageTeam', $this->getCompany());
    }

    private function getCompany(): Company
    {
        $company = Filament::getTenant();

        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function getUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
