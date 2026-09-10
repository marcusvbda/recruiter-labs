<?php

namespace App\Data;

use App\Enums\CandidateImportCandidateOutcome as Outcome;
use App\Enums\CandidateImportMaterialOutcome as MaterialOutcome;
use App\Enums\CandidateImportRowStatus;
use App\Models\CandidateImportRow;

/**
 * How far a running import has actually got, counted from what it committed.
 *
 * Every number here is measured from the rows themselves rather than
 * accumulated as the worker goes. That is the only counting that survives the
 * things this feature has to survive: a worker that died mid-batch and is
 * resumed, a job the queue delivered twice, a row retried after a fix, and a
 * recruiter who closed the tab and came back tomorrow. An incremented counter
 * would drift on every one of those; a recount cannot.
 *
 * It also means the numbers describe results, never attempts. A row tried
 * three times and committed once is one candidate, and a row tried once and
 * rolled back is not a candidate the workspace can look for.
 */
final readonly class CandidateImportProgress
{
    public function __construct(
        /** Rows the confirmed manifest covers. */
        public int $selected = 0,
        /** Selected rows that reached a terminal result, successful or not. */
        public int $processed = 0,
        /** Candidates this import added to the workspace. */
        public int $created = 0,
        /** Existing candidates that gained a CV from this import. */
        public int $reused = 0,
        /** Existing candidates the import deliberately left as they were. */
        public int $unchanged = 0,
        /** CVs now retained that were not before. */
        public int $cvsRetained = 0,
        /** CVs skipped because the candidate already held those exact contents. */
        public int $cvsDuplicate = 0,
        /** Rows waiting on a human because the workspace changed under them. */
        public int $needsReview = 0,
        /** Rows whose import could not complete. */
        public int $failed = 0,
        /** Rows whose committed result has since been deleted from the workspace. */
        public int $erased = 0,
    ) {}

    /** Selected rows with no terminal result yet. */
    public function remaining(): int
    {
        return max(0, $this->selected - $this->processed);
    }

    /** Rows that finished but did not import: the correction list, in short. */
    public function unresolved(): int
    {
        return $this->needsReview + $this->failed;
    }

    public function finished(): bool
    {
        return $this->remaining() === 0;
    }

    /** Recount from the rows on file. Cheap: an import carries at most 1000. */
    public static function measure(int $companyId, int $batchId): self
    {
        $rows = CandidateImportRow::query()
            ->where('company_id', $companyId)
            ->where('batch_id', $batchId)
            ->where('selected', true)
            ->get(['status', 'candidate_outcome', 'material_outcome', 'committed_at', 'result_erased_at']);

        $counts = ['selected' => 0, 'processed' => 0, 'created' => 0, 'reused' => 0, 'unchanged' => 0,
            'cvsRetained' => 0, 'cvsDuplicate' => 0, 'needsReview' => 0, 'failed' => 0, 'erased' => 0];

        foreach ($rows as $row) {
            $counts['selected']++;
            $committed = $row->committed_at !== null;
            $erased = $row->result_erased_at !== null;

            if ($erased) {
                $counts['erased']++;
            }

            if ($committed) {
                $counts['processed']++;
                // An erased row committed once and its result is gone. It is
                // not undone — the import did happen — but it is no longer
                // one of the candidates or CVs the workspace holds, so it
                // stops being counted as one.
                if (! $erased) {
                    match (Outcome::tryFrom((string) $row->candidate_outcome)) {
                        Outcome::Created => $counts['created']++,
                        Outcome::Reused => $counts['reused']++,
                        Outcome::Unchanged => $counts['unchanged']++,
                        default => null,
                    };
                    match (MaterialOutcome::tryFrom((string) $row->material_outcome)) {
                        MaterialOutcome::Retained => $counts['cvsRetained']++,
                        MaterialOutcome::DuplicateSkipped => $counts['cvsDuplicate']++,
                        default => null,
                    };
                }

                continue;
            }

            if ($row->status === CandidateImportRowStatus::NeedsReview || $erased) {
                $counts['processed']++;
                $counts['needsReview']++;
            } elseif ($row->status === CandidateImportRowStatus::Failed) {
                $counts['processed']++;
                $counts['failed']++;
            }
        }

        return new self(
            selected: $counts['selected'],
            processed: $counts['processed'],
            created: $counts['created'],
            reused: $counts['reused'],
            unchanged: $counts['unchanged'],
            cvsRetained: $counts['cvsRetained'],
            cvsDuplicate: $counts['cvsDuplicate'],
            needsReview: $counts['needsReview'],
            failed: $counts['failed'],
            erased: $counts['erased'],
        );
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'selected' => $this->selected,
            'processed' => $this->processed,
            'remaining' => $this->remaining(),
            'created' => $this->created,
            'reused' => $this->reused,
            'unchanged' => $this->unchanged,
            'cvs_retained' => $this->cvsRetained,
            'cvs_duplicate' => $this->cvsDuplicate,
            'needs_review' => $this->needsReview,
            'failed' => $this->failed,
            'erased' => $this->erased,
        ];
    }

    /** @param array<string, mixed>|null $stored */
    public static function fromArray(?array $stored): self
    {
        $stored ??= [];
        $read = static fn (string $key): int => is_numeric($stored[$key] ?? null) ? (int) $stored[$key] : 0;

        return new self(
            selected: $read('selected'),
            processed: $read('processed'),
            created: $read('created'),
            reused: $read('reused'),
            unchanged: $read('unchanged'),
            cvsRetained: $read('cvs_retained'),
            cvsDuplicate: $read('cvs_duplicate'),
            needsReview: $read('needs_review'),
            failed: $read('failed'),
            erased: $read('erased'),
        );
    }
}
