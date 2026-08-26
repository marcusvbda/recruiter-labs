<?php

namespace App\Actions;

use App\Enums\CompanyRole;
use App\Exceptions\WorkspaceInvitationEmailMismatch;
use App\Exceptions\WorkspaceInvitationEmailNotVerified;
use App\Exceptions\WorkspaceInvitationNotAcceptable;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The single path that turns an invitation into membership. Every check runs
 * with the invitation row locked, in the same transaction as the membership
 * write, so the state the decision was made on cannot change underneath it.
 */
class AcceptWorkspaceInvitation
{
    /**
     * Returns the workspace that was joined: it is what the caller needs to send
     * the recipient into the tenant they now belong to.
     */
    public function handle(CompanyInvitation $invitation, User $user): Company
    {
        return DB::transaction(function () use ($invitation, $user): Company {
            // The instance handed in was read before this transaction and may
            // already be revoked or accepted; only the locked row is authoritative.
            $locked = CompanyInvitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $company = $locked->company()->firstOrFail();

            $this->guardAgainstUnacceptableState($locked);
            $this->guardAgainstWrongIdentity($locked, $user);

            $this->ensureMembership($company, $user);

            // Closing the invitation under the same lock is what makes it
            // single-use: any concurrent attempt is serialized behind this
            // commit and then reads it as accepted.
            $locked->forceFill([
                'accepted_at' => now(),
                'accepted_by_id' => $user->getKey(),
            ])->save();

            return $company;
        });
    }

    private function guardAgainstUnacceptableState(CompanyInvitation $locked): void
    {
        if ($locked->isRevoked()) {
            throw WorkspaceInvitationNotAcceptable::revoked($locked);
        }

        if ($locked->isExpired()) {
            throw WorkspaceInvitationNotAcceptable::expired($locked);
        }

        if ($locked->isAccepted()) {
            throw WorkspaceInvitationNotAcceptable::alreadyAccepted($locked);
        }
    }

    /**
     * Both addresses go through the same normalization the invitation was stored
     * with, so casing never decides who may accept, and verification is required
     * before membership activates rather than after.
     */
    private function guardAgainstWrongIdentity(CompanyInvitation $locked, User $user): void
    {
        if (CompanyInvitation::normalizeEmail($user->email) !== CompanyInvitation::normalizeEmail($locked->email)) {
            throw WorkspaceInvitationEmailMismatch::for($locked, $user);
        }

        if (! $user->hasVerifiedEmail()) {
            throw WorkspaceInvitationEmailNotVerified::for($locked, $user);
        }
    }

    /**
     * Membership is created at most once. Someone who already belongs to the
     * workspace keeps the role they have — an Owner accepting an old invitation
     * must not be demoted to Member — and the acceptance still succeeds, because
     * the outcome the invitation promised is already true.
     */
    private function ensureMembership(Company $company, User $user): void
    {
        if ($company->roleFor($user) !== null) {
            return;
        }

        try {
            // A savepoint, so losing the unique(company_id, user_id) race does not
            // poison the transaction that still has to close the invitation.
            DB::transaction(function () use ($company, $user): void {
                $company->users()->attach($user, ['role' => CompanyRole::Member->value]);
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            // The concurrent insert committed the one membership row there will
            // be, which is exactly the state this acceptance was asking for.
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
