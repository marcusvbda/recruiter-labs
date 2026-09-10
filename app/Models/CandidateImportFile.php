<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $batch_id
 * @property string $disk
 * @property string|null $path
 * @property string|null $original_name
 * @property string|null $association_name
 * @property string|null $extension
 * @property string|null $mime_type
 * @property int $size
 * @property string|null $checksum
 * @property array<string, mixed>|null $errors
 * @property CarbonImmutable|null $validated_at
 * @property CarbonImmutable|null $erased_at
 * @property CarbonImmutable|null $cleanup_failed_at
 */
#[Fillable(['company_id', 'batch_id', 'disk', 'path', 'original_name', 'association_name', 'extension', 'mime_type', 'size', 'checksum', 'errors', 'validated_at', 'erased_at', 'cleanup_failed_at'])]
class CandidateImportFile extends Model
{
    protected $attributes = [
        'disk' => 'candidate_materials',
        'size' => 0,
    ];

    protected $hidden = ['disk', 'path', 'checksum'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'errors' => 'array',
            'validated_at' => 'immutable_datetime',
            'erased_at' => 'immutable_datetime',
            'cleanup_failed_at' => 'immutable_datetime',
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
}
