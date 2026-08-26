<?php

namespace App\Exceptions;

use App\Enums\CompanyRole;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The invited address already belongs to an active member of that workspace, so
 * there is nothing to invite them to. It is a product outcome the Team UI
 * explains ("this person already has access"), not a fault.
 */
class WorkspaceMemberAlreadyExists extends RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly string $email,
        public readonly CompanyRole $role,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(string $email, CompanyRole $role): self
    {
        return new self($email, $role, __('team.errors.already_member', [
            'email' => $email,
            'role' => $role->label(),
        ]));
    }
}
