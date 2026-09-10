<?php

namespace App\Policies;

use App\Models\CandidateImportFile;
use App\Models\User;
use App\Services\CandidateMaterialAccess;

class CandidateImportFilePolicy
{
    public function __construct(private readonly CandidateMaterialAccess $access) {}

    public function view(User $user, CandidateImportFile $file): bool
    {
        return $file->erased_at === null
            && $file->batch()->where('company_id', $file->company_id)->whereNull('details_expired_at')->where('expires_at', '>', now())->exists()
            && $this->access->allows($user, $file->company);
    }
}
