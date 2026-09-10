<?php

namespace App\Policies;

use App\Models\CandidateMaterial;
use App\Models\User;
use App\Services\CandidateMaterialAccess;

class CandidateMaterialPolicy
{
    public function __construct(private readonly CandidateMaterialAccess $access) {}

    public function view(User $user, CandidateMaterial $material): bool
    {
        return $material->deleted_at === null
            && $material->candidate()->where('company_id', $material->company_id)->exists()
            && $this->access->allows($user, $material->company);
    }

    public function update(User $user, CandidateMaterial $material): bool
    {
        return $this->view($user, $material);
    }

    public function delete(User $user, CandidateMaterial $material): bool
    {
        return $this->view($user, $material);
    }
}
