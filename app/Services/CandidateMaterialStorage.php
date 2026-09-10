<?php

namespace App\Services;

use App\Enums\CandidateImportStatus;
use App\Exceptions\CandidateImportStagedFileMissing;
use App\Models\Candidate;
use App\Models\CandidateImportBatch;
use App\Models\CandidateImportFile;
use App\Models\CandidateMaterial;
use App\Models\Company;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CandidateMaterialStorage
{
    /** @var list<array{company_id: int, path: string}> */
    private array $pendingPaths = [];

    private int $transactionDepth = 0;

    public function __construct(
        private readonly CandidateCvInspector $inspector,
        private readonly CandidateMaterialMetadata $metadata,
        private readonly CandidateMaterialAccess $access,
        private readonly CandidateMaterialPreparation $preparation,
        private readonly CandidatePrivateFileCleanup $cleanup,
    ) {}

    /**
     * Import execution wraps the entire row here, including candidate creation,
     * retaining its file, and recording success. A failed row cleans its files.
     *
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function atomic(Closure $operation): mixed
    {
        $offset = count($this->pendingPaths);
        $this->transactionDepth++;
        try {
            return DB::transaction($operation);
        } catch (Throwable $exception) {
            if ($this->transactionDepth === 1) {
                foreach (array_slice($this->pendingPaths, $offset) as $pending) {
                    $this->cleanup->run($this->cleanup->remember($pending['company_id'], $pending['path']));
                }
                $this->pendingPaths = array_slice($this->pendingPaths, 0, $offset);
            }
            throw $exception;
        } finally {
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                $this->pendingPaths = [];
            }
        }
    }

    /**
     * The same retention, for bytes that are already staged in this workspace.
     *
     * Import execution never holds an `UploadedFile`: the CV arrived in an
     * earlier request, was inspected then, and has been sitting on the
     * `candidate_materials` disk under a {@see CandidateImportFile} ever
     * since. Rather than give execution a private path into the storage
     * rules, the staged copy is presented as the upload it was and goes
     * through {@see store()} unchanged — same declaration requirement, same
     * inspection, same 20-material ceiling, same checksum duplicate
     * detection, same scheduled preparation, same cleanup of written bytes
     * when the surrounding row throws.
     *
     * A staged file that has been erased or cleaned up is refused here rather
     * than substituted: the row promised a specific document, and importing
     * the candidate while quietly dropping their CV would report a half-kept
     * promise as a kept one.
     *
     * @return array{material: CandidateMaterial, duplicate: bool}
     */
    public function storeStaged(
        Company $company,
        Candidate $candidate,
        User $actor,
        CandidateImportFile $file,
        string $sourceLabel,
        ?string $receivedOn,
        bool $candidateProvidedDeclaration,
        CandidateImportBatch $batch,
        ?string $timezone = null,
    ): array {
        abort_unless($file->company_id === $company->getKey() && $file->batch_id === $batch->getKey(), 404);

        $disk = Storage::disk('candidate_materials');

        if ($file->erased_at !== null || $file->path === null || ! $disk->exists($file->path)) {
            throw new CandidateImportStagedFileMissing((int) $file->getKey());
        }

        // Test mode: the bytes are ours already, so there is no upload to
        // validate and nothing is moved out from under the staged record —
        // putFileAs() streams a copy to the candidate's own directory.
        $upload = new UploadedFile(
            $disk->path($file->path),
            $file->original_name ?? 'cv.'.($file->extension ?? 'pdf'),
            $file->mime_type,
            null,
            true,
        );

        return $this->store($company, $candidate, $actor, $upload, $sourceLabel, $receivedOn, $candidateProvidedDeclaration, $batch, $timezone);
    }

    /** @return array{material: CandidateMaterial, duplicate: bool} */
    public function store(
        Company $company,
        Candidate $candidate,
        User $actor,
        UploadedFile $file,
        string $sourceLabel,
        ?string $receivedOn,
        bool $candidateProvidedDeclaration,
        ?CandidateImportBatch $batch = null,
        ?string $timezone = null,
    ): array {
        abort_unless($candidate->company_id === $company->getKey(), 404);
        if (! $candidateProvidedDeclaration) {
            throw ValidationException::withMessages(['declaration' => 'Confirm these are candidate-provided CVs the workspace is authorized to hold and use for recruitment.']);
        }
        $metadata = $this->metadata->validate($sourceLabel, $receivedOn, $timezone);
        $inspection = $this->inspector->inspect($file);

        return $this->atomic(function () use ($company, $candidate, $actor, $file, $metadata, $inspection, $batch): array {
            $this->access->lockAndAuthorize($actor, $company->getKey());
            $candidate = Candidate::query()->where('company_id', $company->getKey())->whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();
            if ($batch !== null) {
                $batch = CandidateImportBatch::query()->where('company_id', $company->getKey())->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($batch->status === CandidateImportStatus::Processing && $batch->executing_by_id === $actor->getKey()
                    && $batch->confirmed_at !== null && $batch->declaration_at !== null, 409);
            }
            $duplicate = $candidate->materials()->where('company_id', $company->getKey())->where('checksum', $inspection['checksum'])->first();
            if ($duplicate !== null) {
                return ['material' => $duplicate, 'duplicate' => true];
            }
            if ($candidate->materials()->where('company_id', $company->getKey())->count() >= CandidateImportLimits::RETAINED_MATERIALS) {
                throw ValidationException::withMessages(['file' => 'A candidate may retain at most 20 independent CVs, including archived CVs.']);
            }
            $directory = 'companies/'.$company->getKey().'/candidates/'.$candidate->getKey();
            $path = $directory.'/'.Str::uuid().'.'.$inspection['extension'];
            $this->pendingPaths[] = ['company_id' => $company->getKey(), 'path' => $path];
            $stored = Storage::disk('candidate_materials')->putFileAs($directory, $file, basename($path), ['visibility' => 'private']);
            if ($stored !== $path) {
                throw new RuntimeException('The CV could not be retained. Retry this operation.');
            }
            $material = $candidate->materials()->create([
                ...$inspection, ...$metadata,
                'company_id' => $company->getKey(), 'batch_id' => $batch?->getKey(),
                'added_by_id' => $actor->getKey(), 'disk' => 'candidate_materials', 'path' => $path,
                'added_at' => now(), 'declaration_at' => now(),
            ]);
            $this->preparation->schedule($material);

            return ['material' => $material->refresh(), 'duplicate' => false];
        });
    }
}
