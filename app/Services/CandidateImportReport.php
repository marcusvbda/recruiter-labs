<?php

namespace App\Services;

use App\Data\CandidateImportDecision;
use App\Data\CandidateImportProgress;
use App\Enums\CandidateImportCandidateOutcome as Outcome;
use App\Enums\CandidateImportCsvMode;
use App\Enums\CandidateImportCsvSeparator;
use App\Enums\CandidateImportFailureCode as FailureCode;
use App\Enums\CandidateImportIssueCode;
use App\Enums\CandidateImportIssueSeverity;
use App\Enums\CandidateImportMaterialOutcome as MaterialOutcome;
use App\Enums\CandidateImportRowStatus as RowStatus;
use App\Models\Candidate;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportRow;
use Illuminate\Support\Collection;

/**
 * The two files a finished import can be read back out of.
 *
 * An import is only trustworthy if the recruiter can see, afterwards, what it
 * did to each of their rows — and can fix the rows it refused without retyping
 * the ones it accepted. Those are two different documents on purpose:
 *
 * - **The report** is the record. One line per row of the original file, what
 *   happened to the person and to their CV, and why anything was left out. It
 *   is never re-uploaded, so it may say things the importer would not accept.
 * - **The correction file** is work. Only the rows that still need fixing, in
 *   the template's own seven columns, protected exactly the way
 *   {@see CandidateImportCsvReader} decodes a correction file — so it can be
 *   edited and uploaded straight back.
 *
 * Neither file reconstructs anything. A row whose result was erased, and a row
 * whose detail retention has already cleared, are reported as no longer
 * available rather than rebuilt from the candidate they once produced: the
 * whole point of erasure is that the import stops being a second copy of the
 * person. For the same reason the correction file simply omits those rows —
 * there is nothing honest to put in their cells.
 *
 * Both files are generated on demand and held in memory. An import carries at
 * most {@see CandidateImportLimits::ROWS} rows, so a report is a few hundred
 * kilobytes at worst, and generating it per request means it is never a stale
 * artefact sitting on a disk describing a workspace that has since changed.
 */
class CandidateImportReport
{
    /** Columns of the result report, in reading order. */
    public const REPORT_HEADERS = [
        'record', 'name', 'email', 'candidate_result', 'cv_result', 'candidate_id', 'reason',
    ];

    /**
     * What a cell says when the detail behind it is legitimately gone.
     *
     * One phrase for both erasure and expiry, because from the reader's side
     * they are the same fact: this file will not be telling them. Which of the
     * two happened is in the row's reason.
     */
    public const NOT_AVAILABLE = 'not available';

    /**
     * Leading characters a spreadsheet may read as something other than text.
     *
     * `=`, `+`, `-` and `@` open a formula in Excel, Sheets and LibreOffice,
     * and a leading tab or line break lets a value slip past a naive check and
     * start one anyway. A candidate's phone number legitimately starts with
     * `+`, so the answer is never to drop or alter the value.
     */
    private const RISKY_LEADING = ['=', '+', '-', '@', "\t", "\r", "\n"];

    private const BOM = "\xEF\xBB\xBF";

    /**
     * The private result report for one batch, as CSV text.
     *
     * Generatable at every stage of a batch's life, including after retention
     * has cleared the rows' detail: a download that failed once the detail
     * expired would take the record away exactly when the recruiter went
     * looking for it. What it never does is invent the part that is gone.
     */
    public function report(CandidateImportBatch $batch): string
    {
        $rows = $this->rows($batch);
        $accessible = $this->accessibleCandidateIds($batch, $rows);
        $lines = [$this->line(self::REPORT_HEADERS, ',', false)];

        foreach ($rows as $row) {
            $lines[] = $this->line($this->reportCells($row, $accessible), ',', true);
        }

        if ($rows->isEmpty()) {
            $lines[] = $this->line($this->summaryOnlyCells($batch), ',', true);
        }

        return self::BOM.implode("\r\n", $lines)."\r\n";
    }

    /**
     * The correction file for one batch, as CSV text.
     *
     * Only the rows that still need a human to change something in the file:
     * failures, rows waiting on review, and rows excluded because they were
     * refused as supplied. A row the reviewer deliberately dropped — the
     * duplicate they did not pick, the person they did not want — is not a
     * correction and is not here; the report already explains it.
     *
     * The output is the exact inverse of the reader's correction decoding, so
     * a downloaded file that is edited and uploaded again in correction mode
     * yields the values this one started from.
     */
    public function correctionFile(CandidateImportBatch $batch): string
    {
        $separator = (CandidateImportCsvSeparator::tryFrom($batch->separator) ?? CandidateImportCsvSeparator::Comma)->value;
        $lines = [$this->line(CandidateImportCsvReader::HEADERS, $separator, false)];

        foreach ($this->rows($batch) as $row) {
            if (! $this->needsCorrection($row)) {
                continue;
            }

            $lines[] = $this->line($this->correctionCells($row), $separator, false);
        }

        return self::BOM.implode("\r\n", $lines)."\r\n";
    }

    /** Are there any rows worth exporting for correction at all? */
    public function hasCorrections(CandidateImportBatch $batch): bool
    {
        foreach ($this->rows($batch) as $row) {
            if ($this->needsCorrection($row)) {
                return true;
            }
        }

        return false;
    }

    public function reportFilename(CandidateImportBatch $batch): string
    {
        return 'candidate-import-'.((int) $batch->getKey()).'-report.csv';
    }

    public function correctionFilename(CandidateImportBatch $batch): string
    {
        return 'candidate-import-'.((int) $batch->getKey()).'-corrections.csv';
    }

    /**
     * Every row of the batch, in the order the uploaded file had them.
     *
     * Unselected rows are included: "you left this one out" is a result the
     * recruiter is owed, and it is the only place the reason for an exclusion
     * is written down.
     *
     * @return Collection<int, CandidateImportRow>
     */
    private function rows(CandidateImportBatch $batch): Collection
    {
        return CandidateImportRow::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->orderBy('record_number')
            ->get();
    }

    /**
     * Which of the referenced candidates may still be pointed at.
     *
     * A reference to a candidate that has been deleted is worse than no
     * reference: it sends the reader looking for somebody who is not there.
     * The existence check is one query for the whole report, scoped to the
     * workspace like every other read in this feature.
     *
     * @param  Collection<int, CandidateImportRow>  $rows
     * @return array<int, true>
     */
    private function accessibleCandidateIds(CandidateImportBatch $batch, Collection $rows): array
    {
        $ids = $rows
            ->filter(fn (CandidateImportRow $row): bool => $row->candidate_id !== null && $row->result_erased_at === null)
            ->map(fn (CandidateImportRow $row): int => (int) $row->candidate_id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $existing = Candidate::query()
            ->where('company_id', (int) $batch->company_id)
            ->whereIn('id', $ids)
            ->pluck('id');

        /** @var array<int, true> $accessible */
        $accessible = [];

        foreach ($existing as $id) {
            $accessible[(int) $id] = true;
        }

        return $accessible;
    }

    /**
     * One report line's cells, before spreadsheet neutralization.
     *
     * @param  array<int, true>  $accessible
     * @return list<string>
     */
    private function reportCells(CandidateImportRow $row, array $accessible): array
    {
        $erased = $row->result_erased_at !== null;
        $fields = $this->fields($row);
        $known = ! $erased && $fields !== null;
        $candidateId = (int) ($row->candidate_id ?? 0);
        $reference = ! $erased && $candidateId > 0 && isset($accessible[$candidateId]) ? (string) $candidateId : '';

        return [
            (string) $row->record_number,
            $known ? $this->text($fields['name'] ?? null) : self::NOT_AVAILABLE,
            $known ? $this->text($fields['email'] ?? null) : self::NOT_AVAILABLE,
            $this->candidateResult($row),
            $this->materialResult($row),
            $reference,
            $this->reason($row, $known),
        ];
    }

    /**
     * The one line a report falls back to when it has no rows to describe.
     *
     * Retention may eventually take the rows themselves. What survives is the
     * batch's own summary, which is a count and nothing else — so that is
     * exactly what is said, with no per-row detail implied.
     *
     * @return list<string>
     */
    private function summaryOnlyCells(CandidateImportBatch $batch): array
    {
        $progress = CandidateImportProgress::fromArray(is_array($batch->summary['progress'] ?? null)
            ? $batch->summary['progress']
            : null);

        $summary = 'Row detail is no longer retained for this import. Retained summary: '
            .$progress->selected.' selected, '
            .$progress->created.' created, '
            .$progress->reused.' reused, '
            .$progress->unchanged.' unchanged, '
            .$progress->needsReview.' needing review, '
            .$progress->failed.' failed, '
            .$progress->erased.' erased.';

        return ['', self::NOT_AVAILABLE, self::NOT_AVAILABLE, '', '', '', $summary];
    }

    /**
     * The stored pre-confirmation values of one row, or null when gone.
     *
     * `payload` is the preview the recruiter confirmed, which is the only
     * place this feature keeps the name and email as the file supplied them.
     * Erasure nulls it, and retention may clear it too, so its absence is a
     * normal state to report rather than a defect to work around.
     *
     * @return array<string, string|null>|null
     */
    private function fields(CandidateImportRow $row): ?array
    {
        $payload = $row->payload;

        if ($row->result_erased_at !== null || ! is_array($payload)) {
            return null;
        }

        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : null;
        $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : null;

        if ($fields === null && $raw === null) {
            return null;
        }

        /** @var array<string, string|null> $values */
        $values = [];

        foreach (CandidateImportCsvReader::HEADERS as $header) {
            $value = $fields[$header] ?? $raw[$header] ?? null;
            $values[$header] = is_string($value) && $value !== '' ? $value : null;
        }

        return $values;
    }

    /**
     * The cells the file originally held for one row, for re-upload.
     *
     * The raw cells are preferred over the validated fields: a row is in this
     * file because something about it was refused, and the refused value is
     * what the recruiter needs to see in order to fix it. Validation drops it,
     * so reading only the fields would hand back an empty cell where the
     * mistake was.
     *
     * @return list<string>
     */
    private function correctionCells(CandidateImportRow $row): array
    {
        $payload = is_array($row->payload) ? $row->payload : [];
        $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $cells = [];

        foreach (CandidateImportCsvReader::HEADERS as $header) {
            $value = $raw[$header] ?? $fields[$header] ?? null;
            $cells[] = $this->protect(is_string($value) ? $value : '');
        }

        return $cells;
    }

    /**
     * Does this row belong in the correction file?
     *
     * Three things are being told apart here, and only the first two are
     * corrections:
     *
     * - A row that **failed** or **needs review** — execution or validation
     *   could not finish it, so the file has to change.
     * - A row that was **excluded because it was refused as supplied**: it
     *   carries at least one blocking issue, meaning no decision could have
     *   made it importable.
     * - A row the reviewer **chose** to drop: the duplicate they did not pick,
     *   or a person they did not want. Its only issues are notices about that
     *   choice, never blocking ones, and it is deliberately absent from here.
     *
     * Blocking severity is the discriminator rather than the decision, because
     * excluding a row resolves every question about it — including the ones
     * that a corrected file, not a decision, has to answer.
     */
    private function needsCorrection(CandidateImportRow $row): bool
    {
        if ($row->result_erased_at !== null || $row->committed_at !== null || ! is_array($row->payload)) {
            return false;
        }

        if ($row->status === RowStatus::Failed || $row->status === RowStatus::NeedsReview) {
            return true;
        }

        return $row->status === RowStatus::Excluded && $this->hasBlockingIssue($row);
    }

    private function hasBlockingIssue(CandidateImportRow $row): bool
    {
        return $this->issueCodes($row, CandidateImportIssueSeverity::Blocking) !== [];
    }

    /**
     * The row's stored issue codes, optionally of one severity only.
     *
     * @return list<CandidateImportIssueCode>
     */
    private function issueCodes(CandidateImportRow $row, ?CandidateImportIssueSeverity $severity = null): array
    {
        $codes = [];

        foreach (is_array($row->issues) ? $row->issues : [] as $issue) {
            $value = is_array($issue) ? ($issue['code'] ?? null) : null;
            $code = is_string($value) ? CandidateImportIssueCode::tryFrom($value) : null;

            if ($code === null || in_array($code, $codes, true)) {
                continue;
            }

            if ($severity !== null && $code->severity() !== $severity) {
                continue;
            }

            $codes[] = $code;
        }

        return $codes;
    }

    /** What happened to the person behind the row, in words. */
    private function candidateResult(CandidateImportRow $row): string
    {
        if ($row->result_erased_at !== null) {
            return 'Imported, then deleted from the workspace';
        }

        return match (Outcome::tryFrom((string) $row->candidate_outcome)) {
            Outcome::Created => 'Candidate created',
            Outcome::Reused => 'Existing candidate reused',
            Outcome::Unchanged => 'Existing candidate left unchanged',
            Outcome::Excluded => 'Left out of the import',
            Outcome::NeedsReview => 'Needs review',
            Outcome::Failed => 'Not imported',
            null => match ($row->status) {
                RowStatus::Excluded => 'Left out of the import',
                RowStatus::NeedsReview => 'Needs review',
                RowStatus::Failed => 'Not imported',
                RowStatus::Succeeded => 'Imported',
                RowStatus::Pending => 'Not processed',
            },
        };
    }

    /** What happened to the document the row referenced, in words. */
    private function materialResult(CandidateImportRow $row): string
    {
        if ($row->result_erased_at !== null) {
            return self::NOT_AVAILABLE;
        }

        return match (MaterialOutcome::tryFrom((string) $row->material_outcome)) {
            MaterialOutcome::None => 'No CV',
            MaterialOutcome::Retained => 'CV retained',
            MaterialOutcome::DuplicateSkipped => 'CV already held, not stored again',
            MaterialOutcome::Failed => 'CV could not be retained',
            null => '',
        };
    }

    /**
     * Why the row did not simply import, in one short phrase.
     *
     * A failure states its code's meaning; an exclusion states whether the
     * reviewer dropped it or the file refused it; a successful row says
     * nothing, because there is nothing to explain.
     */
    private function reason(CandidateImportRow $row, bool $known): string
    {
        if ($row->result_erased_at !== null) {
            return 'The result of this row was deleted from the workspace, and its detail is no longer available.';
        }

        if (! $known) {
            return 'Row detail is no longer retained for this import.';
        }

        $failure = FailureCode::tryFrom((string) $row->failure_code);

        if ($failure !== null) {
            return $this->failureReason($failure);
        }

        if ($row->status === RowStatus::Excluded) {
            return $this->exclusionReason($row);
        }

        if ($row->status === RowStatus::NeedsReview) {
            $codes = $this->issueCodes($row);

            return $codes === []
                ? 'This row is waiting on a review decision.'
                : 'Waiting on the file or a decision: '.$this->codeList($codes);
        }

        return '';
    }

    private function failureReason(FailureCode $code): string
    {
        return match ($code) {
            FailureCode::ManifestMissing => 'The confirmed instructions for this row are missing, so nothing was applied.',
            FailureCode::IdentityMissing => 'The candidate this row was matched to no longer exists in the workspace.',
            FailureCode::IdentityChanged => 'The matched candidate changed after confirmation, so the row was not applied.',
            FailureCode::IdentityTaken => 'This email now belongs to an existing candidate, so no new candidate was created.',
            FailureCode::MaterialUnavailable => 'The uploaded CV for this row was no longer available when the import ran.',
            FailureCode::MaterialRejected => 'The candidate already holds the maximum number of retained CVs.',
            FailureCode::RowFailed => 'This row could not be applied, and nothing of it was kept.',
            FailureCode::AccessLost => 'The import stopped because workspace access was lost before this row ran.',
            FailureCode::ExecutionFailed => 'The import itself stopped before this row could be applied.',
            FailureCode::ExecutionStalled => 'The import stopped making progress before this row could be applied.',
            FailureCode::ResultErased => 'The result of this row was deleted from the workspace.',
        };
    }

    private function exclusionReason(CandidateImportRow $row): string
    {
        $blocking = $this->issueCodes($row, CandidateImportIssueSeverity::Blocking);

        if ($blocking !== []) {
            return 'Refused as supplied: '.$this->codeList($blocking);
        }

        $decision = CandidateImportDecision::fromArray($row->decision);

        if ($decision !== null && $decision->duplicateGroup !== null && $decision->isExcluded()) {
            return 'Left out by the reviewer: another record of the same duplicate group was kept.';
        }

        if ($decision !== null && $decision->isExcluded()) {
            return 'Left out by the reviewer.';
        }

        return 'Left out of the import.';
    }

    /** @param list<CandidateImportIssueCode> $codes */
    private function codeList(array $codes): string
    {
        return implode(', ', array_map(fn (CandidateImportIssueCode $code): string => $code->value, $codes));
    }

    private function text(?string $value): string
    {
        return $value ?? '';
    }

    /**
     * One CSV record.
     *
     * @param  list<string>  $cells
     * @param  bool  $neutralize  Whether these cells are human-facing report text.
     */
    private function line(array $cells, string $separator, bool $neutralize): string
    {
        $encoded = array_map(
            fn (string $cell): string => $this->quote($neutralize ? $this->neutralize($cell) : $cell, $separator),
            $cells,
        );

        return implode($separator, $encoded);
    }

    /**
     * Make a report cell inert in a spreadsheet.
     *
     * Quoting is not protection: `"=1+1"` is still a formula to Excel, and a
     * cell beginning with `=`, `+`, `-`, `@` or whitespace that hides one is
     * how a candidate's own name becomes a command on the recruiter's machine.
     * A single leading apostrophe forces the whole cell to be read as text,
     * and is removed by the spreadsheet on display.
     *
     * This is export-time only. Nothing stored changes, and nothing here is
     * read back: the report is a document, never an input. The correction
     * file's marker in {@see protect()} looks identical and means something
     * else entirely — it is a transport convention the reader undoes — so the
     * two are kept apart deliberately.
     */
    private function neutralize(string $value): string
    {
        if ($value === '') {
            return '';
        }

        foreach (self::RISKY_LEADING as $character) {
            if (str_starts_with($value, $character)) {
                return CandidateImportCsvMode::PROTECTION_MARKER.$value;
            }
        }

        return $value;
    }

    /**
     * Add the correction file's protection marker to one cell.
     *
     * Exactly one leading apostrophe on every nonempty cell, and nothing at
     * all on an empty one — the precise inverse of the reader's decoding, and
     * the reason a value may safely begin with `'` itself: it leaves here with
     * two and arrives back with one. Headers never carry the marker.
     */
    private function protect(string $value): string
    {
        return $value === '' ? '' : CandidateImportCsvMode::PROTECTION_MARKER.$value;
    }

    /**
     * Quote a cell so the reader gets back exactly this string.
     *
     * A separator, a quote or a line break inside a value only survives inside
     * quotes, and a leading or trailing space is quoted too so that no
     * spreadsheet trims a value the recruiter typed deliberately.
     */
    private function quote(string $value, string $separator): string
    {
        $needs = $value !== trim($value)
            || str_contains($value, $separator)
            || str_contains($value, '"')
            || str_contains($value, "\n")
            || str_contains($value, "\r");

        return $needs ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
