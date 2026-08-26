<?php

namespace App\Exceptions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Removal never applies to the Owner, whoever asks. Allowing it — including the
 * Owner removing themselves — would leave the workspace with nobody able to
 * manage membership, and this feature introduces no way back from that.
 */
class WorkspaceOwnerCannotBeRemoved extends RuntimeException implements ShouldntReport
{
    private function __construct(
        public readonly Company $company,
        public readonly User $owner,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Company $company, User $owner): self
    {
        return new self($company, $owner, __('team.errors.owner_cannot_be_removed'));
    }
}
