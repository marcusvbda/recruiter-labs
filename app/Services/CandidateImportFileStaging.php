<?php

namespace App\Services;

use App\Data\CandidateImportIssue;
use App\Data\CandidateImportStagedFile;
use App\Data\CandidateImportStaging;
use App\Enums\CandidateImportIssueCode;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Normalizer;
use RuntimeException;
use Throwable;

/**
 * Holds uploaded CVs against a batch, before anyone has decided to import them.
 *
 * The bytes go to the private disk under a generated name inside a folder that
 * belongs to one company and one batch. The recruiter's own filename never
 * becomes a path: it is kept beside the record as `association_name`, a
 * matching key and nothing more, so a name carrying a traversal, a control
 * character or another candidate's identity cannot decide where bytes land.
 *
 * A file that fails inspection is recorded anyway, with the reason. Dropping it
 * silently would leave a row insisting its CV was uploaded and a preview
 * insisting it was not, with nothing in between to explain the disagreement.
 * One unreadable document is not a reason to throw away the ninety-nine that
 * were fine.
 *
 * Nothing here creates a candidate or a candidate material. Staged files are
 * inputs to a preview the recruiter may still walk away from, and they are
 * erased with their batch.
 */
class CandidateImportFileStaging
{
    public function __construct(
        private CandidateCvInspector $inspector,
        private CandidatePrivateFileCleanup $cleanup,
    ) {}

    /**
     * Record every supplied file against the batch.
     *
     * The batch-wide limits are checked against what the batch already holds,
     * not against this call alone: uploading in ten goes of eleven files is the
     * same hundred and ten files as uploading them at once. The boundary itself
     * is accepted — a batch may hold exactly the maximum number of files and
     * exactly the maximum number of bytes, and is refused only past that.
     *
     * @param  list<UploadedFile>  $files
     */
    public function stage(CandidateImportBatch $batch, array $files): CandidateImportStaging
    {
        $companyId = (int) $batch->company_id;
        $batchId = (int) $batch->getKey();

        $held = $this->held($companyId, $batchId);
        $incomingBytes = 0;

        foreach ($files as $file) {
            $incomingBytes += max(0, (int) $file->getSize());
        }

        if ($held['files'] + count($files) > CandidateImportLimits::BATCH_FILES) {
            throw ValidationException::withMessages(['files' => 'An import may hold at most '.CandidateImportLimits::BATCH_FILES.' CV files.']);
        }

        if ($held['bytes'] + $incomingBytes > CandidateImportLimits::BATCH_CV_BYTES) {
            throw ValidationException::withMessages(['files' => 'An import may hold at most 250 MiB of CV files in total.']);
        }

        $staged = [];

        foreach ($files as $file) {
            $staged[] = CandidateImportStagedFile::fromModel($this->record($companyId, $batchId, $file));
        }

        $totals = $this->held($companyId, $batchId);

        return new CandidateImportStaging($staged, $totals['files'], $totals['bytes']);
    }

    /**
     * Erase one staged file, bytes first obligation, row second.
     *
     * The deletion is remembered before the record that points at it goes away,
     * so a crash between the two leaves an obligation the retention scheduler
     * can still discharge rather than an orphaned document nobody knows about.
     */
    public function discard(CandidateImportFile $file): void
    {
        $path = $file->path;

        if ($path !== null && $path !== '') {
            $this->cleanup->run($this->cleanup->remember((int) $file->company_id, $path));
        }

        $file->forceFill(['path' => null, 'erased_at' => now()])->save();
        $file->delete();
    }

    /** Erase every file staged against a batch. */
    public function clear(CandidateImportBatch $batch): void
    {
        CandidateImportFile::query()
            ->where('company_id', (int) $batch->company_id)
            ->where('batch_id', (int) $batch->getKey())
            ->orderBy('id')
            ->chunkById(100, function ($files): void {
                foreach ($files as $file) {
                    $this->discard($file);
                }
            });
    }

    /**
     * What the batch already holds, counted in the database rather than guessed.
     *
     * @return array{files: int, bytes: int}
     */
    public function held(int $companyId, int $batchId): array
    {
        $query = CandidateImportFile::query()
            ->where('company_id', $companyId)
            ->where('batch_id', $batchId);

        return [
            'files' => (int) $query->clone()->count(),
            'bytes' => (int) $query->clone()->sum('size'),
        ];
    }

    /**
     * Inspect one upload and write its record, stored or refused.
     *
     * A refused upload keeps no bytes at all. Its record exists to explain the
     * refusal to the rows that asked for it; retaining the document itself
     * would mean holding a candidate's data the workspace already decided it
     * cannot read.
     */
    private function record(int $companyId, int $batchId, UploadedFile $file): CandidateImportFile
    {
        $association = $this->associationName($file);
        $size = max(0, (int) $file->getSize());
        $refusal = $this->refusal($file, $association);
        $inspection = null;

        if ($refusal === null) {
            try {
                $inspection = $this->inspector->inspect($file);
            } catch (Throwable) {
                $refusal = new CandidateImportIssue(CandidateImportIssueCode::CvFileUnreadable, 'file', ['file' => $association]);
            }
        }

        if ($refusal !== null) {
            return $this->create($companyId, $batchId, [
                'original_name' => $association,
                'association_name' => $association,
                'size' => $size,
                'errors' => ['issues' => [$refusal->toArray()]],
            ]);
        }

        $directory = 'companies/'.$companyId.'/imports/'.$batchId;
        $path = $directory.'/'.Str::uuid()->toString().'.'.$inspection['extension'];

        try {
            $stored = Storage::disk('candidate_materials')->putFileAs($directory, $file, basename($path), ['visibility' => 'private']);
            if ($stored !== $path) {
                throw new RuntimeException('The CV could not be retained.');
            }
        } catch (Throwable) {
            $this->cleanup->run($this->cleanup->remember($companyId, $path));

            return $this->create($companyId, $batchId, [
                'original_name' => $inspection['original_name'],
                'association_name' => $association,
                'extension' => $inspection['extension'],
                'mime_type' => $inspection['mime_type'],
                'size' => $inspection['size'],
                'errors' => ['issues' => [(new CandidateImportIssue(
                    CandidateImportIssueCode::CvFileStorageFailed,
                    'file',
                    ['file' => $association],
                ))->toArray()]],
            ]);
        }

        return $this->create($companyId, $batchId, [
            'path' => $path,
            'original_name' => $inspection['original_name'],
            'association_name' => $association,
            'extension' => $inspection['extension'],
            'mime_type' => $inspection['mime_type'],
            'size' => $inspection['size'],
            'checksum' => $inspection['checksum'],
            'validated_at' => now(),
        ]);
    }

    /**
     * Why this upload is refused before it is even read, if it is.
     *
     * The inspector answers with one refusal for everything it dislikes, which
     * is right for a request but useless to a recruiter holding fifty files. So
     * the cases a person can act on — an empty file, one over the size a CV may
     * be, a format the workspace does not read — are told apart here first, and
     * anything the inspector then refuses is reported as unreadable.
     */
    private function refusal(UploadedFile $file, string $association): ?CandidateImportIssue
    {
        $context = ['file' => $association];
        $size = (int) $file->getSize();

        if ($association === '' || ! $this->isUsableName($association)) {
            return new CandidateImportIssue(CandidateImportIssueCode::CvFileNameInvalid, 'file', $context);
        }
        if (! $file->isValid() || $size <= 0) {
            return new CandidateImportIssue(CandidateImportIssueCode::CvFileEmpty, 'file', $context);
        }
        if ($size > CandidateImportLimits::CV_BYTES) {
            return new CandidateImportIssue(CandidateImportIssueCode::CvFileTooLarge, 'file', [
                ...$context,
                'limit' => CandidateImportLimits::CV_BYTES,
                'size' => $size,
            ]);
        }
        if (! in_array(strtolower(pathinfo($association, PATHINFO_EXTENSION)), ['pdf', 'docx'], true)) {
            return new CandidateImportIssue(CandidateImportIssueCode::CvFileTypeUnsupported, 'file', $context);
        }

        return null;
    }

    /**
     * The client filename reduced to a matching key.
     *
     * Normalizing the Unicode representation and removing outer whitespace is
     * the whole of it. Letter case is preserved, because two files that differ
     * only in case are two different names and the product will not guess which
     * one a row meant.
     */
    private function associationName(UploadedFile $file): string
    {
        $name = $file->getClientOriginalName();
        $normalized = Normalizer::normalize($name, Normalizer::FORM_C);

        return trim(is_string($normalized) ? $normalized : $name);
    }

    private function isUsableName(string $name): bool
    {
        try {
            return $this->inspector->filename($name) === $name;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $attributes */
    private function create(int $companyId, int $batchId, array $attributes): CandidateImportFile
    {
        return CandidateImportFile::query()->create([
            'company_id' => $companyId,
            'batch_id' => $batchId,
            'disk' => 'candidate_materials',
            ...$attributes,
        ]);
    }
}
