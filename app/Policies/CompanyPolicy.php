<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    public function update(User $user, Company $company): bool
    {
        return $user->companies()->whereKey($company)->exists();
    }
}
