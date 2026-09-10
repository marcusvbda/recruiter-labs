<?php

namespace App\Services;

use App\Data\CandidateImportCsvReading;
use App\Data\CandidateImportCsvRecord;
use App\Data\CandidateImportDisclosure;
use App\Data\CandidateImportFields;
use App\Data\CandidateImportIdentityLookup;
use App\Data\CandidateImportIssue;
use App\Data\CandidateImportRowPreview;
use App\Data\CandidateImportValidation;
use App\Enums\CandidateImportIdentityAction;
use App\Enums\CandidateImportIssueCode;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportRow;
use Illuminate\Support\Facades\DB;

/**
 * Turns a read CSV into the preview of an import, and persists it as rows.
 *
 * This is the second stage of the import: the reader has already decided what
 * the file's records are, and this decides what each of them means for the
 * destination workspace — which fields are acceptable, which existing candidate
 * the email points at, which records collide with each other, and what the
 * workspace will refuse to change.
 *
 * It creates no candidate and no material. Not one, not even for a row that is
 * perfectly valid and clearly new. Validation runs every time a file is
 * re-uploaded or corrected, the recruiter is entitled to walk away from the
 * preview, and a pool that had already grown by then could not be walked back.
 * Everything written here lives in the batch's own rows and disappears with it.
 *
 * Nothing produced here is a sentence. Issues are codes with context, so the
 * same row can be shown in the preview table, exported into a correction file
 * and quoted in a recovery report, each in the recruiter's language, long after
 * the worker that validated it has exited.
 */
class CandidateImportValidator
{
    /** Rows written per insert, chosen so a full file stays well inside placeholder limits. */
    private const INSERT_CHUNK = 200;

    public function __construct(
        private CandidateImportFieldValidator $fields,
        private CandidateImportIdentityResolver $identities,
    ) {}

    /**
     * Validate a reading against a workspace, without touching the database's
     * candidate pool.
     *
     * `$timezone` is the workspace's displayed timezone; when the caller has
     * none, the application's own timezone decides what "today" means for a
     * received date, which is the only place in this stage where the clock is
     * consulted at all.
     */
    public function validate(
        CandidateImportCsvReading $reading,
        int $companyId,
        string $sourceLabel,
        ?string $timezone = null,
    ): CandidateImportValidation {
        if (! $this->fields->isUsableSourceLabel($sourceLabel)) {
            return CandidateImportValidation::refused([new CandidateImportIssue(
                CandidateImportIssueCode::BatchSourceLabelMissing,
                'source_label',
                ['limit' => CandidateImportFieldValidator::SOURCE_LABEL_LIMIT],
            )]);
        }

        $batchLabel = $this->fields->trimOuter($sourceLabel);
        $zone = $timezone !== null && $timezone !== '' ? $timezone : (string) config('app.timezone');

        /** @var list<array{record: CandidateImportCsvRecord, fields: CandidateImportFields, issues: list<CandidateImportIssue>}> $judged */
        $judged = [];
        /** @var list<string> $emails */
        $emails = [];

        foreach ($reading->records as $record) {
            $result = $this->fields->validate($record, $batchLabel, $zone);
            $judged[] = ['record' => $record, 'fields' => $result['fields'], 'issues' => $result['issues']];
            if ($result['fields']->normalizedEmail !== null) {
                $emails[] = $result['fields']->normalizedEmail;
            }
        }

        $lookup = $this->identities->lookup($companyId, $emails);
        $groups = $this->duplicateGroups($judged);

        $rows = [];

        foreach ($judged as $entry) {
            $rows[] = $this->preview($entry['record'], $entry['fields'], $entry['issues'], $lookup, $groups);
        }

        return new CandidateImportValidation($rows);
    }

    /**
     * Validate a batch's reading and replace its stored rows with the result.
     *
     * The previous rows are dropped first: a re-validated file is a new
     * statement about the same batch, and keeping the rows of an older revision
     * beside it would let the preview show two answers for one record number.
     * The whole replacement happens in one transaction so no reader ever sees a
     * batch with half a preview.
     */
    public function validateBatch(
        CandidateImportBatch $batch,
        CandidateImportCsvReading $reading,
        ?string $timezone = null,
    ): CandidateImportValidation {
        $validation = $this->validate($reading, (int) $batch->company_id, (string) $batch->source_label, $timezone);

        if (! $validation->isValid()) {
            return $validation;
        }

        $this->persist($batch, $validation);

        return $validation;
    }

    /**
     * Write one preview per record, in a single pass.
     *
     * The rows are inserted rather than saved one model at a time: nothing here
     * needs events, and a thousand-row file should cost a handful of statements.
     * Every row carries its company by hand — these tables have no tenant global
     * scope, so a forgotten column would be a workspace boundary lost, not a
     * missing filter.
     */
    public function persist(CandidateImportBatch $batch, CandidateImportValidation $validation): void
    {
        $companyId = (int) $batch->company_id;
        $batchId = (int) $batch->getKey();
        $now = now();

        DB::transaction(function () use ($validation, $companyId, $batchId, $now): void {
            CandidateImportRow::query()
                ->where('company_id', $companyId)
                ->where('batch_id', $batchId)
                ->delete();

            $records = [];

            foreach ($validation->rows as $row) {
                $records[] = [
                    'company_id' => $companyId,
                    'batch_id' => $batchId,
                    'record_number' => $row->recordNumber,
                    'payload' => json_encode($row->payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'normalized_email' => $row->fields->normalizedEmail,
                    'reviewed_candidate_id' => $row->existingCandidateId,
                    'issues' => json_encode($row->issuesToArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'selected' => $row->isPreselected(),
                    'status' => $row->status()->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($records, self::INSERT_CHUNK) as $chunk) {
                CandidateImportRow::query()->insert($chunk);
            }
        });
    }

    /**
     * Everything concluded about one record.
     *
     * @param  list<CandidateImportIssue>  $issues
     * @param  array<string, list<int>>  $groups
     */
    private function preview(
        CandidateImportCsvRecord $record,
        CandidateImportFields $fields,
        array $issues,
        CandidateImportIdentityLookup $lookup,
        array $groups,
    ): CandidateImportRowPreview {
        $email = $fields->normalizedEmail;
        $identity = CandidateImportIdentityAction::Unresolved;
        $existingId = null;
        $materialCount = null;
        $disclosures = [];
        $group = null;
        $others = [];

        if ($email !== null) {
            $members = $groups[$email] ?? [];
            if (count($members) > 1) {
                $group = $email;
                $others = array_values(array_filter($members, fn (int $number): bool => $number !== $record->number));
                $issues[] = new CandidateImportIssue(CandidateImportIssueCode::DuplicateInFile, 'email', [
                    'records' => array_map(fn (int $number): string => (string) $number, $others),
                    'total' => count($members),
                ]);
            }

            $matches = $lookup->matchCount($email);

            if ($matches > 1) {
                $identity = CandidateImportIdentityAction::Ambiguous;
                $issues[] = new CandidateImportIssue(CandidateImportIssueCode::EmailAmbiguous, 'email', ['found' => $matches]);
            } elseif ($matches === 1) {
                $candidate = $lookup->single($email);
                $identity = CandidateImportIdentityAction::Reuse;
                if ($candidate !== null) {
                    $existingId = (int) $candidate->getKey();
                    $materialCount = $lookup->materialCount($existingId);
                    $issues = array_merge($issues, $this->identities->reuseIssues($candidate, $fields, $materialCount));
                    $disclosures = $this->identities->disclosures($candidate, $fields);
                    if ($disclosures !== []) {
                        $issues[] = new CandidateImportIssue(CandidateImportIssueCode::ExistingContactPreserved, null, [
                            'fields' => array_map(
                                fn (CandidateImportDisclosure $disclosure): string => $disclosure->field,
                                $disclosures,
                            ),
                        ]);
                    }
                }
            } else {
                $identity = CandidateImportIdentityAction::Create;
            }
        }

        return new CandidateImportRowPreview(
            recordNumber: $record->number,
            fields: $fields,
            identity: $identity,
            issues: $issues,
            disclosures: $disclosures,
            existingCandidateId: $existingId,
            duplicateGroup: $group,
            duplicateRecordNumbers: $others,
            existingMaterialCount: $materialCount,
            raw: $record->values,
        );
    }

    /**
     * Which records claim the same identity as each other.
     *
     * Every record sharing a normalized email belongs to the same group, and the
     * group is marked even when the rows are byte-for-byte identical: two
     * identical rows are still two claims on one candidate, and importing "the
     * first one" would be the product choosing which CV the candidate keeps.
     * Rows whose email could not be read have no identity to collide with and
     * are left out.
     *
     * @param  list<array{record: CandidateImportCsvRecord, fields: CandidateImportFields, issues: list<CandidateImportIssue>}>  $judged
     * @return array<string, list<int>>
     */
    private function duplicateGroups(array $judged): array
    {
        /** @var array<string, list<int>> $groups */
        $groups = [];

        foreach ($judged as $entry) {
            $email = $entry['fields']->normalizedEmail;
            if ($email === null) {
                continue;
            }
            $groups[$email][] = $entry['record']->number;
        }

        return $groups;
    }
}
