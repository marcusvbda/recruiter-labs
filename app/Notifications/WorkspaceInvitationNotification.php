<?php

namespace App\Notifications;

use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * The invitation email. It is deliberately limited to workspace identity,
 * inviter identity and expiration: it is delivered to an address that may not
 * belong to anyone with workspace access yet, so it must never carry candidate,
 * application, interview, hiring-note or member-list data.
 */
class WorkspaceInvitationNotification extends Notification
{
    /** The route T05 registers for the public invitation landing page. */
    private const ROUTE_NAME = 'workspace-invitations.show';

    /**
     * The plaintext token lives only in this object and in the delivered email;
     * only its hash is ever persisted.
     */
    public function __construct(
        private readonly CompanyInvitation $invitation,
        private readonly string $token,
    ) {
        $this->locale(self::preferredLocaleFor($invitation->email));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->company->name;

        // `invited_by_id` is nullable on delete, so the email must still make
        // sense once the inviter's account is gone.
        $invitedBy = $this->invitation->invitedBy;
        $inviter = $invitedBy !== null ? $invitedBy->name : __('team.invitation_email.unknown_inviter');

        return (new MailMessage)
            ->subject(__('team.invitation_email.subject', ['workspace' => $workspace]))
            ->greeting(__('team.invitation_email.greeting'))
            ->line(__('team.invitation_email.line_1', [
                'inviter' => $inviter,
                'workspace' => $workspace,
            ]))
            ->action(__('team.invitation_email.action'), $this->invitationUrl())
            ->line(__('team.invitation_email.expires', [
                'date' => $this->invitation->expires_at->translatedFormat('d M Y'),
            ]))
            ->line(__('team.invitation_email.line_2'));
    }

    /**
     * T05 registers the named route; until it exists the same path is built by
     * hand so the link already emailed stays the final one.
     */
    private function invitationUrl(): string
    {
        return Route::has(self::ROUTE_NAME)
            ? route(self::ROUTE_NAME, ['token' => $this->token])
            : url('/invitations/'.$this->token);
    }

    /**
     * The recipient may have no account yet, in which case there is no stored
     * preference and the application locale is the only honest answer.
     */
    private static function preferredLocaleFor(string $email): ?string
    {
        $user = User::query()
            ->whereRaw('lower(email) = ?', [CompanyInvitation::normalizeEmail($email)])
            ->first();

        return $user?->preferredLocale();
    }
}
