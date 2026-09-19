<?php

namespace App\Models;

use App\Enums\CandidateImportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $uploaded_by_id
 * @property int|null $confirmed_by_id
 * @property int|null $executing_by_id
 * @property string $source_label
 * @property CandidateImportStatus $status
 * @property string $separator
 * @property bool $correction_mode
 * @property string $disk
 * @property string|null $csv_path
 * @property string|null $csv_original_name
 * @property int $revision
 * @property int|null $validated_revision
 * @property int|null $confirmed_revision
 * @property int $execution_generation
 * @property int|null $executing_company_id
 * @property array<string, mixed>|null $summary
 * @property array<string, mixed>|null $errors
 * @property string|null $failure_code
 * @property CarbonImmutable|null $validated_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $declaration_at
 * @property CarbonImmutable|null $resumed_at
 * @property CarbonImmutable|null $last_progress_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $details_expired_at
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['company_id', 'uploaded_by_id', 'confirmed_by_id', 'executing_by_id', 'source_label', 'status', 'separator', 'correction_mode', 'disk', 'csv_path', 'csv_original_name', 'revision', 'validated_revision', 'confirmed_revision', 'execution_generation', 'executing_company_id', 'summary', 'errors', 'failure_code', 'validated_at', 'confirmed_at', 'declaration_at', 'resumed_at', 'last_progress_at', 'completed_at', 'details_expired_at', 'expires_at'])]
class CandidateImportBatch extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'separator' => ',',
        'correction_mode' => false,
        'disk' => 'candidate_materials',
        'revision' => 1,
        'execution_generation' => 0,
    ];

    protected $hidden = ['disk', 'csv_path'];

    protected static function booted(): void
    {
        // Drives the Candidate import batches table's realtime refresh
        // (CandidateImportBatchesTable::socket()) instead of polling.
        static::saved(function (CandidateImportBatch $batch): void {
            RealtimeEvent::dispatch('candidate_import_batches_'.$batch->company->slug, 'CandidateImportBatchUpdated');
        });

        static::deleted(function (CandidateImportBatch $batch): void {
            RealtimeEvent::dispatch('candidate_import_batches_'.$batch->company->slug, 'CandidateImportBatchUpdated');
        });

        // Drives this one batch's progress page realtime refresh
        // (progress.blade.php) instead of polling. Scoped by batch id rather
        // than company, so unrelated batches in the same workspace don't
        // trigger a spurious refresh of this page.
        static::saved(function (CandidateImportBatch $batch): void {
            RealtimeEvent::dispatch('candidate_import_batch_'.$batch->id, 'CandidateImportBatchUpdated');
        });
    }

    protected function casts(): array
    {
        return [
            'correction_mode' => 'boolean',
            'revision' => 'integer',
            'validated_revision' => 'integer',
            'confirmed_revision' => 'integer',
            'execution_generation' => 'integer',
            'summary' => 'array',
            'errors' => 'array',
            'validated_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'declaration_at' => 'immutable_datetime',
            'resumed_at' => 'immutable_datetime',
            'last_progress_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'details_expired_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'status' => CandidateImportStatus::class,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function executingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executing_by_id');
    }

    /** @return HasMany<CandidateImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(CandidateImportRow::class, 'batch_id');
    }

    /** @return HasMany<CandidateImportFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(CandidateImportFile::class, 'batch_id');
    }
}
