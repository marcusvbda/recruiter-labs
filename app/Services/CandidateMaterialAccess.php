<?php

namespace App\Services;

use App\Enums\Feature;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CandidateMaterialAccess
{
    public function allows(User $user, Company $company): bool
    {
        return $company->hasWorkspaceAccess($user)
            && $company->fresh()?->hasFeature(Feature::Candidates) === true;
    }

    /** Call inside the committing transaction; membership revocation must wait for it. */
    public function lockAndAuthorize(User $user, int $companyId): Company
    {
        $company = Company::query()->lockForUpdate()->findOrFail($companyId);
        DB::table('company_user')->where('company_id', $companyId)
            ->where('user_id', $user->getKey())->lockForUpdate()->first();

        if (! $this->allows($user, $company)) {
            throw new AuthorizationException;
        }

        return $company;
    }
}
