<?php

namespace App\Actions;

use App\Enums\CompanyRole;
use App\Exceptions\WorkspaceInvitationAlreadyPending;
use App\Exceptions\WorkspaceMemberAlreadyExists;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * The single path that creates an invitation. Authorization lives here rather
 * than in the Team screen so a direct request from a Member is refused too.
 */
class InviteWorkspaceMember
{
    public const EXPIRES_IN_DAYS = 7;

    public function handle(Company $company, User $inviter, string $email): CompanyInvitation
    {
        Gate::forUser($inviter)->authorize('manageTeam', $company);

        $normalizedEmail = CompanyInvitation::normalizeEmail($email);

        $token = CompanyInvitation::generateToken();

        $invitation = DB::transaction(function () use ($company, $inviter, $normalizedEmail, $token): CompanyInvitation {
            // Cheap rejection of the common case, before taking any row lock.
            $this->guardAgainstActiveMember($company, $normalizedEmail);

            return $this->issueInvitation($company, $inviter, $normalizedEmail, $token);
        });

        // Delivery happens after the row is committed: the plaintext token only
        // ever exists here and in the email, never in storage or in a log.
        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation, $token));

        return $invitation;
    }

    private function guardAgainstActiveMember(Company $company, string $normalizedEmail): void
    {
        $member = $company->activeMembers()->get()
            ->first(fn (User $user): bool => CompanyInvitation::normalizeEmail($user->email) === $normalizedEmail);

        if ($member === null) {
            return;
        }

        // They are already on this team either way, so no invitation is created.
        // A member whose access is disabled does not need one: the owner restores
        // their access directly, and telling the owner this person "already has
        // access" would point them at the wrong action entirely.
        if (! $company->hasWorkspaceAccess($member)) {
            throw WorkspaceMemberAlreadyExists::withAccessDisabled($normalizedEmail);
        }

        throw WorkspaceMemberAlreadyExists::for(
            $normalizedEmail,
            $company->roleFor($member) ?? CompanyRole::Member,
        );
    }

    private function issueInvitation(Company $company, User $inviter, string $normalizedEmail, string $token): CompanyInvitation
    {
        $invitation = $this->lockQuery($company, $normalizedEmail)->first();

        if ($invitation === null) {
            try {
                // A savepoint, so losing the unique(company_id, email) race to a
                // concurrent invite does not poison the surrounding transaction.
                return DB::transaction(fn (): CompanyInvitation => $this->createInvitation($company, $inviter, $normalizedEmail, $token));
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                // The winner of the race committed the only row there will ever
                // be for this pair, so this invite continues on that row.
                $invitation = $this->lockQuery($company, $normalizedEmail)->firstOrFail();
            }
        }

        // Authoritative membership check: it runs with the invitation row already
        // locked, so a concurrent acceptance can no longer commit membership
        // between the read and the decision and turn a just-accepted invitation
        // into a fresh pending one for someone who already has access.
        $this->guardAgainstActiveMember($company, $normalizedEmail);

        if ($invitation->isPending()) {
            throw WorkspaceInvitationAlreadyPending::for($invitation);
        }

        return $this->reissueInvitation($invitation, $inviter, $token);
    }

    /** @return Builder<CompanyInvitation> */
    private function lockQuery(Company $company, string $normalizedEmail): Builder
    {
        return CompanyInvitation::query()
            ->whereBelongsTo($company)
            ->forEmail($normalizedEmail)
            ->lockForUpdate();
    }

    private function createInvitation(Company $company, User $inviter, string $normalizedEmail, string $token): CompanyInvitation
    {
        $invitation = new CompanyInvitation;
        $invitation->company()->associate($company);
        $invitation->invitedBy()->associate($inviter);
        $invitation->forceFill([
            'email' => $normalizedEmail,
            'token_hash' => CompanyInvitation::hashToken($token),
            'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
        ])->save();

        return $invitation;
    }

    /**
     * Reusing the one row per (workspace, email) hands a previously revoked or
     * previously accepted-then-removed person a clean invitation, and rotating
     * the token is what stops every link issued before this one.
     */
    private function reissueInvitation(CompanyInvitation $invitation, User $inviter, string $token): CompanyInvitation
    {
        $invitation->forceFill([
            'token_hash' => CompanyInvitation::hashToken($token),
            'invited_by_id' => $inviter->getKey(),
            'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
            'accepted_at' => null,
            'accepted_by_id' => null,
            'revoked_at' => null,
        ])->save();

        return $invitation;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
