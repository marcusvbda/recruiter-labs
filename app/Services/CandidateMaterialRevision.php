<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Company;

class CandidateMaterialRevision
{
    /** Call in the material transaction, after locking company then candidate. */
    public function advance(int $companyId, int $candidateId): void
    {
        Candidate::query()->where('company_id', $companyId)->whereKey($candidateId)->increment('materials_revision');
        Company::query()->whereKey($companyId)->increment('candidate_pool_revision');
    }
}
