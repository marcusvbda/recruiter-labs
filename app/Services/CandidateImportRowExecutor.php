<?php

namespace App\Services;

use App\Data\CandidateImportManifest;
use App\Data\CandidateImportRowResult;
use App\Enums\CandidateImportFailureCode as FailureCode;
use App\Enums\CandidateImportMaterialOutcome as MaterialOutcome;
use App\Exceptions\CandidateImportStagedFileMissing;
use App\Models\Candidate;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportFile;
use App\Models\CandidateImportOrigin;
use App\Models\CandidateImportRow;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * One row of a confirmed import, applied or refused as a single fact.
 *
 * The unit of this whole feature is the row, not the batch. Creating the
 * person, recording where they came from, retaining the CV they arrived with
 * and writing down that all of it happened are one transaction, because the
 * alternative is a workspace that shows a new candidate whose required CV was
 * never stored, or a stored file with nobody to belong to. If any part fails,
 * the row leaves nothing behind — {@see CandidateMaterialStorage::atomic()}
 * also removes bytes already written to disk — and the failure is recorded
 * afterwards, on its own, so the report can still tell the recruiter what
 * happened to that record.
 *
 * It reads the manifest and only the manifest. What it does re-check is the
 * world: the importer's access, and whether the identity the reviewer approved
 * is still the identity in front of it. Those checks cannot be frozen at
 * confirmation time, because acting on a stale answer is exactly what would
 * create a duplicate person or file someone else's CV under the wrong name.
 * When the world moved, the row stops and says so; it never improvises a
 * different import.
 */
class CandidateImportRowExecutor
{
    public function __construct(
        private readonly CandidateMaterialStorage $storage,
        private readonly CandidateMaterialAccess $access,
    ) {}

    /**
     * Apply one row, or record why it was not applied.
     *
     * Returns `null` when there was nothing to do: the row already committed,
     * or its result has since been erased. Both are silence on purpose —
     * rerunning a committed row would double-count it, and rerunning an erased
     * one would resurrect a candidate or a document somebody deleted.
     *
     * An {@see AuthorizationException} is deliberately allowed to escape. It
     * is not this row's failure but the whole execution's, and the runner
     * answers it by pausing the remaining work rather than marking rows
     * failed for a reason that has nothing to do with them.
     *
     * @throws AuthorizationException
     */
    public function execute(CandidateImportBatch $batch, User $actor, int $rowId): ?CandidateImportRowResult
    {
        try {
            return $this->storage->atomic(function () use ($batch, $actor, $rowId): ?CandidateImportRowResult {
                $company = $this->access->lockAndAuthorize($actor, (int) $batch->company_id);
                $row = $this->lockRow($company, $batch, $rowId);

                if ($row === null) {
                    return null;
                }

                $manifest = CandidateImportManifest::fromRow($row);

                if ($manifest === null || ! $row->selected) {
                    return $this->record($row, CandidateImportRowResult::needsReview(FailureCode::ManifestMissing));
                }

                return $this->record($row, $this->apply($company, $batch, $actor, $manifest));
            });
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // The row's own transaction is gone, along with any candidate it
            // had begun to create and any bytes it had written. Recording the
            // failure is a separate, tiny write, so a row that could not be
            // imported is still visible as such instead of looking pending
            // forever.
            return $this->recordFailure($batch, $rowId, $this->failureFor($exception));
        }
    }

    /**
     * The row as it is right now, locked, or nothing if it must not run.
     *
     * A row carrying `result_erased_at` is a tombstone: it succeeded once and
     * its candidate or CV has since been deleted. Replaying it would recreate
     * data the workspace deliberately removed, so it is left exactly as
     * erasure wrote it.
     */
    private function lockRow(Company $company, CandidateImportBatch $batch, int $rowId): ?CandidateImportRow
    {
        /** @var CandidateImportRow|null $row */
        $row = CandidateImportRow::query()
            ->where('company_id', $company->getKey())
            ->where('batch_id', $batch->getKey())
            ->lockForUpdate()
            ->find($rowId);

        if ($row === null || $row->committed_at !== null || $row->result_erased_at !== null) {
            return null;
        }

        return $row;
    }

    /** The actual import of one row, inside the row's transaction. */
    private function apply(Company $company, CandidateImportBatch $batch, User $actor, CandidateImportManifest $manifest): CandidateImportRowResult
    {
        $drift = $this->drift($company, $manifest);

        if ($drift !== null) {
            return CandidateImportRowResult::needsReview($drift, $manifest->fileId);
        }

        $created = $manifest->creates();
        $candidate = $created
            ? $this->create($company, $batch, $actor, $manifest)
            : $this->reuse($company, $manifest);

        if ($candidate === null) {
            return CandidateImportRowResult::needsReview(FailureCode::IdentityMissing, $manifest->fileId);
        }

        $candidateId = (int) $candidate->getKey();

        if (! $manifest->addsMaterial()) {
            // Nothing was promised beyond the person. A row that only carried
            // contact details for somebody already in the pool changed
            // nothing at all, and says so rather than claiming a reuse.
            return $created
                ? CandidateImportRowResult::created($candidateId, MaterialOutcome::None, null, null)
                : CandidateImportRowResult::unchanged($candidateId, MaterialOutcome::None, null, null);
        }

        $file = CandidateImportFile::query()
            ->where('company_id', $company->getKey())
            ->where('batch_id', $batch->getKey())
            ->lockForUpdate()
            ->find($manifest->fileId);

        if ($file === null) {
            throw new CandidateImportStagedFileMissing((int) $manifest->fileId);
        }

        $stored = $this->storage->storeStaged(
            $company,
            $candidate,
            $actor,
            $file,
            $manifest->sourceLabel,
            $manifest->receivedOn,
            $batch->declaration_at !== null,
            $batch,
        );

        $materialId = (int) $stored['material']->getKey();

        if ($stored['duplicate']) {
            // The candidate already held this exact document. The link is
            // still recorded, so deleting that CV later also removes the
            // staged copy this row brought in.
            return $created
                ? CandidateImportRowResult::created($candidateId, MaterialOutcome::DuplicateSkipped, $materialId, $manifest->fileId)
                : CandidateImportRowResult::unchanged($candidateId, MaterialOutcome::DuplicateSkipped, $materialId, $manifest->fileId);
        }

        return $created
            ? CandidateImportRowResult::created($candidateId, MaterialOutcome::Retained, $materialId, $manifest->fileId)
            : CandidateImportRowResult::reused($candidateId, $materialId, $manifest->fileId);
    }

    /**
     * Whether the pool still looks the way the reviewer was told it did.
     *
     * Confirmation approved a specific answer to "is this person already
     * here?". Between then and now somebody may have created that person by
     * hand, deleted them, or changed their email — and every one of those
     * makes the approved answer wrong. The row is sent back for review rather
     * than resolved again, because a duplicate person and a CV attached to
     * the wrong file are both worse than an unfinished row.
     */
    private function drift(Company $company, CandidateImportManifest $manifest): ?FailureCode
    {
        if ($manifest->normalizedEmail === null) {
            return null;
        }

        /** @var Candidate|null $matching */
        $matching = Candidate::matchingEmail((int) $company->getKey(), $manifest->normalizedEmail)
            ->lockForUpdate()
            ->first();

        if ($manifest->creates()) {
            return $matching === null ? null : FailureCode::IdentityTaken;
        }

        if ($matching === null) {
            return FailureCode::IdentityMissing;
        }

        return (int) $matching->getKey() === (int) $manifest->candidateId ? null : FailureCode::IdentityChanged;
    }

    /**
     * The candidate the review matched, still locked and still themselves.
     *
     * Nothing about them is written. Import never overwrites a name, an
     * email, a phone number or a social profile that the workspace already
     * holds — not even an empty one, because "we have no phone for them" is
     * an answer a recruiter may have arrived at on purpose, and a spreadsheet
     * is not evidence against it.
     */
    private function reuse(Company $company, CandidateImportManifest $manifest): ?Candidate
    {
        /** @var Candidate|null $candidate */
        $candidate = Candidate::query()
            ->where('company_id', $company->getKey())
            ->whereKey($manifest->candidateId)
            ->lockForUpdate()
            ->first();

        return $candidate;
    }

    /** A person the workspace did not have, plus the record of where they came from. */
    private function create(Company $company, CandidateImportBatch $batch, User $actor, CandidateImportManifest $manifest): ?Candidate
    {
        $name = $manifest->name ?? $manifest->email;

        if ($name === null) {
            return null;
        }

        $candidate = new Candidate;
        $candidate->forceFill([
            'company_id' => $company->getKey(),
            'name' => $name,
            'phone' => $manifest->phone,
            'socials' => $manifest->linkedinUrl === null
                ? []
                : [['network' => 'linkedin', 'account' => $manifest->linkedinUrl]],
        ]);
        // Through the mutator, so the normalized column the unique index
        // guards is written by exactly the same rule the lookup used.
        $candidate->email = $manifest->email;
        $candidate->save();

        // First entry only, and immutable from here. A candidate who was
        // already in the pool keeps the provenance they already had, whatever
        // this file says about where they came from.
        CandidateImportOrigin::query()->firstOrCreate(
            ['company_id' => $company->getKey(), 'candidate_id' => $candidate->getKey()],
            [
                'batch_id' => $batch->getKey(),
                'added_by_id' => $actor->getKey(),
                'source_label' => $manifest->sourceLabel,
                'added_at' => now(),
            ],
        );

        return $candidate;
    }

    /**
     * What a thrown exception means for this row, in the vocabulary of the report.
     *
     * A lost race on the email is drift, not a defect: somebody created that
     * person between the check and the insert, and the honest answer is the
     * same one the drift check gives.
     */
    private function failureFor(Throwable $exception): CandidateImportRowResult
    {
        if ($exception instanceof CandidateImportStagedFileMissing) {
            return CandidateImportRowResult::failed(FailureCode::MaterialUnavailable, MaterialOutcome::Failed, $exception->fileId);
        }

        if ($exception instanceof ValidationException) {
            $keys = array_keys($exception->errors());

            if (in_array('email', $keys, true)) {
                return CandidateImportRowResult::needsReview(FailureCode::IdentityTaken);
            }

            if (in_array('file', $keys, true)) {
                return CandidateImportRowResult::failed(FailureCode::MaterialRejected, MaterialOutcome::Failed);
            }
        }

        return CandidateImportRowResult::failed(FailureCode::RowFailed);
    }

    /** Write a result for a row whose own transaction has already been rolled back. */
    private function recordFailure(CandidateImportBatch $batch, int $rowId, CandidateImportRowResult $result): CandidateImportRowResult
    {
        DB::transaction(function () use ($batch, $rowId, $result): void {
            /** @var CandidateImportRow|null $row */
            $row = CandidateImportRow::query()
                ->where('company_id', (int) $batch->company_id)
                ->where('batch_id', $batch->getKey())
                ->lockForUpdate()
                ->find($rowId);

            if ($row === null || $row->committed_at !== null || $row->result_erased_at !== null) {
                return;
            }

            $this->record($row, $result);
        });

        return $result;
    }

    private function record(CandidateImportRow $row, CandidateImportRowResult $result): CandidateImportRowResult
    {
        $row->forceFill($result->attributes())->save();

        return $result;
    }
}
