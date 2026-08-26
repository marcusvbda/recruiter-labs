<?php

namespace App\Exceptions;

use App\Enums\CompanyRole;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The invited address already belongs to a member of that workspace, so there is
 * nothing to invite them to. It is a product outcome the Team UI explains, not a
 * fault.
 *
 * Two outcomes, kept distinguishable by {@see $accessDisabled} so the caller can
 * present them differently: the person already has access, or the person is on
 * the team with their workspace access currently disabled — which the owner
 * restores directly instead of inviting them again.
 */
class WorkspaceMemberAlreadyExists extends RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly string $email,
        public readonly CompanyRole $role,
        public readonly bool $accessDisabled,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(string $email, CompanyRole $role): self
    {
        return new self($email, $role, false, __('team.errors.already_member', [
            'email' => $email,
            'role' => $role->label(),
        ]));
    }

    public static function withAccessDisabled(string $email): self
    {
        return new self($email, CompanyRole::Member, true, __('team.errors.already_member_access_disabled', [
            'email' => $email,
        ]));
    }
}
