<?php

namespace App\Actions;

use App\Exceptions\WorkspaceInvitationNotResendable;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Resending never creates a second invitation: it refreshes the one row that
 * exists for that workspace and address. Authorization is resolved against the
 * invitation's own workspace, so it cannot be driven from another tenant.
 */
class ResendWorkspaceInvitation
{
    public function handle(CompanyInvitation $invitation, User $actor): CompanyInvitation
    {
        Gate::forUser($actor)->authorize('resend', $invitation);

        $token = CompanyInvitation::generateToken();

        $invitation = DB::transaction(function () use ($invitation, $token): CompanyInvitation {
            $locked = CompanyInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->guardAgainstUnresendableState($locked);

            // A fresh token is what guarantees a single usable invitation path:
            // every link mailed before this one stops resolving.
            $locked->forceFill([
                'token_hash' => CompanyInvitation::hashToken($token),
                'expires_at' => now()->addDays(InviteWorkspaceMember::EXPIRES_IN_DAYS),
                'accepted_at' => null,
                'accepted_by_id' => null,
            ])->save();

            return $locked;
        });

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation, $token));

        return $invitation;
    }

    /**
     * Pending and expired invitations are both resendable. A revoked one is not:
     * the Owner withdrew access deliberately and must invite again. An accepted
     * one is only resendable once its accepter has left the workspace, which is
     * how a removed person gets a new valid invitation.
     */
    private function guardAgainstUnresendableState(CompanyInvitation $locked): void
    {
        if ($locked->isRevoked()) {
            throw WorkspaceInvitationNotResendable::revoked($locked);
        }

        if ($locked->isAccepted() && $this->accepterStillHasAccess($locked)) {
            throw WorkspaceInvitationNotResendable::alreadyAccepted($locked);
        }
    }

    private function accepterStillHasAccess(CompanyInvitation $locked): bool
    {
        $accepter = $locked->acceptedBy;
        $company = $locked->company;

        return $accepter !== null && $company !== null && $company->roleFor($accepter) !== null;
    }
}
