<?php

namespace App\Data;

use App\Enums\CandidateImportCandidateOutcome as Outcome;
use App\Enums\CandidateImportFailureCode as FailureCode;
use App\Enums\CandidateImportMaterialOutcome as MaterialOutcome;
use App\Enums\CandidateImportRowStatus;

/**
 * One row's terminal result, on both axes at once.
 *
 * Built inside the row's transaction and written by the same statement that
 * commits it, so a row is never observed as "succeeded" without the outcome
 * that says what succeeding meant for it.
 */
final readonly class CandidateImportRowResult
{
    public function __construct(
        public Outcome $candidateOutcome,
        public MaterialOutcome $materialOutcome = MaterialOutcome::None,
        public ?int $candidateId = null,
        public ?int $materialId = null,
        public ?int $fileId = null,
        public ?FailureCode $failureCode = null,
    ) {}

    public static function created(int $candidateId, MaterialOutcome $material, ?int $materialId, ?int $fileId): self
    {
        return new self(Outcome::Created, $material, $candidateId, $materialId, $fileId);
    }

    public static function reused(int $candidateId, int $materialId, ?int $fileId): self
    {
        return new self(Outcome::Reused, MaterialOutcome::Retained, $candidateId, $materialId, $fileId);
    }

    /** The candidate was already there and the row added nothing to them. */
    public static function unchanged(int $candidateId, MaterialOutcome $material, ?int $materialId, ?int $fileId): self
    {
        return new self(Outcome::Unchanged, $material, $candidateId, $materialId, $fileId);
    }

    public static function needsReview(FailureCode $code, ?int $fileId = null): self
    {
        return new self(Outcome::NeedsReview, MaterialOutcome::None, null, null, $fileId, $code);
    }

    public static function failed(FailureCode $code, MaterialOutcome $material = MaterialOutcome::None, ?int $fileId = null): self
    {
        return new self(Outcome::Failed, $material, null, null, $fileId, $code);
    }

    public function committed(): bool
    {
        return $this->candidateOutcome->isCommitted();
    }

    public function status(): CandidateImportRowStatus
    {
        return match ($this->candidateOutcome) {
            Outcome::Created, Outcome::Reused, Outcome::Unchanged => CandidateImportRowStatus::Succeeded,
            Outcome::Excluded => CandidateImportRowStatus::Excluded,
            Outcome::NeedsReview => CandidateImportRowStatus::NeedsReview,
            Outcome::Failed => CandidateImportRowStatus::Failed,
        };
    }

    /**
     * The row columns this result owns.
     *
     * `material_id` is only ever set, never cleared here: it is the single
     * link erasure follows from a retained CV back to the staged upload behind
     * it, and dropping it would leave a downloadable copy of a deleted
     * document inside an unexpired batch.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $attributes = [
            'status' => $this->status(),
            'candidate_outcome' => $this->candidateOutcome->value,
            'material_outcome' => $this->materialOutcome->value,
            'failure_code' => $this->failureCode?->value,
            'committed_at' => $this->committed() ? now() : null,
        ];

        foreach (['candidate_id' => $this->candidateId, 'material_id' => $this->materialId, 'file_id' => $this->fileId] as $column => $value) {
            if ($value !== null) {
                $attributes[$column] = $value;
            }
        }

        return $attributes;
    }
}
