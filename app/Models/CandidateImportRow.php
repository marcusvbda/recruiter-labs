<?php

namespace App\Models;

use App\Enums\CandidateImportRowStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $batch_id
 * @property int $record_number
 * @property array<string, mixed>|null $payload
 * @property string|null $normalized_email
 * @property int|null $reviewed_candidate_id
 * @property int|null $candidate_id
 * @property int|null $material_id
 * @property int|null $file_id
 * @property array<string, mixed>|null $issues
 * @property array<string, mixed>|null $decision
 * @property array<string, mixed>|null $manifest
 * @property bool $selected
 * @property CandidateImportRowStatus $status
 * @property string|null $candidate_outcome
 * @property string|null $material_outcome
 * @property string|null $failure_code
 * @property CarbonImmutable|null $committed_at
 * @property CarbonImmutable|null $result_erased_at
 */
#[Fillable(['company_id', 'batch_id', 'record_number', 'payload', 'normalized_email', 'reviewed_candidate_id', 'candidate_id', 'material_id', 'file_id', 'issues', 'decision', 'manifest', 'selected', 'status', 'candidate_outcome', 'material_outcome', 'failure_code', 'committed_at', 'result_erased_at'])]
class CandidateImportRow extends Model
{
    protected $attributes = [
        'selected' => false,
        'status' => 'pending',
    ];

    protected $hidden = ['payload', 'manifest', 'normalized_email'];

    protected function casts(): array
    {
        return [
            'record_number' => 'integer',
            'payload' => 'array',
            'issues' => 'array',
            'decision' => 'array',
            'manifest' => 'array',
            'selected' => 'boolean',
            'committed_at' => 'immutable_datetime',
            'result_erased_at' => 'immutable_datetime',
            'status' => CandidateImportRowStatus::class,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<CandidateImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CandidateImportBatch::class, 'batch_id');
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** @return BelongsTo<Candidate, $this> */
    public function reviewedCandidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'reviewed_candidate_id');
    }

    /** @return BelongsTo<CandidateMaterial, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(CandidateMaterial::class, 'material_id');
    }

    /** @return BelongsTo<CandidateImportFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(CandidateImportFile::class, 'file_id');
    }
}
