<?php

namespace App\Policies;

use App\Models\CandidateImportBatch;
use App\Models\User;
use App\Services\CandidateMaterialAccess;

class CandidateImportBatchPolicy
{
    public function __construct(private readonly CandidateMaterialAccess $access) {}

    public function view(User $user, CandidateImportBatch $batch): bool
    {
        return $this->access->allows($user, $batch->company);
    }

    public function update(User $user, CandidateImportBatch $batch): bool
    {
        return $this->view($user, $batch);
    }
}
