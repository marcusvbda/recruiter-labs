<?php

namespace App\Exceptions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The Owner always has workspace access, whoever asks and in whichever
 * direction — including the Owner asking about themselves. Their access is not
 * a managed state, so there is nothing to enable and nothing to disable, and no
 * path may leave a workspace whose Owner cannot enter it.
 */
class WorkspaceOwnerAccessCannotBeChanged extends RuntimeException implements ShouldntReport
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
        return new self($company, $owner, __('team.errors.owner_access_cannot_be_changed'));
    }
}
