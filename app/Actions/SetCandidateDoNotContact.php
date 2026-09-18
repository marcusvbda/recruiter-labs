<?php

namespace App\Actions;

use App\Models\Candidate;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetCandidateDoNotContact
{
    /** The state is workspace-owned and changes only by a human action. */
    public function run(User $actor, Candidate $candidate, bool $doNotContact): Candidate
    {
        return DB::transaction(function () use ($actor, $candidate, $doNotContact): Candidate {
            $company = Company::query()->lockForUpdate()->findOrFail($candidate->company_id);
            Gate::forUser($actor)->authorize('update', $company);

            $candidate = Candidate::query()
                ->whereBelongsTo($company)
                ->lockForUpdate()
                ->findOrFail($candidate->getKey());

            $candidate->forceFill([
                'do_not_contact_at' => $doNotContact ? now() : null,
                'do_not_contact_by_id' => $doNotContact ? $actor->getKey() : null,
            ])->save();

            return $candidate;
        });
    }
}
