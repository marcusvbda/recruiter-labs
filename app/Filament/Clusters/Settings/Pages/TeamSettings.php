<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Actions\InviteWorkspaceMember;
use App\Exceptions\WorkspaceInvitationAlreadyPending;
use App\Exceptions\WorkspaceMemberAlreadyExists;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Clusters\Settings\Widgets\WorkspaceInvitationsTable;
use App\Filament\Clusters\Settings\Widgets\WorkspaceMembersTable;
use App\Models\Company;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Membership administration for the current workspace: who has access
 * (active members) and who has been asked to join but has not yet accepted
 * (pending invitations). It sits right after Workspace — membership is core
 * workspace configuration, ahead of the integration-flavoured Email provider
 * and Calendar pages and the AI/Plan pages further down.
 */
class TeamSettings extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('team.navigation_label');
    }

    public function getTitle(): string
    {
        return __('team.title');
    }

    public function getSubheading(): string
    {
        return __('team.subtitle');
    }

    public function mount(): void
    {
        Gate::forUser($this->getUser())->authorize('viewTeam', $this->getCompany());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('team.actions.invite'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (): bool => Gate::forUser($this->getUser())->allows('manageTeam', $this->getCompany()))
                ->modalHeading(__('team.invite.heading'))
                ->modalDescription(__('team.invite.description'))
                ->modalSubmitActionLabel(__('team.invite.confirm'))
                ->schema([
                    TextInput::make('email')
                        ->label(__('team.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data, InviteWorkspaceMember $inviteWorkspaceMember): void {
                    $email = $data['email'];

                    try {
                        $inviteWorkspaceMember->handle($this->getCompany(), $this->getUser(), $email);
                    } catch (WorkspaceMemberAlreadyExists $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->warning()
                            ->send();

                        return;
                    } catch (WorkspaceInvitationAlreadyPending $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->body(__('team.invite.use_resend'))
                            ->warning()
                            ->send();

                        return;
                    } catch (Throwable $exception) {
                        // The invitation row is committed before delivery is
                        // attempted, so a mail-transport failure here means the
                        // invitation exists and can be resent — not that the
                        // invite itself failed.
                        report($exception);

                        Notification::make()
                            ->title(__('team.notifications.invitation_email_failed', ['email' => $email]))
                            ->warning()
                            ->send();

                        $this->refreshTables();

                        return;
                    }

                    Notification::make()
                        ->title(__('team.notifications.invitation_sent', ['email' => $email]))
                        ->success()
                        ->send();

                    $this->refreshTables();
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            WorkspaceMembersTable::class,
            WorkspaceInvitationsTable::class,
        ];
    }

    private function refreshTables(): void
    {
        $this->dispatch('workspace-team-changed');
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
