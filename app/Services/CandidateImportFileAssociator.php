<?php

namespace App\Services;

use App\Data\CandidateImportAssociation;
use App\Data\CandidateImportCsvReading;
use App\Data\CandidateImportIssue;
use App\Data\CandidateImportRowPreview;
use App\Data\CandidateImportStagedFile;
use App\Data\CandidateImportValidation;
use App\Enums\CandidateImportIssueCode;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportFile;
use App\Models\CandidateImportRow;
use Illuminate\Support\Facades\DB;

/**
 * Decides which uploaded document belongs to which row, and refuses to guess.
 *
 * The whole rule is one sentence: after normalizing the Unicode representation
 * and removing outer whitespace, a row is attached to the upload whose name is
 * exactly the name the row asked for, letter case included. Nothing else is
 * consulted — not the candidate's name, not an email found inside the document,
 * not the order the recruiter selected the files in, not how similar two
 * filenames look. A near miss is a missing file, and it is reported as one.
 *
 * Everything that could make an attachment uncertain blocks the rows it touches
 * and only those rows: two uploads carrying one name, two rows wanting one
 * upload, a document that could not be read. A hundred good rows do not wait on
 * one unreadable PDF, and no candidate ever receives a CV the product had to
 * pick between two answers for.
 *
 * Uploads nobody asked for are counted and named back to the recruiter, and go
 * no further. Attaching them to a candidate inferred from the document would
 * be the product inventing an identity out of a file; that is exactly the
 * inference this stage exists to refuse.
 */
class CandidateImportFileAssociator
{
    /** Rows read and written per statement, so a full batch stays a handful of queries. */
    private const CHUNK = 200;

    /** How many names an unreferenced-uploads notice quotes before it stops listing them. */
    private const NAMES_QUOTED = 20;

    public function __construct(
        private CandidateImportValidator $validator,
    ) {}

    /**
     * Validate a batch's reading and immediately reconcile it with its uploads.
     *
     * The two passes are one act for the caller: a preview that showed field
     * problems now and file problems after the next click would have the
     * recruiter correct the same file twice.
     */
    public function analyzeBatch(
        CandidateImportBatch $batch,
        CandidateImportCsvReading $reading,
        ?string $timezone = null,
    ): CandidateImportAssociation {
        $validation = $this->validator->validateBatch($batch, $reading, $timezone);

        if (! $validation->isValid()) {
            return new CandidateImportAssociation($validation);
        }

        $association = $this->analyze($batch, $validation);
        $this->persist($batch, $association);

        return $association;
    }

    /** Reconcile an existing preview with whatever the batch currently holds. */
    public function analyze(CandidateImportBatch $batch, CandidateImportValidation $validation): CandidateImportAssociation
    {
        return $this->associate($validation, $this->staged($batch));
    }

    /**
     * The association pass itself, over data alone.
     *
     * @param  list<CandidateImportStagedFile>  $files
     */
    public function associate(CandidateImportValidation $validation, array $files): CandidateImportAssociation
    {
        $byName = $this->byName($files);
        $requests = $this->requests($validation->rows);

        /** @var array<int, list<CandidateImportIssue>> $added */
        $added = [];
        /** @var array<int, int> $fileIds */
        $fileIds = [];
        /** @var array<string, list<array{record: int, identity: string, file: string}>> $byChecksum */
        $byChecksum = [];

        foreach ($validation->rows as $row) {
            $name = $row->fields->cvFilename;

            if ($name === null) {
                continue;
            }

            $matches = $byName[$name] ?? [];
            $number = $row->recordNumber;
            $added[$number] ??= [];

            if ($matches === []) {
                $added[$number][] = new CandidateImportIssue(
                    CandidateImportIssueCode::CvFileMissing,
                    'cv_filename',
                    ['file' => $name],
                );

                continue;
            }

            if (count($matches) > 1) {
                $added[$number][] = new CandidateImportIssue(
                    CandidateImportIssueCode::CvFileAmbiguous,
                    'cv_filename',
                    ['file' => $name, 'found' => count($matches)],
                );

                continue;
            }

            $file = $matches[0];
            $claimants = $requests[$name] ?? [];
            $shared = count($claimants) > 1;

            if ($shared) {
                $added[$number][] = new CandidateImportIssue(
                    CandidateImportIssueCode::CvFileSharedByRows,
                    'cv_filename',
                    [
                        'file' => $name,
                        'records' => array_map(
                            fn (int $other): string => (string) $other,
                            array_values(array_filter($claimants, fn (int $other): bool => $other !== $number)),
                        ),
                        'total' => count($claimants),
                    ],
                );
            }

            if (! $file->isUsable()) {
                $added[$number][] = new CandidateImportIssue(
                    CandidateImportIssueCode::CvFileRejected,
                    'cv_filename',
                    [
                        'file' => $name,
                        'reason' => $file->rejection()?->code->value ?? CandidateImportIssueCode::CvFileUnreadable->value,
                    ],
                );

                continue;
            }

            if ($shared) {
                continue;
            }

            $fileIds[$number] = $file->id;

            if ($file->checksum !== null) {
                $byChecksum[$file->checksum][] = [
                    'record' => $number,
                    'identity' => $row->fields->normalizedEmail ?? 'record:'.$number,
                    'file' => $name,
                ];
            }
        }

        foreach ($this->repeatedContents($byChecksum) as $number => $issue) {
            $added[$number][] = $issue;
        }

        $rows = array_map(
            fn (CandidateImportRowPreview $row): CandidateImportRowPreview => $row->withIssues($added[$row->recordNumber] ?? []),
            $validation->rows,
        );

        $unreferenced = $this->unreferenced($files, $requests);

        return new CandidateImportAssociation(
            validation: new CandidateImportValidation($rows, $validation->errors),
            files: $files,
            fileIds: $fileIds,
            notices: $this->notices($byName, $unreferenced),
            unreferenced: $unreferenced,
        );
    }

    /**
     * Write the association back onto the batch's rows.
     *
     * Only what the file pass changed is written: the issues the row now
     * carries, the status and preselection those issues imply, and the upload
     * the row is entitled to. Rows are updated in groups that share the same
     * outcome, so a thousand-row batch costs a handful of statements rather
     * than a thousand.
     */
    public function persist(CandidateImportBatch $batch, CandidateImportAssociation $association): void
    {
        $companyId = (int) $batch->company_id;
        $batchId = (int) $batch->getKey();

        /** @var array<string, array{values: array<string, mixed>, records: list<int>}> $groups */
        $groups = [];

        foreach ($association->rows() as $row) {
            $values = [
                'issues' => json_encode($row->issuesToArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => $row->status()->value,
                'selected' => $row->isPreselected(),
                'file_id' => $association->fileIdFor($row->recordNumber),
            ];
            $key = md5((string) json_encode($values));
            $groups[$key] ??= ['values' => $values, 'records' => []];
            $groups[$key]['records'][] = $row->recordNumber;
        }

        DB::transaction(function () use ($groups, $companyId, $batchId): void {
            foreach ($groups as $group) {
                foreach (array_chunk($group['records'], self::CHUNK) as $chunk) {
                    CandidateImportRow::query()
                        ->where('company_id', $companyId)
                        ->where('batch_id', $batchId)
                        ->whereIn('record_number', $chunk)
                        ->update([...$group['values'], 'updated_at' => now()]);
                }
            }
        });
    }

    /**
     * Every file currently staged against the batch, read in chunks.
     *
     * @return list<CandidateImportStagedFile>
     */
    public function staged(CandidateImportBatch $batch): array
    {
        /** @var list<CandidateImportStagedFile> $files */
        $files = [];

        CandidateImportFile::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->whereNull('erased_at')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($chunk) use (&$files): void {
                foreach ($chunk as $file) {
                    $files[] = CandidateImportStagedFile::fromModel($file);
                }
            });

        return $files;
    }

    /**
     * Uploads grouped by the exact name they answer to.
     *
     * @param  list<CandidateImportStagedFile>  $files
     * @return array<string, list<CandidateImportStagedFile>>
     */
    private function byName(array $files): array
    {
        /** @var array<string, list<CandidateImportStagedFile>> $byName */
        $byName = [];

        foreach ($files as $file) {
            if ($file->associationName === '') {
                continue;
            }
            $byName[$file->associationName][] = $file;
        }

        return $byName;
    }

    /**
     * Which records ask for each filename.
     *
     * @param  list<CandidateImportRowPreview>  $rows
     * @return array<string, list<int>>
     */
    private function requests(array $rows): array
    {
        /** @var array<string, list<int>> $requests */
        $requests = [];

        foreach ($rows as $row) {
            $name = $row->fields->cvFilename;
            if ($name === null) {
                continue;
            }
            $requests[$name][] = $row->recordNumber;
        }

        return $requests;
    }

    /**
     * The same document handed to two different people.
     *
     * Byte-identical uploads under different names are not an error by
     * themselves — a recruiter may well export the same CV twice. They stop
     * being harmless the moment they are claimed by rows that are not the same
     * candidate, because one of those two candidates is then about to receive
     * somebody else's CV. The product will not decide which; it says so and
     * waits.
     *
     * @param  array<string, list<array{record: int, identity: string, file: string}>>  $byChecksum
     * @return array<int, CandidateImportIssue>
     */
    private function repeatedContents(array $byChecksum): array
    {
        /** @var array<int, CandidateImportIssue> $issues */
        $issues = [];

        foreach ($byChecksum as $claims) {
            if (count($claims) < 2) {
                continue;
            }

            $identities = array_unique(array_map(fn (array $claim): string => $claim['identity'], $claims));

            if (count($identities) < 2) {
                continue;
            }

            foreach ($claims as $claim) {
                $others = array_values(array_filter(
                    $claims,
                    fn (array $other): bool => $other['record'] !== $claim['record'],
                ));

                $issues[$claim['record']] = new CandidateImportIssue(
                    CandidateImportIssueCode::CvFileContentsRepeated,
                    'cv_filename',
                    [
                        'file' => $claim['file'],
                        'records' => array_map(fn (array $other): string => (string) $other['record'], $others),
                        'total' => count($claims),
                    ],
                );
            }
        }

        return $issues;
    }

    /**
     * Names of uploads no row asks for.
     *
     * @param  list<CandidateImportStagedFile>  $files
     * @param  array<string, list<int>>  $requests
     * @return list<string>
     */
    private function unreferenced(array $files, array $requests): array
    {
        /** @var list<string> $names */
        $names = [];

        foreach ($files as $file) {
            if (isset($requests[$file->associationName])) {
                continue;
            }
            $names[] = $file->associationName;
        }

        return $names;
    }

    /**
     * What the batch as a whole has to be told, owned by no single row.
     *
     * @param  array<string, list<CandidateImportStagedFile>>  $byName
     * @param  list<string>  $unreferenced
     * @return list<CandidateImportIssue>
     */
    private function notices(array $byName, array $unreferenced): array
    {
        /** @var list<CandidateImportIssue> $notices */
        $notices = [];

        foreach ($byName as $name => $files) {
            if (count($files) > 1) {
                $notices[] = new CandidateImportIssue(
                    CandidateImportIssueCode::CvFileAmbiguous,
                    'files',
                    ['file' => (string) $name, 'found' => count($files)],
                );
            }
        }

        if ($unreferenced !== []) {
            $notices[] = new CandidateImportIssue(
                CandidateImportIssueCode::CvFilesUnreferenced,
                'files',
                [
                    'total' => count($unreferenced),
                    'files' => array_slice($unreferenced, 0, self::NAMES_QUOTED),
                ],
            );
        }

        return $notices;
    }
}
