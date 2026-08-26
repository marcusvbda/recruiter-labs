<?php

namespace App\Exceptions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The person whose workspace access was being changed no longer belongs to that
 * workspace — a stale team list, or a request naming someone from elsewhere.
 * Either way there is no membership to change, and nothing is written.
 */
class WorkspaceMemberNotFound extends RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly Company $company,
        public readonly User $user,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Company $company, User $user): self
    {
        return new self($company, $user, __('team.errors.not_a_member', ['name' => $user->name]));
    }
}
