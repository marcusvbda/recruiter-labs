<?php

namespace App\Models;

use App\Enums\CandidateMaterialPreparationStatus;
use App\Services\CandidateImportLimits;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $candidate_id
 * @property int|null $batch_id
 * @property int|null $added_by_id
 * @property int|null $metadata_corrected_by_id
 * @property string $disk
 * @property string|null $path
 * @property string|null $original_name
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property string|null $checksum
 * @property string|null $source_label
 * @property CarbonImmutable|null $received_on
 * @property CarbonImmutable $added_at
 * @property CarbonImmutable $declaration_at
 * @property CarbonImmutable|null $metadata_corrected_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $preparation_started_at
 * @property CarbonImmutable|null $prepared_at
 * @property CarbonImmutable|null $cleanup_failed_at
 * @property CandidateMaterialPreparationStatus $preparation_status
 * @property int $preparation_generation
 * @property string|null $preparation_error
 * @property string|null $prepared_text
 * @property bool $text_is_partial
 */
#[Fillable(['company_id', 'candidate_id', 'batch_id', 'added_by_id', 'metadata_corrected_by_id', 'disk', 'path', 'original_name', 'extension', 'mime_type', 'size', 'checksum', 'source_label', 'received_on', 'added_at', 'declaration_at', 'metadata_corrected_at', 'archived_at', 'deleted_at', 'preparation_started_at', 'prepared_at', 'cleanup_failed_at', 'preparation_status', 'preparation_generation', 'preparation_error', 'prepared_text', 'text_is_partial'])]
class CandidateMaterial extends Model
{
    protected static function booted(): void
    {
        static::updating(function (CandidateMaterial $material): void {
            if ($material->isDirty(['company_id', 'candidate_id', 'batch_id', 'added_by_id', 'added_at', 'declaration_at'])) {
                throw new LogicException('Material identity and original adding attribution are immutable.');
            }
        });
    }

    protected $attributes = [
        'disk' => 'candidate_materials',
        'preparation_status' => 'stored',
        'preparation_generation' => 0,
        'text_is_partial' => false,
    ];

    protected $hidden = ['disk', 'path', 'checksum', 'prepared_text'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'received_on' => 'immutable_date',
            'added_at' => 'immutable_datetime',
            'declaration_at' => 'immutable_datetime',
            'metadata_corrected_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'preparation_started_at' => 'immutable_datetime',
            'prepared_at' => 'immutable_datetime',
            'cleanup_failed_at' => 'immutable_datetime',
            'preparation_generation' => 'integer',
            'text_is_partial' => 'boolean',
            'preparation_status' => CandidateMaterialPreparationStatus::class,
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** @return BelongsTo<CandidateImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CandidateImportBatch::class, 'batch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function metadataCorrectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'metadata_corrected_by_id');
    }

    public function isAvailable(): bool
    {
        return $this->deleted_at === null && $this->archived_at === null;
    }

    public function hasReadableText(): bool
    {
        return $this->isAvailable() && $this->preparation_status === CandidateMaterialPreparationStatus::Ready && filled($this->prepared_text);
    }

    public function effectivePreparationStatus(): CandidateMaterialPreparationStatus
    {
        if ($this->preparation_status === CandidateMaterialPreparationStatus::Preparing
            && $this->preparation_started_at?->lte(now()->subMinutes(CandidateImportLimits::STALLED_MINUTES))) {
            return CandidateMaterialPreparationStatus::Failed;
        }

        return $this->preparation_status;
    }
}
