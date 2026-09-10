<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateMaterial;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CandidateMaterialLifecycle
{
    public function __construct(
        private readonly CandidateMaterialAccess $access,
        private readonly CandidateMaterialMetadata $metadata,
        private readonly CandidateMaterialRevision $revision,
        private readonly CandidateMaterialErasure $erasure,
    ) {}

    public function archive(Company $company, CandidateMaterial $material, User $actor, bool $archived = true): CandidateMaterial
    {
        return DB::transaction(function () use ($company, $material, $actor, $archived): CandidateMaterial {
            $material = $this->lock($company, $material, $actor);
            if (($material->archived_at !== null) !== $archived) {
                $material->forceFill(['archived_at' => $archived ? now() : null])->save();
                $this->revision->advance($company->getKey(), $material->candidate_id);
            }

            return $material;
        });
    }

    public function correctMetadata(Company $company, CandidateMaterial $material, User $actor, string $sourceLabel, ?string $receivedOn, ?string $timezone = null): CandidateMaterial
    {
        $metadata = $this->metadata->validate($sourceLabel, $receivedOn, $timezone);

        return DB::transaction(function () use ($company, $material, $actor, $metadata): CandidateMaterial {
            $material = $this->lock($company, $material, $actor);
            $dateChanged = $material->received_on?->toDateString() !== $metadata['received_on'];
            if ($dateChanged || $material->source_label !== $metadata['source_label']) {
                $material->forceFill([...$metadata, 'metadata_corrected_by_id' => $actor->getKey(), 'metadata_corrected_at' => now()])->save();
                if ($dateChanged && $material->isAvailable()) {
                    $this->revision->advance($company->getKey(), $material->candidate_id);
                }
            }

            return $material;
        });
    }

    public function delete(Company $company, CandidateMaterial $material, User $actor): void
    {
        DB::transaction(function () use ($company, $material, $actor): void {
            $material = $this->lock($company, $material, $actor);
            $this->erasure->eraseMaterial($material);
        });
    }

    private function lock(Company $company, CandidateMaterial $material, User $actor): CandidateMaterial
    {
        $this->access->lockAndAuthorize($actor, $company->getKey());
        abort_unless($material->company_id === $company->getKey(), 404);
        Candidate::query()->where('company_id', $company->getKey())->whereKey($material->candidate_id)->lockForUpdate()->firstOrFail();

        return CandidateMaterial::query()->where('company_id', $company->getKey())->whereNull('deleted_at')->whereKey($material->getKey())->lockForUpdate()->firstOrFail();
    }
}
