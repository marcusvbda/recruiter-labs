<?php

namespace App\Filament\Clusters\Settings\Widgets;

use App\Actions\RemoveWorkspaceMember;
use App\Actions\SetWorkspaceMemberAccess;
use App\Enums\CompanyRole;
use App\Exceptions\WorkspaceMemberNotFound;
use App\Exceptions\WorkspaceOwnerAccessCannotBeChanged;
use App\Exceptions\WorkspaceOwnerCannotBeRemoved;
use App\Models\Company;
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
 * Every active member of the current workspace. The query is built from the
 * tenant's own membership relation, never from `User::query()`, so a user who
 * belongs only to another workspace can never appear here.
 */
class WorkspaceMembersTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    #[On('workspace-team-changed')]
    public function refreshTable(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('team.members.heading'))
            ->description(__('team.members.description'))
            ->query(fn (): Builder => $this->getCompany()->activeMembers()->getQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('team.fields.name'))
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('team.fields.email'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('team.fields.role'))
                    ->badge()
                    ->state(fn (User $record): string => $this->roleFor($record)->label())
                    ->color(fn (User $record): string => $this->roleFor($record) === CompanyRole::Owner ? 'primary' : 'gray'),
                TextColumn::make('access')
                    ->label(__('team.fields.access'))
                    ->badge()
                    ->state(fn (User $record): string => $this->accessLabel($record))
                    ->color(fn (User $record): string => $this->hasWorkspaceAccess($record) ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('disableAccess')
                    ->label(__('team.actions.disable_access'))
                    ->color('gray')
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn (User $record): bool => $this->canManageTeam() && $this->roleFor($record) !== CompanyRole::Owner && $this->hasWorkspaceAccess($record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => __('team.disable_access.heading', ['name' => $record->name]))
                    ->modalDescription(__('team.disable_access.description'))
                    ->modalSubmitActionLabel(__('team.disable_access.confirm'))
                    ->action(fn (User $record, SetWorkspaceMemberAccess $setWorkspaceMemberAccess) => $this->changeAccess($record, $setWorkspaceMemberAccess, enabled: false)),
                Action::make('enableAccess')
                    ->label(__('team.actions.enable_access'))
                    ->color('gray')
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (User $record): bool => $this->canManageTeam() && $this->roleFor($record) !== CompanyRole::Owner && ! $this->hasWorkspaceAccess($record))
                    ->action(fn (User $record, SetWorkspaceMemberAccess $setWorkspaceMemberAccess) => $this->changeAccess($record, $setWorkspaceMemberAccess, enabled: true)),
                Action::make('remove')
                    ->label(__('team.actions.remove'))
                    ->color('danger')
                    ->icon('heroicon-o-user-minus')
                    ->visible(fn (User $record): bool => $this->canManageTeam() && $this->roleFor($record) !== CompanyRole::Owner)
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => __('team.remove.heading', ['name' => $record->name]))
                    ->modalDescription(__('team.remove.description'))
                    ->modalSubmitActionLabel(__('team.remove.confirm'))
                    ->action(function (User $record, RemoveWorkspaceMember $removeWorkspaceMember): void {
                        $company = $this->getCompany();

                        try {
                            $removeWorkspaceMember->handle($company, $record, $this->getUser());
                        } catch (WorkspaceOwnerCannotBeRemoved $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('team.notifications.member_removed', ['name' => $record->name]))
                            ->success()
                            ->send();

                        $this->dispatch('workspace-team-changed');
                    }),
            ]);
    }

    private function changeAccess(User $record, SetWorkspaceMemberAccess $setWorkspaceMemberAccess, bool $enabled): void
    {
        $company = $this->getCompany();

        try {
            $setWorkspaceMemberAccess->handle($company, $record, $this->getUser(), $enabled);
        } catch (WorkspaceOwnerAccessCannotBeChanged|WorkspaceMemberNotFound $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title(__($enabled ? 'team.notifications.access_enabled' : 'team.notifications.access_disabled', ['name' => $record->name]))
            ->success()
            ->send();

        $this->dispatch('workspace-team-changed');
    }

    private function hasWorkspaceAccess(User $record): bool
    {
        return $this->getCompany()->hasWorkspaceAccess($record);
    }

    private function accessLabel(User $record): string
    {
        if ($this->roleFor($record) === CompanyRole::Owner) {
            return __('team.access.owner');
        }

        return __($this->hasWorkspaceAccess($record) ? 'team.access.enabled' : 'team.access.disabled');
    }

    private function roleFor(User $record): CompanyRole
    {
        return $this->getCompany()->roleFor($record) ?? CompanyRole::Member;
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
