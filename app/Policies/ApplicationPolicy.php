<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->companies()->exists();
    }

    public function view(User $user, Application $application): bool
    {
        if (! $user->companies()->whereKey($application->company_id)->exists()) {
            return false;
        }

        if (! $application->job()->where('company_id', $application->company_id)->exists()) {
            return false;
        }

        if (! $application->candidate()->where('company_id', $application->company_id)->exists()) {
            return false;
        }

        if (! $application->status()->where('company_id', $application->company_id)->exists()) {
            return false;
        }

        if ($application->referral_id === null) {
            return true;
        }

        return $application->referral()
            ->where('company_id', $application->company_id)
            ->where('job_id', $application->job_id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Application $application): bool
    {
        return $this->view($user, $application);
    }

    public function delete(User $user, Application $application): bool
    {
        return false;
    }

    public function restore(User $user, Application $application): bool
    {
        return false;
    }

    public function forceDelete(User $user, Application $application): bool
    {
        return false;
    }
}
