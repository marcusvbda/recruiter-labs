<?php

namespace App\Services;

use App\Enums\CandidateMaterialPreparationStatus as Status;
use App\Jobs\PrepareCandidateMaterial;
use App\Models\Candidate;
use App\Models\CandidateMaterial;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CandidateMaterialPreparation
{
    public function __construct(
        private readonly CandidateCvInspector $inspector,
        private readonly CandidateMaterialAccess $access,
        private readonly CandidateMaterialRevision $revision,
    ) {}

    /** The caller holds company/candidate/material locks in a transaction. */
    public function schedule(CandidateMaterial $material): void
    {
        $material->forceFill([
            'preparation_status' => Status::Preparing,
            'preparation_generation' => $material->preparation_generation + 1,
            'preparation_started_at' => now(), 'preparation_error' => null,
        ])->save();
        $companyId = $material->company_id;
        $materialId = $material->getKey();
        $generation = $material->preparation_generation;
        DB::afterCommit(function () use ($companyId, $materialId, $generation): void {
            try {
                PrepareCandidateMaterial::dispatch($companyId, $materialId, $generation);
            } catch (Throwable) {
                $this->fail($companyId, $materialId, $generation, 'queue_unavailable');
            }
        });
    }

    public function retry(Company $company, CandidateMaterial $material, User $actor): void
    {
        DB::transaction(function () use ($company, $material, $actor): void {
            $this->access->lockAndAuthorize($actor, $company->getKey());
            $material = $this->lockMaterial($company->getKey(), $material->getKey());
            abort_unless($material !== null && $material->deleted_at === null, 404);
            $stalled = $material->preparation_status === Status::Preparing
                && $material->preparation_started_at?->lte(now()->subMinutes(CandidateImportLimits::STALLED_MINUTES));
            abort_unless($stalled || in_array($material->preparation_status, [Status::Stored, Status::Failed, Status::NoReadableText, Status::FileUnavailable], true), 409);
            $this->schedule($material);
        });
    }

    public function prepare(int $companyId, int $materialId, int $generation): void
    {
        $material = CandidateMaterial::query()->where('company_id', $companyId)->whereNull('deleted_at')->find($materialId);
        if ($material === null || $material->preparation_generation !== $generation || $material->preparation_status !== Status::Preparing) {
            return;
        }
        if ($material->disk !== 'candidate_materials' || $material->path === null) {
            $this->fail($companyId, $materialId, $generation, 'file_unavailable', Status::FileUnavailable);

            return;
        }
        try {
            if (! Storage::disk('candidate_materials')->exists($material->path)) {
                $this->fail($companyId, $materialId, $generation, 'file_unavailable', Status::FileUnavailable);

                return;
            }
            $text = $this->inspector->read(Storage::disk('candidate_materials')->path($material->path), $material->extension);
            $text = trim((string) preg_replace('/\s+/u', ' ', mb_scrub($text, 'UTF-8')));
            $retainedText = Str::limit($text, CandidateImportLimits::TEXT_CHARACTERS, '');
            $partial = $retainedText !== $text;
            $text = $retainedText;
            DB::transaction(function () use ($companyId, $materialId, $generation, $text, $partial): void {
                $material = $this->lockMaterial($companyId, $materialId);
                if ($material === null || $material->deleted_at !== null || $material->preparation_generation !== $generation || $material->preparation_status !== Status::Preparing) {
                    return;
                }
                $changed = $material->prepared_text !== ($text === '' ? null : $text);
                $material->forceFill([
                    'prepared_text' => $text === '' ? null : $text, 'text_is_partial' => $partial,
                    'preparation_status' => $text === '' ? Status::NoReadableText : Status::Ready,
                    'prepared_at' => now(), 'preparation_error' => null,
                ])->save();
                if ($changed && $material->isAvailable() && $material->candidate_id !== null) {
                    $this->revision->advance($companyId, $material->candidate_id);
                }
            });
        } catch (Throwable) {
            $this->fail($companyId, $materialId, $generation, 'preparation_failed');
        }
    }

    public function fail(int $companyId, int $materialId, int $generation, string $reason, Status $status = Status::Failed): void
    {
        DB::transaction(function () use ($companyId, $materialId, $generation, $reason, $status): void {
            $material = $this->lockMaterial($companyId, $materialId);
            if ($material === null || $material->deleted_at !== null || $material->preparation_generation !== $generation || $material->preparation_status !== Status::Preparing) {
                return;
            }
            $hadText = filled($material->prepared_text);
            $material->forceFill(['preparation_status' => $status, 'preparation_error' => $reason, 'prepared_text' => null, 'text_is_partial' => false])->save();
            if ($hadText && $material->isAvailable() && $material->candidate_id !== null) {
                $this->revision->advance($companyId, $material->candidate_id);
            }
        });
    }

    public function markFileUnavailable(int $companyId, int $materialId): void
    {
        DB::transaction(function () use ($companyId, $materialId): void {
            $material = $this->lockMaterial($companyId, $materialId);
            if ($material === null || $material->deleted_at !== null || $material->preparation_status === Status::FileUnavailable) {
                return;
            }
            $material->forceFill(['preparation_status' => Status::FileUnavailable,
                'preparation_generation' => $material->preparation_generation + 1,
                'preparation_error' => 'file_unavailable', 'prepared_text' => null, 'text_is_partial' => false])->save();
            if ($material->isAvailable() && $material->candidate_id !== null) {
                $this->revision->advance($companyId, $material->candidate_id);
            }
        });
    }

    private function lockMaterial(int $companyId, int $materialId): ?CandidateMaterial
    {
        Company::query()->whereKey($companyId)->lockForUpdate()->first();
        $candidateId = CandidateMaterial::query()->where('company_id', $companyId)->whereKey($materialId)->value('candidate_id');
        Candidate::query()->where('company_id', $companyId)->whereKey($candidateId)->lockForUpdate()->first();

        return CandidateMaterial::query()->where('company_id', $companyId)->lockForUpdate()->find($materialId);
    }
}
