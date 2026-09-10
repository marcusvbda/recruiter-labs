<?php

namespace App\Services;

use App\Data\CandidateImportDecision;
use App\Data\CandidateImportReviewRow;
use App\Data\CandidateImportSummary;
use App\Enums\CandidateImportDecisionKind;
use App\Enums\CandidateImportIdentityAction;
use App\Enums\CandidateImportIssueCode;
use App\Enums\CandidateImportRefusalCode;
use App\Exceptions\CandidateImportRefused;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportFile;
use App\Models\CandidateImportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The five things a reviewer may decide, and nothing else.
 *
 * Include an eligible row. Exclude a row. Confirm the reuse of the existing
 * candidate the row was shown. Import a contact and deliberately omit its CV.
 * Choose which row of an intra-file duplicate group is the one that counts.
 * That is the whole vocabulary, and it is small on purpose: the preview is not
 * a spreadsheet editor, because a product that let a recruiter retype an
 * address here would be importing a candidate the source file never described,
 * with nothing left to distinguish supplied data from invented data.
 *
 * Every decision is written on the row it concerns, and `selected` and
 * `status` are recomputed from it rather than set by hand — one place decides
 * what a row means, so the review screen, the summary and the manifest can
 * never disagree. Every decision also moves the batch's revision, so a second
 * tab holding the previous set of answers cannot confirm them over the top of
 * these ones.
 *
 * Nothing here touches a candidate, a material or a file. A reviewer is still
 * free to walk away from the entire import, and walking away has to cost the
 * workspace nothing.
 */
class CandidateImportReview
{
    /** Rows read and written per statement, so a full batch stays a handful of queries. */
    private const CHUNK = 200;

    public function __construct(
        private CandidateImportRevisions $revisions,
    ) {}

    /** Keep an already eligible row in the import. */
    public function include(CandidateImportBatch $batch, int $recordNumber, User $actor): CandidateImportSummary
    {
        return $this->decide($batch, $recordNumber, function (CandidateImportReviewRow $row) use ($actor): CandidateImportDecision {
            $decided = ($row->decision ?? new CandidateImportDecision)
                ->with(CandidateImportDecisionKind::Include, decidedById: (int) $actor->getKey());

            if (! $row->withDecision($decided)->isEligible()) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::RowNotEligible,
                    ['record' => $row->recordNumber],
                    'This row still has to be corrected, excluded, or imported without its CV.',
                );
            }

            return $decided;
        });
    }

    /** Leave a row out of the import entirely. */
    public function exclude(CandidateImportBatch $batch, int $recordNumber, User $actor): CandidateImportSummary
    {
        return $this->decide($batch, $recordNumber, fn (CandidateImportReviewRow $row): CandidateImportDecision => ($row->decision ?? new CandidateImportDecision)
            ->with(CandidateImportDecisionKind::Exclude, decidedById: (int) $actor->getKey()));
    }

    /**
     * Reuse the existing candidate the row was shown, name conflict and all.
     *
     * The candidate is named by the caller and checked against the one the
     * preview offered. A confirmation that quietly reused a different
     * candidate than the screen displayed — because the pool moved on between
     * the render and the click — would be the product attaching a stranger's
     * CV to somebody's profile.
     */
    public function confirmReuse(
        CandidateImportBatch $batch,
        int $recordNumber,
        User $actor,
        int $existingCandidateId,
    ): CandidateImportSummary {
        return $this->decide($batch, $recordNumber, function (CandidateImportReviewRow $row) use ($actor, $existingCandidateId): CandidateImportDecision {
            $shown = $row->existingCandidateId();

            if ($shown === null || $row->identity() !== CandidateImportIdentityAction::Reuse) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::ReuseUnavailable,
                    ['record' => $row->recordNumber],
                    'This row has no single existing candidate to reuse.',
                );
            }

            if ($shown !== $existingCandidateId) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::ReuseMismatch,
                    ['record' => $row->recordNumber, 'shown' => $shown, 'confirmed' => $existingCandidateId],
                    'The candidate confirmed is not the one this row was shown.',
                );
            }

            return ($row->decision ?? new CandidateImportDecision)->with(
                CandidateImportDecisionKind::ConfirmReuse,
                candidateId: $shown,
                decidedById: (int) $actor->getKey(),
            );
        });
    }

    /**
     * Import the contact and deliberately omit the CV the row referenced.
     *
     * This answers every question about the document at once — the upload that
     * was missing, the two uploads carrying one name, the file two rows both
     * wanted, the PDF that would not open — and it answers none about who the
     * contact is. The received date goes with the CV, because a row that keeps
     * no document has nothing it received, and a manifest claiming otherwise
     * would be recording a date for a file that was never imported.
     *
     * It frees nothing for anybody else. If two rows wanted the same upload,
     * the other one is still ambiguous and still has to be decided; the
     * product does not hand a contested file to the survivor of a decision
     * that was never about them.
     */
    public function importContactOnly(CandidateImportBatch $batch, int $recordNumber, User $actor): CandidateImportSummary
    {
        return $this->decide($batch, $recordNumber, function (CandidateImportReviewRow $row) use ($actor): CandidateImportDecision {
            if (! $row->referencesCv()) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::ContactOnlyNotApplicable,
                    ['record' => $row->recordNumber],
                    'This row references no CV, so there is nothing to omit.',
                );
            }

            return ($row->decision ?? new CandidateImportDecision)
                ->with(CandidateImportDecisionKind::ContactOnly, decidedById: (int) $actor->getKey());
        }, dropFile: true);
    }

    /**
     * Keep exactly one row of an intra-file duplicate group.
     *
     * The rest of the group is excluded in the same statement, which is what
     * makes selecting two of them impossible rather than merely discouraged:
     * there is no moment, and no second request, in which the group has two
     * survivors.
     */
    public function chooseDuplicate(CandidateImportBatch $batch, int $recordNumber, User $actor): CandidateImportSummary
    {
        $this->revisions->assertReviewable($batch);

        DB::transaction(function () use ($batch, $recordNumber, $actor): void {
            $chosen = $this->row($batch, $recordNumber);
            $group = $chosen->duplicateGroup();

            if ($group === null) {
                throw CandidateImportRefused::make(
                    CandidateImportRefusalCode::DuplicateGroupMissing,
                    ['record' => $recordNumber],
                    'This row belongs to no duplicate group.',
                );
            }

            $decision = ($chosen->decision ?? new CandidateImportDecision)->with(
                CandidateImportDecisionKind::ChooseDuplicate,
                duplicateGroup: $group,
                decidedById: (int) $actor->getKey(),
            );

            $this->write($batch, $chosen->withDecision($decision));

            /** @var list<CandidateImportRow> $models */
            $models = CandidateImportRow::query()
                ->where('company_id', (int) $batch->company_id)
                ->where('batch_id', (int) $batch->getKey())
                ->where('normalized_email', $group)
                ->where('record_number', '!=', $recordNumber)
                ->get()
                ->all();

            foreach ($models as $model) {
                $other = CandidateImportReviewRow::fromModel($model);
                $excluded = ($other->decision ?? new CandidateImportDecision)->with(
                    CandidateImportDecisionKind::Exclude,
                    duplicateGroup: $group,
                    decidedById: (int) $actor->getKey(),
                );
                $this->write($batch, $other->withDecision($excluded));
            }
        });

        $this->revisions->recordDecision($batch);

        return $this->summarize($batch);
    }

    /**
     * Every row of the batch, decision included, in file order.
     *
     * @return list<CandidateImportReviewRow>
     */
    public function rows(CandidateImportBatch $batch): array
    {
        /** @var list<CandidateImportReviewRow> $rows */
        $rows = [];

        CandidateImportRow::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->orderBy('record_number')
            ->chunkById(self::CHUNK, function ($chunk) use (&$rows): void {
                foreach ($chunk as $model) {
                    $rows[] = CandidateImportReviewRow::fromModel($model);
                }
            });

        return $rows;
    }

    /**
     * Count what confirming this batch would do, and store the answer.
     *
     * The summary is recomputed from the rows themselves every time anything
     * changes rather than adjusted in place. An import screen that quoted a
     * running total kept up to date by hand would eventually quote a number no
     * set of rows produces, and the last screen before a candidate pool
     * changes is the worst possible place for arithmetic nobody can reproduce.
     */
    public function summarize(CandidateImportBatch $batch): CandidateImportSummary
    {
        $rows = $this->rows($batch);
        $summary = $this->summaryOf($batch, $rows);

        $batch->forceFill(['summary' => $summary->toArray()])->save();

        return $summary;
    }

    /**
     * The counts for a set of rows already in hand.
     *
     * @param  list<CandidateImportReviewRow>  $rows
     */
    public function summaryOf(CandidateImportBatch $batch, array $rows): CandidateImportSummary
    {
        $creates = 0;
        $reuses = 0;
        $needsIdentity = 0;
        $invalid = 0;
        $excluded = 0;
        $contactOnly = 0;
        $cvsToAdd = 0;
        $retained = 0;
        $invalidFiles = 0;
        /** @var array<int, true> $used */
        $used = [];

        foreach ($rows as $row) {
            if ($row->hasInvalidFileReference()) {
                $invalidFiles++;
            }

            if ($row->isExcluded()) {
                $excluded++;

                continue;
            }

            if ($row->isBlocked()) {
                $invalid++;
            }

            foreach ($row->outstanding() as $issue) {
                if ($issue->code->concernsIdentity()) {
                    $needsIdentity++;

                    break;
                }
            }

            if (! $row->willImport()) {
                continue;
            }

            if ($row->existingCandidateId() !== null) {
                $reuses++;
                $retained += $row->existingMaterialCount();
            } else {
                $creates++;
            }

            if ($row->isContactOnly()) {
                $contactOnly++;
            }

            if ($row->addsCv() && $row->fileId !== null) {
                $cvsToAdd++;
                $used[$row->fileId] = true;
            }
        }

        $staged = $this->stagedFileIds($batch);
        $unused = count(array_filter($staged, fn (int $id): bool => ! isset($used[$id])));

        return new CandidateImportSummary(
            rows: count($rows),
            ignoredEmptyRecords: CandidateImportSummary::fromArray($batch->summary)->ignoredEmptyRecords,
            creates: $creates,
            reuses: $reuses,
            needsIdentityReview: $needsIdentity,
            invalid: $invalid,
            excluded: $excluded,
            contactOnly: $contactOnly,
            cvsToAdd: $cvsToAdd,
            alreadyRetainedCvs: $retained,
            invalidFileReferences: $invalidFiles,
            unusedFiles: $unused,
        );
    }

    /**
     * Apply one decision to one row, then move the batch on.
     *
     * @param  callable(CandidateImportReviewRow): CandidateImportDecision  $decide
     */
    private function decide(
        CandidateImportBatch $batch,
        int $recordNumber,
        callable $decide,
        bool $dropFile = false,
    ): CandidateImportSummary {
        $this->revisions->assertReviewable($batch);

        DB::transaction(function () use ($batch, $recordNumber, $decide, $dropFile): void {
            $row = $this->row($batch, $recordNumber);
            $this->write($batch, $row->withDecision($decide($row)), $dropFile);
        });

        $this->revisions->recordDecision($batch);

        return $this->summarize($batch);
    }

    /** Read one row of this batch, refusing a record number it does not have. */
    private function row(CandidateImportBatch $batch, int $recordNumber): CandidateImportReviewRow
    {
        $model = CandidateImportRow::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->where('record_number', $recordNumber)
            ->lockForUpdate()
            ->first();

        if ($model === null) {
            throw CandidateImportRefused::make(
                CandidateImportRefusalCode::RowNotFound,
                ['record' => $recordNumber],
                'This import has no record '.$recordNumber.'.',
            );
        }

        return CandidateImportReviewRow::fromModel($model);
    }

    /**
     * Persist a decided row, with `selected` and `status` derived from it.
     *
     * A contact-only row also gives up the upload it was attached to, so the
     * association it no longer uses cannot travel into the manifest and be
     * read there as a CV that was imported.
     */
    private function write(CandidateImportBatch $batch, CandidateImportReviewRow $row, bool $dropFile = false): void
    {
        $decision = $row->decision?->toArray();

        $values = [
            'decision' => $decision === null
                ? null
                : json_encode($decision, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'selected' => $row->resolvedSelected(),
            'status' => $row->resolvedStatus()->value,
            'updated_at' => now(),
        ];

        if ($dropFile || $row->isContactOnly()) {
            $values['file_id'] = null;
        }

        CandidateImportRow::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->where('record_number', $row->recordNumber)
            ->update($values);
    }

    /**
     * The ids of every file still staged against the batch.
     *
     * @return list<int>
     */
    private function stagedFileIds(CandidateImportBatch $batch): array
    {
        /** @var list<int> $ids */
        $ids = CandidateImportFile::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->whereNull('erased_at')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /** Does any row still carry a problem of this code, unanswered? */
    public function outstandingCount(CandidateImportBatch $batch, CandidateImportIssueCode $code): int
    {
        $count = 0;

        foreach ($this->rows($batch) as $row) {
            foreach ($row->outstanding() as $issue) {
                if ($issue->code === $code) {
                    $count++;

                    break;
                }
            }
        }

        return $count;
    }
}
